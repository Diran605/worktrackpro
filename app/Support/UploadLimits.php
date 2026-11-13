<?php

namespace App\Support;

/**
 * The largest upload the server (PHP) accepts, which can be lower than the
 * app's own limits (config/knowledge.php). Raise upload_max_filesize and
 * post_max_size in php.ini to allow bigger files.
 */
class UploadLimits
{
    public static function maxBytes(): int
    {
        $sizes = array_filter([self::bytes(ini_get('upload_max_filesize')), self::bytes(ini_get('post_max_size'))]);

        return $sizes === [] ? 0 : min($sizes);
    }

    public static function maxLabel(): string
    {
        $bytes = self::maxBytes();

        return $bytes === 0 ? 'its limit' : rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
    }

    public static function tooLargeMessage(): string
    {
        return 'The file is too large for the server, which accepts files up to ' . self::maxLabel() . '. Use a smaller file, or ask your administrator to raise the upload limit.';
    }

    /** "2M", "512K", "1G" or a plain number of bytes. */
    public static function bytes(string|false $value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
