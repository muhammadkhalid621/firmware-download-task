<?php

declare(strict_types=1);

namespace App\Service;

final class AuditLogger
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $action, array $context): void
    {
        $path = $this->projectDir . '/var/audit.log';
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $record = [
            'timestamp' => gmdate('c'),
            'action' => $action,
            'context' => $context,
        ];

        file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
