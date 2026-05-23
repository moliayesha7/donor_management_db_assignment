<?php

namespace App\Services;

use App\Models\Donation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReceiptService
{
    public function buildXlsx(Donation $donation): string
    {
        $donation->loadMissing(['donor', 'project', 'student']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Receipt');
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(50);

        // Header
        $sheet->mergeCells('A1:B1');
        $sheet->setCellValue('A1', 'DONATION RECEIPT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1d4ed8');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getRowDimension(1)->setRowHeight(36);

        $sheet->mergeCells('A2:B2');
        $sheet->setCellValue('A2', 'Donor Management System');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('666666');

        $rows = [
            ['Receipt Number',   $donation->receipt_number],
            ['Date',             optional($donation->transaction_date)->format('Y-m-d H:i') ?? '—'],
            ['Donor Name',       $donation->donor?->name ?? '—'],
            ['Donor ID',         $donation->donor?->donor_id_code ?? '—'],
            ['Phone',            $donation->donor?->phone_number ?? '—'],
            ['Email',            $donation->donor?->email ?? '—'],
            ['Project',          $donation->project ? ($donation->project->name . ' (' . $donation->project->project_code . ')') : '—'],
            ['Student',          $donation->student?->student_name ?: '— (General Project Funding)'],
            ['Payment Method',   $donation->payment_method ?? '—'],
            ['Status',           strtoupper($donation->status ?? '—')],
            ['Amount',           number_format((float) $donation->amount, 2) . ' BDT'],
        ];

        $row = 4;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }

        // Amount row highlight
        $amountRow = $row - 1;
        $sheet->getStyle("A{$amountRow}:B{$amountRow}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$amountRow}:B{$amountRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('dcfce7');

        $row++;
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Thank you for your generous contribution. May Allah accept it. ﷽');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('555555');
        $sheet->getRowDimension($row)->setRowHeight(28);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }
}
