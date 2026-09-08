<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Run;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;

/**
 * Resolves to Shopware's "main" monolog handler when it exists, and to a
 * no-op handler otherwise. Shopware only defines monolog.handler.main for
 * the dev and prod environments — in every other
 * environment (test, phpstan_dev, ...) the service does not exist.
 */
final class OptionalMainHandlerFactory
{
    public static function create(?HandlerInterface $mainHandler): HandlerInterface
    {
        return $mainHandler ?? new NullHandler();
    }
}
