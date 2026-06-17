<?php
/** @var string $inboxHeroTitle */
/** @var string|null $inboxHeroMeta */
/** @var string|null $inboxHeroMetaHtml optional unescaped meta markup */
/** @var string|null $inboxHeroActionsHtml */
/** @var string $inboxHeroClass optional extra classes on the hero section */
$inboxHeroMeta = isset($inboxHeroMeta) ? (string) $inboxHeroMeta : '';
$hasInboxMetaHtml = isset($inboxHeroMetaHtml);
$inboxHeroClass = isset($inboxHeroClass) ? trim((string) $inboxHeroClass) : '';
$sectionClasses = trim('notification-inbox-hero' . ($inboxHeroClass !== '' ? ' ' . $inboxHeroClass : ''));
?>
<section class="<?php echo htmlspecialchars($sectionClasses, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="notification-inbox-hero-main">
        <h1><?php echo htmlspecialchars((string) $inboxHeroTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($hasInboxMetaHtml): ?>
            <p class="notification-inbox-hero-meta"><?php echo $inboxHeroMetaHtml; ?></p>
        <?php elseif ($inboxHeroMeta !== ''): ?>
            <p class="notification-inbox-hero-meta">
                <span><?php echo htmlspecialchars($inboxHeroMeta, ENT_QUOTES, 'UTF-8'); ?></span>
            </p>
        <?php endif; ?>
    </div>
    <?php if (!empty($inboxHeroActionsHtml)): ?>
        <div class="notification-inbox-hero-actions<?php echo !empty($inboxHeroActionsClass) ? ' ' . htmlspecialchars((string) $inboxHeroActionsClass, ENT_QUOTES, 'UTF-8') : ''; ?>">
            <?php echo $inboxHeroActionsHtml; ?>
        </div>
    <?php endif; ?>
</section>
