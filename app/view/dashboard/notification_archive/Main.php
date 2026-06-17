<?php
/** @var array<int, array<string, mixed>> $notifications */
$notifications = is_array($notifications ?? null) ? $notifications : [];
$page = (int) ($page ?? 1);
$limit = (int) ($limit ?? 20);
$totalPages = (int) ($totalPages ?? 1);
$total = (int) ($total ?? count($notifications));
$typeFilter = isset($typeFilter) ? $typeFilter : null;
$cursorMode = !empty($cursorMode) || !empty($data['cursorMode']);
$nextCursor = (string) ($nextCursor ?? ($data['nextCursor'] ?? ''));

$queryParams = $_GET;
unset($queryParams['filters_open']);
$buildPageUrl = static function (int $targetPage) use ($queryParams, $typeFilter): string {
    $params = $queryParams;
    unset($params['cursor']);
    $params['page'] = $targetPage;
    if ($typeFilter !== null && $typeFilter !== '') {
        $params['type'] = $typeFilter;
    } else {
        unset($params['type']);
    }
    return DIRPAGE . 'notification/archive?' . http_build_query($params);
};
$buildCursorUrl = static function (string $cursorValue) use ($queryParams, $typeFilter): string {
    $params = $queryParams;
    unset($params['page']);
    $params['cursor'] = $cursorValue;
    if ($typeFilter !== null && $typeFilter !== '') {
        $params['type'] = $typeFilter;
    } else {
        unset($params['type']);
    }
    return DIRPAGE . 'notification/archive?' . http_build_query($params);
};

$types = [
    '' => 'Todas',
    'Chat' => 'Chat',
    'Solicitações' => 'Solicitações',
    'Disputas' => 'Disputas',
    'Pagamentos' => 'Pagamentos',
    'Documentos' => 'Documentos',
    'Confiança' => 'Confiança',
    'Conta' => 'Conta',
    'Destaque' => 'Destaque',
    'Acompanhamento' => 'Acompanhamento',
    'Notificação' => 'Outras',
];

$groupNotifications = static function (array $items): array {
    $groups = [
        'today' => ['label' => 'Hoje', 'items' => []],
        'week' => ['label' => 'Esta semana', 'items' => []],
        'earlier' => ['label' => 'Anteriores', 'items' => []],
    ];

    $now = time();
    foreach ($items as $item) {
        $createdAt = strtotime((string) ($item['created_at'] ?? ''));
        if ($createdAt === false) {
            $groups['earlier']['items'][] = $item;
            continue;
        }

        if (date('Y-m-d', $createdAt) === date('Y-m-d', $now)) {
            $groups['today']['items'][] = $item;
            continue;
        }

        if ($createdAt >= strtotime('-7 days', $now)) {
            $groups['week']['items'][] = $item;
            continue;
        }

        $groups['earlier']['items'][] = $item;
    }

    return array_values(array_filter($groups, static fn (array $group): bool => !empty($group['items'])));
};

$notificationGroups = $groupNotifications($notifications);
$activeTypeLabel = 'Todas';
if ($typeFilter !== null && $typeFilter !== '' && isset($types[$typeFilter])) {
    $activeTypeLabel = (string) $types[$typeFilter];
} elseif ($typeFilter !== null && $typeFilter !== '') {
    $activeTypeLabel = (string) $typeFilter;
}
?>

<?php
$dashboardPageClass = 'notification-inbox-view notification-archive-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$inboxHeroTitle = 'Arquivo';
ob_start();
?>
<span class="notification-archive-filter-pill"><?php echo htmlspecialchars($activeTypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<span><?php echo (int) $total; ?> arquivada(s)</span>
<?php
$inboxHeroMetaHtml = ob_get_clean();
ob_start();
?>
<a href="<?php echo DIRPAGE; ?>notification/inbox" class="notification-inbox-text-btn">Voltar à inbox</a>
<?php
$inboxHeroActionsHtml = ob_get_clean();
include DIRREQ . 'app/view/partials/dashboard_inbox_hero.php';
unset($inboxHeroMetaHtml, $inboxHeroActionsHtml);
?>

    <div class="notification-archive-tabs-wrap">
        <nav class="notification-archive-tabs" aria-label="Filtrar arquivo por tipo">
            <?php foreach ($types as $value => $label): ?>
                <?php
                    $isActive = ($value === '' && ($typeFilter === null || $typeFilter === '')) || ($value !== '' && $typeFilter === $value);
                    $href = DIRPAGE . 'notification/archive?' . http_build_query(array_filter([
                        'type' => $value !== '' ? $value : null,
                    ]));
                ?>
                <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
                   class="notification-archive-tab <?php echo $isActive ? 'is-active' : ''; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="notification-inbox-panel">
        <?php if (empty($notifications)): ?>
            <div class="notification-inbox-empty">
                <span class="notification-inbox-empty-icon" aria-hidden="true"><i class="fa fa-archive"></i></span>
                <strong>Arquivo vazio</strong>
                <p>Sem notificações arquivadas<?php echo $activeTypeLabel !== 'Todas' ? ' para este filtro' : ''; ?>.</p>
            </div>
        <?php else: ?>
            <div class="notification-feed notification-feed--inbox notification-feed--archive">
                <?php foreach ($notificationGroups as $group): ?>
                    <section class="notification-feed-group">
                        <h2 class="notification-feed-group-title"><?php echo htmlspecialchars((string) $group['label']); ?></h2>
                        <div class="notification-feed-group-list">
                            <?php foreach ($group['items'] as $n): ?>
                                <?php
                                    $notification = $n;
                                    $compact = false;
                                    $showMenu = true;
                                    $archiveMode = true;
                                    require __DIR__ . '/../../partials/notification_feed_item.php';
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <?php if (!$cursorMode && $totalPages > 1): ?>
                <div class="notification-inbox-pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo htmlspecialchars($buildPageUrl($page - 1)); ?>" class="notification-inbox-page-btn">&larr; Anterior</a>
                    <?php endif; ?>
                    <span class="notification-inbox-page-copy">Página <?php echo (int) $page; ?> de <?php echo (int) $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo htmlspecialchars($buildPageUrl($page + 1)); ?>" class="notification-inbox-page-btn">Próxima &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($cursorMode && $nextCursor !== ''): ?>
                <div class="notification-inbox-pagination">
                    <a href="<?php echo htmlspecialchars($buildCursorUrl($nextCursor)); ?>" class="notification-inbox-page-btn">Carregar mais</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
