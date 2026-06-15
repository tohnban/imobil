<?php

namespace Src\classes;

/**
 * Centralized SEO configuration, structured data, and meta helpers.
 */
class ClassSEO
{
    public const SITE_NAME = 'Imobil Fácil';
    public const SITE_DESCRIPTION = 'Plataforma angolana para encontrar, anunciar e negociar imóveis com segurança.';
    public const SITE_LANGUAGE = 'pt';
    public const SITE_LOCALE = 'pt_AO';
    public const SITE_COUNTRY = 'AO';
    public const DEFAULT_CURRENCY = 'AOA';
    public const DEFAULT_KEYWORDS = 'imóveis angola, casas, apartamentos, aluguer, venda, luanda, imobil facil';

    public const SCHEMA_ORG = 'https://schema.org';

    public const OG_TYPE_WEBSITE = 'website';
    public const OG_TYPE_ARTICLE = 'article';
    public const OG_TYPE_PRODUCT = 'product';

    private const PRIVATE_VIEW_PREFIXES = [
        'auth/',
        'dashboard/',
        'notification/',
        'property/moderate',
        'errors/',
    ];

    public static function defaultOgImage(): string
    {
        return DIRIMG . 'logo-imobilfacil.png';
    }

    public static function faviconIcoUrl(): string
    {
        return DIRPAGE . 'public/img/favicon.ico';
    }

    public static function faviconPngUrl(): string
    {
        return DIRPAGE . 'public/img/favicon.png';
    }

    public static function appleTouchIconUrl(): string
    {
        return self::faviconPngUrl();
    }

    public static function isPrivateViewDir(string $viewDir): bool
    {
        $viewDir = trim($viewDir);
        foreach (self::PRIVATE_VIEW_PREFIXES as $prefix) {
            if ($viewDir === rtrim($prefix, '/') || str_starts_with($viewDir, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function robotsForViewDir(string $viewDir): string
    {
        if (self::isPrivateViewDir($viewDir)) {
            return 'noindex, nofollow';
        }

        return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    }

    /**
     * Default meta for authenticated / private areas when controllers omit SEO.
     */
    public static function primeLayoutSeo(ClassRender $render): void
    {
        $viewDir = (string) $render->getDir();

        if ($render->getRobotsMeta() === '') {
            $render->setRobotsMeta(self::robotsForViewDir($viewDir));
        }

        if (self::isPrivateViewDir($viewDir)) {
            return;
        }

        if (!$render->getOgImage()) {
            $render->setOgImage(self::defaultOgImage());
        }

        if (!$render->getTitle()) {
            $render->setTitle(self::SITE_NAME);
        }

        if (!$render->getDescription()) {
            $render->setDescription(self::SITE_DESCRIPTION);
        }

        if (!$render->getKeywords()) {
            $render->setKeywords(self::DEFAULT_KEYWORDS);
        }
    }

    public static function renderRobotsTxt(): string
    {
        $base = rtrim(DIRPAGE, '/');

        return implode("\n", [
            '# Imobil Fácil — robots.txt (gerado dinamicamente)',
            '# Atualizado: ' . date('Y-m-d'),
            '',
            'User-agent: *',
            'Allow: /',
            '',
            'Disallow: /dashboard',
            'Disallow: /notification',
            'Disallow: /api',
            'Disallow: /auth',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /recover',
            'Disallow: /reset',
            'Disallow: /verify',
            'Disallow: /file',
            '',
            '# Parâmetros de tracking / filtros avançados (evitar URLs duplicadas)',
            'Disallow: /*?*sort=',
            'Disallow: /*?*filter=',
            'Disallow: /*?*cursor=',
            'Disallow: /*?*ref=',
            '',
            'User-agent: AhrefsBot',
            'Crawl-delay: 10',
            '',
            'User-agent: SemrushBot',
            'Crawl-delay: 10',
            '',
            'User-agent: DotBot',
            'Crawl-delay: 10',
            '',
            'Sitemap: ' . $base . '/sitemap',
        ]) . "\n";
    }

    public static function getOrganizationSchema(): array
    {
        return [
            '@context' => self::SCHEMA_ORG,
            '@type' => 'RealEstateAgent',
            'name' => self::SITE_NAME,
            'description' => self::SITE_DESCRIPTION,
            'url' => rtrim(DIRPAGE, '/'),
            'logo' => self::defaultOgImage(),
            'image' => self::defaultOgImage(),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'areaServed' => self::SITE_COUNTRY,
                'availableLanguage' => ['pt', 'pt-AO'],
            ],
        ];
    }

    public static function getWebSiteSchema(): array
    {
        $base = rtrim(DIRPAGE, '/');

        return [
            '@context' => self::SCHEMA_ORG,
            '@type' => 'WebSite',
            'name' => self::SITE_NAME,
            'description' => self::SITE_DESCRIPTION,
            'url' => $base,
            'inLanguage' => self::SITE_LOCALE,
            'publisher' => [
                '@type' => 'Organization',
                'name' => self::SITE_NAME,
                'logo' => self::defaultOgImage(),
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $base . '/properties?search={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function getBreadcrumbSchema(array $items): array
    {
        $breadcrumbs = [
            '@context' => self::SCHEMA_ORG,
            '@type' => 'BreadcrumbList',
            'itemListElement' => [],
        ];

        foreach ($items as $index => $item) {
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return $breadcrumbs;
    }

    public static function propertyImageUrl(array $property): string
    {
        $images = json_decode((string) ($property['images'] ?? '[]'), true);
        if (!is_array($images) || empty($images[0])) {
            return self::defaultOgImage();
        }

        $image = trim((string) $images[0]);
        if ($image === '') {
            return self::defaultOgImage();
        }

        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        return ClassMediaUrl::propertyImage($image);
    }

    public static function excerptFromText(?string $text, int $maxLength = 160): string
    {
        $text = trim(strip_tags((string) $text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        if ($text === '') {
            return self::SITE_DESCRIPTION;
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 3)) . '...';
    }

    public static function getPropertySchema(array $property): array
    {
        $statusMap = [
            'disponivel' => 'InStock',
            'vendido' => 'OutOfStock',
            'alugado' => 'OutOfStock',
        ];

        $status = (string) ($property['status'] ?? 'disponivel');
        $availability = $statusMap[$status] ?? 'InStock';
        $propertyUrl = rtrim(DIRPAGE, '/') . '/property/' . (int) ($property['id'] ?? 0);
        $currency = strtoupper((string) ($property['currency'] ?? self::DEFAULT_CURRENCY));
        $location = trim((string) ($property['location'] ?? ''));
        $purpose = (string) ($property['purpose'] ?? '');
        $offerCategory = str_starts_with($purpose, 'aluguer') ? 'lease' : 'sale';

        $schema = [
            '@context' => self::SCHEMA_ORG,
            '@type' => 'Accommodation',
            '@id' => $propertyUrl,
            'name' => $property['title'] ?? 'Imóvel',
            'description' => self::excerptFromText($property['description'] ?? '', 500),
            'url' => $propertyUrl,
            'image' => self::propertyImageUrl($property),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $location,
                'addressCountry' => self::SITE_COUNTRY,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $propertyUrl,
                'priceCurrency' => $currency,
                'price' => (float) ($property['price'] ?? 0),
                'availability' => 'https://schema.org/' . $availability,
                'businessFunction' => $offerCategory === 'lease'
                    ? 'http://purl.org/goodrelations/v1#LeaseOut'
                    : 'http://purl.org/goodrelations/v1#Sell',
            ],
        ];

        if (!empty($property['bedrooms'])) {
            $schema['numberOfRooms'] = (int) $property['bedrooms'];
        }
        if (!empty($property['bathrooms'])) {
            $schema['numberOfBathroomsTotal'] = (int) $property['bathrooms'];
        }
        if (!empty($property['area'])) {
            $schema['floorSize'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $property['area'],
                'unitCode' => 'MTK',
            ];
        }
        if (!empty($property['owner_name'])) {
            $schema['offers']['seller'] = [
                '@type' => 'Person',
                'name' => (string) $property['owner_name'],
            ];
        }

        return $schema;
    }

    public static function getCollectionPageSchema(
        string $title,
        string $description,
        array $items = [],
        int $page = 1,
        int $totalPages = 1,
        ?string $canonicalPath = null
    ): array {
        $base = rtrim(DIRPAGE, '/');
        $pageUrl = $canonicalPath !== null
            ? $base . '/' . ltrim($canonicalPath, '/')
            : self::getCanonicalUrl();

        $schema = [
            '@context' => self::SCHEMA_ORG,
            '@type' => 'CollectionPage',
            'name' => $title,
            'description' => $description,
            'url' => $pageUrl,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => [],
            ],
        ];

        foreach ($items as $index => $item) {
            $schema['mainEntity']['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => $item['url'] ?? '',
            ];
        }

        if ($totalPages > 1) {
            $listPath = 'properties';
            if ($canonicalPath !== null) {
                $pathPart = strtok(ltrim($canonicalPath, '/'), '?');
                if (is_string($pathPart) && $pathPart !== '') {
                    $listPath = $pathPart;
                }
            }
            if ($page > 1) {
                $prevPage = $page - 1;
                $schema['previousPage'] = $base . '/' . $listPath . ($prevPage > 1 ? '?page=' . $prevPage : '');
            }
            if ($page < $totalPages) {
                $schema['nextPage'] = $base . '/' . $listPath . '?page=' . ($page + 1);
            }
        }

        return $schema;
    }

    public static function sanitizeTitle(?string $title, int $maxLength = 60): string
    {
        $title = strip_tags((string) $title);
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        if (mb_strlen($title) > $maxLength) {
            $title = rtrim(mb_substr($title, 0, $maxLength - 3)) . '...';
        }

        return $title;
    }

    public static function sanitizeDescription(?string $description, int $maxLength = 160): string
    {
        $description = strip_tags((string) $description);
        $description = preg_replace('/\s+/u', ' ', $description) ?? '';
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        if (mb_strlen($description) > $maxLength) {
            $description = rtrim(mb_substr($description, 0, $maxLength - 3)) . '...';
        }

        return $description;
    }

    /**
     * Canonical URL without tracking params; keeps ?page= for paginated listings.
     */
    public static function getCanonicalUrl(?string $explicitCanonical = null): string
    {
        if ($explicitCanonical !== null && $explicitCanonical !== '') {
            return $explicitCanonical;
        }

        $base = rtrim(DIRPAGE, '/');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $query = [];
        $queryString = parse_url($uri, PHP_URL_QUERY);
        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $query);
        }

        $allowed = [];
        if (isset($query['page']) && (int) $query['page'] > 1) {
            $allowed['page'] = (int) $query['page'];
        }

        $canonical = $base . $path;
        if (!empty($allowed)) {
            $canonical .= '?' . http_build_query($allowed);
        }

        return $canonical;
    }
}
