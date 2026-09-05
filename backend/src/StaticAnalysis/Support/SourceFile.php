<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Support;

/**
 * A file's content pre-split into lines, with a best-effort (not a real
 * lexer/parser) heuristic to skip obviously-commented-out lines. This is a
 * deliberate simplification: rules built on this class trade some recall for
 * a much lower false-positive rate than raw regex-over-raw-text, without the
 * cost of embedding a real parser per language.
 */
final readonly class SourceFile
{
    /** @var string[] 1-indexed via array_values, index 0 unused */
    public array $lines;

    public function __construct(
        public string $relativePath,
        public string $contents,
    ) {
        $this->lines = ['', ...explode("\n", $contents)];
    }

    public function lineCount(): int
    {
        return count($this->lines) - 1;
    }

    /**
     * True if the line, once trimmed, starts with a common single-line
     * comment marker. Does not attempt to track multi-line block comments.
     */
    public function isFullLineComment(int $lineNumber): bool
    {
        $trimmed = ltrim($this->lines[$lineNumber] ?? '');

        return '' !== $trimmed && (
            str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '/*')
        );
    }
}
