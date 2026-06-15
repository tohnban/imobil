<?php
$type = $_GET['type'] ?? '';
$purpose = $_GET['purpose'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$location = $_GET['location'] ?? '';
$countryId = (int) ($_GET['country_id'] ?? 0);
$regionId = (int) ($_GET['region_id'] ?? 0);
$ownerUsername = trim((string) ($_GET['owner_username'] ?? $_GET['owner_name'] ?? ''));
$bedrooms = $_GET['bedrooms'] ?? '';
$bathrooms = $_GET['bathrooms'] ?? '';
$minArea = $_GET['min_area'] ?? '';
$maxArea = $_GET['max_area'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$trustedOnly = !empty($_GET['trusted_only']);
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int) ($page ?? ($_GET['page'] ?? 1)));
$perPage = max(1, (int) ($perPage ?? 12));
$totalPages = max(1, (int) ($totalPages ?? 1));
$totalProperties = (int) ($totalProperties ?? (is_array($properties ?? null) ? count($properties) : 0));
$resultCount = $totalProperties;
$visibleCount = is_array($properties ?? null) ? count($properties) : 0;
$rangeStart = $resultCount > 0 ? (($page - 1) * $perPage) + 1 : 0;
$rangeEnd = $resultCount > 0 ? min($resultCount, $rangeStart + $visibleCount - 1) : 0;
$queryParams = $_GET;
unset($queryParams['filters_open']);
$cursorMode = !empty($cursorMode) || !empty($data['cursorMode']);
$nextCursor = (string) ($nextCursor ?? ($data['nextCursor'] ?? ''));
$buildCursorUrl = static function (string $cursorValue) use ($queryParams): string {
    $params = $queryParams;
    unset($params['page']);
    $params['cursor'] = $cursorValue;
    return DIRPAGE . 'properties?' . http_build_query($params);
};
$buildPageUrl = static function (int $targetPage) use ($queryParams): string {
    $params = $queryParams;
    $params['page'] = $targetPage;

    return DIRPAGE . 'properties?' . http_build_query($params);
};
$typeLabels = Src\classes\PropertyTypeHelper::getTypeLabels();
$typeFilterOptions = Src\classes\PropertyTypeHelper::getPublicFilterTypes();
$typeGroupedOptions = Src\classes\PropertyTypeHelper::getGroupedTypes();
$countries = $countries ?? [];
$countryById = [];
foreach ($countries as $countryRow) {
    $countryById[(int) ($countryRow['id'] ?? 0)] = (string) ($countryRow['name'] ?? '');
}
$regions = $regions ?? [];
$regionById = [];
foreach ($regions as $regionRow) {
    $regionById[(int) ($regionRow['id'] ?? 0)] = (string) ($regionRow['name'] ?? '');
}
$purposeLabels = [
    'venda' => 'Venda',
    'aluguer_curto' => 'Aluguer curto',
    'aluguer_longo' => 'Aluguer longo',
];
$formatType = static function (?string $value) use ($typeLabels): string {
    return $typeLabels[$value ?? ''] ?? 'Tipo nao definido';
};
$formatPurpose = static function (?string $value) use ($purposeLabels): string {
    return $purposeLabels[$value ?? ''] ?? 'Finalidade nao definida';
};
$activeFilters = [];
if ($keyword !== '') {
    $activeFilters[] = 'Busca: ' . $keyword;
}
if ($type !== '') {
    $activeFilters[] = 'Tipo: ' . $formatType($type);
}
if ($purpose !== '') {
    $activeFilters[] = 'Finalidade: ' . $formatPurpose($purpose);
}
if ($location !== '') {
    $activeFilters[] = 'Localizacao: ' . $location;
}
if ($countryId > 0 && !empty($countryById[$countryId])) {
    $activeFilters[] = 'Pais: ' . $countryById[$countryId];
}
if ($regionId > 0 && !empty($regionById[$regionId])) {
    $activeFilters[] = 'Regiao: ' . $regionById[$regionId];
}
if ($ownerUsername !== '') {
    $activeFilters[] = 'Proprietário: ' . $ownerUsername;
}
if ($minPrice !== '') {
    $activeFilters[] = 'Preco min.: ' . number_format((float) $minPrice, 0, ',', '.') . ' Kz';
}
if ($maxPrice !== '') {
    $activeFilters[] = 'Preco max.: ' . number_format((float) $maxPrice, 0, ',', '.') . ' Kz';
}
if ($bedrooms !== '') {
    $activeFilters[] = 'Quartos min.: ' . (int) $bedrooms;
}
if ($bathrooms !== '') {
    $activeFilters[] = 'Banhos min.: ' . (int) $bathrooms;
}
if ($minArea !== '') {
    $activeFilters[] = 'Area min.: ' . number_format((float) $minArea, 0, ',', '.') . ' m2';
}
if ($maxArea !== '') {
    $activeFilters[] = 'Area max.: ' . number_format((float) $maxArea, 0, ',', '.') . ' m2';
}
if ($trustedOnly) {
    $activeFilters[] = 'Somente proprietarios com confianca';
}
$hasAdvancedFilters = $type !== '' || $purpose !== '' || $minPrice !== '' || $maxPrice !== ''
    || $location !== '' || $countryId > 0 || $regionId > 0 || $ownerUsername !== ''
    || $bedrooms !== '' || $bathrooms !== '' || $minArea !== '' || $maxArea !== ''
    || $trustedOnly || ($sort !== '' && $sort !== 'newest');
$discoveryPersonalized = !empty($discoveryPersonalized) || !empty($data['discoveryPersonalized']);
$continueExploring = isset($continueExploring) && is_array($continueExploring)
    ? $continueExploring
    : (($data['continueExploring'] ?? []) ?: []);
$partialGridCard = DIRREQ . 'app/view/partials/property_grid_card.php';
$listingProperties = is_array($properties ?? null) ? $properties : [];
$sponsored = [];
$regular = [];
$sponsoredCount = 0;
if ($discoveryPersonalized) {
    $listingProperties = array_values($listingProperties);
} else {
    $sponsored = array_values(array_filter($listingProperties, static fn($p) => !empty($p['featured'])));
    $regular = array_values(array_filter($listingProperties, static fn($p) => empty($p['featured'])));
    $sponsoredCount = count($sponsored);
}
?>

<div class="properties-page-view">

<section class="container sales-filter-wrap properties-filter-top">
    <form method="GET"
          action="<?php echo DIRPAGE; ?>properties"
          class="sales-filter-form filter-toolbar-form"
          id="properties-filter-form"
          data-filter-collapse-on-submit="1">
        <div class="filter-toolbar filter-toolbar-sticky filter-toolbar-public properties-filter-toolbar" role="search">
            <label class="filter-toolbar-field filter-toolbar-field-grow">
                <span class="filter-toolbar-field-label">Busca rápida</span>
                <span class="filter-toolbar-input-wrap">
                    <i class="fa fa-search filter-toolbar-input-icon" aria-hidden="true"></i>
                    <input type="search"
                           name="keyword"
                           class="filter-toolbar-input"
                           placeholder="Título, bairro, zona ou @proprietário..."
                           value="<?php echo htmlspecialchars((string) $keyword); ?>"
                           autocomplete="off">
                </span>
            </label>
            <div class="filter-toolbar-actions">
                <button type="button"
                        class="btn-secondary filter-toolbar-more-btn"
                        id="properties-filter-toggle"
                        data-filter-toggle
                        aria-expanded="false"
                        aria-controls="properties-filter-advanced">
                    <i class="fa fa-sliders" aria-hidden="true"></i>
                    <span>Filtros</span>
                    <?php if ($hasAdvancedFilters): ?>
                        <span class="filter-active-dot" aria-hidden="true"></span>
                    <?php endif; ?>
                </button>
                <button type="submit" class="btn-primary filter-toolbar-submit">Filtrar</button>
            </div>
        </div>

        <?php if (!empty($activeFilters)): ?>
            <div class="sales-active-filters filter-active-chips">
                <?php foreach ($activeFilters as $activeFilter): ?>
                    <span class="sales-filter-chip"><?php echo htmlspecialchars($activeFilter); ?></span>
                <?php endforeach; ?>
                <a href="<?php echo DIRPAGE; ?>properties" class="sales-filter-chip sales-filter-chip-clear">Limpar tudo</a>
            </div>
        <?php endif; ?>

        <?php require DIRREQ . 'app/view/partials/property_list_quick_filters.php'; ?>

        <details class="sales-filter-disclosure filter-advanced-panel" id="properties-filter-advanced">
            <summary class="filter-advanced-summary-hidden">Filtros avançados</summary>
            <div class="sales-filter-fields">
        <div class="sales-filter-grid">
            <label class="properties-filter-field properties-filter-field--quick-type">
                <span>Tipo</span>
                <select name="type">
                    <option value="">Todos</option>
                    <?php foreach ($typeGroupedOptions as $groupLabel => $groupTypes): ?>
                        <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                            <?php foreach ($groupTypes as $typeValue => $typeText): ?>
                                <option value="<?php echo htmlspecialchars($typeValue); ?>"<?php echo $type === $typeValue ? ' selected' : ''; ?>><?php echo htmlspecialchars($typeText); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="properties-filter-field properties-filter-field--quick-purpose">
                <span>Finalidade</span>
                <select name="purpose">
                    <option value="">Qualquer</option>
                    <option value="venda"<?php echo $purpose === 'venda' ? ' selected' : ''; ?>>Venda</option>
                    <option value="aluguer_curto"<?php echo $purpose === 'aluguer_curto' ? ' selected' : ''; ?>>Aluguer curto</option>
                    <option value="aluguer_longo"<?php echo $purpose === 'aluguer_longo' ? ' selected' : ''; ?>>Aluguer longo</option>
                </select>
            </label>
            <label class="properties-filter-field">
                <span>Preco minimo</span>
                <input type="number" name="min_price" placeholder="Ex: 50000" value="<?php echo htmlspecialchars((string) $minPrice); ?>">
            </label>
            <label class="properties-filter-field">
                <span>Preco maximo</span>
                <input type="number" name="max_price" placeholder="Ex: 300000" value="<?php echo htmlspecialchars((string) $maxPrice); ?>">
            </label>
            <label class="properties-filter-field">
                <span>Quartos minimos</span>
                <input type="number" name="bedrooms" min="0" step="1" placeholder="Ex: 2" value="<?php echo htmlspecialchars((string) $bedrooms); ?>">
            </label>
            <label class="properties-filter-field">
                <span>Banhos minimos</span>
                <input type="number" name="bathrooms" min="0" step="1" placeholder="Ex: 1" value="<?php echo htmlspecialchars((string) $bathrooms); ?>">
            </label>
            <label class="properties-filter-field">
                <span>Area minima</span>
                <input type="number" name="min_area" min="0" step="0.01" placeholder="Ex: 80" value="<?php echo htmlspecialchars((string) $minArea); ?>">
            </label>
            <label class="properties-filter-field">
                <span>Area maxima</span>
                <input type="number" name="max_area" min="0" step="0.01" placeholder="Ex: 250" value="<?php echo htmlspecialchars((string) $maxArea); ?>">
            </label>
            <label class="properties-filter-field">
                <span>Ordenacao</span>
                <select name="sort">
                    <option value="newest"<?php echo $sort === 'newest' ? ' selected' : ''; ?>>Mais recentes</option>
                    <option value="price_asc"<?php echo $sort === 'price_asc' ? ' selected' : ''; ?>>Menor preco</option>
                    <option value="price_desc"<?php echo $sort === 'price_desc' ? ' selected' : ''; ?>>Maior preco</option>
                    <option value="oldest"<?php echo $sort === 'oldest' ? ' selected' : ''; ?>>Mais antigos</option>
                </select>
            </label>
            <label class="sales-filter-location properties-filter-field">
                <span>País</span>
                <select name="country_id">
                    <option value="">Todos</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo (int) ($country['id'] ?? 0); ?>"<?php echo $countryId === (int) ($country['id'] ?? 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($country['name'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="sales-filter-location properties-filter-field">
                <span>Região</span>
                <select name="region_id"<?php echo $countryId > 0 ? '' : ' disabled'; ?>>
                    <option value=""><?php echo $countryId > 0 ? 'Todas' : 'Selecione um país primeiro'; ?></option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?php echo (int) ($region['id'] ?? 0); ?>" data-country-id="<?php echo (int) ($region['country_id'] ?? 0); ?>"<?php echo $regionId === (int) ($region['id'] ?? 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($region['name'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="sales-filter-check properties-filter-field">
                <span>Confianca</span>
                <span class="sales-filter-checkbox">
                    <input type="checkbox" name="trusted_only" value="1"<?php echo $trustedOnly ? ' checked' : ''; ?>>
                    <em>Somente proprietarios com selo de confianca</em>
                </span>
            </label>
        </div>
        <div class="sales-filter-actions filter-advanced-actions">
            <button type="submit" class="btn-primary">Aplicar filtros</button>
            <a href="<?php echo DIRPAGE; ?>properties" class="btn-secondary">Limpar</a>
        </div>
            </div>
        </details>
    </form>
</section>

<section class="container sales-page-head">
    <div class="sales-head-copy">
        <span class="sales-kicker">Imóveis verificados em Angola</span>
        <h1>Encontre o Seu Imóvel</h1>
        <p>Imóveis residenciais, comerciais, industriais, terrenos e opções de turismo com proprietários confirmados. Fale diretamente e feche negócio sem intermediários.</p>
    </div>
    <div class="sales-head-summary">
        <strong><?php echo number_format($resultCount, 0, ',', '.'); ?></strong>
        <span>imóveis disponíveis agora</span>
    </div>
</section>

<?php if (!empty($continueExploring)): ?>
<section class="container sales-section discovery-continue-section">
    <div class="section-header sales-section-header">
        <div>
            <h2>Continuar a explorar</h2>
            <p>Imóveis que já visitou — retome de onde parou.</p>
        </div>
    </div>
    <div class="discovery-continue-grid">
        <?php foreach ($continueExploring as $property): ?>
            <?php
            $partialCard = DIRREQ . 'app/view/partials/property_carousel_card.php';
            if (is_file($partialCard)) {
                $badgeLabel = 'Visto recentemente';
                $showSponsoredBadge = !empty($property['featured']);
                $position = 0;
                include $partialCard;
            }
            ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="container sales-results-toolbar">
    <div class="sales-results-copy">
        <strong><?php echo $resultCount > 0 ? ('Mostrando ' . number_format($rangeStart, 0, ',', '.') . ' a ' . number_format($rangeEnd, 0, ',', '.') . ' de ' . number_format($resultCount, 0, ',', '.')) : 'Sem resultados'; ?></strong>
        <span><?php echo $resultCount > 0 ? 'Refine a busca ou abra os filtros avançados para ajustar tipo, preço e localização.' : 'Altere a busca ou os filtros para ver mais imóveis disponíveis.'; ?></span>
    </div>
</section>

<?php if (!$discoveryPersonalized && !empty($sponsored)): ?>
<section class="sales-premium-strip-wrap">
    <div class="container">
        <div class="sales-premium-strip-head">
            <div class="sales-premium-strip-title-row">
                <div class="sales-premium-strip-title">
                    <i class="fa fa-bullhorn"></i>
                    <span>Patrocinados em Destaque</span>
                    <span class="sales-premium-badge">Prioridade Maxima</span>
                </div>
                <div class="sales-carousel-nav-btns sales-premium-nav-btns">
                    <button type="button" id="listPremiumPrev" aria-label="Patrocinado anterior"<?php echo $sponsoredCount <= 1 ? ' disabled aria-disabled="true"' : ''; ?>><i class="fa fa-chevron-left"></i></button>
                    <button type="button" id="listPremiumNext" aria-label="Proximo patrocinado"<?php echo $sponsoredCount <= 1 ? ' disabled aria-disabled="true"' : ''; ?>><i class="fa fa-chevron-right"></i></button>
                </div>
            </div>
            <small class="sales-sponsorship-note">Imóveis com proprietários prontos a responder — promovidos para que os encontre mais facilmente.</small>
        </div>
        <div class="sales-carousel-viewport sales-premium-viewport" id="listPremiumViewport">
            <div class="sales-premium-rail sales-carousel-track sales-premium-track" id="listPremiumTrack">
                <?php foreach ($sponsored as $sp): ?>
                    <?php
                    $isFavSp = in_array((int) ($sp['id'] ?? 0), $favoriteIds ?? [], true);
                    $spImages = json_decode((string) ($sp['images'] ?? '[]'), true);
                    $spFirstImage = (is_array($spImages) && !empty($spImages[0])) ? (string) $spImages[0] : '';
                    if ($spFirstImage !== '') {
                        $spFirstImage = \Src\classes\ClassMediaUrl::propertyImage($spFirstImage);
                    }
                    $spCoverImage = $spFirstImage !== '' ? $spFirstImage : (DIRIMG . 'apt20.avif');
                    ?>
                    <article class="sales-premium-card">
                        <div class="sales-premium-card-img">
                            <img src="<?php echo htmlspecialchars($spCoverImage); ?>" alt="<?php echo htmlspecialchars($sp['title']); ?>">
                            <?php if (Src\classes\ClassAuth::check() && !(Src\classes\ClassAuth::user()['is_admin'] ?? false)): ?>
                                <form method="POST" action="<?php echo DIRPAGE; ?>property/<?php echo $isFavSp ? 'unfavorite' : 'favorite'; ?>/<?php echo (int) $sp['id']; ?>" class="favorite-form-inline favorite-overlay">
                                    <?php echo Src\classes\ClassCsrf::field(); ?>
                                    <button type="submit" class="btn-favorite<?php echo $isFavSp ? ' is-active' : ''; ?>" title="<?php echo $isFavSp ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>" aria-label="<?php echo $isFavSp ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>">
                                        <i class="fa <?php echo $isFavSp ? 'fa-heart' : 'fa-heart-o'; ?>"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="sales-premium-card-body">
                            <span class="sales-card-badge"><i class="fa fa-bullhorn"></i> Patrocinado</span>
                            <h3><a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $sp['id']; ?>" class="card-title-link"><?php echo htmlspecialchars($sp['title']); ?></a></h3>
                            <p class="sales-location"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($sp['location']); ?></p>
                            <p class="sales-price"><?php echo number_format((float) $sp['price'], 0, ',', '.'); ?> Kz</p>
                            <div class="sales-meta-row">
                                <span><i class="fa fa-tag"></i> <?php echo htmlspecialchars($formatType($sp['type'] ?? null)); ?></span>
                                <span><i class="fa fa-briefcase"></i> <?php echo htmlspecialchars($formatPurpose($sp['purpose'] ?? null)); ?></span>
                                <?php if (!empty($sp['area'])): ?>
                                    <span><i class="fa fa-expand"></i> <?php echo number_format((float) $sp['area'], 0, ',', '.'); ?> m2</span>
                                <?php endif; ?>
                            </div>
                            <div class="sales-meta-row">
                                <span><i class="fa fa-bed"></i> <?php echo (int) $sp['bedrooms']; ?> quartos</span>
                                <span><i class="fa fa-bath"></i> <?php echo (int) $sp['bathrooms']; ?> banhos</span>
                            </div>
                            <div class="sales-trust-row">
                                <?php
                                    $spOwnerHandle = htmlspecialchars(Src\classes\UserDisplay::publicHandleFromRow($sp, 'owner_username', 'owner_name', 'Proprietário'));
                                ?>
                                <span><i class="fa fa-user"></i> <?php if (!empty($sp['affiliate_id'])): ?><a href="<?php echo htmlspecialchars(Src\classes\ClassPlan::getPublicProfileUrl((int) $sp['affiliate_id'])); ?>" class="owner-name-link"><?php echo $spOwnerHandle; ?></a><?php else: ?><?php echo $spOwnerHandle; ?><?php endif; ?></span>
                                <?php if (!empty($sp['owner_verified'])): ?>
                                    <span class="sales-trust-chip verified"><i class="fa fa-check-circle"></i> Verificado</span>
                                <?php endif; ?>
                                <?php if (!empty($sp['owner_trusted'])): ?>
                                    <span class="sales-trust-chip trusted"><i class="fa fa-shield"></i> Confianca</span>
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $sp['id']; ?>" class="btn-primary sales-card-cta">Ver este imóvel</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sales-carousel-dots sales-premium-dots" id="listPremiumDots"></div>
    </div>
</section>
<?php endif; ?>

<section class="container sales-listing-section">
    <?php if ($discoveryPersonalized): ?>
        <p class="sales-grid-section-title">Imóveis para si — patrocinados aparecem misturados na lista, com distinção visual</p>
    <?php elseif (!empty($sponsored)): ?>
        <p class="sales-grid-section-title">Outros imoveis</p>
    <?php endif; ?>
    <div class="sales-property-grid">
        <?php
        $gridItems = $discoveryPersonalized ? $listingProperties : $regular;
        ?>
        <?php if (!empty($gridItems)): ?>
            <?php foreach ($gridItems as $property): ?>
                <?php
                if (is_file($partialGridCard)) {
                    include $partialGridCard;
                }
                ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sales-empty-state">
                <i class="fa fa-search"></i>
                <h3>Nenhum imovel encontrado</h3>
                <p><?php echo !$discoveryPersonalized && !empty($sponsored) ? 'Consulte os imóveis em destaque acima, ou ajuste os filtros para ampliar a pesquisa.' : 'Altere os filtros para encontrar mais imóveis disponíveis.'; ?></p>
                <a href="<?php echo DIRPAGE; ?>properties" class="btn-primary">Reiniciar pesquisa</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!$cursorMode && $totalPages > 1): ?>
<section class="container sales-pagination-section">
    <div class="sales-pagination-inline sales-pagination-inline-bottom">
        <?php if ($page > 1): ?>
            <a href="<?php echo htmlspecialchars($buildPageUrl($page - 1)); ?>" class="btn-secondary">&larr; Anterior</a>
        <?php endif; ?>
        <span>Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="<?php echo htmlspecialchars($buildPageUrl($page + 1)); ?>" class="btn-secondary">Próxima &rarr;</a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($cursorMode && $nextCursor !== ''): ?>
<section class="container sales-pagination-section">
    <div class="sales-pagination-inline sales-pagination-inline-bottom">
        <a href="<?php echo htmlspecialchars($buildCursorUrl($nextCursor)); ?>" class="btn-secondary">Próxima &rarr;</a>
    </div>
</section>
<?php endif; ?>

</div>
