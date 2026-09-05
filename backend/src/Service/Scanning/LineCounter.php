<?php

declare(strict_types=1);

namespace App\Service\Scanning;

/**
 * Streams a file in fixed-size chunks to count lines without loading the
 * whole file into memory.
 */
final class LineCounter
{
    private const CHUNK_SIZE = 8192;

    public function count(string $absolutePath): int
    {
        $handle = fopen($absolutePath, 'rb');
        if (false === $handle) {
            return 0;
        }

        try {
            $lines = 0;
            $lastChunkEndedWithNewline = true;
            $sawAnyContent = false;

            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                if (false === $chunk || '' === $chunk) {
                    continue;
                }

                $sawAnyContent = true;
                $lines += substr_count($chunk, "\n");
                $lastChunkEndedWithNewline = str_ends_with($chunk, "\n");
            }

            if ($sawAnyContent && !$lastChunkEndedWithNewline) {
                ++$lines; // count a final line with no trailing newline
            }

            return $lines;
        } finally {
            fclose($handle);
        }
    }
}
