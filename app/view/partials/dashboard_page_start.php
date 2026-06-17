<?php
/** @var string $dashboardPageClass optional extra classes on the page wrapper */
/** @var string $dashboardPageBase optional wrapper base classes, default container dashboard-view */
/** @var string $dashboardPageAttrs optional raw attributes (id, data-*, aria-*) — caller must escape values */
$pageClass = isset($dashboardPageClass) ? trim((string) $dashboardPageClass) : '';
$pageBase = isset($dashboardPageBase) ? trim((string) $dashboardPageBase) : 'container dashboard-view';
$pageAttrs = isset($dashboardPageAttrs) ? trim((string) $dashboardPageAttrs) : '';

function dashboardPageSanitizeAttrs(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    // Reject obviously dangerous injections.
    $lower = strtolower($raw);
    if (str_contains($lower, '<') || str_contains($lower, '>') || str_contains($lower, 'javascript:') || preg_match('/\\son[a-z]+\\s*=/', $lower)) {
        return '';
    }

    // Allow only attributes like: id="...", role="...", aria-*, data-* (double-quoted values).
    $out = [];
    if (preg_match_all('/\\s*([a-zA-Z][a-zA-Z0-9_-]*)\\s*=\\s*\"([^\"]*)\"/', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $name = strtolower((string) $match[1]);
            $value = (string) $match[2];

            $allowed = $name === 'id' || $name === 'role' || str_starts_with($name, 'data-') || str_starts_with($name, 'aria-');
            if (!$allowed) {
                continue;
            }

            $out[] = $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
    }

    return implode(' ', $out);
}
?>
<div class="<?php echo htmlspecialchars($pageBase, ENT_QUOTES, 'UTF-8'); ?><?php echo $pageClass !== '' ? ' ' . htmlspecialchars($pageClass, ENT_QUOTES, 'UTF-8') : ''; ?>"<?php echo $pageAttrs !== '' ? ' ' . dashboardPageSanitizeAttrs($pageAttrs) : ''; ?>>
