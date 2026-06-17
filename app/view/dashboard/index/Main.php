<?php
$isDashboardAdmin = Src\classes\ClassAccess::isAdmin($user ?? null);
$recentRequests = is_array($recentRequests ?? null) ? $recentRequests : [];
$hasActivity = !empty($notifications) || (!$isDashboardAdmin && !empty($recentRequests));

$overviewCards = [
    [
        'label' => 'Pedidos',
        'value' => count($requests),
        'tone' => 'blue',
        'icon' => 'fa-inbox',
        'meta' => $isDashboardAdmin ? 'Fluxo geral da plataforma' : 'Contactos sobre os seus imóveis',
        'href' => DIRPAGE . 'requests',
    ],
    [
        'label' => 'Por ler',
        'value' => (int) $unreadNotifications,
        'tone' => 'red',
        'icon' => 'fa-bell',
        'meta' => 'Alertas e atualizações recentes',
        'href' => DIRPAGE . 'notification/inbox',
    ],
];

if (!empty($user['is_affiliate'])) {
    $overviewCards[] = [
        'label' => 'Imóveis indicados',
        'value' => (int) ($stats['properties'] ?? 0),
        'tone' => 'green',
        'icon' => 'fa-link',
        'meta' => 'Anúncios com o seu link',
    ];
    $overviewCards[] = [
        'label' => 'Comissões',
        'value' => number_format((float) ($stats['commissions'] ?? 0), 0, ',', '.') . ' Kz',
        'tone' => 'yellow',
        'icon' => 'fa-money',
        'meta' => 'Total acumulado',
    ];
}

if (!empty($rejectedDocuments)) {
    $overviewCards[] = [
        'label' => 'Documentos',
        'value' => count($rejectedDocuments),
        'tone' => 'red',
        'icon' => 'fa-exclamation-triangle',
        'meta' => 'Precisam de novo envio',
    ];
}

$quickActions = [];
if (empty($user['is_admin'])) {
    $quickActions[] = ['href' => DIRPAGE . 'property/create', 'icon' => 'fa-plus-circle', 'title' => 'Novo imóvel', 'description' => 'Publique um anúncio e envie para aprovação.'];
}
$quickActions[] = ['href' => DIRPAGE . 'requests', 'icon' => 'fa-inbox', 'title' => 'Pedidos', 'description' => 'Veja negociações e estados.'];
$quickActions[] = ['href' => DIRPAGE . 'dashboard/requestChats', 'icon' => 'fa-comments', 'title' => 'Conversas', 'description' => 'Mensagens das suas negociações.'];

if (!empty($user['is_affiliate'])) {
    $quickActions[] = ['href' => DIRPAGE . 'dashboard/afiliados?tab=commissions', 'icon' => 'fa-money', 'title' => 'Comissões', 'description' => 'Valores pagos e por pagar.'];
    $quickActions[] = ['href' => DIRPAGE . 'referrals', 'icon' => 'fa-link', 'title' => 'Indicações', 'description' => 'Links e alcance dos seus anúncios.'];
}
if (Src\classes\ClassAccess::can('users.review', $user)) {
    $quickActions[] = ['href' => DIRPAGE . 'dashboard/moderate_users', 'icon' => 'fa-users', 'title' => 'Perfis', 'description' => 'Aprovações e confiança.'];
}
if (Src\classes\ClassAccess::can('properties.moderate', $user)) {
    $quickActions[] = ['href' => DIRPAGE . 'property/moderate', 'icon' => 'fa-building-o', 'title' => 'Imóveis', 'description' => 'Anúncios pendentes de revisão.'];
}
if (Src\classes\ClassAccess::can('documents.review', $user)) {
    $quickActions[] = ['href' => DIRPAGE . 'dashboard/reviewDocuments', 'icon' => 'fa-file-text-o', 'title' => 'Documentos', 'description' => 'Validar envios dos utilizadores.'];
}
if (Src\classes\ClassAccess::can('requests.manage', $user)) {
    $quickActions[] = ['href' => DIRPAGE . 'dashboard/disputes', 'icon' => 'fa-balance-scale', 'title' => 'Disputas', 'description' => 'Casos em análise.'];
}
if (Src\classes\ClassAccess::isSuperAdmin($user)) {
    $quickActions[] = ['href' => DIRPAGE . 'dashboard/kpi', 'icon' => 'fa-line-chart', 'title' => 'Indicadores', 'description' => 'Números da plataforma.'];
}
if (Src\classes\ClassAccess::can('payments.manage', $user)) {
    $quickActions[] = ['href' => DIRPAGE . 'dashboard/payments', 'icon' => 'fa-credit-card', 'title' => 'Pagamentos', 'description' => 'Confirmar repasses.'];
}
if (Src\classes\ClassAccess::can('audit.view', $user)) {
    $quickActions[] = ['href' => DIRPAGE . 'dashboard/auditLog', 'icon' => 'fa-shield', 'title' => 'Auditoria', 'description' => 'Registo de acções importantes.'];
}

?>

<?php
$dashboardPageBase = 'container dashboard-home dashboard-home-overview';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
?>
    <section class="dashboard-hero-panel dashboard-hero-panel--overview">
        <div class="dashboard-hero-copy">
            <span class="dashboard-hero-kicker">A sua conta</span>
            <h1>O seu painel</h1>
            <p>
                <?php if ($isDashboardAdmin): ?>
                    Resumo do dia: alertas, pedidos e atalhos de gestão.
                <?php else: ?>
                    Tudo o que precisa para acompanhar pedidos, alertas e a saúde da sua conta.
                <?php endif; ?>
            </p>

            <div class="dashboard-hero-tags">
                <?php if ((int) $unreadNotifications > 0): ?>
                    <span><i class="fa fa-bell"></i> <?php echo (int) $unreadNotifications; ?> alerta<?php echo (int) $unreadNotifications === 1 ? '' : 's'; ?> por ler</span>
                <?php endif; ?>
                <?php if (!$isDashboardAdmin): ?>
                    <span><i class="fa fa-check-circle"></i> <?php echo !empty($trust['verified']) ? 'Perfil verificado' : 'Verificação em curso'; ?></span>
                <?php endif; ?>
                <?php if (!empty($user['is_affiliate'])): ?>
                    <span><i class="fa fa-link"></i> Afiliado</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-hero-side">
            <div class="dashboard-hero-highlight">
                <div class="dashboard-hero-highlight-label">O que importa agora</div>
                <div class="dashboard-hero-highlight-value">
                    <?php if (!empty($rejectedDocuments)): ?>
                        <?php echo count($rejectedDocuments); ?> documento<?php echo count($rejectedDocuments) === 1 ? '' : 's'; ?>
                    <?php elseif ((int) $unreadNotifications > 0): ?>
                        <?php echo (int) $unreadNotifications; ?> alerta<?php echo (int) $unreadNotifications === 1 ? '' : 's'; ?>
                    <?php else: ?>
                        Em dia
                    <?php endif; ?>
                </div>
                <p>
                    <?php if (!empty($rejectedDocuments)): ?>
                        Há documentos que precisam de novo envio para desbloquear a conta.
                    <?php elseif ((int) $unreadNotifications > 0): ?>
                        Tem notificações por ler — vale a pena rever.
                    <?php else: ?>
                        Não há pendências urgentes neste momento.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </section>

    <section class="dashboard-overview-grid" aria-labelledby="dashboard-overview-title">
        <h2 class="dashboard-section-title" id="dashboard-overview-title"><?php echo $isDashboardAdmin ? 'Resumo' : 'A sua conta em números'; ?></h2>
        <?php foreach ($overviewCards as $card): ?>
            <?php $cardHref = (string) ($card['href'] ?? ''); ?>
            <?php if ($cardHref !== ''): ?>
                <a href="<?php echo htmlspecialchars($cardHref, ENT_QUOTES, 'UTF-8'); ?>" class="dashboard-overview-card dashboard-overview-card-link tone-<?php echo htmlspecialchars($card['tone']); ?>">
            <?php else: ?>
                <article class="dashboard-overview-card tone-<?php echo htmlspecialchars($card['tone']); ?>">
            <?php endif; ?>
                <div class="dashboard-overview-icon"><i class="fa <?php echo htmlspecialchars($card['icon']); ?>"></i></div>
                <div class="dashboard-overview-body">
                    <span><?php echo htmlspecialchars($card['label']); ?></span>
                    <strong><?php echo htmlspecialchars((string) $card['value']); ?></strong>
                    <small><?php echo htmlspecialchars($card['meta']); ?></small>
                </div>
            <?php echo $cardHref !== '' ? '</a>' : '</article>'; ?>
        <?php endforeach; ?>
    </section>

    <?php if (!empty($rejectedDocuments)): ?>
    <section class="dashboard-home-grid dashboard-home-priority" id="rejected-documents">
        <div class="dashboard-alert-card dashboard-alert-card-spaced">
            <div class="dashboard-module-head">
                <div>
                    <span class="dashboard-module-kicker">Precisa da sua atenção</span>
                    <h3><i class="fa fa-exclamation-triangle"></i> Documentos recusados</h3>
                </div>
                <span class="dashboard-alert-count"><?php echo count($rejectedDocuments); ?></span>
            </div>

            <div class="dashboard-alert-list">
                <?php foreach ($rejectedDocuments as $doc): ?>
                    <div class="dashboard-alert-item">
                        <div class="dashboard-alert-copy">
                            <strong><?php echo htmlspecialchars(str_replace('_', ' ', $doc['type'])); ?> (<?php echo htmlspecialchars($doc['version']); ?>)</strong>
                            <p>Recusado em <?php echo date('d/m/Y H:i', strtotime($doc['reviewed_at'])); ?></p>
                            <?php if (!empty($doc['rejection_reason'])): ?>
                                <div class="dashboard-alert-reason"><?php echo htmlspecialchars($doc['rejection_reason']); ?></div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-primary" data-doc-modal-open="resubmitModal<?php echo (int) $doc['id']; ?>">
                            Enviar de novo
                        </button>
                    </div>
                    <?php require __DIR__ . '/../../partials/doc_resubmit_sheet.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="dashboard-activity-feed-section" aria-labelledby="dashboard-activity-title">
        <div class="notification-inbox-panel dashboard-activity-feed-panel">
            <div class="dashboard-module-head compact dashboard-activity-feed-head">
                <div>
                    <span class="dashboard-module-kicker">Recente</span>
                    <h2 class="dashboard-section-title dashboard-section-title--inline" id="dashboard-activity-title">Última actividade</h2>
                </div>
                <div class="dashboard-activity-feed-links">
                    <a href="<?php echo DIRPAGE; ?>notification/inbox" class="notification-inbox-text-btn">Notificações</a>
                    <?php if (!$isDashboardAdmin): ?>
                        <a href="<?php echo DIRPAGE; ?>requests" class="notification-inbox-text-btn notification-inbox-text-btn--muted">Pedidos</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$hasActivity): ?>
                <div class="notification-inbox-empty dashboard-activity-empty">
                    <span class="notification-inbox-empty-icon" aria-hidden="true"><i class="fa fa-leaf"></i></span>
                    <strong>Sem novidades por agora</strong>
                    <p>Quando houver pedidos ou alertas, aparecem aqui.</p>
                </div>
            <?php else: ?>
                <?php if (!empty($notifications)): ?>
                    <div class="dashboard-activity-block">
                        <?php if (!$isDashboardAdmin && !empty($recentRequests)): ?>
                            <h3 class="dashboard-activity-block-title">Notificações</h3>
                        <?php endif; ?>
                        <div class="notification-feed notification-feed--inbox notification-feed--compact-dashboard">
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                    $compact = true;
                                    $showMenu = false;
                                    require __DIR__ . '/../../partials/notification_feed_item.php';
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$isDashboardAdmin && !empty($recentRequests)): ?>
                    <div class="dashboard-activity-block">
                        <h3 class="dashboard-activity-block-title">Pedidos recentes</h3>
                        <div class="notification-feed notification-feed--inbox notification-feed--compact-dashboard dashboard-request-preview-feed">
                            <?php foreach ($recentRequests as $request): ?>
                                <?php require __DIR__ . '/../../partials/dashboard_request_preview_item.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="dashboard-quick-actions-section" aria-labelledby="dashboard-quick-actions-title">
        <div class="dashboard-module-card dashboard-quick-actions-panel">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Atalhos</span>
                    <h2 class="dashboard-section-title dashboard-section-title--inline" id="dashboard-quick-actions-title">Ir para</h2>
                </div>
            </div>
            <div class="dashboard-action-grid">
                <?php foreach ($quickActions as $action): ?>
                    <a href="<?php echo htmlspecialchars((string) ($action['href'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="dashboard-action-card">
                        <div class="dashboard-action-icon"><i class="fa <?php echo htmlspecialchars($action['icon']); ?>"></i></div>
                        <div>
                            <strong><?php echo htmlspecialchars($action['title']); ?></strong>
                            <p><?php echo htmlspecialchars($action['description']); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
