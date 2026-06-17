<?php

use Src\classes\FeedGrouping;

    $requests = is_array($requests ?? []) ? $requests : [];
    $requestChatSummaries = is_array($requestChatSummaries ?? []) ? $requestChatSummaries : [];
    $requestView = (string) ($requestView ?? '');
    $scope = (string) ($scope ?? 'user');
    $canManageAllRequests = !empty($canManageAllRequests);
    $requestCount = count($requests);
    $selectedRequestId = (int) ($selectedRequestId ?? 0);
    $selectedRequest = is_array($selectedRequest ?? null) ? $selectedRequest : null;
    $hasChatSelected = $selectedRequestId > 0 && $selectedRequest !== null;

    if (!function_exists('requestChatsPageUrl')) {
        function requestChatsPageUrl(int $chatId = 0, string $view = '', array $extra = []): string {
            $params = $extra;
            if ($view !== '') {
                $params['view'] = $view;
            }
            if ($chatId > 0) {
                $params['chat'] = $chatId;
            }
            $query = http_build_query($params);

            return DIRPAGE . 'dashboard/requestChats' . ($query !== '' ? ('?' . $query) : '');
        }
    }

    $listBackUrl = requestChatsPageUrl(0, $requestView);

    $unreadTotal = 0;
    foreach ($requestChatSummaries as $summaryRow) {
        $unreadTotal += (int) ($summaryRow['unread_count'] ?? 0);
    }

    usort($requests, static function (array $a, array $b) use ($requestChatSummaries): int {
        $aId = (int) ($a['id'] ?? 0);
        $bId = (int) ($b['id'] ?? 0);
        $aSummary = $requestChatSummaries[$aId] ?? [];
        $bSummary = $requestChatSummaries[$bId] ?? [];
        $aUnread = (int) ($aSummary['unread_count'] ?? 0);
        $bUnread = (int) ($bSummary['unread_count'] ?? 0);
        if ($aUnread !== $bUnread) {
            return $bUnread <=> $aUnread;
        }
        $aTime = strtotime((string) ($aSummary['last_message_at'] ?? $a['created_at'] ?? '')) ?: 0;
        $bTime = strtotime((string) ($bSummary['last_message_at'] ?? $b['created_at'] ?? '')) ?: 0;

        return $bTime <=> $aTime;
    });

    $chatGroups = FeedGrouping::byRecencyWithUnreadBucket(
        $requests,
        static function (array $item) use ($requestChatSummaries): bool {
            $requestId = (int) ($item['id'] ?? 0);

            return (int) (($requestChatSummaries[$requestId] ?? [])['unread_count'] ?? 0) > 0;
        },
        static function (array $item) use ($requestChatSummaries): int|false {
            $requestId = (int) ($item['id'] ?? 0);
            $summary = $requestChatSummaries[$requestId] ?? [];
            $timestamp = strtotime((string) ($summary['last_message_at'] ?? $item['created_at'] ?? ''));

            return $timestamp === false ? false : $timestamp;
        }
    );
?>

<?php
$dashboardPageClass = 'notification-inbox-view requests-inbox-view request-chats-inbox-view request-chats-dashboard-view' . ($hasChatSelected ? ' has-chat-selected' : '');
$dashboardPageAttrs = sprintf(
    'data-chat-mark-read-url="%s" data-chat-mark-unread-url="%s" data-chat-summaries-feed-url="%s"',
    htmlspecialchars(DIRPAGE . 'dashboard/requestChatMarkRead/', ENT_QUOTES, 'UTF-8'),
    htmlspecialchars(DIRPAGE . 'dashboard/requestChatMarkUnread/', ENT_QUOTES, 'UTF-8'),
    htmlspecialchars(DIRPAGE . 'dashboard/requestChatSummariesFeed?view=' . urlencode($requestView), ENT_QUOTES, 'UTF-8')
);
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$inboxHeroTitle = (string) ($pageTitle ?? 'Conversas de Negociação');
$inboxHeroClass = 'request-chats-inbox-hero' . ($hasChatSelected ? ' is-chat-open' : '');
ob_start();
?>
<span><?php echo (int) $requestCount; ?> negociação<?php echo $requestCount === 1 ? '' : 'ões'; ?></span>
<?php if ($unreadTotal > 0): ?>
    <span class="notification-feed-dot" aria-hidden="true">·</span>
    <span class="notification-inbox-unread-pill"><?php echo (int) $unreadTotal; ?> não lidas</span>
<?php endif;
$inboxHeroMetaHtml = ob_get_clean();
ob_start();
?>
<a href="<?php echo DIRPAGE; ?>requests<?php echo $requestView !== '' ? ('?view=' . urlencode($requestView)) : ''; ?>" class="notification-inbox-text-btn">Solicitações</a>
<?php
$inboxHeroActionsHtml = ob_get_clean();
include DIRREQ . 'app/view/partials/dashboard_inbox_hero.php';
unset($inboxHeroMetaHtml, $inboxHeroActionsHtml);
?>

    <?php if ($scope === 'owner'): ?>
        <?php $requestView = $requestView !== '' ? $requestView : 'received'; ?>
        <div class="requests-scope-navigation requests-inbox-scope request-chats-inbox-scope">
            <div class="requests-scope-pills">
                <a href="<?php echo requestChatsPageUrl(0, 'received'); ?>"
                   class="requests-scope-pill <?php echo $requestView === 'received' ? 'is-active' : ''; ?>"
                   aria-current="<?php echo $requestView === 'received' ? 'page' : 'false'; ?>">
                    <i class="fa fa-inbox" aria-hidden="true"></i>
                    <span>Recebidas</span>
                </a>
                <a href="<?php echo requestChatsPageUrl(0, 'sent'); ?>"
                   class="requests-scope-pill <?php echo $requestView === 'sent' ? 'is-active' : ''; ?>"
                   aria-current="<?php echo $requestView === 'sent' ? 'page' : 'false'; ?>">
                    <i class="fa fa-paper-plane" aria-hidden="true"></i>
                    <span>Enviadas</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="notification-inbox-panel request-chats-inbox-panel request-chats-shell <?php echo $hasChatSelected ? 'has-chat-selected' : ''; ?>">
        <div class="request-chats-layout">
            <aside class="request-chats-panel" aria-label="Lista de negociações">
                <?php if ($requestCount > 0): ?>
                    <div class="notification-feed notification-feed--inbox request-chats-feed" role="list">
                        <?php foreach ($chatGroups as $group): ?>
                            <section class="notification-feed-group">
                                <h2 class="notification-feed-group-title"><?php echo htmlspecialchars((string) $group['label']); ?></h2>
                                <div class="notification-feed-group-list">
                                    <?php foreach ($group['items'] as $request): ?>
                                        <?php
                                            $summary = $requestChatSummaries[(int) ($request['id'] ?? 0)] ?? [];
                                            require __DIR__ . '/../../partials/request_chat_feed_item.php';
                                        ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="notification-inbox-empty request-chats-panel-empty">
                        <span class="notification-inbox-empty-icon" aria-hidden="true"><i class="fa fa-comments"></i></span>
                        <strong>Sem conversas</strong>
                        <p>Não há negociações com chat disponível neste momento.</p>
                        <a href="<?php echo DIRPAGE; ?>requests" class="notification-inbox-page-btn">Ver solicitações</a>
                    </div>
                <?php endif; ?>
            </aside>

            <section class="request-chats-conversation" aria-label="Conversa selecionada">
                <?php if ($hasChatSelected): ?>
                    <?php
                        $request = $selectedRequest;
                        include DIRREQ . 'app/view/dashboard/request_chats/chat_panel.php';
                    ?>
                <?php else: ?>
                    <div class="request-chats-conversation-placeholder notification-inbox-empty">
                        <span class="notification-inbox-empty-icon" aria-hidden="true"><i class="fa fa-comments"></i></span>
                        <strong>Selecione uma negociação</strong>
                        <p>Escolha uma conversa na lista para ver mensagens e responder.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
