<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Thin wrapper around the qpdf command-line tool (https://qpdf.sourceforge.io).
 * Used to merge uploaded PDF sections into a generated manual and to stamp page footers.
 */
final class Qpdf
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        try {
            $result = Process::timeout(15)->run([self::binary(), '--version']);
            return self::$available = $result->successful() && str_contains($result->output(), 'qpdf');
        } catch (Throwable) {
            return self::$available = false;
        }
    }

    /** Forget the cached availability (e.g. after the config changes in tests). */
    public static function reset(): void
    {
        self::$available = null;
    }

    public static function pageCount(string $path): int
    {
        $output = trim(self::run(['--show-npages', $path]));

        if (!ctype_digit($output)) {
            throw new RuntimeException("qpdf could not count the pages of {$path}.");
        }

        return (int) $output;
    }

    /**
     * Build a new PDF from page ranges of existing files, in order.
     *
     * @param array<int, array{0: string, 1: string}> $parts [path, range] where range is like "1-3" or "1-z"
     */
    public static function assemble(array $parts, string $destination): void
    {
        $args = ['--empty', '--pages'];
        foreach ($parts as [$path, $range]) {
            $args[] = $path;
            $args[] = $range;
        }
        $args[] = '--';
        $args[] = $destination;

        self::run($args);
    }

    /**
     * Lay each page of $overlay on top of the matching page of $base (used for page-number footers).
     */
    public static function overlay(string $base, string $overlay, string $destination): void
    {
        self::run([$base, '--overlay', $overlay, '--', $destination]);
    }

    private static function run(array $args): string
    {
        $result = Process::timeout(300)->run([self::binary(), ...$args]);

        // qpdf exit code 3 = success with warnings (common with slightly malformed PDFs).
        if (!in_array($result->exitCode(), [0, 3], true)) {
            throw new RuntimeException('qpdf failed: ' . trim($result->errorOutput() ?: $result->output()));
        }

        return $result->output();
    }

    private static function binary(): string
    {
        return (string) config('knowledge.qpdf_path', 'qpdf');
    }
}
