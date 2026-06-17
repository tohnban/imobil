<?php
/** @var string $heroKicker */
/** @var string $heroTitle */
/** @var string $heroLead */
/** @var string|null $heroLeadHtml optional unescaped lead markup (caller must escape) */
/** @var string|null $heroActionsHtml optional actions block inside the section */
/** @var string $heroClass optional extra class on section */
/** @var string $heroModifier optional, default compact */
$heroModifier = isset($heroModifier) ? trim((string) $heroModifier) : 'compact';
$heroExtraClass = isset($heroClass) ? trim((string) $heroClass) : '';
$sectionClasses = trim('dashboard-view-hero ' . $heroModifier . ($heroExtraClass !== '' ? ' ' . $heroExtraClass : ''));
$hasLeadHtml = isset($heroLeadHtml);
?>
<section class="<?php echo htmlspecialchars($sectionClasses, ENT_QUOTES, 'UTF-8'); ?>">
    <div>
        <?php if (!empty($heroKicker)): ?>
            <span class="dashboard-hero-kicker"><?php echo htmlspecialchars((string) $heroKicker, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <h1><?php echo htmlspecialchars((string) $heroTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($hasLeadHtml): ?>
            <?php echo $heroLeadHtml; ?>
        <?php elseif (!empty($heroLead)): ?>
            <p><?php echo htmlspecialchars((string) $heroLead, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($heroActionsHtml)): ?>
        <?php echo $heroActionsHtml; ?>
    <?php endif; ?>
</section>
