<?php

namespace App\Domain\Ai\Support;

/**
 * Normalizes raw provider/HTTP errors into safe, human-readable categories.
 * Never leaks: API keys, Authorization headers, raw provider payloads.
 */
final class ErrorNormalizer
{
    public const INVALID_KEY = 'invalid_key';

    public const INVALID_URL = 'invalid_url';

    public const INVALID_MODEL = 'invalid_model';

    public const TIMEOUT = 'timeout';

    public const RATE_LIMITED = 'rate_limited';

    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';

    public const AUTH_FAILED = 'auth_failed';

    public const PROVIDER_ERROR = 'provider_error';

    public const INVALID_OUTPUT = 'invalid_output';

    public const UNKNOWN = 'unknown';

    /** @var array<string, string> */
    public const MESSAGES = [
        self::INVALID_KEY => 'API key ditolak provider. Periksa kembali API key kamu.',
        self::INVALID_URL => 'Base URL tidak valid atau tidak dapat dihubungi. Periksa format URL (harus diawali http/https).',
        self::INVALID_MODEL => 'Model tidak dikenal oleh provider. Pilih model yang tersedia.',
        self::TIMEOUT => 'Provider merespons terlalu lama (timeout). Coba lagi atau gunakan model yang lebih cepat.',
        self::RATE_LIMITED => 'Rate limit provider tercapai. Tunggu sebentar lalu coba lagi.',
        self::PROVIDER_UNAVAILABLE => 'Provider sedang tidak tersedia. Coba lagi nanti.',
        self::AUTH_FAILED => 'Autentikasi ke provider gagal.',
        self::PROVIDER_ERROR => 'Provider mengembalikan error internal. Coba lagi.',
        self::INVALID_OUTPUT => 'AI mengembalikan output yang tidak valid. Coba generate ulang.',
        self::UNKNOWN => 'Terjadi error tak terduga saat menghubungi AI provider.',
    ];

    public static function categorize(\Throwable $e, ?int $status = null): array
    {
        $message = mb_strtolower($e->getMessage());

        return match (true) {
            $status !== null && $status === 401 => [self::INVALID_KEY, self::MESSAGES[self::INVALID_KEY]],
            $status !== null && $status === 403 => [self::AUTH_FAILED, self::MESSAGES[self::AUTH_FAILED]],
            $status !== null && $status === 404 => [self::INVALID_URL, self::MESSAGES[self::INVALID_URL]],
            $status !== null && $status === 429 => [self::RATE_LIMITED, self::MESSAGES[self::RATE_LIMITED]],
            $status !== null && $status >= 500 => [self::PROVIDER_UNAVAILABLE, self::MESSAGES[self::PROVIDER_UNAVAILABLE]],
            str_contains($message, 'timed out') || str_contains($message, 'timeout') || str_contains($message, 'cURL error 28') => [self::TIMEOUT, self::MESSAGES[self::TIMEOUT]],
            str_contains($message, 'cURL error 3') || str_contains($message, 'could not resolve host') => [self::INVALID_URL, self::MESSAGES[self::INVALID_URL]],
            str_contains($message, 'cURL error 7') || str_contains($message, 'connection refused') => [self::INVALID_URL, self::MESSAGES[self::INVALID_URL]],
            str_contains($message, 'model') && (str_contains($message, 'not found') || str_contains($message, 'does not exist')) => [self::INVALID_MODEL, self::MESSAGES[self::INVALID_MODEL]],
            str_contains($message, 'api key') || str_contains($message, 'invalid_api_key') || str_contains($message, 'unauthorized') => [self::INVALID_KEY, self::MESSAGES[self::INVALID_KEY]],
            str_contains($message, 'json') => [self::INVALID_OUTPUT, self::MESSAGES[self::INVALID_OUTPUT]],
            default => [self::UNKNOWN, self::MESSAGES[self::UNKNOWN]],
        };
    }

    public static function message(string $category): string
    {
        return self::MESSAGES[$category] ?? self::MESSAGES[self::UNKNOWN];
    }

    /** Strip credentials/prompt content from any error message before logging. */
    public static function sanitize(string $raw): string
    {
        $raw = preg_replace('/(Bearer|sk-|api[_-]?key)[^\s"\']*/i', '[redacted]', $raw) ?? $raw;

        return mb_substr($raw, 0, 500);
    }
}
