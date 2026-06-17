<?php
/** @var int $paginationPage */
/** @var int $paginationTotalPages */
/** @var string|null $paginationCountCopy */
/** @var string|null $paginationPrevUrl */
/** @var string|null $paginationNextUrl */
/** @var string $paginationWrapClass */
$paginationPage = max(1, (int) ($paginationPage ?? 1));
$paginationTotalPages = max(1, (int) ($paginationTotalPages ?? 1));
$paginationWrapClass = isset($paginationWrapClass) ? trim((string) $paginationWrapClass) : 'dashboard-pagination-wrap dashboard-pagination-wrap-start';
$hasCountCopy = !empty($paginationCountCopy);
$hasPages = $paginationTotalPages > 1;

if (!$hasCountCopy && !$hasPages) {
    return;
}
?>
<?php if ($hasCountCopy): ?>
    <p class="dashboard-pagination-copy afiliados-pagination-copy"><?php echo htmlspecialchars((string) $paginationCountCopy, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if ($hasPages): ?>
    <div class="<?php echo htmlspecialchars($paginationWrapClass, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($paginationPrevUrl)): ?>
            <a href="<?php echo htmlspecialchars((string) $paginationPrevUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-secondary">&larr; Anterior</a>
        <?php endif; ?>
        <span class="dashboard-pagination-copy">Página <?php echo $paginationPage; ?> de <?php echo $paginationTotalPages; ?></span>
        <?php if (!empty($paginationNextUrl)): ?>
            <a href="<?php echo htmlspecialchars((string) $paginationNextUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-secondary">Próxima &rarr;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
