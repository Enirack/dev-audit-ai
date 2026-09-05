<?php

declare(strict_types=1);

namespace App\Service\Scanning;

final class BinaryFileDetector
{
    private const SNIFF_BYTES = 8192;

    public function __construct(private readonly ScannerConfig $config)
    {
    }

    public function isBinary(string $absolutePath, string $extension): bool
    {
        if (in_array(strtolower($extension), $this->config->binaryExtensions, true)) {
            return true;
        }

        $handle = fopen($absolutePath, 'rb');
        if (false === $handle) {
            return false;
        }

        try {
            $chunk = fread($handle, self::SNIFF_BYTES);

            return false !== $chunk && str_contains($chunk, "\0");
        } finally {
            fclose($handle);
        }
    }
}
