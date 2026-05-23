<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    /**
     * GET /api/backup/url — issue a 15-minute signed URL for the SQL dump.
     */
    public function signedUrl()
    {
        $url = URL::temporarySignedRoute('backup.download', now()->addMinutes(15));

        return response()->json([
            'success' => true,
            'data'    => ['url' => $url, 'expires_in' => 900],
        ]);
    }

    /**
     * GET /api/backup/download — actual SQL stream (signed).
     */
    public function download(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Backup link expired or tampered with.');
        }

        $dbConfig = config('database.connections.' . config('database.default'));
        $filename = sprintf('backup_%s_%s.sql', $dbConfig['database'], now()->format('Ymd_His'));

        // Try mysqldump first (cleaner SQL, native to the engine)
        $sql = $this->tryMysqldump($dbConfig);

        // Fall back to a PHP-driven dump if mysqldump is unavailable on PATH
        if ($sql === null) {
            $sql = $this->phpDump($dbConfig);
        }

        return response($sql, 200, [
            'Content-Type'        => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function tryMysqldump(array $cfg): ?string
    {
        $cmd = [
            'mysqldump',
            '-h', $cfg['host'],
            '-P', (string) $cfg['port'],
            '-u', $cfg['username'],
            ...($cfg['password'] !== '' ? ['-p' . $cfg['password']] : []),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            $cfg['database'],
        ];

        try {
            $process = new Process($cmd);
            $process->setTimeout(120);
            $process->run();
            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Minimal pure-PHP fallback. Produces a SQL dump comparable to mysqldump for
     * the common case (no triggers/views, default charset).
     */
    protected function phpDump(array $cfg): string
    {
        $out = "-- Donor Management System backup\n";
        $out .= "-- Database: {$cfg['database']}\n";
        $out .= "-- Generated: " . now()->toIso8601String() . "\n\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . $cfg['database'];

        foreach ($tables as $row) {
            $table = $row->{$key};
            $create = DB::select("SHOW CREATE TABLE `{$table}`")[0];
            $createKey = array_key_exists('Create Table', (array) $create) ? 'Create Table' : 'Create View';

            // $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
            // $out .= $create->{$createKey} . ";\n\n";

            // $rows = DB::table($table)->get();
            // if ($rows->isEmpty()) continue;

            // $cols = array_keys((array) $rows[0]);
            // $colsSql = '`' . implode('`,`', $cols) . '`';

            // foreach (DB::table($table)->cursor() as $r) {
            //     $values = array_map(function ($v) {
            //         if ($v === null) return 'NULL';
            //         if (is_numeric($v) && !is_string($v)) return $v;
            //         return "'" . addslashes((string) $v) . "'";
            //     }, (array) $r);
            //     $out .= "INSERT INTO `{$table}` ({$colsSql}) VALUES (" . implode(',', $values) . ");\n";
            // }
            // $out .= "\n";
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create->{$createKey} . ";\n\n";

            // Use cursor() to stream rows to avoid memory exhaustion
            foreach (DB::table($table)->cursor() as $r) {
                $values = array_map(fn($v) => $v === null ? 'NULL' : (is_numeric($v) ? $v : "'" . addslashes((string)$v) . "'"), (array) $r);
                $out .= "INSERT INTO `{$table}` VALUES (" . implode(',', $values) . ");\n";
            }
            $out .= "\n";
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }
}
