<?php

namespace Endereco\Shopware6Client\Run;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Factory class for creating logger instances
 */
class LoggerFactory
{
    /**
     * @var string Directory path for log files
     */
    private string $logDir;

    /**
     * @param string $logDir Directory path for log files
     */
    public function __construct(string $logDir)
    {
        $this->logDir = $logDir;
    }

    /**
     * Creates a rotating logger instance
     *
     * @param string $filePrefix Prefix for the log filename
     * @return \Monolog\Logger Configured logger instance
     */
    public function createRotating(string $filePrefix): Logger
    {
        $logger = new Logger($filePrefix);
        $logger->pushHandler(
            new RotatingFileHandler(
                "{$this->logDir}/{$filePrefix}.log",
                14
            )
        );
        return $logger;
    }
}
