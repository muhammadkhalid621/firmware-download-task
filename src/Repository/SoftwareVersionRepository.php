<?php

declare(strict_types=1);

namespace App\Repository;

final class SoftwareVersionRepository
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $rows = $this->loadRows();

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['name'], $left['system_version_alt']] <=> [$right['name'], $right['system_version_alt']]
        );

        return $rows;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, int>, stats: array<string, int>}
     */
    public function paginate(string $query = '', string $filter = 'all', int $page = 1, int $perPage = 10): array
    {
        $rows = $this->all();
        $query = trim(mb_strtolower($query));
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $filtered = array_values(array_filter($rows, static function (array $row) use ($query, $filter): bool {
            $haystack = mb_strtolower(implode(' ', [
                (string) $row['name'],
                (string) $row['system_version'],
                (string) $row['system_version_alt'],
                (string) $row['link'],
                (string) $row['st'],
                (string) $row['gd'],
            ]));

            $matchesQuery = $query === '' || str_contains($haystack, $query);
            $matchesFilter = match ($filter) {
                'latest' => (bool) $row['latest'],
                'st' => (string) $row['st'] !== '',
                'gd' => (string) $row['gd'] !== '',
                'lci' => str_starts_with((string) $row['name'], 'LCI'),
                default => true,
            };

            return $matchesQuery && $matchesFilter;
        }));

        $total = count($filtered);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($filtered, $offset, $perPage),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
            'stats' => [
                'total' => count($rows),
                'latest' => count(array_filter($rows, static fn (array $row): bool => (bool) $row['latest'])),
                'st' => count(array_filter($rows, static fn (array $row): bool => (string) $row['st'] !== '')),
                'gd' => count(array_filter($rows, static fn (array $row): bool => (string) $row['gd'] !== '')),
            ],
        ];
    }

    public function find(string $id): ?array
    {
        foreach ($this->loadRows() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    public function findDuplicate(string $name, string $systemVersionAlt, ?string $ignoreId = null): ?array
    {
        foreach ($this->loadRows() as $row) {
            if ($ignoreId !== null && $row['id'] === $ignoreId) {
                continue;
            }

            if (strcasecmp($row['name'], $name) === 0 && strcasecmp($row['system_version_alt'], $systemVersionAlt) === 0) {
                return $row;
            }
        }

        return null;
    }

    public function findLatestConflict(string $name, ?string $ignoreId = null): ?array
    {
        foreach ($this->loadRows() as $row) {
            if ($ignoreId !== null && $row['id'] === $ignoreId) {
                continue;
            }

            if ($row['latest'] && strcasecmp($row['name'], $name) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function create(array $row): void
    {
        $rows = $this->loadRows();
        $row['id'] = $this->generateId();
        $rows[] = $this->normalizeRow($row);

        $this->writeRows($rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function update(string $id, array $row): bool
    {
        $rows = $this->loadRows();

        foreach ($rows as $index => $existingRow) {
            if ($existingRow['id'] !== $id) {
                continue;
            }

            $row['id'] = $id;
            $rows[$index] = $this->normalizeRow($row);
            $this->writeRows($rows);

            return true;
        }

        return false;
    }

    public function delete(string $id): bool
    {
        $rows = $this->loadRows();

        foreach ($rows as $index => $row) {
            if ($row['id'] !== $id) {
                continue;
            }

            array_splice($rows, $index, 1);
            $this->writeRows($rows);

            return true;
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRows(): array
    {
        $path = $this->getStoragePath();

        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $decoded = json_decode($contents ?: '[]', true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach (array_values($decoded) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!isset($row['id']) || !is_string($row['id']) || $row['id'] === '') {
                $row['id'] = sprintf('legacy-%05d', $index + 1);
            }

            $rows[] = $this->normalizeRow($row);
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function writeRows(array $rows): void
    {
        $path = $this->getStoragePath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open the software version storage file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock the software version storage file.');
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? $this->generateId()),
            'name' => trim((string) ($row['name'] ?? '')),
            'system_version' => trim((string) ($row['system_version'] ?? '')),
            'system_version_alt' => trim((string) ($row['system_version_alt'] ?? '')),
            'link' => trim((string) ($row['link'] ?? '')),
            'st' => trim((string) ($row['st'] ?? '')),
            'gd' => trim((string) ($row['gd'] ?? '')),
            'latest' => filter_var($row['latest'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    private function generateId(): string
    {
        return 'sv-' . bin2hex(random_bytes(6));
    }

    private function getStoragePath(): string
    {
        return $this->projectDir . '/data/softwareversions.json';
    }
}
