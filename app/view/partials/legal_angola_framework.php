<?php
/** @var string $legalFrameworkTitle */
/** @var array<int, string> $legalFrameworkItems */
$legalFrameworkTitle = isset($legalFrameworkTitle) ? (string) $legalFrameworkTitle : 'Enquadramento legal em Angola';
$legalFrameworkItems = isset($legalFrameworkItems) && is_array($legalFrameworkItems) ? $legalFrameworkItems : [];
?>
<aside class="legal-framework" role="note" aria-label="Enquadramento legal">
    <p class="legal-framework-title"><?php echo htmlspecialchars($legalFrameworkTitle, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if (!empty($legalFrameworkItems)): ?>
    <ul>
        <?php foreach ($legalFrameworkItems as $item): ?>
        <li><?php echo htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</aside>
