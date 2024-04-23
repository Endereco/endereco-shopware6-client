<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Run;

use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Factory class for creating Logger instances.
 * This class is designed to simplify the creation of Monolog Logger instances with a predefined configuration.
 */
class LoggerFactory
{
    /**
     * Creates a rotating file logger instance with the specified file prefix.
     *
     * The logger is configured with:
     * - A custom LogHandler to handle log records
     * - A PsrLogMessageProcessor to process log messages according to PSR-3 standards
     *
     * @param string $filePrefix The prefix for the log files, used to identify different log files.
     *
     * @return Logger Returns a configured Logger instance.
     */
    public function createRotating(string $filePrefix): Logger
    {
        $logger = new Logger($filePrefix);
        $logger->pushHandler(new LogHandler());
        $logger->pushProcessor(new PsrLogMessageProcessor());

        return $logger;
    }
}
