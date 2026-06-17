<?php
/** @var string $dashboardPageClass optional extra classes on the page wrapper */
/** @var string $dashboardPageBase optional wrapper base classes, default container dashboard-view */
/** @var string $dashboardPageAttrs optional raw attributes (id, data-*, aria-*) — caller must escape values */
$pageClass = isset($dashboardPageClass) ? trim((string) $dashboardPageClass) : '';
$pageBase = isset($dashboardPageBase) ? trim((string) $dashboardPageBase) : 'container dashboard-view';
$pageAttrs = isset($dashboardPageAttrs) ? trim((string) $dashboardPageAttrs) : '';
?>
<div class="<?php echo htmlspecialchars($pageBase, ENT_QUOTES, 'UTF-8'); ?><?php echo $pageClass !== '' ? ' ' . htmlspecialchars($pageClass, ENT_QUOTES, 'UTF-8') : ''; ?>"<?php echo $pageAttrs !== '' ? ' ' . $pageAttrs : ''; ?>>
