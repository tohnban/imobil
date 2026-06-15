<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only." . PHP_EOL;
    exit(1);
}

$rootDir = dirname(__DIR__);

$directories = [
    'storage/cache',
    'storage/logs',
    'storage/documents',
    'storage/uploads/profiles',
    'public/storage/uploads/boost_proofs',
    'public/storage/uploads/trust_badge_proofs',
    'public/storage/uploads/subscription_proofs',
    'public/storage/uploads/commission_proofs',
    'public/storage/uploads/commission_payout_proofs',
    'public/storage/uploads/request_chat_attachments',
    'public/storage/uploads/properties',
];

$created = 0;
foreach ($directories as $relative) {
    $path = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true) && !is_dir($path)) {
            fwrite(STDERR, "Failed to create: {$relative}" . PHP_EOL);
            exit(1);
        }
        $created++;
    }
}

echo json_encode([
    'ok' => true,
    'created' => $created,
    'directories' => count($directories),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit(0);
