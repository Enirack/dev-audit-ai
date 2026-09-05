<?php

namespace App;

use App\Doctrine\NoopSchemaListener;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        // doctrine-bridge registers these four *SchemaListener services unconditionally
        // whenever doctrine/orm is present (for the DBAL cache adapter, remember-me token
        // provider, doctrine lock store, and PDO session handler — none of which this app
        // uses). Each one's postGenerateSchema() unconditionally calls
        // GenerateSchemaEventArgs::setSchema(), which requires the DBAL Schema::edit() API
        // only available from doctrine/dbal ^4.5 (not yet stable), so every one of them
        // crashes schema/migration generation. Other core services hold a direct reference
        // to these service ids, so they can't simply be removed — swap their class for a
        // no-op instead.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $ids = [
                    'doctrine.orm.listeners.doctrine_dbal_cache_adapter_schema_listener',
                    'doctrine.orm.listeners.doctrine_token_provider_schema_listener',
                    'doctrine.orm.listeners.lock_store_schema_listener',
                    'doctrine.orm.listeners.pdo_session_handler_schema_listener',
                ];

                foreach ($ids as $id) {
                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setClass(NoopSchemaListener::class);
                    }
                }
            }
        });
    }
}
