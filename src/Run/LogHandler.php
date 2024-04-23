<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Run;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

/**
 * Custom log handler for capturing and storing log entries.
 * Extends Monolog's AbstractProcessingHandler to provide a storage mechanism for log records.
 */
class LogHandler extends AbstractProcessingHandler
{
    /**
     * @var array<string, mixed>[] Storage for log records. Each record is an associative array.
     */
    private array $logs;

    /**
     * Constructor for LogHandler.
     * Initializes the log storage and calls the parent constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->logs = [];
    }

    /**
     * Retrieves all stored logs.
     *
     * @return array<string, mixed>[] Returns an array of log entries, each represented as an associative array.
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Clears all stored logs.
     */
    public function flush(): void
    {
        $this->logs = [];
    }

    /**
     * Processes a log record.
     *
     * @param LogRecord $record The log record to process. Expected to contain level and message.
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $update = [
            'level' => $record->level->value,
            'message' => $record->message,
        ];

        $this->logs[] = $update;
    }
}
