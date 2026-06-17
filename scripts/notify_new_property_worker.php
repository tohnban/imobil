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

use App\model\BackgroundJob;
use App\model\Log;
use App\model\Property;
use App\model\SavedSearch;
use App\model\User;
use Src\classes\ClassMailer;
use Src\classes\ClassSettings;

$workerId = 'notify-new-property-worker-' . getmypid() . '-' . bin2hex(random_bytes(3));
$batchSize = max(1, ClassSettings::int('notify_property_batch_size', 20));
$lockTimeout = max(60, ClassSettings::int('notify_property_lock_timeout_seconds', 300));

$result = [
    'timestamp' => date('c'),
    'ok' => true,
    'worker' => $workerId,
    'requeued_stale' => 0,
    'claimed' => 0,
    'completed' => 0,
    'failed' => 0,
    'error' => null,
];

try {
    $result['requeued_stale'] = BackgroundJob::requeueStaleProcessing($lockTimeout);
    $jobs = BackgroundJob::claimBatch('notify_new_property', $workerId, $batchSize);
    $result['claimed'] = count($jobs);

    foreach ($jobs as $job) {
        $jobId = (int) ($job['id'] ?? 0);
        $payload = (array) ($job['payload'] ?? []);

        $userId = (int) ($payload['user_id'] ?? 0);
        $propertyId = (int) ($payload['property_id'] ?? 0);
        $savedSearchId = isset($payload['saved_search_id']) ? (int) $payload['saved_search_id'] : 0;
        $filters = (array) ($payload['filters'] ?? []);

        if ($jobId <= 0 || $userId <= 0 || $propertyId <= 0) {
            BackgroundJob::markFailedOrRetry($jobId, 'Payload inválido para notify_new_property.');
            $result['failed']++;
            continue;
        }

        $user = User::findById($userId);
        $property = Property::find($propertyId);
        $savedSearch = $savedSearchId > 0 ? SavedSearch::find($savedSearchId) : null;

        if (!$user || empty($user['email'])) {
            BackgroundJob::markFailedOrRetry($jobId, 'Usuário não encontrado ou e-mail ausente.');
            $result['failed']++;
            continue;
        }

        if (!$property) {
            BackgroundJob::markFailedOrRetry($jobId, 'Propriedade não encontrada.');
            $result['failed']++;
            continue;
        }

        $propertyTitle = trim((string) ($property['title'] ?? 'Nova propriedade disponível'));
        $propertyUrl = DIRPAGE . 'property/' . $propertyId;
        $searchName = $savedSearch['name'] ?? 'sua pesquisa salva';
        $subject = 'Nova propriedade encontrada para ' . $searchName;

        $intro = "Olá " . htmlspecialchars($user['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8') . ",<br><br>";
        $intro .= "Encontramos uma nova propriedade que corresponde às suas preferências.";
        if (!empty($searchName)) {
            $intro .= "<br>Pesquisa: <strong>" . htmlspecialchars($searchName, ENT_QUOTES, 'UTF-8') . "</strong>";
        }
        $intro .= "<br><br>";

        $details = '';
        if (!empty($propertyTitle)) {
            $details .= "<strong>" . htmlspecialchars($propertyTitle, ENT_QUOTES, 'UTF-8') . "</strong><br>";
        }
        if (!empty($property['location'])) {
            $details .= htmlspecialchars($property['location'], ENT_QUOTES, 'UTF-8') . "<br>";
        }
        if (!empty($property['price'])) {
            $details .= "Preço: " . htmlspecialchars((string) $property['price'], ENT_QUOTES, 'UTF-8') . "<br>";
        }
        if (!empty($property['type'])) {
            $details .= "Tipo: " . htmlspecialchars($property['type'], ENT_QUOTES, 'UTF-8') . "<br>";
        }

        $message = $intro
            . $details
            . "<br><a href=\"{$propertyUrl}\" style=\"display:inline-block;padding:10px 18px;background:#1a73e8;color:#fff;text-decoration:none;border-radius:4px;\">Ver propriedade</a><br><br>"
            . "Se desejar ajustar sua pesquisa, acesse seu painel de pesquisas salvas.";

        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));

        $sent = ClassMailer::sendQueued($user['email'], $user['name'] ?? '', $subject, $message, $plainText);

        if ($sent) {
            BackgroundJob::markCompleted($jobId);
            $result['completed']++;
            continue;
        }

        BackgroundJob::markFailedOrRetry($jobId, 'Falha ao enviar notificação de nova propriedade.');
        $result['failed']++;
    }
} catch (\Throwable $e) {
    $result['ok'] = false;
    $result['error'] = $e->getMessage();
}

Log::create([
    'user_id' => null,
    'action' => 'notify_new_property_worker_run',
    'entity_type' => 'background_job',
    'entity_id' => null,
    'details' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
