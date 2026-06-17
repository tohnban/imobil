<?php

use Src\classes\FeedGrouping;

$transactions = is_array($transactions ?? null) ? $transactions : [];
$filterStatus = (string) ($filterStatus ?? '');
$filterType = (string) ($filterType ?? '');
$exportQuery = http_build_query([
    'status' => $filterStatus,
    'type' => $filterType,
]);

$transactionGroups = FeedGrouping::byRecency($transactions, 'created_at');
$transactionTotal = count($transactions);
?>

<?php
$dashboardPageClass = 'notification-inbox-view payment-account-feed-view payment-history-dashboard-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$inboxHeroTitle = 'Movimentos da conta';
$inboxHeroMeta = (int) $transactionTotal . ' registo' . ($transactionTotal === 1 ? '' : 's');
ob_start();
?>
<a href="<?php echo DIRPAGE; ?>dashboard/exportPaymentHistoryPdf?<?php echo htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8'); ?>"
   class="notification-inbox-text-btn">Descarregar PDF</a>
<a href="<?php echo DIRPAGE; ?>dashboard/exportPaymentHistoryCsv?<?php echo htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8'); ?>"
   class="notification-inbox-text-btn notification-inbox-text-btn--muted">Descarregar CSV</a>
<?php
$inboxHeroActionsHtml = ob_get_clean();
include DIRREQ . 'app/view/partials/dashboard_inbox_hero.php';
unset($inboxHeroActionsHtml);
?>

    <div class="notification-inbox-panel payment-account-feed-panel">
        <form method="GET" action="<?php echo DIRPAGE; ?>dashboard/paymentHistory" class="payment-account-feed-filters filter-toolbar-form">
            <div class="filter-toolbar filter-toolbar-dashboard payment-account-feed-toolbar payment-account-feed-toolbar-padded">
                <div class="filter-toolbar-inline-fields">
                    <div class="filter-toolbar-field">
                        <label class="filter-toolbar-field-label" for="history-status">Estado</label>
                        <select name="status" id="history-status" class="filter-toolbar-select">
                            <option value="">Todos</option>
                            <option value="pendente" <?php echo $filterStatus === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="processando" <?php echo $filterStatus === 'processando' ? 'selected' : ''; ?>>Processando</option>
                            <option value="confirmado" <?php echo $filterStatus === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                            <option value="cancelado" <?php echo $filterStatus === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            <option value="falhado" <?php echo $filterStatus === 'falhado' ? 'selected' : ''; ?>>Falhado</option>
                            <option value="rejeitado" <?php echo $filterStatus === 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
                        </select>
                    </div>
                    <div class="filter-toolbar-field">
                        <label class="filter-toolbar-field-label" for="history-type">Tipo</label>
                        <select name="type" id="history-type" class="filter-toolbar-select">
                            <option value="">Todos</option>
                            <option value="commission_owner_payment" <?php echo $filterType === 'commission_owner_payment' ? 'selected' : ''; ?>>Comissão (proprietário)</option>
                            <option value="commission_payout" <?php echo $filterType === 'commission_payout' ? 'selected' : ''; ?>>Pagamento ao afiliado</option>
                            <option value="system_commission" <?php echo $filterType === 'system_commission' ? 'selected' : ''; ?>>Taxa do sistema</option>
                            <option value="boost_fee" <?php echo $filterType === 'boost_fee' ? 'selected' : ''; ?>>Destaque</option>
                            <option value="trust_badge_fee" <?php echo $filterType === 'trust_badge_fee' ? 'selected' : ''; ?>>Selo</option>
                            <option value="subscription_fee" <?php echo $filterType === 'subscription_fee' ? 'selected' : ''; ?>>Subscrição</option>
                            <option value="manual_adjustment" <?php echo $filterType === 'manual_adjustment' ? 'selected' : ''; ?>>Ajuste manual</option>
                        </select>
                    </div>
                </div>
                <div class="filter-toolbar-actions">
                    <button type="submit" class="btn-primary filter-toolbar-submit">Filtrar</button>
                    <a href="<?php echo DIRPAGE; ?>dashboard/paymentHistory" class="notification-inbox-text-btn notification-inbox-text-btn--muted">Limpar</a>
                </div>
            </div>
        </form>

        <?php
            $shellHasItems = !empty($transactions);
            $shellEmptyIcon = 'fa-credit-card';
            $shellEmptyTitle = 'Ainda não há movimentos';
            $shellEmptyMessage = empty($filterStatus) && empty($filterType)
                ? 'Quando fizer ou receber pagamentos pela plataforma, o histórico aparece aqui.'
                : 'Nenhum movimento corresponde aos filtros escolhidos. Tente outra combinação ou limpe os filtros.';
            $feedGroups = $transactionGroups;
            $shellFeedItemPartial = 'payment_transaction_feed_item.php';
            $shellFeedItemVarName = 'transaction';
            $shellFeedExtraClass = 'payment-account-feed';
            require __DIR__ . '/../../partials/user_feed_shell.php';
        ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
