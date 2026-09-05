<?php

declare(strict_types=1);

namespace App\Service\Scanning;

/**
 * Identifies environment files whose *contents* must never be read by the
 * scanner (line counting, binary sniffing, or any other content access).
 * Only the fact that they exist (and their size) may ever be recorded.
 */
final class SensitiveFileGuard
{
    private const SAFE_SUFFIXES = ['.example', '.sample', '.dist', '.test'];

    public function isSensitiveEnvFile(string $filename): bool
    {
        if (!str_starts_with($filename, '.env')) {
            return false;
        }

        foreach (self::SAFE_SUFFIXES as $suffix) {
            if (str_ends_with($filename, $suffix)) {
                return false;
            }
        }

        return true;
    }
}
