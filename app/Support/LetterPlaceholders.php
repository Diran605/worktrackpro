<?php

namespace App\Support;

use App\Models\CompanyLetterhead;
use App\Models\User;
use Carbon\Carbon;

/**
 * The placeholders letter templates can use, and how they are filled in.
 */
final class LetterPlaceholders
{
    public const AVAILABLE = [
        '{{worker_name}}' => 'the employee\'s full name',
        '{{worker_department}}' => 'the employee\'s department',
        '{{date}}' => 'today\'s date, e.g. 11 November 2026',
        '{{admin_name}}' => 'your name (the person issuing the letter)',
        '{{company_name}}' => 'the company name on the active letterhead (or the organisation name)',
    ];

    /**
     * Replace the known placeholders. Values are HTML-escaped when filling HTML (the letter body).
     */
    public static function fill(string $text, User $worker, User $issuer, bool $html = false): string
    {
        $letterhead = CompanyLetterhead::query()
            ->where('organisation_id', $issuer->organisation_id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $values = [
            '{{worker_name}}' => $worker->name,
            '{{worker_department}}' => $worker->department?->name ?? '',
            '{{date}}' => Carbon::parse($issuer->localToday())->format('j F Y'),
            '{{admin_name}}' => $issuer->name,
            '{{company_name}}' => $letterhead?->company_name ?: ($issuer->organisation?->name ?? ''),
        ];

        if ($html) {
            $values = array_map(fn ($v) => e($v), $values);
        }

        return strtr($text, $values);
    }

    /**
     * Placeholders still left in the text, e.g. ['{{custom_field_1}}'].
     *
     * @return array<int, string>
     */
    public static function unfilled(string $text): array
    {
        preg_match_all('/\{\{\s*[\w.]+\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * Help text listing the placeholders, for forms.
     */
    public static function helpText(): string
    {
        $list = collect(self::AVAILABLE)->map(fn ($meaning, $tag) => "{$tag} = {$meaning}")->join('; ');

        return "These are filled in automatically when the letter is generated: {$list}. "
            . 'For anything else (a salary, an incident date…), write a clear marker such as [SALARY] and replace it on the Generate Letter page.';
    }
}
