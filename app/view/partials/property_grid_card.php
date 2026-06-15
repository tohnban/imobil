<?php
/** @var array $property */
/** @var array $favoriteIds */
/** @var callable $formatType */
/** @var callable $formatPurpose */

$favoriteIds = isset($favoriteIds) && is_array($favoriteIds) ? $favoriteIds : [];
$isFav = in_array((int) ($property['id'] ?? 0), $favoriteIds, true);
$regularImages = json_decode((string) ($property['images'] ?? '[]'), true);
$regularFirstImage = (is_array($regularImages) && !empty($regularImages[0])) ? (string) $regularImages[0] : '';
if ($regularFirstImage !== '') {
    $regularFirstImage = \Src\classes\ClassMediaUrl::propertyImage($regularFirstImage);
}
$regularCoverImage = $regularFirstImage !== '' ? $regularFirstImage : (DIRIMG . 'apt20.avif');
?>
<article class="sales-property-card<?php echo !empty($property['featured']) ? ' is-promoted' : ''; ?>">
    <div class="sales-property-media">
        <img src="<?php echo htmlspecialchars($regularCoverImage); ?>" alt="<?php echo htmlspecialchars((string) ($property['title'] ?? '')); ?>" loading="lazy">
        <?php if (Src\classes\ClassAuth::check() && !(Src\classes\ClassAuth::user()['is_admin'] ?? false)): ?>
            <form method="POST" action="<?php echo DIRPAGE; ?>property/<?php echo $isFav ? 'unfavorite' : 'favorite'; ?>/<?php echo (int) $property['id']; ?>" class="favorite-form-inline favorite-overlay">
                <?php echo Src\classes\ClassCsrf::field(); ?>
                <button type="submit" class="btn-favorite<?php echo $isFav ? ' is-active' : ''; ?>" title="<?php echo $isFav ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>" aria-label="<?php echo $isFav ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>">
                    <i class="fa <?php echo $isFav ? 'fa-heart' : 'fa-heart-o'; ?>"></i>
                </button>
            </form>
        <?php endif; ?>
        <?php if (!empty($property['featured'])): ?>
            <span class="sales-card-badge"><i class="fa fa-bullhorn"></i> Patrocinado</span>
        <?php elseif (!empty($property['video_url'])): ?>
            <span class="sales-card-badge"><i class="fa fa-video-camera"></i> Video</span>
        <?php endif; ?>
    </div>

    <div class="sales-property-body">
        <h3><a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $property['id']; ?>" class="card-title-link"><?php echo htmlspecialchars((string) ($property['title'] ?? '')); ?></a></h3>
        <p class="sales-location"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars((string) ($property['location'] ?? '')); ?></p>
        <p class="sales-price"><?php echo number_format((float) ($property['price'] ?? 0), 0, ',', '.'); ?> Kz</p>

        <div class="sales-meta-row">
            <span><i class="fa fa-tag"></i> <?php echo htmlspecialchars($formatType($property['type'] ?? null)); ?></span>
            <span><i class="fa fa-briefcase"></i> <?php echo htmlspecialchars($formatPurpose($property['purpose'] ?? null)); ?></span>
            <?php if (!empty($property['area'])): ?>
                <span><i class="fa fa-expand"></i> <?php echo number_format((float) $property['area'], 0, ',', '.'); ?> m2</span>
            <?php endif; ?>
        </div>

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
                <span class="sales-trust-chip trusted"><i class="fa fa-shield"></i> Confianca</span>
            <?php endif; ?>
        </div>

        <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $property['id']; ?>" class="btn-primary sales-card-cta">Ver este imóvel</a>
    </div>
</article>
