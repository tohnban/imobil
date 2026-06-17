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

use App\model\Commission;
use App\model\PaymentTransaction;

$db = new \App\model\ManipularBanco();

$report = [
    'timestamp' => date('c'),
    'system_tx_backfilled' => 0,
    'overdue_pending_count' => 0,
    'property_status_repaired' => 0,
];

try {
    // 1. Comissões pagas sem transaction de receita do sistema — backfill
    $stmt = $db->prepare("
        SELECT c.id, c.system_amount, c.payment_reference, c.affiliate_id, c.paid_at
        FROM commissions c
        WHERE c.status = 'pago'
          AND c.system_amount > 0
          AND NOT EXISTS (
              SELECT 1 FROM payment_transactions pt
              WHERE pt.related_entity_type = 'commission'
                AND pt.related_entity_id = c.id
                AND pt.transaction_type = 'system_commission'
          )
        LIMIT 200
    ");
    $stmt->execute();
    $missing = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $backfilled = 0;
    foreach ($missing as $row) {
        try {
            PaymentTransaction::create([
                'transaction_type'     => 'system_commission',
                'direction'            => 'incoming',
                'status'               => 'confirmado',
                'amount'               => (float) $row['system_amount'],
                'currency'             => 'AOA',
                'counterparty_user_id' => null,
                'related_entity_type'  => 'commission',
                'related_entity_id'    => (int) $row['id'],
                'reference_code'       => (string) ($row['payment_reference'] ?? ''),
                'confirmed_at'         => $row['paid_at'] ?? date('Y-m-d H:i:s'),
                'notes'                => 'Taxa sistema (2%) — backfill',
            ]);
            $backfilled++;
        } catch (\Throwable $e) {
            // continua
        }
    }

    $report['system_tx_backfilled'] = $backfilled;

    // 2. Comissões vencidas ainda pendentes
    $stmtOverdue = $db->prepare("
        SELECT COUNT(*) AS total FROM commissions
        WHERE status = 'pendente' AND due_at IS NOT NULL AND due_at < NOW()
    ");
    $stmtOverdue->execute();
    $report['overdue_pending_count'] = (int) ($stmtOverdue->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

    // 3. Imóveis com comissão pendente mas estado ainda não fechado comercialmente
    try {
        $report['property_status_repaired'] = Commission::repairPropertyStatusForPendingCommissions();
    } catch (\Throwable $e) {
        $report['property_status_repair_error'] = 'repair_failed';
    }
} catch (\Throwable $e) {
    $report['error'] = 'scheduler_failed';
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(isset($report['error']) ? 1 : 0);
