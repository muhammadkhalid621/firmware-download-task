#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$kernel = new Kernel('dev', true);

$cases = [
    ['label' => 'Standard ST update', 'version' => 'v3.3.6.mmipri.c', 'hwVersion' => 'CPAA_2020.10.10'],
    ['label' => 'Standard ST latest', 'version' => 'v3.3.7.mmipri.c', 'hwVersion' => 'CPAA_2020.10.10'],
    ['label' => 'Standard GD update', 'version' => 'v3.1.1.4.mmi.b', 'hwVersion' => 'CPAA_G_2020.10.10'],
    ['label' => 'LCI CIC update', 'version' => 'v3.4.1.mmiprixu.c', 'hwVersion' => 'B_C_2020.10.10'],
    ['label' => 'LCI PRO EVO latest', 'version' => 'v3.4.4.mmiprolci', 'hwVersion' => 'B_E_G_2020.10.10'],
    ['label' => 'Missing version', 'version' => '', 'hwVersion' => 'CPAA_2020.10.10'],
    ['label' => 'Missing hwVersion', 'version' => 'v3.3.6.mmipri.c', 'hwVersion' => ''],
    ['label' => 'Invalid hardware', 'version' => 'v3.3.6.mmipri.c', 'hwVersion' => 'INVALID_HW'],
    ['label' => 'Unknown version', 'version' => 'v0.0.0.test', 'hwVersion' => 'CPAA_2020.10.10'],
];

$failures = [];
$results = [];

foreach ($cases as $case) {
    $legacy = legacy_match($case['version'], $case['hwVersion']);
    $current = current_match($kernel, $case['version'], $case['hwVersion']);

    $matches = $legacy === $current;
    $results[] = [
        'label' => $case['label'],
        'matches' => $matches,
        'legacy' => $legacy,
        'current' => $current,
    ];

    if (!$matches) {
        $failures[] = $case['label'];
    }
}

echo json_encode([
    'status' => $failures === [] ? 'ok' : 'failed',
    'failures' => $failures,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($failures === [] ? 0 : 1);

/**
 * @return array<string, mixed>
 */
function current_match(Kernel $kernel, string $version, string $hwVersion): array
{
    $request = Request::create(
        '/api2/carplay/software/version',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode([
            'version' => $version,
            'mcuVersion' => '',
            'hwVersion' => $hwVersion,
        ], JSON_THROW_ON_ERROR),
    );

    $response = $kernel->handle($request);
    $content = json_decode($response->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
    $kernel->terminate($request, $response);

    return is_array($content) ? $content : [];
}

/**
 * @return array<string, mixed>
 */
function legacy_match(string $version, string $hwVersion): array
{
    if ($version === '') {
        return ['msg' => 'Version is required'];
    }

    if ($hwVersion === '') {
        return ['msg' => 'HW Version is required'];
    }

    $patternST = '/^CPAA_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i';
    $patternGD = '/^CPAA_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i';
    $patternLCI_CIC = '/^B_C_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';
    $patternLCI_NBT = '/^B_N_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';
    $patternLCI_EVO = '/^B_E_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';

    $hwVersionBool = false;
    $stBool = false;
    $gdBool = false;
    $isLCI = false;
    $lciHwType = '';

    if (preg_match($patternST, $hwVersion) === 1) {
        $hwVersionBool = true;
        $stBool = true;
    }

    if (preg_match($patternGD, $hwVersion) === 1) {
        $hwVersionBool = true;
        $gdBool = true;
    }

    if (preg_match($patternLCI_CIC, $hwVersion) === 1) {
        $hwVersionBool = true;
        $isLCI = true;
        $lciHwType = 'CIC';
        $stBool = true;
    } elseif (preg_match($patternLCI_NBT, $hwVersion) === 1) {
        $hwVersionBool = true;
        $isLCI = true;
        $lciHwType = 'NBT';
        $gdBool = true;
    } elseif (preg_match($patternLCI_EVO, $hwVersion) === 1) {
        $hwVersionBool = true;
        $isLCI = true;
        $lciHwType = 'EVO';
        $gdBool = true;
    }

    if (!$hwVersionBool) {
        return ['msg' => 'There was a problem identifying your software. Contact us for help.'];
    }

    $softwareVersions = json_decode((string) file_get_contents(dirname(__DIR__) . '/softwareversions.json'), true, flags: JSON_THROW_ON_ERROR);
    if (str_starts_with(strtolower($version), 'v')) {
        $version = substr($version, 1);
    }

    foreach ($softwareVersions as $row) {
        if (strcasecmp((string) $row['system_version_alt'], $version) !== 0) {
            continue;
        }

        $isLciEntry = str_starts_with((string) $row['name'], 'LCI');
        if ($isLCI !== $isLciEntry) {
            continue;
        }

        if ($isLCI && stripos((string) $row['name'], $lciHwType) === false) {
            continue;
        }

        if (!empty($row['latest'])) {
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
            'msg' => 'The latest version of software is ' . ($isLCI ? 'v3.4.4' : 'v3.3.7') . ' ',
            'link' => (string) $row['link'],
            'st' => $stBool ? (string) $row['st'] : '',
            'gd' => $gdBool ? (string) $row['gd'] : '',
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
