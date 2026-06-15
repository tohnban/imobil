<?php
$owner = $owner ?? [];
$trustMetrics = $trustMetrics ?? [];
$officialPlan = $officialPlan ?? [];
$properties = $properties ?? [];

$ownerDisplay = htmlspecialchars(Src\classes\UserDisplay::handleWithAt($owner));
if ($ownerDisplay === '') {
    $ownerDisplay = 'Proprietário';
}
$ownerPlan = htmlspecialchars((string) ($officialPlan['name'] ?? 'Plano Essencial'));
$isVerified = !empty($trustMetrics['verified']);
$isTrusted = !empty($trustMetrics['trusted']);

$rawPhoto = (string) ($owner['profile_photo'] ?? '');
$avatarUrl = $rawPhoto !== '' ? \Src\classes\ClassMediaUrl::profilePhoto($rawPhoto) : '';

$presentation = htmlspecialchars((string) ($owner['presentation'] ?? ''));
$propertyCount = count($properties);
$rawEmail = trim((string) ($owner['email'] ?? ''));
$rawPhone = trim((string) ($owner['phone'] ?? ''));
$adminCanViewContact = Src\classes\ClassAuth::check() && Src\classes\ClassAccess::isAdmin(Src\classes\ClassAuth::user());
$phoneDigits = preg_replace('/\D+/', '', $rawPhone);
?>

<div class="container property-page-shell property-owner-page-view">

    <section class="property-show-hero">
        <div class="property-show-copy">
            <div class="property-show-topline">
                <span class="sales-kicker">Perfil</span>
            </div>
            <h1><?php echo $ownerDisplay; ?></h1>
            <p>Veja as informações do perfil, os selos de confiança e os imóveis disponíveis neste portfólio.</p>

            <div class="property-show-tags">
                <?php if ($isVerified): ?>
                    <span><i class="fa fa-check-circle"></i> Conta verificada</span>
                <?php endif; ?>
                <?php if ($isTrusted): ?>
                    <span><i class="fa fa-shield"></i> Utilizador de confiança</span>
                <?php endif; ?>
                <?php if (!$isVerified && !$isTrusted): ?>
                    <span><i class="fa fa-user"></i> Conta ativa</span>
                <?php endif; ?>
                <span><i class="fa fa-home"></i> <?php echo $propertyCount; ?> imóvel(is) disponível(is)</span>
            </div>

            <div class="property-show-proof">
                <div>
                    <strong><?php echo $propertyCount; ?></strong>
                    <span>imóveis activos</span>
                </div>
                <div>
                    <strong><?php echo $ownerPlan; ?></strong>
                    <span>plano na plataforma</span>
                </div>
                <div>
                    <strong><?php echo $isVerified ? 'Sim' : 'Não'; ?></strong>
                    <span>conta verificada</span>
                </div>
            </div>
        </div>

        <aside class="property-show-summary-card owner-summary-card">
            <span class="property-summary-kicker">Identidade</span>
            <?php if ($avatarUrl !== ''): ?>
                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo $ownerDisplay; ?>" class="owner-profile-avatar">
            <?php else: ?>
                <div class="owner-profile-avatar-placeholder"><i class="fa fa-user"></i></div>
            <?php endif; ?>
            <div class="property-summary-owner"><?php echo $ownerDisplay; ?></div>
            <div class="property-summary-badges">
                <?php if ($isVerified): ?>
                    <span class="owner-verified-badge"><i class="fa fa-check-circle"></i> Perfil verificado</span>
                <?php endif; ?>
                <?php if ($isTrusted): ?>
                    <span class="owner-trust-badge"><i class="fa fa-shield"></i> Utilizador de confiança</span>
                <?php endif; ?>
                <?php if (!$isVerified && !$isTrusted): ?>
                    <span class="property-summary-muted">Conta sem selos adicionais no momento.</span>
                <?php endif; ?>
            </div>
            <?php if ($presentation !== ''): ?>
                <p class="owner-profile-presentation"><?php echo $presentation; ?></p>
            <?php endif; ?>
            <?php if ($adminCanViewContact): ?>
                <div class="property-summary-badges owner-contact-heading-wrap">
                    <span class="property-summary-kicker owner-contact-heading">Contacto (Admin)</span>
                </div>
                <div class="property-summary-meta owner-contact-list">
                    <?php if ($rawPhone !== ''): ?>
                        <div class="owner-contact-item"><i class="fa fa-phone"></i> <a href="tel:<?php echo htmlspecialchars($phoneDigits !== '' ? $phoneDigits : $rawPhone); ?>"><?php echo htmlspecialchars($rawPhone); ?></a></div>
                    <?php endif; ?>
                    <?php if ($rawEmail !== ''): ?>
                        <div class="owner-contact-item"><i class="fa fa-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($rawEmail); ?>"><?php echo htmlspecialchars($rawEmail); ?></a></div>
                    <?php endif; ?>
                    <?php if ($rawPhone === '' && $rawEmail === ''): ?>
                        <span class="property-summary-muted">Sem dados de contacto disponíveis.</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>
    </section>

    <section class="owner-properties-section">
        <div class="property-section-head" style="margin-bottom: 20px;">
            <span class="sales-kicker">Portfólio</span>
            <h3>Imóveis de <?php echo $ownerDisplay; ?></h3>
        </div>

        <?php if (empty($properties)): ?>
            <div class="property-state-card property-state-card-muted">
                <strong>Sem imóveis disponíveis</strong>
                <p>Este proprietário não tem imóveis publicados neste momento.</p>
            </div>
        <?php else: ?>
            <div class="sales-property-grid">
                <?php foreach ($properties as $prop): ?>
                    <?php
                    $isFav = in_array((int) ($prop['id'] ?? 0), $favoriteIds ?? [], true);
                    $propImages = json_decode((string) ($prop['images'] ?? '[]'), true);
                    $propFirstImage = (is_array($propImages) && !empty($propImages[0])) ? (string) $propImages[0] : '';
                    if ($propFirstImage !== '') {
                        $propFirstImage = \Src\classes\ClassMediaUrl::propertyImage($propFirstImage);
                    }
                    $propCover = $propFirstImage !== '' ? $propFirstImage : (DIRIMG . 'apt20.avif');
                    $propPurpose = ucfirst(str_replace('_', ' ', (string) ($prop['purpose'] ?? '')));
                    $propType = ucfirst((string) ($prop['type'] ?? ''));
                    ?>
                    <article class="sales-property-card">
                        <div class="sales-property-media">
                            <img src="<?php echo htmlspecialchars($propCover); ?>" alt="<?php echo htmlspecialchars($prop['title']); ?>">
                            <?php if (Src\classes\ClassAuth::check() && !(Src\classes\ClassAuth::user()['is_admin'] ?? false)): ?>
                                <form method="POST" action="<?php echo DIRPAGE; ?>property/<?php echo $isFav ? 'unfavorite' : 'favorite'; ?>/<?php echo (int) $prop['id']; ?>" class="favorite-form-inline favorite-overlay">
                                    <?php echo Src\classes\ClassCsrf::field(); ?>
                                    <button type="submit" class="btn-favorite<?php echo $isFav ? ' is-active' : ''; ?>" title="<?php echo $isFav ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>" aria-label="<?php echo $isFav ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>">
                                        <i class="fa <?php echo $isFav ? 'fa-heart' : 'fa-heart-o'; ?>"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="sales-property-body">
                            <h3><?php echo htmlspecialchars($prop['title']); ?></h3>
                            <p class="sales-location"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars((string) ($prop['location'] ?? '')); ?></p>
                            <p class="sales-price"><?php echo number_format((float) ($prop['price'] ?? 0), 0, ',', '.'); ?> Kz</p>
                            <div class="sales-meta-row">
                                <span><i class="fa fa-bed"></i> <?php echo (int) ($prop['bedrooms'] ?? 0); ?> quartos</span>
                                <span><i class="fa fa-bath"></i> <?php echo (int) ($prop['bathrooms'] ?? 0); ?> banhos</span>
                            </div>
                            <div class="sales-meta-row">
                                <span><i class="fa fa-tag"></i> <?php echo htmlspecialchars($propType); ?></span>
                                <span><i class="fa fa-exchange"></i> <?php echo htmlspecialchars($propPurpose); ?></span>
                            </div>
                            <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $prop['id']; ?>" class="btn-primary sales-card-cta">Explorar este imóvel</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>
