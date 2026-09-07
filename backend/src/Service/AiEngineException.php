<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Thrown whenever the ai-engine is unreachable, times out, rejects the
 * request, or returns something that isn't the expected JSON shape.
 * Callers (AiInsightService) turn this into a clean API error — it is never
 * allowed to surface as an uncaught 500 with an internal stack trace.
 */
final class AiEngineException extends \RuntimeException
{
}
