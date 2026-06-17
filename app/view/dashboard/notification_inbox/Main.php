<?php

use Src\classes\FeedGrouping;

/** @var array<int, array<string, mixed>> $notifications */
$notifications = is_array($notifications ?? null) ? $notifications : [];
$page = (int) ($page ?? 1);
$limit = (int) ($limit ?? 15);
$totalPages = (int) ($totalPages ?? 1);
$total = (int) ($total ?? count($notifications));
$unread = (int) ($unread ?? 0);
$cursorMode = !empty($cursorMode) || !empty($data['cursorMode']);
$nextCursor = (string) ($nextCursor ?? ($data['nextCursor'] ?? ''));

$queryParams = $_GET;
unset($queryParams['filters_open']);
$buildPageUrl = static function (int $targetPage) use ($queryParams): string {
    $params = $queryParams;
    unset($params['cursor']);
    $params['page'] = $targetPage;
    return DIRPAGE . 'notification/inbox?' . http_build_query($params);
};
$buildCursorUrl = static function (string $cursorValue) use ($queryParams): string {
    $params = $queryParams;
    unset($params['page']);
    $params['cursor'] = $cursorValue;
    return DIRPAGE . 'notification/inbox?' . http_build_query($params);
};

$notificationGroups = FeedGrouping::byRecency($notifications);
?>

<?php
$dashboardPageClass = 'notification-inbox-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$inboxHeroTitle = 'Notificações';
ob_start();
if ($unread > 0): ?>
    <span class="notification-inbox-unread-pill"><?php echo (int) $unread; ?> não lidas</span>
<?php endif; ?>
<span><?php echo (int) $total; ?> no total</span>
<?php
$inboxHeroMetaHtml = ob_get_clean();
ob_start();
if ($unread > 0): ?>
    <form action="<?php echo DIRPAGE; ?>notification/mark_all_as_read" method="POST" class="notification-inbox-inline-form">
        <?php echo Src\classes\ClassCsrf::field(); ?>
        <button type="submit" class="notification-inbox-text-btn">Marcar todas como lidas</button>
    </form>
<?php endif; ?>
<a href="<?php echo DIRPAGE; ?>notification/archive" class="notification-inbox-text-btn notification-inbox-text-btn--muted">Arquivo</a>
<?php
$inboxHeroActionsHtml = ob_get_clean();
include DIRREQ . 'app/view/partials/dashboard_inbox_hero.php';
unset($inboxHeroMetaHtml, $inboxHeroActionsHtml);
?>

    <div class="notification-inbox-panel">
        <?php if (empty($notifications)): ?>
            <div class="notification-inbox-empty">
                <span class="notification-inbox-empty-icon" aria-hidden="true"><i class="fa fa-bell-slash"></i></span>
                <strong>Sem notificações</strong>
                <p>Quando houver novidades sobre solicitações, pagamentos ou a sua conta, aparecem aqui.</p>
            </div>
        <?php else: ?>
            <?php
                $feedGroups = $notificationGroups;
                $feedItemPartial = 'notification_feed_item.php';
                $feedItemVarName = 'notification';
                $feedItemContext = ['compact' => false, 'showMenu' => true];
                require __DIR__ . '/../../partials/user_feed_groups.php';
            ?>

            <?php if (!$cursorMode && $totalPages > 1): ?>
                <?php
                    $prevUrl = $page > 1 ? $buildPageUrl($page - 1) : '';
                    $nextUrl = $page < $totalPages ? $buildPageUrl($page + 1) : '';
                    $pageCopy = 'Página ' . (int) $page . ' de ' . (int) $totalPages;
                    require __DIR__ . '/../../partials/user_feed_pagination.php';
                ?>
            <?php endif; ?>

            <?php if ($cursorMode && $nextCursor !== ''): ?>
                <?php
                    $prevUrl = '';
                    $nextUrl = '';
                    $pageCopy = '';
                    $loadMoreUrl = $buildCursorUrl($nextCursor);
                    require __DIR__ . '/../../partials/user_feed_pagination.php';
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
