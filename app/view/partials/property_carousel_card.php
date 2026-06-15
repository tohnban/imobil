<?php
/** @var array $property */
/** @var array $favoriteIds */
/** @var int|null $position */
/** @var string $badgeLabel */
/** @var bool $showSponsoredBadge */

$position = isset($position) ? (int) $position : 0;
$badgeLabel = isset($badgeLabel) ? (string) $badgeLabel : '';
$showSponsoredBadge = !empty($showSponsoredBadge);
$favoriteIds = isset($favoriteIds) && is_array($favoriteIds) ? $favoriteIds : [];

$isFav = in_array((int) ($property['id'] ?? 0), $favoriteIds, true);
$createdAt = strtotime((string) ($property['created_at'] ?? ''));
$daysDiff = $createdAt > 0 ? (int) floor((time() - $createdAt) / 86400) : 99;
if ($badgeLabel === '') {
    if ($daysDiff === 0) {
        $badgeLabel = 'Publicado hoje';
    } elseif ($daysDiff === 1) {
        $badgeLabel = 'Publicado ontem';
    } elseif ($daysDiff <= 7) {
        $badgeLabel = 'Publicado há ' . $daysDiff . ' dias';
    } else {
        $badgeLabel = 'Alta procura';
    }
}
$imagesList = json_decode((string) ($property['images'] ?? '[]'), true);
$firstImage = (is_array($imagesList) && !empty($imagesList[0])) ? (string) $imagesList[0] : '';
if ($firstImage !== '') {
    $firstImage = \Src\classes\ClassMediaUrl::propertyImage($firstImage);
}
$coverImage = $firstImage !== '' ? $firstImage : (DIRIMG . 'apt20.avif');
$ownerPhoneDigits = preg_replace('/\D+/', '', (string) ($property['owner_phone'] ?? ''));
if ($ownerPhoneDigits !== '' && strpos($ownerPhoneDigits, '244') !== 0) {
    $ownerPhoneDigits = '244' . $ownerPhoneDigits;
}
$waMessage = rawurlencode('Ola, vi o imovel ' . ($property['title'] ?? '') . ' em ' . DIRPAGE . ' e quero negociar.');
$waLink = $ownerPhoneDigits !== '' ? ('https://wa.me/' . $ownerPhoneDigits . '?text=' . $waMessage) : '';
?>
<article class="sales-carousel-card" data-property-id="<?php echo (int) ($property['id'] ?? 0); ?>">
    <div class="sales-carousel-img">
        <img src="<?php echo htmlspecialchars($coverImage); ?>" alt="<?php echo htmlspecialchars((string) ($property['title'] ?? '')); ?>" loading="lazy">
        <?php if (Src\classes\ClassAuth::check() && !(Src\classes\ClassAuth::user()['is_admin'] ?? false)): ?>
            <form method="POST" action="<?php echo DIRPAGE; ?>property/<?php echo $isFav ? 'unfavorite' : 'favorite'; ?>/<?php echo (int) $property['id']; ?>" class="favorite-form-inline favorite-overlay favorite-overlay-rank-shift">
                <?php echo Src\classes\ClassCsrf::field(); ?>
                <button type="submit" class="btn-favorite<?php echo $isFav ? ' is-active' : ''; ?>" title="<?php echo $isFav ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>" aria-label="<?php echo $isFav ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>">
                    <i class="fa <?php echo $isFav ? 'fa-heart' : 'fa-heart-o'; ?>"></i>
                </button>
            </form>
        <?php endif; ?>
        <?php if ($showSponsoredBadge): ?>
            <span class="sales-card-badge"><i class="fa fa-bullhorn"></i> Patrocinado</span>
        <?php endif; ?>
        <?php if ($position > 0 && $position <= 3): ?>
            <span class="sales-rank-badge">TOP <?php echo $position; ?></span>
        <?php endif; ?>
    </div>
    <div class="sales-carousel-card-body">
        <div class="sales-carousel-tags">
            <span class="sales-urgency-tag"><i class="fa <?php echo $daysDiff <= 1 ? 'fa-clock-o' : ($daysDiff <= 7 ? 'fa-calendar' : 'fa-fire'); ?>"></i> <?php echo htmlspecialchars($badgeLabel); ?></span>
        </div>
        <h3><a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $property['id']; ?>" class="card-title-link"><?php echo htmlspecialchars((string) ($property['title'] ?? '')); ?></a></h3>
        <p class="sales-location"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars((string) ($property['location'] ?? '')); ?></p>
        <p class="sales-price"><?php echo number_format((float) ($property['price'] ?? 0), 0, ',', '.'); ?> Kz</p>
        <div class="sales-meta-row">
            <span><i class="fa fa-bed"></i> <?php echo (int) ($property['bedrooms'] ?? 0); ?> quartos</span>
            <span><i class="fa fa-bath"></i> <?php echo (int) ($property['bathrooms'] ?? 0); ?> banhos</span>
        </div>
        <div class="sales-trust-row">
            <?php $ownerHandle = htmlspecialchars(Src\classes\UserDisplay::publicHandleFromRow($property, 'owner_username', 'owner_name', 'Proprietário')); ?>
            <span><i class="fa fa-user"></i>
                <?php if (!empty($property['affiliate_id'])): ?>
                    <a href="<?php echo htmlspecialchars(Src\classes\ClassPlan::getPublicProfileUrl((int) $property['affiliate_id'])); ?>" class="owner-name-link"><?php echo $ownerHandle; ?></a>
                <?php else: ?>
                    <?php echo $ownerHandle; ?>
                <?php endif; ?>
            </span>
            <?php if (!empty($property['owner_verified'])): ?>
                <span class="sales-trust-chip verified"><i class="fa fa-check-circle"></i> Verificado</span>
            <?php endif; ?>
            <?php if (!empty($property['owner_trusted'])): ?>
                <span class="sales-trust-chip trusted"><i class="fa fa-shield"></i> Confiança</span>
            <?php endif; ?>
        </div>
        <div class="sales-carousel-actions">
            <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $property['id']; ?>" class="btn-primary sales-card-cta">Ver este imóvel</a>
            <?php if ($waLink !== ''): ?>
                <a href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" rel="noopener noreferrer" class="btn-secondary sales-card-cta sales-wa-cta">
                    <i class="fa fa-whatsapp"></i> Contactar agora
                </a>
            <?php endif; ?>
        </div>
    </div>
</article>
