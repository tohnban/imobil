<?php use Src\classes\FeedGrouping; ?>
<?php
$dashboardPageClass = 'notification-inbox-view requests-inbox-view requests-dashboard-view';
$dashboardPageAttrs = sprintf(
    'id="requestsDashboardRoot" data-chat-summaries-feed-url="%s"',
    htmlspecialchars(DIRPAGE . 'dashboard/requestChatSummariesFeed?view=' . urlencode((string) ($requestView ?? '')), ENT_QUOTES, 'UTF-8')
);
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
        $currentView = (string) ($requestView ?? '');
        $isAdminScope = ($scope ?? 'user') === 'admin';
        $canManageAllRequests = !empty($canManageAllRequests);
        $actionFilter = (string) ($actionFilter ?? 'all');
        $page = max(1, (int) ($page ?? 1));
        $totalPages = max(1, (int) ($totalPages ?? 1));
        $requestsTotal = (int) ($requestsTotal ?? count($requests ?? []));
        $requestsList = is_array($requests ?? null) ? $requests : [];
        $showManagementColumns = ($scope ?? 'user') === 'admin'
            || (($scope ?? 'user') === 'owner' && $currentView !== 'sent');
        $showSentContextColumns = !$showManagementColumns;

        function requestsPageUrl(int $targetPage, string $requestView, string $actionFilter): string {
            $params = [];
            if ($requestView !== '') {
                $params['view'] = $requestView;
            }
            if ($actionFilter !== 'all') {
                $params['action_filter'] = $actionFilter;
            }
            if ($targetPage > 1) {
                $params['page'] = $targetPage;
            }
            $query = http_build_query($params);
            return DIRPAGE . 'requests' . ($query !== '' ? ('?' . $query) : '');
        }

        $requestGroups = FeedGrouping::byRecency($requestsList);
        $listHeading = $showManagementColumns
            ? 'Todas as solicitações'
            : ($currentView === 'sent' ? 'Enviadas por si' : 'Recebidas');
$inboxHeroTitle = (string) ($pageTitle ?? 'Minhas Solicitações');
ob_start();
?>
<span><?php echo (int) $requestsTotal; ?> no total</span>
<?php if (!empty($requestsList)): ?>
    <span class="notification-feed-dot" aria-hidden="true">·</span>
    <span><?php echo count($requestsList); ?> nesta página</span>
<?php endif;
$inboxHeroMetaHtml = ob_get_clean();
ob_start();
?>
<a href="<?php echo DIRPAGE; ?>dashboard/requestChats" class="notification-inbox-text-btn">Ver conversas</a>
<?php
$inboxHeroActionsHtml = ob_get_clean();
include DIRREQ . 'app/view/partials/dashboard_inbox_hero.php';
unset($inboxHeroMetaHtml, $inboxHeroActionsHtml);
?>

    <div class="requests-legend requests-inbox-legend">
        <div class="legend-title">
            <div class="legend-title-content">
                <i class="fa fa-info-circle"></i>
                <strong>O que significa cada estado</strong>
            </div>
            <button type="button" class="requests-legend-sheet-btn" id="requestsLegendSheetBtn" aria-controls="requestsLegendSheet" aria-expanded="false">
                Ver explicação
            </button>
            <button type="button" class="legend-toggle" id="legendToggle" aria-expanded="false" aria-label="Mostrar ou esconder explicação dos estados">
                <i class="fa fa-chevron-up"></i>
            </button>
        </div>
        <div class="legend-grid" id="legendGrid">
            <?php require __DIR__ . '/../../partials/requests_legend_items.php'; ?>
        </div>
    </div>

    <div class="sheet-modal requests-legend-sheet" id="requestsLegendSheet" hidden>
        <div class="sheet-modal-backdrop" data-sheet-close aria-hidden="true"></div>
        <div class="sheet-modal-panel" role="dialog" aria-modal="true" aria-labelledby="requestsLegendSheetTitle">
            <div class="sheet-modal-handle" aria-hidden="true"></div>
            <div class="sheet-modal-head">
                <h2 id="requestsLegendSheetTitle">O que significa cada estado</h2>
                <button type="button" class="sheet-modal-close" data-sheet-close aria-label="Fechar">&times;</button>
            </div>
            <div class="sheet-modal-body requests-legend-sheet-body">
                <?php require __DIR__ . '/../../partials/requests_legend_items.php'; ?>
            </div>
        </div>
    </div>

    <?php if (($scope ?? 'user') === 'owner'): ?>
        <?php $currentView = $currentView !== '' ? $currentView : 'received'; ?>
        <div class="requests-scope-navigation requests-inbox-scope">
            <div class="requests-scope-pills">
                <a href="<?php echo DIRPAGE; ?>requests?view=received"
                   class="requests-scope-pill <?php echo $currentView === 'received' ? 'is-active' : ''; ?>"
                   aria-current="<?php echo $currentView === 'received' ? 'page' : 'false'; ?>">
                    <i class="fa fa-inbox" aria-hidden="true"></i>
                    <span>Recebidas</span>
                </a>
                <a href="<?php echo DIRPAGE; ?>requests?view=sent"
                   class="requests-scope-pill <?php echo $currentView === 'sent' ? 'is-active' : ''; ?>"
                   aria-current="<?php echo $currentView === 'sent' ? 'page' : 'false'; ?>">
                    <i class="fa fa-paper-plane" aria-hidden="true"></i>
                    <span>Enviadas</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="notification-inbox-panel requests-inbox-panel">
        <div class="requests-inbox-panel-toolbar">
            <div class="requests-inbox-toolbar-head">
                <h2 class="requests-inbox-panel-title"><?php echo htmlspecialchars($listHeading); ?></h2>
                <button type="button"
                        class="requests-inbox-filter-toggle"
                        id="requestsFilterToggle"
                        aria-expanded="false"
                        aria-controls="requestsFiltersPanel">
                    <i class="fa fa-filter" aria-hidden="true"></i>
                    <span>Filtros</span>
                    <span class="requests-inbox-filter-badge" id="requestsFilterBadge" hidden></span>
                </button>
            </div>
            <div class="requests-inbox-filters-panel" id="requestsFiltersPanel">
                <div class="filter-toolbar filter-toolbar-dashboard requests-filter-toolbar requests-inbox-filters" role="search">
                    <div class="filter-toolbar-inline-fields">
                        <div class="filter-toolbar-field">
                            <label class="filter-toolbar-field-label" for="statusFilter">Estado</label>
                            <select id="statusFilter" class="request-history-filter-select filter-toolbar-select">
                                <option value="all">Todos</option>
                                <option value="em_contacto">Em contacto</option>
                                <option value="fechado_ganho">Negócio fechado</option>
                                <option value="cancelado">Cancelado</option>
                                <option value="expirado">Expirado</option>
                                <option value="em_disputa">Em disputa</option>
                            </select>
                        </div>
                        <div class="filter-toolbar-field">
                            <label class="filter-toolbar-field-label" for="propertyStatusFilter">Imóvel</label>
                            <select id="propertyStatusFilter" class="request-history-filter-select filter-toolbar-select">
                                <option value="all">Todos</option>
                                <option value="disponivel">Disponível</option>
                                <option value="vendido">Vendido</option>
                                <option value="alugado">Alugado</option>
                                <option value="pendente">Pendente</option>
                                <option value="em_analise">Em análise</option>
                                <option value="rejeitado">Rejeitado</option>
                            </select>
                        </div>
                        <div class="filter-toolbar-field">
                            <label class="filter-toolbar-field-label" for="paymentStatusFilter">Pagamento</label>
                            <select id="paymentStatusFilter" class="request-history-filter-select filter-toolbar-select">
                                <option value="all">Todos</option>
                                <option value="pendente">Pendente</option>
                                <option value="declarado_comprador">Declarado pelo comprador</option>
                                <option value="confirmado_proprietario">Confirmado pelo proprietário</option>
                                <option value="contestado">Contestado</option>
                            </select>
                        </div>
                    </div>
                </div>
                <small id="requestFilterFeedback" class="request-filter-feedback requests-filter-feedback-below" aria-live="polite"></small>
            </div>
        </div>

        <?php if (empty($requestsList)): ?>
            <div class="notification-inbox-empty">
                <span class="notification-inbox-empty-icon" aria-hidden="true"><i class="fa fa-inbox"></i></span>
                <strong>Nenhuma solicitação encontrada</strong>
                <p>Quando houver contactos sobre imóveis, aparecem aqui com o estado e as ações disponíveis.</p>
            </div>
        <?php else: ?>
            <?php
                $feedGroups = $requestGroups;
                $feedItemPartial = 'request_feed_item.php';
                $feedItemVarName = 'request';
                $feedExtraClass = 'request-feed';
                require __DIR__ . '/../../partials/user_feed_groups.php';
            ?>

            <?php if ($totalPages > 1): ?>
                <?php
                    $prevUrl = $page > 1 ? requestsPageUrl($page - 1, $currentView, $actionFilter) : '';
                    $nextUrl = $page < $totalPages ? requestsPageUrl($page + 1, $currentView, $actionFilter) : '';
                    $pageCopy = 'Página ' . (int) $page . ' de ' . (int) $totalPages;
                    if ($requestsTotal > 0) {
                        $pageCopy .= ' · ' . count($requestsList) . ' de ' . (int) $requestsTotal;
                    }
                    require __DIR__ . '/../../partials/user_feed_pagination.php';
                ?>
            <?php elseif ($requestsTotal > 0): ?>
                <?php
                    $pageCopy = count($requestsList) . ' de ' . (int) $requestsTotal . ' solicitações';
                    require __DIR__ . '/../../partials/user_feed_pagination.php';
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
