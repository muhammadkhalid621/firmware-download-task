<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SoftwareVersionRepository;

final class SoftwareVersionValidator
{
    public function __construct(
        private readonly SoftwareVersionRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    public function validate(array $values, ?string $ignoreId = null): array
    {
        $errors = [];

        foreach (['name', 'system_version', 'system_version_alt', 'link'] as $field) {
            if (((string) ($values[$field] ?? '')) === '') {
                $errors[] = sprintf('%s is required.', ucfirst(str_replace('_', ' ', $field)));
            }
        }

        if (($values['system_version'] ?? '') !== '' && !preg_match('/^[vV]/', (string) $values['system_version'])) {
            $errors[] = 'System version must start with "v" to match the legacy format.';
        }

        if (($values['system_version_alt'] ?? '') !== '' && preg_match('/^[vV]/', (string) $values['system_version_alt'])) {
            $errors[] = 'System version alt must not start with "v".';
        }

        foreach (['link', 'st', 'gd'] as $field) {
            $value = (string) ($values[$field] ?? '');
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                $errors[] = sprintf('%s must be a valid URL.', strtoupper($field) === 'LINK' ? 'Main link' : strtoupper($field) . ' link');
            }
        }

        $duplicate = $this->repository->findDuplicate((string) ($values['name'] ?? ''), (string) ($values['system_version_alt'] ?? ''), $ignoreId);
        if ($duplicate !== null) {
            $errors[] = 'A software version with the same product name and alt version already exists.';
        }

        if (!empty($values['latest'])) {
            $latestConflict = $this->repository->findLatestConflict((string) ($values['name'] ?? ''), $ignoreId);
            if ($latestConflict !== null) {
                $errors[] = 'Only one entry per product name can be marked as latest.';
            }
        }

        $name = strtoupper((string) ($values['name'] ?? ''));
        if (str_contains($name, 'CIC') && (string) ($values['st'] ?? '') === '') {
            $errors[] = 'CIC entries should include an ST link.';
        }

        if ((str_contains($name, 'NBT') || str_contains($name, 'EVO')) && (string) ($values['gd'] ?? '') === '') {
            $errors[] = 'NBT and EVO entries should include a GD link.';
        }

        return array_values(array_unique($errors));
    }
}
