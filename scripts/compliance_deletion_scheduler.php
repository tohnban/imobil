<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script must be executed via CLI." . PHP_EOL;
    exit(1);
}

$rootDir = dirname(__DIR__);
if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = $rootDir;
}
if (!isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] === '') {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once $rootDir . '/src/vendor/autoload.php';
require_once $rootDir . '/config/config.php';

use App\services\ComplianceDeletionService;

$limit = 50;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $limit = max(1, (int) $argv[1]);
}

$result = ComplianceDeletionService::runScheduledMaintenance($limit);

echo 'Compliance deletion run:' . PHP_EOL;
echo '  Accounts purged: ' . (int) $result['accounts'] . PHP_EOL;
echo '  Properties purged: ' . (int) $result['properties'] . PHP_EOL;
echo '  Account reminders sent: ' . (int) $result['account_reminders'] . PHP_EOL;
echo '  Property reminders sent: ' . (int) $result['property_reminders'] . PHP_EOL;
echo '  Overdue accounts: ' . (int) $result['overdue_accounts'] . PHP_EOL;
echo '  Overdue properties: ' . (int) $result['overdue_properties'] . PHP_EOL;

exit(0);
