<?php

declare(strict_types=1);

namespace App\Service\Scanning;

/**
 * Extension-based language detection. Deterministic, no content sniffing.
 */
final class LanguageDetector
{
    private const EXTENSION_MAP = [
        'js' => 'JavaScript',
        'jsx' => 'JavaScript',
        'mjs' => 'JavaScript',
        'cjs' => 'JavaScript',
        'ts' => 'TypeScript',
        'tsx' => 'TypeScript',
        'php' => 'PHP',
        'py' => 'Python',
        'pyi' => 'Python',
        'html' => 'HTML',
        'htm' => 'HTML',
        'css' => 'CSS',
        'json' => 'JSON',
        'yaml' => 'YAML',
        'yml' => 'YAML',
        'md' => 'Markdown',
        'markdown' => 'Markdown',
    ];

    public function detect(string $extension): ?string
    {
        return self::EXTENSION_MAP[strtolower($extension)] ?? null;
    }
}
