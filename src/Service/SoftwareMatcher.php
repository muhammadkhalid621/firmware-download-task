<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SoftwareVersionRepository;

final class SoftwareMatcher
{
    public function __construct(
        private readonly SoftwareVersionRepository $repository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function match(?string $version, ?string $hwVersion): array
    {
        $version = trim((string) $version);
        $hwVersion = trim((string) $hwVersion);

        if ($version === '') {
            return ['msg' => 'Version is required'];
        }

        if ($hwVersion === '') {
            return ['msg' => 'HW Version is required'];
        }

        $hardware = $this->detectHardware($hwVersion);

        if ($hardware === null) {
            return $this->identificationError();
        }

        if (str_starts_with(strtolower($version), 'v')) {
            $version = substr($version, 1);
        }

        foreach ($this->repository->all() as $row) {
            if (strcasecmp((string) $row['system_version_alt'], $version) !== 0) {
                continue;
            }

            $isLciEntry = str_starts_with((string) $row['name'], 'LCI');

            if ($hardware['isLci'] !== $isLciEntry) {
                continue;
            }

            if ($hardware['isLci'] && stripos((string) $row['name'], (string) $hardware['lciType']) === false) {
                continue;
            }

            if ($row['latest']) {
                return [
                    'versionExist' => true,
                    'msg' => 'Your system is upto date!',
                    'link' => '',
                    'st' => '',
                    'gd' => '',
                ];
            }

            return [
                'versionExist' => true,
                'msg' => 'The latest version of software is ' . ($hardware['isLci'] ? 'v3.4.4' : 'v3.3.7') . ' ',
                'link' => (string) $row['link'],
                'st' => $hardware['stBool'] ? (string) $row['st'] : '',
                'gd' => $hardware['gdBool'] ? (string) $row['gd'] : '',
            ];
        }

        return [
            'versionExist' => false,
            'msg' => 'There was a problem identifying your software. Contact us for help.',
            'link' => '',
            'st' => '',
            'gd' => '',
        ];
    }

    /**
     * @return array{isLci: bool, lciType: string, stBool: bool, gdBool: bool}|null
     */
    private function detectHardware(string $hwVersion): ?array
    {
        if (preg_match('/^CPAA_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i', $hwVersion) === 1) {
            return ['isLci' => false, 'lciType' => '', 'stBool' => true, 'gdBool' => false];
        }

        if (preg_match('/^CPAA_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i', $hwVersion) === 1) {
            return ['isLci' => false, 'lciType' => '', 'stBool' => false, 'gdBool' => true];
        }

        if (preg_match('/^B_C_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i', $hwVersion) === 1) {
            return ['isLci' => true, 'lciType' => 'CIC', 'stBool' => true, 'gdBool' => false];
        }

        if (preg_match('/^B_N_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i', $hwVersion) === 1) {
            return ['isLci' => true, 'lciType' => 'NBT', 'stBool' => false, 'gdBool' => true];
        }

        if (preg_match('/^B_E_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i', $hwVersion) === 1) {
            return ['isLci' => true, 'lciType' => 'EVO', 'stBool' => false, 'gdBool' => true];
        }

        return null;
    }

    /**
     * @return array{msg: string}
     */
    private function identificationError(): array
    {
        return ['msg' => 'There was a problem identifying your software. Contact us for help.'];
    }
}
