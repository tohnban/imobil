<?php
/** @var string $legalReferencesTitle */
/** @var string $legalReferencesContent */
$legalReferencesTitle = isset($legalReferencesTitle) ? (string) $legalReferencesTitle : 'Referências legais (Angola)';
$legalReferencesContent = isset($legalReferencesContent) ? (string) $legalReferencesContent : '';

function legalReferencesAllowlistHtml(string $html): string {
    return strip_tags($html, '<a><strong><b><em><i><u><p><br><ul><ol><li><small><span>');
}
?>
<details class="legal-references">
    <summary class="legal-references-summary"><?php echo htmlspecialchars($legalReferencesTitle, ENT_QUOTES, 'UTF-8'); ?></summary>
    <div class="legal-references-body">
        <?php echo legalReferencesAllowlistHtml($legalReferencesContent); ?>
    </div>
</details>
