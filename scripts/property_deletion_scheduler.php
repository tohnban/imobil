<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script must be executed via CLI." . PHP_EOL;
    exit(1);
}

$rootDir = dirname(__DIR__);
$target = $rootDir . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'compliance_deletion_scheduler.php';

fwrite(STDERR, 'Deprecated: property_deletion_scheduler.php forwards to compliance_deletion_scheduler.php' . PHP_EOL);

$limit = isset($argv[1]) && is_numeric($argv[1]) ? (string) max(1, (int) $argv[1]) : '';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($target);
if ($limit !== '') {
    $command .= ' ' . escapeshellarg($limit);
}

passthru($command, $exitCode);
exit((int) $exitCode);
