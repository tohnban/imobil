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
use Src\classes\ClassSettings;

$workerId = 'image-worker-' . getmypid() . '-' . bin2hex(random_bytes(3));
$batchSize = max(1, ClassSettings::int('image_queue_batch_size', 10));
$lockTimeout = max(60, ClassSettings::int('image_queue_lock_timeout_seconds', 300));

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

function processImageJob(array $payload): bool {
    $root = rtrim(dirname(__DIR__), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $source = isset($payload['source']) ? (string) $payload['source'] : '';
    if ($source === '') return false;

    $srcPath = $root . ltrim($source, '/\\');
    if (!file_exists($srcPath)) return false;

    $sizes = $payload['sizes'] ?? [[ 'w' => 400, 'h' => 300 ]];

    foreach ($sizes as $size) {
        $w = max(1, (int) ($size['w'] ?? 400));
        $h = max(1, (int) ($size['h'] ?? 300));

        $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
        $destDir = $root . 'public/storage/uploads/processed_images/';
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return false;
        }

        $baseName = pathinfo($srcPath, PATHINFO_FILENAME);
        $destPath = $destDir . $baseName . '_' . $w . 'x' . $h . '.webp';

        try {
            if (in_array($ext, ['jpg', 'jpeg'])) {
                $img = @imagecreatefromjpeg($srcPath);
            } elseif ($ext === 'png') {
                $img = @imagecreatefrompng($srcPath);
            } elseif ($ext === 'webp') {
                $img = @imagecreatefromwebp($srcPath);
            } else {
                // unsupported
                return false;
            }

            if ($img === false) {
                return false;
            }

            $srcW = imagesx($img);
            $srcH = imagesy($img);
            $ratio = max($w / $srcW, $h / $srcH);
            $newW = (int) ceil($srcW * $ratio);
            $newH = (int) ceil($srcH * $ratio);

            $tmp = imagecreatetruecolor($w, $h);
            // preserve transparency for PNG
            imagefill($tmp, 0, 0, imagecolorallocate($tmp, 255, 255, 255));
            imagecopyresampled($tmp, $img, 0 - ($newW - $w) / 2, 0 - ($newH - $h) / 2, 0, 0, $newW, $newH, $srcW, $srcH);

            // save as webp
            if (!imagewebp($tmp, $destPath, 80)) {
                imagedestroy($img);
                imagedestroy($tmp);
                return false;
            }

            imagedestroy($img);
            imagedestroy($tmp);
        } catch (\Throwable $e) {
            return false;
        }
    }

    return true;
}

try {
    $result['requeued_stale'] = BackgroundJob::requeueStaleProcessing($lockTimeout);
    $jobs = BackgroundJob::claimBatch('image_processing', $workerId, $batchSize);
    $result['claimed'] = count($jobs);

    foreach ($jobs as $job) {
        $jobId = (int) ($job['id'] ?? 0);
        $payload = (array) ($job['payload'] ?? []);

        if ($jobId <= 0) {
            $result['failed']++;
            continue;
        }

        $ok = processImageJob($payload);
        if ($ok) {
            BackgroundJob::markCompleted($jobId);
            $result['completed']++;
            continue;
        }

        BackgroundJob::markFailedOrRetry($jobId, 'Image processing failed.');
        $result['failed']++;
    }
} catch (\Throwable $e) {
    $result['ok'] = false;
    $result['error'] = $e->getMessage();
}

Log::create([
    'user_id' => null,
    'action' => 'image_queue_worker_run',
    'entity_type' => 'background_job',
    'entity_id' => null,
    'details' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
