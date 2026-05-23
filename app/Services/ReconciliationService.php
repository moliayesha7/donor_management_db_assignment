<?php

namespace App\Services;

use App\Models\BankStatementUpload;
use App\Models\BankTransaction;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;

class ReconciliationService
{
    /**
     * Canonical header order for the importable template + export. Header strings
     * are loose-matched (lower-cased, _ vs space) so users can tweak the file.
     */
    public const TEMPLATE_HEADERS = [
        'source_id', 'order_id', 'date', 'time', 'status',
        'first_name', 'last_name', 'phone', 'email', 'address_line_1',
        'amount', 'payment_method', 'reference', 'project_code',
    ];

    public function processUpload(BankStatementUpload $upload, bool $autoCreateDonors = false): BankStatementUpload
    {
        $upload->update(['status' => 'processing']);

        try {
            $rows = $this->readRows($upload->stored_path, $upload->format);

            if (empty($rows)) {
                $upload->update(['status' => 'failed', 'notes' => 'No data rows found in file.']);
                return $upload;
            }

            $headerRow = array_map(fn ($h) => $this->normalizeHeader((string) $h), $rows[0]);
            $dataRows  = array_slice($rows, 1);

            $stats = [
                'total_rows' => 0, 'matched_rows' => 0, 'unmatched_rows' => 0,
                'donor_created_rows' => 0, 'duplicate_rows' => 0, 'error_rows' => 0,
                'total_amount' => 0,
            ];

            DB::transaction(function () use ($upload, $headerRow, $dataRows, $autoCreateDonors, &$stats) {
                foreach ($dataRows as $i => $rawRow) {
                    if ($this->isEmptyRow($rawRow)) continue;
                    $stats['total_rows']++;

                    $assoc = $this->rowToAssoc($headerRow, $rawRow);
                    $stats['total_amount'] += (float) ($assoc['amount'] ?? 0);

                    try {
                        $this->processRow($upload, $i + 2, $assoc, $rawRow, $autoCreateDonors, $stats);
                    } catch (\Throwable $e) {
                        $stats['error_rows']++;
                        BankTransaction::create([
                            'upload_id'    => $upload->id,
                            'row_number'   => $i + 2,
                            'amount'       => (float) ($assoc['amount'] ?? 0),
                            'match_status' => 'error',
                            'notes'        => 'Row error: ' . $e->getMessage(),
                            'raw_payload'  => $assoc,
                        ]);
                        Log::warning('Reconciliation row error', [
                            'upload_id' => $upload->id, 'row' => $i + 2, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            $upload->update(array_merge($stats, ['status' => 'completed']));
        } catch (\Throwable $e) {
            $upload->update([
                'status' => 'failed',
                'notes'  => 'Parse failed: ' . $e->getMessage(),
            ]);
            Log::error('Reconciliation parse failed', [
                'upload_id' => $upload->id, 'error' => $e->getMessage(),
            ]);
        }

        return $upload->fresh();
    }

    /**
     * Manually link an unmatched transaction to a donor and create the donation.
     */
    public function manualMatch(BankTransaction $txn, Donor $donor, ?int $projectId = null): BankTransaction
    {
        return DB::transaction(function () use ($txn, $donor, $projectId) {
            $upload = $txn->upload;
            $project = $this->resolveProject($txn->project_code, $projectId ?? $upload->default_project_id);

            if (!$project) {
                throw new \RuntimeException('No project specified; cannot create donation.');
            }

            $donation = $this->createDonation($donor, $project, $txn);

            // Roll back the old stats bucket
            $oldStatus = $txn->match_status;
            $upload->decrement(match ($oldStatus) {
                'unmatched'     => 'unmatched_rows',
                'donor_created' => 'donor_created_rows',
                default         => 'unmatched_rows',
            });

            $txn->update([
                'match_status'        => 'matched',
                'matched_donor_id'    => $donor->id,
                'created_donation_id' => $donation->id,
                'notes'               => 'Manually matched by user.',
            ]);

            $upload->increment('matched_rows');

            return $txn->fresh(['matchedDonor', 'createdDonation']);
        });
    }

    /**
     * Build a blank .xlsx template the user can download.
     * Returns the binary contents.
     */
    public function buildTemplateXlsx(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('External Donations');

        foreach (self::TEMPLATE_HEADERS as $col => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
            $sheet->getStyle("{$colLetter}1")->getFont()->setBold(true);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Add an example row showing valid formats
        $sheet->setCellValue('A2', 'JustGiving');
        $sheet->setCellValue('B2', 'JG-100001');
        $sheet->setCellValue('C2', '2026-05-15');
        $sheet->setCellValue('D2', '14:30:00');
        $sheet->setCellValue('E2', 'paid');
        $sheet->setCellValue('F2', 'Rahim');
        $sheet->setCellValue('G2', 'Ahmed');
        $sheet->setCellValue('H2', '+8801712345001');
        $sheet->setCellValue('I2', 'rahim.ahmed@example.com');
        $sheet->setCellValue('J2', 'House 12, Road 5');
        $sheet->setCellValue('K2', 500.00);
        $sheet->setCellValue('L2', 'Stripe');
        $sheet->setCellValue('M2', 'TXN-REF-12345');
        $sheet->setCellValue('N2', 'PRJ-RAMADAN-2026');
        $sheet->getStyle('A2:N2')->getFont()->setItalic(true)->getColor()->setRGB('888888');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /* --------------------------- internals ------------------------------------ */

    protected function readRows(string $path, string $format): array
    {
        $fullPath = storage_path('app/' . $path);
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        if ($format === 'csv') {
            $rows = [];
            if (($handle = fopen($fullPath, 'r')) !== false) {
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
            return $rows;
        }

        // xlsx (and xls if ever needed)
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($fullPath);
        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    protected function processRow(
        BankStatementUpload $upload,
        int $rowNumber,
        array $assoc,
        array $rawRow,
        bool $autoCreateDonors,
        array &$stats,
    ): void {
        $donor   = $this->findDonor($assoc);
        $project = $this->resolveProject($assoc['project_code'] ?? null, $upload->default_project_id);

        $base = [
            'upload_id'         => $upload->id,
            'row_number'        => $rowNumber,
            'source_id'         => $assoc['source_id'] ?? null,
            'order_id'          => $assoc['order_id'] ?? null,
            'transaction_date'  => $this->parseDate($assoc['date'] ?? null),
            'transaction_time'  => $this->parseTime($assoc['time'] ?? null),
            'transaction_status'=> $assoc['status'] ?? null,
            'amount'            => (float) ($assoc['amount'] ?? 0),
            'payment_method'    => $assoc['payment_method'] ?? null,
            'reference'         => $assoc['reference'] ?? null,
            'first_name'        => $assoc['first_name'] ?? null,
            'last_name'         => $assoc['last_name'] ?? null,
            'phone'             => $assoc['phone'] ?? null,
            'email'             => $assoc['email'] ?? null,
            'address_line_1'    => $assoc['address_line_1'] ?? null,
            'project_code'      => $assoc['project_code'] ?? null,
            'raw_payload'       => $assoc,
        ];

        // Duplicate detection: same upload's source_id + order_id already seen
        if (!empty($assoc['source_id']) && !empty($assoc['order_id'])) {
            $existing = BankTransaction::where('upload_id', $upload->id)
                ->where('source_id', $assoc['source_id'])
                ->where('order_id', $assoc['order_id'])
                ->exists();
            if ($existing) {
                BankTransaction::create(array_merge($base, [
                    'match_status' => 'duplicate',
                    'notes'        => 'Duplicate source_id + order_id within this upload.',
                ]));
                $stats['duplicate_rows']++;
                return;
            }
        }

        if (!$donor && $autoCreateDonors && !empty($assoc['email'])) {
            $donor = $this->createDonor($assoc, $upload->uploaded_by);
            $matchStatus = 'donor_created';
            $stats['donor_created_rows']++;
        } elseif (!$donor) {
            BankTransaction::create(array_merge($base, [
                'match_status' => 'unmatched',
                'notes'        => 'No donor with that email or phone.',
            ]));
            $stats['unmatched_rows']++;
            return;
        } else {
            $matchStatus = 'matched';
            $stats['matched_rows']++;
        }

        if (!$project) {
            // Donor exists but no project — record as unmatched with a clear reason.
            BankTransaction::create(array_merge($base, [
                'match_status'     => 'unmatched',
                'matched_donor_id' => $donor->id,
                'notes'            => 'Donor matched but no project resolved.',
            ]));
            // Roll back the previous increment since this path won't create a donation.
            $stats[$matchStatus === 'donor_created' ? 'donor_created_rows' : 'matched_rows']--;
            $stats['unmatched_rows']++;
            return;
        }

        $donation = $this->createDonation($donor, $project, (object) $base);

        BankTransaction::create(array_merge($base, [
            'match_status'        => $matchStatus,
            'matched_donor_id'    => $donor->id,
            'created_donation_id' => $donation->id,
            'notes'               => $matchStatus === 'donor_created'
                ? 'New donor auto-created from row.'
                : 'Donor matched by ' . $this->lastMatchedBy($assoc, $donor) . '.',
        ]));
    }

    protected function findDonor(array $assoc): ?Donor
    {
        $email = trim((string) ($assoc['email'] ?? ''));
        $phone = $this->normalizePhone((string) ($assoc['phone'] ?? ''));

        if ($email !== '') {
            $byEmail = Donor::where('email', $email)->first();
            if ($byEmail) return $byEmail;
        }

        if ($phone !== '') {
            // Try a couple of phone-shapes: as-given, no-leading-+, last 10 digits.
            $candidates = array_unique(array_filter([
                $assoc['phone'] ?? null,
                $phone,
                substr($phone, -10),
            ]));
            foreach ($candidates as $candidate) {
                $hit = Donor::where('phone_number', $candidate)
                    ->orWhere('phone_number', 'like', '%' . $candidate)
                    ->first();
                if ($hit) return $hit;
            }
        }

        return null;
    }

    protected function lastMatchedBy(array $assoc, Donor $donor): string
    {
        if (!empty($assoc['email']) && $donor->email === $assoc['email']) return 'email';
        return 'phone';
    }

    protected function createDonor(array $assoc, int $createdBy): Donor
    {
        $code = 'DNR-' . (1000 + (Donor::max('id') ?? 0) + 1);
        return Donor::create([
            'donor_id_code' => $code,
            'name'          => trim(($assoc['first_name'] ?? '') . ' ' . ($assoc['last_name'] ?? '')) ?: 'Imported Donor',
            'phone_number'  => $assoc['phone'] ?? '',
            'email'         => $assoc['email'] ?? null,
            'address_line_1'=> $assoc['address_line_1'] ?? null,
            'post_code'     => 'IMPORTED',
            'created_by'    => $createdBy,
        ]);
    }

    protected function createDonation(Donor $donor, Project $project, $row): Donation
    {
        $amount = (float) ($row->amount ?? 0);
        $when   = $row->transaction_date ?? now()->toDateString();
        $time   = $row->transaction_time ?? '00:00:00';
        $txnDateTime = Carbon::parse($when)->setTimeFromTimeString(is_string($time) ? $time : '00:00:00');

        return Donation::create([
            'donor_id'         => $donor->id,
            'project_id'       => $project->id,
            'student_id'       => null,
            'amount'           => $amount,
            'payment_method'   => $row->payment_method ?? 'Bank Transfer',
            'transaction_date' => $txnDateTime,
            'receipt_number'   => 'REC-IMP-' . Str::upper(Str::random(8)),
            'status'           => 'confirmed',
        ]);
    }

    protected function resolveProject(?string $projectCode, ?int $defaultProjectId): ?Project
    {
        if ($projectCode) {
            $byCode = Project::where('project_code', $projectCode)->first();
            if ($byCode) return $byCode;
        }
        if ($defaultProjectId) {
            return Project::find($defaultProjectId);
        }
        return null;
    }

    protected function rowToAssoc(array $headers, array $row): array
    {
        $assoc = [];
        foreach ($headers as $i => $key) {
            if ($key === '') continue;
            $val = $row[$i] ?? null;
            $assoc[$key] = is_string($val) ? trim($val) : $val;
        }
        return $assoc;
    }

    protected function normalizeHeader(string $h): string
    {
        $h = strtolower(trim($h));
        $h = preg_replace('/[\s\-]+/', '_', $h);
        return $h ?? '';
    }

    protected function normalizePhone(string $phone): string
    {
        $stripped = preg_replace('/[^\d+]/', '', $phone);
        return ltrim($stripped, '+');
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (!is_null($cell) && (string) $cell !== '') return false;
        }
        return true;
    }

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            // Excel serial date
            try { return PhpSpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d'); }
            catch (\Throwable $e) {}
        }
        try { return Carbon::parse($value)->toDateString(); }
        catch (\Throwable $e) { return null; }
    }

    protected function parseTime($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value) && (float) $value < 1) {
            $seconds = (int) round($value * 86400);
            return gmdate('H:i:s', $seconds);
        }
        try { return Carbon::parse($value)->format('H:i:s'); }
        catch (\Throwable $e) { return null; }
    }
}
