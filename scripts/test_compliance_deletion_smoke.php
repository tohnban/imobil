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

use App\model\Document;
use App\model\Property;
use App\model\PropertyBoostRequest;
use App\model\User;
use App\services\ComplianceDeletionService;

$checks = [
    'ComplianceDeletionService::runScheduledMaintenance' => method_exists(ComplianceDeletionService::class, 'runScheduledMaintenance'),
    'ComplianceDeletionService::purgePropertyResidualData' => method_exists(ComplianceDeletionService::class, 'purgePropertyResidualData'),
    'Document::getAllByProperty' => method_exists(Document::class, 'getAllByProperty'),
    'Document::deleteAllRecordsForProperty' => method_exists(Document::class, 'deleteAllRecordsForProperty'),
    'PropertyBoostRequest::clearPaymentProofsForProperty' => method_exists(PropertyBoostRequest::class, 'clearPaymentProofsForProperty'),
    'User::requestAccountDeletion' => method_exists(User::class, 'requestAccountDeletion'),
    'Property::purgePermanently' => method_exists(Property::class, 'purgePermanently'),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, 'Smoke checks failed:' . PHP_EOL);
    foreach ($failed as $label) {
        fwrite(STDERR, '  - ' . $label . PHP_EOL);
    }
    exit(1);
}

$result = ComplianceDeletionService::runScheduledMaintenance(1);

echo 'Compliance deletion smoke test passed.' . PHP_EOL;
echo 'Scheduler sample run:' . PHP_EOL;
echo '  Accounts purged: ' . (int) ($result['accounts'] ?? 0) . PHP_EOL;
echo '  Properties purged: ' . (int) ($result['properties'] ?? 0) . PHP_EOL;
echo '  Account reminders sent: ' . (int) ($result['account_reminders'] ?? 0) . PHP_EOL;
echo '  Property reminders sent: ' . (int) ($result['property_reminders'] ?? 0) . PHP_EOL;
echo '  Overdue accounts: ' . (int) ($result['overdue_accounts'] ?? 0) . PHP_EOL;
echo '  Overdue properties: ' . (int) ($result['overdue_properties'] ?? 0) . PHP_EOL;

exit(($result['overdue_accounts'] ?? 0) > 0 || ($result['overdue_properties'] ?? 0) > 0 ? 2 : 0);
