<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Firmware Download';
$appName = $appName ?? 'app';
$payload = $payload ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <div id="app" data-app="<?= htmlspecialchars((string) $appName, ENT_QUOTES, 'UTF-8') ?>"></div>
    <script id="app-payload" type="application/json"><?= json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <script type="module" src="/assets/app.js"></script>
</body>
</html>
