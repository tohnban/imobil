<?php
/** @var string $legalReferencesTitle */
/** @var string $legalReferencesContent */
$legalReferencesTitle = isset($legalReferencesTitle) ? (string) $legalReferencesTitle : 'Referências legais (Angola)';
$legalReferencesContent = isset($legalReferencesContent) ? (string) $legalReferencesContent : '';

function legalReferencesAllowlistHtml(string $html): string {
    $html = (string) $html;
    if ($html === '') {
        return '';
    }

    // Keep only basic formatting tags, then remove dangerous attributes/protocols.
    $allowed = '<a><strong><b><em><i><u><p><br><ul><ol><li><small><span>';
    $filtered = strip_tags($html, $allowed);

    // If DOM is unavailable, fall back to plain text.
    if (!class_exists(\DOMDocument::class)) {
        return htmlspecialchars($filtered, ENT_QUOTES, 'UTF-8');
    }

    $doc = new \DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $filtered, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new \DOMXPath($doc);
    foreach ($xpath->query('//*[@*]') as $node) {
        /** @var \DOMElement $node */
        $attrs = [];
        foreach (iterator_to_array($node->attributes ?? []) as $attr) {
            $attrs[] = (string) $attr->name;
        }

        foreach ($attrs as $name) {
            $lname = strtolower($name);
            // Remove event handlers and styling hooks.
            if (str_starts_with($lname, 'on') || $lname === 'style') {
                $node->removeAttribute($name);
                continue;
            }

            // For links, keep only safe href protocols.
            if ($node->tagName === 'a' && $lname === 'href') {
                $href = trim((string) $node->getAttribute('href'));
                $hrefLower = strtolower($href);
                $isRelative = $hrefLower === '' || str_starts_with($hrefLower, '/') || str_starts_with($hrefLower, '#');
                $isHttp = str_starts_with($hrefLower, 'http://') || str_starts_with($hrefLower, 'https://');
                $isMailto = str_starts_with($hrefLower, 'mailto:');
                if (!$isRelative && !$isHttp && !$isMailto) {
                    $node->removeAttribute('href');
                }
                continue;
            }

            // Drop all other attributes by default.
            if (!($node->tagName === 'a' && $lname === 'href')) {
                $node->removeAttribute($name);
            }
        }
    }

    return (string) $doc->saveHTML();
}
?>
<details class="legal-references">
    <summary class="legal-references-summary"><?php echo htmlspecialchars($legalReferencesTitle, ENT_QUOTES, 'UTF-8'); ?></summary>
    <div class="legal-references-body">
        <?php echo legalReferencesAllowlistHtml($legalReferencesContent); ?>
    </div>
</details>
