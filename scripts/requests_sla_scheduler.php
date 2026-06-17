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

use App\model\Request;
use App\model\Notification;

$expiredCount = 0;
$dueAlerts = [];
$alertsSent = 0;
$error = null;

try {
    $expiredCount = Request::autoExpireOpenRequests();
    $dueAlerts = Request::getDueSlaAlerts(200);

    foreach ($dueAlerts as $entry) {
        $requestId = (int) ($entry['id'] ?? 0);
        if ($requestId <= 0) {
            continue;
        }

        $propertyTitle = (string) ($entry['property_title'] ?? 'Imovel');
        $status = (string) ($entry['status'] ?? 'pendente');
        $daysWithoutUpdate = (int) ($entry['days_without_update'] ?? 0);
        $requesterId = (int) ($entry['requester_id'] ?? 0);
        $ownerId = (int) ($entry['owner_id'] ?? 0);

        if ($requesterId > 0) {
            Notification::notifyRequestSlaReminder(
                $requesterId,
                $requestId,
                $propertyTitle,
                $status,
                $daysWithoutUpdate
            );
        }

        if ($ownerId > 0 && $ownerId !== $requesterId) {
            Notification::notifyRequestSlaReminder(
                $ownerId,
                $requestId,
                $propertyTitle,
                $status,
                $daysWithoutUpdate
            );
        }

        if (Request::markSlaAlertSent($requestId, 7)) {
            $alertsSent++;
        }
    }
} catch (\Throwable $e) {
    $error = 'scheduler_failed';
}

$report = [
    'timestamp' => date('c'),
    'expired_requests' => $expiredCount,
    'due_alerts_processed' => count($dueAlerts),
    'alerts_marked_sent' => $alertsSent,
];
if ($error !== null) {
    $report['error'] = $error;
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($error === null ? 0 : 1);
