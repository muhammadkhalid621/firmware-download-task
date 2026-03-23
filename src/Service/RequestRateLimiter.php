<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class RequestRateLimiter
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{allowed: bool, remaining: int, retryAfter: int, limit: int}
     */
    public function hit(Request $request, string $bucket, int $limit, int $windowSeconds): array
    {
        $store = $this->loadStore();
        $key = $this->buildKey($request, $bucket);
        $now = time();

        $entry = $store[$key] ?? ['count' => 0, 'resetAt' => $now + $windowSeconds];

        if (($entry['resetAt'] ?? 0) <= $now) {
            $entry = ['count' => 0, 'resetAt' => $now + $windowSeconds];
        }

        $entry['count']++;
        $store[$key] = $entry;
        $this->writeStore($store, $now);

        $remaining = max(0, $limit - (int) $entry['count']);

        return [
            'allowed' => (int) $entry['count'] <= $limit,
            'remaining' => (int) $entry['count'] < $limit ? $remaining : 0,
            'retryAfter' => max(1, (int) $entry['resetAt'] - $now),
            'limit' => $limit,
        ];
    }

    /**
     * @return array<string, array{count: int, resetAt: int}>
     */
    private function loadStore(): array
    {
        $path = $this->getStoragePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array{count: int, resetAt: int}> $store
     */
    private function writeStore(array $store, int $now): void
    {
        $path = $this->getStoragePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        foreach ($store as $key => $entry) {
            if (($entry['resetAt'] ?? 0) <= $now) {
                unset($store[$key]);
            }
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open rate limit storage.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock rate limit storage.');
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function buildKey(Request $request, string $bucket): string
    {
        return $bucket . ':' . sha1($request->getClientIp() ?? 'unknown');
    }

    private function getStoragePath(): string
    {
        return $this->projectDir . '/var/rate_limits.json';
    }
}
