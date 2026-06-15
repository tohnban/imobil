<?php
/** @var array $agencyUser */
/** @var array $properties */
/** @var array $trustMetrics */
/** @var array $portfolioStats */
/** @var array $favoriteIds */
/** @var string $publicProfileUrl */
/** @var array $topLocations */
/** @var array $topTypes */
/** @var array $featuredProperties */
/** @var int|null $memberSinceYear */
/** @var string $documentLabel */
/** @var bool $isJuridica */

$agencyUser = $agencyUser ?? [];
$properties = $properties ?? [];
$trustMetrics = $trustMetrics ?? [];
$portfolioStats = $portfolioStats ?? ['total' => 0, 'for_sale' => 0, 'for_rent' => 0, 'min_price' => null, 'max_price' => null];
$favoriteIds = isset($favoriteIds) && is_array($favoriteIds) ? $favoriteIds : [];
$publicProfileUrl = (string) ($publicProfileUrl ?? '');
$topLocations = is_array($topLocations ?? null) ? $topLocations : [];
$topTypes = is_array($topTypes ?? null) ? $topTypes : [];
$featuredProperties = is_array($featuredProperties ?? null) ? $featuredProperties : [];
$memberSinceYear = isset($memberSinceYear) ? (int) $memberSinceYear : 0;
$documentLabel = (string) ($documentLabel ?? '');
$isJuridica = !empty($isJuridica);

$agencyName = htmlspecialchars((string) ($agencyUser['name'] ?? 'Empresa'));
$entityTitle = $isJuridica ? 'Empresa imobiliária' : 'Profissional imobiliário';
$entityIcon = $isJuridica ? 'fa-building' : 'fa-briefcase';

$isVerified = !empty($trustMetrics['verified']);
$isTrusted = !empty($trustMetrics['trusted']);

$rawPhoto = (string) ($agencyUser['profile_photo'] ?? '');
$avatarUrl = $rawPhoto !== '' ? \Src\classes\ClassMediaUrl::profilePhoto($rawPhoto) : '';

$presentation = trim((string) ($agencyUser['presentation'] ?? ''));
if ($presentation === '') {
    $presentation = $isJuridica
        ? 'Empresa de mediação imobiliária com imóveis publicados e verificados nesta plataforma.'
        : 'Profissional com portfólio de imóveis publicados nesta plataforma.';
}
$presentation = htmlspecialchars($presentation);

$rawPhone = trim((string) ($agencyUser['phone'] ?? ''));
$rawEmail = trim((string) ($agencyUser['email'] ?? ''));
$phoneDigits = preg_replace('/\D+/', '', $rawPhone);
$showContact = $rawPhone !== '' || $rawEmail !== '';

$propertyCount = (int) ($portfolioStats['total'] ?? count($properties));
$forSale = (int) ($portfolioStats['for_sale'] ?? 0);
$forRent = (int) ($portfolioStats['for_rent'] ?? 0);
$minPrice = $portfolioStats['min_price'] ?? null;
$maxPrice = $portfolioStats['max_price'] ?? null;

$partialGridCard = DIRREQ . 'app/view/partials/property_grid_card.php';
$typeLabels = \Src\classes\PropertyTypeHelper::getTypeLabels();
$formatType = static function (?string $value) use ($typeLabels): string {
    return $typeLabels[$value ?? ''] ?? 'Tipo não definido';
};
$purposeLabels = [
    'venda' => 'Venda',
    'aluguer_curto' => 'Aluguer curto',
    'aluguer_longo' => 'Aluguer longo',
];
$formatPurpose = static function (?string $value) use ($purposeLabels): string {
    return $purposeLabels[$value ?? ''] ?? 'Finalidade não definida';
};

$coverImage = static function (array $property): string {
    $images = json_decode((string) ($property['images'] ?? '[]'), true);
    $first = (is_array($images) && !empty($images[0])) ? (string) $images[0] : '';
    if ($first !== '') {
        $first = \Src\classes\ClassMediaUrl::propertyImage($first);
    }
    return $first !== '' ? $first : (DIRIMG . 'apt20.avif');
};
?>

<div class="container property-page-shell agency-page-shell">

    <nav class="agency-breadcrumb" aria-label="Navegação">
        <a href="<?php echo DIRPAGE; ?>">Início</a>
        <span aria-hidden="true">/</span>
        <a href="<?php echo DIRPAGE; ?>properties">Imóveis</a>
        <span aria-hidden="true">/</span>
        <span><?php echo $agencyName; ?></span>
    </nav>

    <header class="agency-hero-band">
        <div class="agency-hero-brand">
            <div class="agency-hero-logo" aria-hidden="true">
                <?php if ($avatarUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="">
                <?php else: ?>
                    <i class="fa <?php echo $entityIcon; ?>"></i>
                <?php endif; ?>
            </div>
            <div class="agency-hero-text">
                <span class="agency-entity-badge"><i class="fa <?php echo $entityIcon; ?>" aria-hidden="true"></i> <?php echo $entityTitle; ?></span>
                <h1><?php echo $agencyName; ?></h1>
                <p class="agency-hero-tagline">
                    <?php if ($isJuridica): ?>
                        Página oficial da empresa no ImobilFacil — consulte o portfólio, áreas de actuação e contacto comercial.
                    <?php else: ?>
                        Página comercial com portfólio de imóveis e contacto directo.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php if ($propertyCount > 0): ?>
        <div class="agency-hero-stats">
            <div class="agency-hero-stat">
                <strong><?php echo $propertyCount; ?></strong>
                <span>imóveis</span>
            </div>
            <?php if ($forSale > 0): ?>
            <div class="agency-hero-stat">
                <strong><?php echo $forSale; ?></strong>
                <span>venda</span>
            </div>
            <?php endif; ?>
            <?php if ($forRent > 0): ?>
            <div class="agency-hero-stat">
                <strong><?php echo $forRent; ?></strong>
                <span>arrendamento</span>
            </div>
            <?php endif; ?>
            <?php if ($minPrice !== null && $maxPrice !== null): ?>
            <div class="agency-hero-stat agency-hero-stat-wide">
                <strong><?php echo number_format((float) $minPrice, 0, ',', '.'); ?> – <?php echo number_format((float) $maxPrice, 0, ',', '.'); ?></strong>
                <span>Kz (faixa)</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>

    <div class="agency-layout">
        <div class="agency-main">

            <section class="agency-panel">
                <h2 class="agency-panel-title">Sobre <?php echo $isJuridica ? 'a empresa' : 'o profissional'; ?></h2>
                <p class="agency-panel-lead"><?php echo $presentation; ?></p>
                <ul class="agency-facts">
                    <?php if ($isJuridica && $documentLabel !== ''): ?>
                        <li><i class="fa fa-id-card" aria-hidden="true"></i> Identificação fiscal: <strong><?php echo htmlspecialchars($documentLabel); ?></strong></li>
                    <?php endif; ?>
                    <?php if ($memberSinceYear > 0): ?>
                        <li><i class="fa fa-calendar" aria-hidden="true"></i> Na plataforma desde <strong><?php echo $memberSinceYear; ?></strong></li>
                    <?php endif; ?>
                    <?php if ($isVerified): ?>
                        <li><i class="fa fa-check-circle" aria-hidden="true"></i> Conta <strong>verificada</strong> pela plataforma</li>
                    <?php endif; ?>
                    <?php if ($isTrusted): ?>
                        <li><i class="fa fa-shield" aria-hidden="true"></i> <strong>Selo de confiança</strong> atribuído</li>
                    <?php endif; ?>
                </ul>
            </section>

            <?php if (!empty($topTypes) || !empty($topLocations)): ?>
            <section class="agency-panel agency-panel-split">
                <?php if (!empty($topTypes)): ?>
                <div>
                    <h3 class="agency-subtitle">Tipos de imóvel</h3>
                    <ul class="agency-chip-list">
                        <?php foreach ($topTypes as $row): ?>
                            <li><span><?php echo htmlspecialchars((string) ($row['label'] ?? '')); ?></span> <em><?php echo (int) ($row['count'] ?? 0); ?></em></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty($topLocations)): ?>
                <div>
                    <h3 class="agency-subtitle">Onde actua</h3>
                    <ul class="agency-chip-list agency-chip-list-locations">
                        <?php foreach ($topLocations as $place => $count): ?>
                            <li><span><?php echo htmlspecialchars((string) $place); ?></span> <em><?php echo (int) $count; ?></em></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($featuredProperties)): ?>
            <section class="agency-panel">
                <h2 class="agency-panel-title">Destaques</h2>
                <p class="agency-panel-note">Imóveis em destaque neste catálogo.</p>
                <div class="agency-featured-row">
                    <?php foreach ($featuredProperties as $fp): ?>
                        <?php
                        $fpId = (int) ($fp['id'] ?? 0);
                        $fpTitle = htmlspecialchars((string) ($fp['title'] ?? 'Imóvel'));
                        $fpImg = htmlspecialchars($coverImage($fp));
                        $fpPrice = number_format((float) ($fp['price'] ?? 0), 0, ',', '.');
                        ?>
                        <a href="<?php echo DIRPAGE; ?>property/<?php echo $fpId; ?>" class="agency-featured-card">
                            <img src="<?php echo $fpImg; ?>" alt="" loading="lazy">
                            <div class="agency-featured-body">
                                <span class="agency-featured-badge">Destaque</span>
                                <strong><?php echo $fpTitle; ?></strong>
                                <span><?php echo $fpPrice; ?> Kz</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

        </div>

        <aside class="agency-sidebar">
            <div class="agency-contact-card">
                <span class="agency-contact-kicker"><?php echo $isJuridica ? 'Contacto comercial' : 'Contacto'; ?></span>
                <p class="agency-contact-intro">Fale directamente com <?php echo $isJuridica ? 'a empresa' : 'o profissional'; ?> sobre os imóveis deste catálogo.</p>

                <?php if ($showContact): ?>
                    <div class="agency-contact-actions">
                        <?php if ($rawPhone !== ''): ?>
                            <a href="tel:<?php echo htmlspecialchars($phoneDigits !== '' ? $phoneDigits : $rawPhone); ?>" class="btn-primary agency-contact-btn">
                                <i class="fa fa-phone" aria-hidden="true"></i> Ligar
                            </a>
                        <?php endif; ?>
                        <?php if ($rawEmail !== ''): ?>
                            <a href="mailto:<?php echo htmlspecialchars($rawEmail); ?>" class="btn-secondary agency-contact-btn">
                                <i class="fa fa-envelope" aria-hidden="true"></i> E-mail
                            </a>
                        <?php endif; ?>
                    </div>
                    <ul class="agency-contact-details">
                        <?php if ($rawPhone !== ''): ?>
                            <li><i class="fa fa-phone" aria-hidden="true"></i> <?php echo htmlspecialchars($rawPhone); ?></li>
                        <?php endif; ?>
                        <?php if ($rawEmail !== ''): ?>
                            <li><i class="fa fa-envelope" aria-hidden="true"></i> <?php echo htmlspecialchars($rawEmail); ?></li>
                        <?php endif; ?>
                    </ul>
                <?php else: ?>
                    <p class="agency-contact-muted">Contacto não disponível publicamente. Utilize o formulário em cada imóvel para pedidos.</p>
                <?php endif; ?>

                <?php if ($isVerified || $isTrusted): ?>
                <div class="agency-sidebar-trust">
                    <?php if ($isVerified): ?>
                        <span class="owner-verified-badge"><i class="fa fa-check-circle" aria-hidden="true"></i> Verificada</span>
                    <?php endif; ?>
                    <?php if ($isTrusted): ?>
                        <span class="owner-trust-badge"><i class="fa fa-shield" aria-hidden="true"></i> Confiança</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($publicProfileUrl !== ''): ?>
                <div class="agency-share-block">
                    <span class="agency-share-label">Link desta página</span>
                    <div class="agency-share-row">
                        <span class="agency-share-url" id="agency-public-url"><?php echo htmlspecialchars($publicProfileUrl); ?></span>
                        <button type="button" class="agency-copy-icon" data-copy-target="agency-public-url" title="Copiar link" aria-label="Copiar link">
                            <i class="fa fa-copy" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="agency-compare-note">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <p>
                    <?php if ($isJuridica): ?>
                        Esta é a <strong>página da empresa</strong>, com catálogo e dados comerciais. O perfil individual de anunciante mostra apenas a conta pessoal na plataforma.
                    <?php else: ?>
                        Página comercial com catálogo completo de imóveis e contacto directo.
                    <?php endif; ?>
                </p>
            </div>
        </aside>

        <section class="agency-panel agency-catalog-panel">
            <div class="agency-catalog-head">
                <div>
                    <h2 class="agency-panel-title">Catálogo de imóveis</h2>
                    <p class="agency-panel-note">
                        <?php if ($propertyCount > 0): ?>
                            <?php echo $propertyCount === 1 ? '1 imóvel disponível' : $propertyCount . ' imóveis disponíveis'; ?> publicados por <?php echo $agencyName; ?>.
                        <?php else: ?>
                            Sem imóveis disponíveis de momento.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (empty($properties)): ?>
                <div class="property-state-card property-state-card-muted">
                    <strong>Catálogo vazio</strong>
                    <p>Volte mais tarde ou explore outras ofertas na plataforma.</p>
                    <a href="<?php echo DIRPAGE; ?>properties" class="btn-primary">Ver todos os imóveis</a>
                </div>
            <?php else: ?>
                <div class="sales-property-grid">
                    <?php foreach ($properties as $property): ?>
                        <?php include $partialGridCard; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
