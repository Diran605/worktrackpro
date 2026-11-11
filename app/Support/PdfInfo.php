<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use Throwable;

final class PdfInfo
{
    /**
     * Number of pages in a PDF, or null when qpdf isn't installed or can't read the file.
     */
    public static function pageCount(string $absolutePath): ?int
    {
        try {
            $result = Process::timeout(30)->run([config('knowledge.qpdf_path'), '--show-npages', $absolutePath]);
        } catch (Throwable) {
            return null;
        }

        $output = trim($result->output());

        return $result->successful() && ctype_digit($output) ? (int) $output : null;
    }

    public static function looksLikePdf(string $absolutePath): bool
    {
        $handle = @fopen($absolutePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }
}
