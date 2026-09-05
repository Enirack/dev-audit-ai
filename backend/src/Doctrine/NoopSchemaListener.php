<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Stand-in for Symfony\Bridge\Doctrine\SchemaListener\DoctrineDbalCacheAdapterSchemaListener,
 * see App\Kernel::build() for why the original is swapped out.
 */
final class NoopSchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
    }
}
