<?php

namespace App\controller;

use App\model\Country;
use App\model\Favorite;
use App\model\MetricEvent;
use App\model\Property;
use App\model\PropertyAffiliate;
use App\model\PropertyBehaviorEvent;
use App\model\Region;
use App\model\Request;
use App\model\User;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCommissionGuard;
use Src\classes\ClassCookieConsent;
use Src\classes\ClassPlan;
use Src\classes\ClassRender;
use Src\classes\ClassSEO;
use Src\classes\ClassSession;
use Src\classes\ClassSettings;
use Src\classes\DiscoveryEngine;
use Src\classes\PageCache;
use Src\classes\PropertyTypeHelper;
use Src\traits\TraitUrlParser;

class ControllerPropertyCatalog
{
    use TraitUrlParser;

    private function decodeCursor(?string $cursor): ?array
    {
        $cursor = trim((string) $cursor);
        if ($cursor === '') {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        $data = json_decode($decoded, true);
        return is_array($data) ? $data : null;
    }


    private function encodeCursor(?string $createdAt, ?int $id): ?string
    {
        $createdAt = $createdAt !== null ? trim((string) $createdAt) : '';
        $id = (int) ($id ?? 0);
        if ($createdAt === '' || $id <= 0) {
            return null;
        }
        $payload = json_encode(['created_at' => $createdAt, 'id' => $id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $b64 = base64_encode((string) $payload);
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }


    private function resolveRouteInt($primary, int $segmentIndex = 2): int
    {
        if ($primary !== null && $primary !== '' && is_numeric($primary)) {
            return (int) $primary;
        }

        $parts = $this->parseUrl();
        if (isset($parts[$segmentIndex]) && is_numeric($parts[$segmentIndex])) {
            return (int) $parts[$segmentIndex];
        }

        return (int) ($_GET['id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function normalizePropertyListFilters(array $query): array
    {
        $allowedSort = ['newest', 'price_asc', 'price_desc', 'oldest'];
        $filters = [];

        $stringKeys = [
            'keyword',
            'type',
            'purpose',
            'location',
            'owner_username',
            'owner_name',
        ];

        foreach ($stringKeys as $key) {
            if (!isset($query[$key]) || is_array($query[$key])) {
                continue;
            }
            $value = trim((string) $query[$key]);
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        $numericKeys = [
            'min_price',
            'max_price',
            'bedrooms',
            'bathrooms',
            'min_area',
            'max_area',
            'latitude',
            'longitude',
            'radius_km',
        ];

        foreach ($numericKeys as $key) {
            if (!isset($query[$key]) || is_array($query[$key])) {
                continue;
            }
            $value = trim((string) $query[$key]);
            if ($value !== '' && is_numeric($value)) {
                $filters[$key] = $value;
            }
        }

        if (isset($filters['min_price'], $filters['max_price']) && (float) $filters['min_price'] > (float) $filters['max_price']) {
            [$filters['min_price'], $filters['max_price']] = [$filters['max_price'], $filters['min_price']];
        }

        if (isset($filters['min_area'], $filters['max_area']) && (float) $filters['min_area'] > (float) $filters['max_area']) {
            [$filters['min_area'], $filters['max_area']] = [$filters['max_area'], $filters['min_area']];
        }

        if (!empty($query['country_id']) && !is_array($query['country_id'])) {
            $countryId = (int) $query['country_id'];
            if ($countryId > 0 && Country::exists($countryId)) {
                $filters['country_id'] = $countryId;
            }
        }

        if (!empty($query['region_id']) && !is_array($query['region_id'])) {
            $regionId = (int) $query['region_id'];
            if ($regionId > 0) {
                if (!empty($filters['country_id'])) {
                    if (Region::belongsToCountry($regionId, (int) $filters['country_id'])) {
                        $filters['region_id'] = $regionId;
                    }
                } elseif (Region::exists($regionId)) {
                    $filters['region_id'] = $regionId;
                }
            }
        }

        if (!empty($filters['type']) && !PropertyTypeHelper::isValid((string) $filters['type'])) {
            unset($filters['type']);
        }

        $allowedPurpose = ['venda', 'aluguer_curto', 'aluguer_longo'];
        if (!empty($filters['purpose']) && !in_array((string) $filters['purpose'], $allowedPurpose, true)) {
            unset($filters['purpose']);
        }

        $sort = trim((string) ($query['sort'] ?? 'newest'));
        $filters['sort'] = in_array($sort, $allowedSort, true) ? $sort : 'newest';

        if (!empty($query['trusted_only']) && (string) $query['trusted_only'] !== '0') {
            $filters['trusted_only'] = 1;
        }

        foreach (['has_garage', 'has_pool', 'has_elevator', 'has_security'] as $featureKey) {
            if (!array_key_exists($featureKey, $query) || is_array($query[$featureKey])) {
                continue;
            }
            if ((string) $query[$featureKey] !== '0' && $query[$featureKey] !== '') {
                $filters[$featureKey] = !empty($query[$featureKey]);
            }
        }

        return $filters;
    }


    public function index()
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $cursorRaw = isset($_GET['cursor']) ? trim((string) $_GET['cursor']) : '';
        $cursor = $this->decodeCursor($cursorRaw);

        $filters = $this->normalizePropertyListFilters($_GET);

        if (ClassCookieConsent::hasBehavioralConsent()) {
            $filters['viewer_visitor_key'] = ClassSession::getOrCreateVisitorKey();
            if (ClassAuth::check()) {
                $filters['viewer_user_id'] = (int) (ClassAuth::user()['id'] ?? 0);
            }
        }

        $perPage = 12;

        $discoveryPersonalized = false;
        $continueExploring = [];

        $cursorMode = $cursor !== null;
        $nextCursor = null;
        if ($cursorMode) {
            $properties = Property::getFilteredCursor(
                $filters,
                $perPage,
                isset($cursor['created_at']) ? (string) $cursor['created_at'] : null,
                isset($cursor['id']) ? (int) $cursor['id'] : null
            );
            if (!empty($properties)) {
                $last = $properties[count($properties) - 1];
                $nextCursor = $this->encodeCursor($last['created_at'] ?? null, isset($last['id']) ? (int) $last['id'] : null);
            }
            $totalProperties = Property::countFiltered($filters);
            $totalPages = 1;
            $page = 1;
        } else {
            $totalProperties = Property::countFiltered($filters);
            $listing = DiscoveryEngine::propertyListingPage($filters, $page, $perPage);
            $properties = $listing['properties'];
            $discoveryPersonalized = (bool) ($listing['discoveryPersonalized'] ?? false);
            $continueExploring = $listing['continueExploring'] ?? [];
            $totalPages = max(1, (int) ceil($totalProperties / $perPage));
        }

        $favoriteIds = [];

        if (!$cursorMode && $page > $totalPages) {
            $page = $totalPages;
            $listing = DiscoveryEngine::propertyListingPage($filters, $page, $perPage);
            $properties = $listing['properties'];
            $discoveryPersonalized = (bool) ($listing['discoveryPersonalized'] ?? false);
            $continueExploring = $listing['continueExploring'] ?? [];
        }

        if (ClassAuth::check()) {
            $favoriteIds = Favorite::getPropertyIdsByUser(ClassAuth::user()['id']);
        }

        $render = new ClassRender();

        // SEO Configuration for listing page
        $pageNum = $page > 1 ? ' - Página ' . $page : '';
        $render->setTitle('Imóveis Disponíveis' . $pageNum);
        $render->setDescription('Encontre e compare imóveis disponíveis. Anuncie a sua propriedade com segurança e facilidade na Imobil Fácil.');
        $render->setKeywords(ClassSEO::DEFAULT_KEYWORDS);
        $render->setOgTitle('Imóveis Disponíveis' . $pageNum);
        $render->setOgDescription('Procure imóveis por localização, preço e características. Negocie com segurança na nossa plataforma.');
        $render->setCanonical(DIRPAGE . 'properties' . ($page > 1 ? '?page=' . $page : ''));

        // Add collection page structured data
        $itemsForSchema = array_map(function ($prop) {
            return ['url' => rtrim(DIRPAGE, '/') . '/property/' . $prop['id']];
        }, $properties);

        $collectionSchema = ClassSEO::getCollectionPageSchema(
            'Imóveis Disponíveis',
            'Encontre imóveis verificados na plataforma Imobil Fácil',
            $itemsForSchema,
            $page,
            $totalPages,
            'properties' . ($page > 1 ? '?page=' . $page : '')
        );
        $render->addStructuredData($collectionSchema);

        // Add breadcrumb
        $render->addStructuredData(ClassSEO::getBreadcrumbSchema([
            ['name' => 'Home', 'url' => rtrim(DIRPAGE, '/')],
            ['name' => 'Propriedades', 'url' => rtrim(DIRPAGE, '/') . '/properties'],
        ]));

        $render->setData([
            'properties' => $properties,
            'countries' => Country::getActive(),
            'regions' => Region::getActive(),
            'favoriteIds' => $favoriteIds,
            'page' => $page,
            'perPage' => $perPage,
            'totalProperties' => $totalProperties,
            'totalPages' => $totalPages,
            'discoveryPersonalized' => $discoveryPersonalized,
            'continueExploring' => $continueExploring,
            'cursorMode' => $cursorMode,
            'cursor' => $cursorRaw,
            'nextCursor' => $nextCursor,
        ]);
        $render->setDir('property/list');

        if (!ClassAuth::check() && !ClassCookieConsent::hasBehavioralConsent()) {
            $cacheTtl = max(30, ClassSettings::int('page_cache_property_list_ttl_seconds', 120));
            $html = PageCache::capture('property_list:' . $page . ':' . md5(http_build_query($filters)), $cacheTtl, function () use ($render) {
                ob_start();
                $render->renderLayout();
                return ob_get_clean();
            });
            echo $html;
            return;
        }

        $render->renderLayout();
    }


    public function properties()
    {
        // Alias para index()
        $this->index();
    }


    public function show($id)
    {
        $property = Property::find($id);
        if (!$property) {
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        MetricEvent::track('property_page_view', [
            'entity_type' => 'property',
            'entity_id' => $property['id'] ?? null,
            'user_id' => ClassAuth::check() ? ClassAuth::user()['id'] : null,
            'metadata' => [
                'status' => $property['status'] ?? null,
                'affiliate_id' => $property['affiliate_id'] ?? null,
            ],
        ]);

        // Keep sold/rented details publicly accessible, but non-public moderation states remain restricted.
        $isModerator = ClassAuth::check() && ClassAccess::can('properties.moderate');
        $publicStatuses = ['disponivel', 'vendido', 'alugado'];
        $isPubliclyVisible = in_array((string) ($property['status'] ?? ''), $publicStatuses, true);
        $isOwner = ClassAuth::check()
            && (int) (ClassAuth::user()['id'] ?? 0) === (int) ($property['affiliate_id'] ?? 0);

        if (!$isPubliclyVisible && !$isModerator && !$isOwner) {
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        $ownerId = (int) ($property['affiliate_id'] ?? 0);
        if ($ownerId > 0 && !$isModerator) {
            $owner = User::findById($ownerId);
            if ($owner && User::isPendingDeletion($owner)) {
                header('Location: ' . DIRPAGE . '404');
                exit;
            }
        }

        $isFavorite = false;

        // Check for referral
        if (isset($_GET['ref'])) {
            $affiliate = User::findByAffiliateCode($_GET['ref']);
            if ($affiliate && !empty($affiliate['is_affiliate']) && ($affiliate['status'] ?? '') === 'ativo') {
                ClassSession::set('referred_by', $affiliate['id']);
            }
        }

        $isAffiliate = false;
        $hasAffiliateRequest = false;
        $affiliateStatus = null; // 'pendente', 'ativo', 'rejeitado'
        $hasActiveRequest = false;
        $viewerUserId = null;
        if (ClassAuth::check()) {
            $viewerUserId = (int) (ClassAuth::user()['id'] ?? 0);
            $isFavorite = Favorite::exists(ClassAuth::user()['id'], (int) $property['id']);
            $isAffiliate = PropertyAffiliate::isActiveAffiliate(ClassAuth::user()['id'], (int) $property['id']);
            $hasAffiliateRequest = PropertyAffiliate::exists(ClassAuth::user()['id'], (int) $property['id']);
            $affiliateStatus = $hasAffiliateRequest
                ? PropertyAffiliate::getStatusForUser((int) ClassAuth::user()['id'], (int) $property['id'])
                : null;
            $hasActiveRequest = Request::hasActiveRequest((int) ClassAuth::user()['id'], (int) $property['id']);

        }

        if (ClassCookieConsent::hasBehavioralConsent()) {
            $visitorKey = ClassSession::getOrCreateVisitorKey();
            // Lightweight behavioral signal for personalized discovery ranking.
            PropertyBehaviorEvent::track($viewerUserId, (int) ($property['id'] ?? 0), 'view', $visitorKey);
        }

        $render = new ClassRender();

        // SEO Configuration
        $title = (string) ($property['title'] ?? 'Imóvel');
        $description = ClassSEO::excerptFromText($property['description'] ?? '');
        $propertyType = (string) ($property['type'] ?? 'imóvel');
        $location = trim((string) ($property['location'] ?? ''));
        $titleSuffix = $location !== '' ? ' — ' . $location : '';

        $render->setTitle($title . $titleSuffix);
        $render->setDescription($description);
        $render->setKeywords('imóvel angola, ' . $propertyType . ', ' . $location . ', venda, aluguer');
        $render->setOgTitle($title);
        $render->setOgDescription($description);
        $render->setOgImage(ClassSEO::propertyImageUrl($property));
        $render->setOgType('product');
        $render->setCanonical(DIRPAGE . 'property/' . $property['id']);

        // Add property structured data (RealEstateProperty schema)
        $propertySchema = ClassSEO::getPropertySchema($property);
        $render->addStructuredData($propertySchema);

        // Add breadcrumb navigation
        $render->addStructuredData(ClassSEO::getBreadcrumbSchema([
            ['name' => 'Home', 'url' => rtrim(DIRPAGE, '/')],
            ['name' => 'Propriedades', 'url' => rtrim(DIRPAGE, '/') . '/properties'],
            ['name' => $title, 'url' => rtrim(DIRPAGE, '/') . '/property/' . $property['id']],
        ]));

        $render->setData([
            'property' => $property,
            'isFavorite' => $isFavorite,
            'isAffiliate' => $isAffiliate,
            'hasAffiliateRequest' => $hasAffiliateRequest,
            'affiliateStatus' => $affiliateStatus,
            'hasActiveRequest' => $hasActiveRequest,
            'hasBlockingOverdueCommissions' => ClassCommissionGuard::currentUserHasBlockingOverdue(),
            'canSubmitPropertyRequest' => ClassAuth::check() && ClassAccess::canSubmitPropertyRequest(),
            'hasLimitedAccountAccess' => ClassAuth::check() && ClassAccess::hasLimitedPlatformAccess(),
        ]);
        $render->setDir('property/show');

        if (!ClassAuth::check()) {
            $cacheTtl = max(60, ClassSettings::int('page_cache_property_show_ttl_seconds', 180));
            $html = PageCache::capture('property_show:' . (int) $id, $cacheTtl, function () use ($render) {
                ob_start();
                $render->renderLayout();
                return ob_get_clean();
            });
            echo $html;
            return;
        }

        $render->renderLayout();
    }


    public function agency($agencyUserId = null)
    {
        $agencyUserId = $this->resolveRouteInt($agencyUserId, 2);

        if ($agencyUserId <= 0) {
            header('Location: ' . DIRPAGE . 'properties');
            exit;
        }

        $agencyUser = User::findById($agencyUserId);
        if (!$agencyUser || (string) ($agencyUser['status'] ?? '') !== 'ativo') {
            header('HTTP/1.0 404 Not Found');
            $render = new ClassRender();
            $render->setTitle('Página não encontrada');
            $render->setDir('404');
            $render->renderLayout();
            return;
        }

        if (!ClassPlan::canUseInstitutionalPage($agencyUserId)) {
            header('Location: ' . DIRPAGE . 'property/owner/' . $agencyUserId, true, 302);
            exit;
        }

        $trustMetrics = User::getTrustMetrics($agencyUserId);
        $officialPlan = ClassPlan::getOfficialPlanByUser($agencyUserId);
        $properties = Property::getByAffiliate($agencyUserId);
        $properties = array_values(array_filter($properties, static fn ($p) => ($p['status'] ?? '') === 'disponivel'));

        $favoriteIds = [];
        if (ClassAuth::check()) {
            $favoriteIds = Favorite::getPropertyIdsByUser((int) (ClassAuth::user()['id'] ?? 0));
        }

        $portfolioStats = [
            'total' => count($properties),
            'for_sale' => 0,
            'for_rent' => 0,
            'min_price' => null,
            'max_price' => null,
        ];
        $locationCounts = [];
        $typeCounts = [];
        $featuredProperties = [];

        foreach ($properties as $property) {
            $purpose = (string) ($property['purpose'] ?? '');
            if (str_starts_with($purpose, 'aluguer')) {
                $portfolioStats['for_rent']++;
            } else {
                $portfolioStats['for_sale']++;
            }
            $price = (float) ($property['price'] ?? 0);
            if ($price > 0) {
                $portfolioStats['min_price'] = $portfolioStats['min_price'] === null
                    ? $price
                    : min($portfolioStats['min_price'], $price);
                $portfolioStats['max_price'] = $portfolioStats['max_price'] === null
                    ? $price
                    : max($portfolioStats['max_price'], $price);
            }

            $location = trim((string) ($property['location'] ?? ''));
            if ($location !== '') {
                $locationCounts[$location] = ($locationCounts[$location] ?? 0) + 1;
            }

            $typeCode = trim((string) ($property['type'] ?? ''));
            if ($typeCode !== '') {
                $typeCounts[$typeCode] = ($typeCounts[$typeCode] ?? 0) + 1;
            }

            if (!empty($property['featured']) && count($featuredProperties) < 3) {
                $featuredProperties[] = $property;
            }
        }

        arsort($locationCounts);
        $topLocations = array_slice($locationCounts, 0, 6, true);

        arsort($typeCounts);
        $topTypes = [];
        $typeLabels = PropertyTypeHelper::getTypeLabels();
        foreach (array_slice($typeCounts, 0, 5, true) as $code => $count) {
            $topTypes[] = [
                'code' => $code,
                'label' => $typeLabels[$code] ?? ucfirst($code),
                'count' => $count,
            ];
        }

        $memberSinceYear = null;
        if (!empty($agencyUser['created_at'])) {
            $ts = strtotime((string) $agencyUser['created_at']);
            if ($ts) {
                $memberSinceYear = (int) date('Y', $ts);
            }
        }

        $isJuridica = (string) ($agencyUser['user_type'] ?? '') === 'pessoa_juridica';
        $documentNumber = trim((string) ($agencyUser['document_number'] ?? ''));
        $documentLabel = '';
        if ($isJuridica && $documentNumber !== '') {
            $documentLabel = strlen($documentNumber) > 6
                ? substr($documentNumber, 0, 3) . '···' . substr($documentNumber, -3)
                : $documentNumber;
        }

        $agencyName = (string) ($agencyUser['name'] ?? 'Agência');
        $agencyDescription = ($isJuridica ? 'Empresa imobiliária' : 'Profissional imobiliário')
            . ' com catálogo de imóveis disponíveis: '
            . $agencyName
            . '.';
        $render = new ClassRender();
        $render->setTitle($agencyName . ($isJuridica ? ' — Empresa' : ' — Profissional'));
        $render->setDescription($agencyDescription);
        $render->setKeywords('agência imobiliária angola, promotor, imóveis, ' . $agencyName);
        $render->setCanonical(ClassPlan::getPublicProfileUrl($agencyUserId));
        $render->setOgTitle($agencyName . ' — Imobil Fácil');
        $render->setOgDescription($agencyDescription);
        $render->setOgImage(ClassSEO::defaultOgImage());
        $render->addStructuredData(ClassSEO::getBreadcrumbSchema([
            ['name' => 'Início', 'url' => DIRPAGE],
            ['name' => 'Imóveis', 'url' => DIRPAGE . 'properties'],
            ['name' => $agencyName, 'url' => ClassPlan::getPublicProfileUrl($agencyUserId)],
        ]));
        $render->setData([
            'agencyUser' => $agencyUser,
            'properties' => $properties,
            'trustMetrics' => $trustMetrics,
            'portfolioStats' => $portfolioStats,
            'favoriteIds' => $favoriteIds,
            'publicProfileUrl' => ClassPlan::getPublicProfileUrl($agencyUserId),
            'topLocations' => $topLocations,
            'topTypes' => $topTypes,
            'featuredProperties' => $featuredProperties,
            'memberSinceYear' => $memberSinceYear,
            'documentLabel' => $documentLabel,
            'isJuridica' => $isJuridica,
        ]);
        $render->setDir('property/agency');
        $render->renderLayout();
    }


    public function featured()
    {
        $perPage = 12;
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $totalFeatured = Property::countFeatured();
        $hasBehaviorConsent = ClassCookieConsent::hasBehavioralConsent();
        $viewerUserId = ($hasBehaviorConsent && ClassAuth::check()) ? (int) (ClassAuth::user()['id'] ?? 0) : null;
        $visitorKey = $hasBehaviorConsent ? ClassSession::getOrCreateVisitorKey() : null;

        $listing = DiscoveryEngine::featuredListingPage($page, $perPage, $viewerUserId, $visitorKey);
        $properties = $listing['properties'];
        $discoveryPersonalized = (bool) ($listing['discoveryPersonalized'] ?? false);
        $continueExploring = $listing['continueExploring'] ?? [];

        $favoriteIds = [];
        $totalPages = max(1, (int) ceil($totalFeatured / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
            $listing = DiscoveryEngine::featuredListingPage($page, $perPage, $viewerUserId, $visitorKey);
            $properties = $listing['properties'];
            $discoveryPersonalized = (bool) ($listing['discoveryPersonalized'] ?? false);
            $continueExploring = $listing['continueExploring'] ?? [];
        }

        if (ClassAuth::check()) {
            $favoriteIds = Favorite::getPropertyIdsByUser(ClassAuth::user()['id']);
        }

        $pageLabel = $page > 1 ? ' — Página ' . $page : '';
        $render = new ClassRender();
        $render->setTitle('Imóveis em Destaque' . $pageLabel);
        $render->setDescription('Imóveis patrocinados e em destaque na Imobil Fácil. Descubra as melhores oportunidades em Angola.');
        $render->setKeywords('imóveis destaque, patrocinados, angola, ' . ClassSEO::DEFAULT_KEYWORDS);
        $render->setOgTitle('Imóveis em Destaque — Imobil Fácil');
        $render->setOgDescription('Seleção de imóveis em destaque com maior visibilidade na plataforma.');
        $render->setOgImage(ClassSEO::defaultOgImage());
        $render->setCanonical(DIRPAGE . 'featured' . ($page > 1 ? '?page=' . $page : ''));

        $itemsForSchema = array_map(static function ($prop) {
            return ['url' => rtrim(DIRPAGE, '/') . '/property/' . $prop['id']];
        }, $properties);

        $render->addStructuredData(ClassSEO::getCollectionPageSchema(
            'Imóveis em Destaque',
            'Imóveis patrocinados e em destaque na plataforma Imobil Fácil',
            $itemsForSchema,
            $page,
            $totalPages,
            'featured' . ($page > 1 ? '?page=' . $page : '')
        ));

        $render->addStructuredData(ClassSEO::getBreadcrumbSchema([
            ['name' => 'Início', 'url' => rtrim(DIRPAGE, '/')],
            ['name' => 'Destaques', 'url' => rtrim(DIRPAGE, '/') . '/featured'],
        ]));
        $render->setData([
            'properties' => $properties,
            'favoriteIds' => $favoriteIds,
            'page' => $page,
            'perPage' => $perPage,
            'totalFeatured' => $totalFeatured,
            'totalPages' => $totalPages,
            'discoveryPersonalized' => $discoveryPersonalized,
            'continueExploring' => $continueExploring,
        ]);
        $render->setDir('property/featured');

        if (!ClassAuth::check() && !ClassCookieConsent::hasBehavioralConsent()) {
            $cacheTtl = max(30, ClassSettings::int('page_cache_property_list_ttl_seconds', 120));
            $html = PageCache::capture('featured_list:' . $page, $cacheTtl, function () use ($render) {
                ob_start();
                $render->renderLayout();
                return ob_get_clean();
            });
            echo $html;
            return;
        }

        $render->renderLayout();
    }


    public function owner($id)
    {
        $ownerId = $this->resolveRouteInt($id, 2);
        if ($ownerId > 0 && ClassPlan::canUseInstitutionalPage($ownerId)) {
            header('Location: ' . ClassPlan::getPublicProfileUrl($ownerId), true, 302);
            exit;
        }

        $owner = User::findById($ownerId);
        if (!$owner || ($owner['status'] ?? '') !== 'ativo') {
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        $isModerator = ClassAuth::check() && ClassAccess::isAdmin(ClassAuth::user());
        if (User::isPendingDeletion($owner) && !$isModerator) {
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        $trustMetrics = User::getTrustMetrics($ownerId);
        $officialPlan = ClassPlan::getOfficialPlanByUser($ownerId);
        $properties = Property::getByAffiliate($ownerId);
        $favoriteIds = [];
        // Only show available properties to public
        $properties = array_filter($properties, fn ($p) => ($p['status'] ?? '') === 'disponivel');

        if (ClassAuth::check()) {
            $favoriteIds = Favorite::getPropertyIdsByUser((int) (ClassAuth::user()['id'] ?? 0));
        }

        $render = new ClassRender();
        $ownerTitle = \Src\classes\UserDisplay::handleWithAt($owner);
        if ($ownerTitle === '') {
            $ownerTitle = \Src\classes\UserDisplay::publicLabel($owner);
        }
        $render->setTitle('Perfil de ' . $ownerTitle);
        $render->setDescription('Conheça o proprietário e os seus imóveis disponíveis.');
        $render->setKeywords('proprietário, imóveis, perfil');
        $render->setData([
            'owner' => $owner,
            'trustMetrics' => $trustMetrics,
            'officialPlan' => $officialPlan,
            'properties' => array_values($properties),
            'favoriteIds' => $favoriteIds,
        ]);
        $render->setDir('property/owner');
        $render->renderLayout();
    }

}
