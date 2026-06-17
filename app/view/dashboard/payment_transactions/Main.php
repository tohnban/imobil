<?php
$filterStatus = (string) ($filterStatus ?? '');
$filterType = (string) ($filterType ?? '');
$summary = is_array($summary ?? null) ? $summary : [];
$page = max(1, (int) ($page ?? 1));
$total = max(0, (int) ($total ?? 0));
$perPage = max(1, (int) ($perPage ?? 50));
$totalPages = max(1, (int) ($totalPages ?? 1));
$rangeStart = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$rangeEnd = $total > 0 ? min($page * $perPage, $total) : 0;

$exportQuery = http_build_query([
    'status' => $filterStatus,
    'type' => $filterType,
]);

$typeLabels = [
    'commission_payout' => 'Comissão',
    'boost_fee' => 'Destaque',
    'trust_badge_fee' => 'Selo',
    'manual_adjustment' => 'Ajuste',
    'subscription_fee' => 'Subscrição',
    'system_commission' => 'Taxa sistema',
    'commission_owner_payment' => 'Comissão (proprietário)',
];

$typePillClass = [
    'commission_payout' => 'ptx-type-commission',
    'boost_fee' => 'ptx-type-boost',
    'trust_badge_fee' => 'ptx-type-trust',
    'manual_adjustment' => 'ptx-type-adjust',
    'subscription_fee' => 'ptx-type-subscription',
];

function ptxStatusChipClass(string $status): string {
    return match ($status) {
        'confirmado' => 'dashboard-chip-success',
        'cancelado', 'rejeitado' => 'dashboard-chip-neutral',
        'falhado' => 'dashboard-chip-danger',
        default => 'dashboard-chip-warning',
    };
}

function ptxPaginationUrl(int $targetPage, string $status, string $type): string {
    $params = ['page' => $targetPage];
    if ($status !== '') {
        $params['status'] = $status;
    }
    if ($type !== '') {
        $params['type'] = $type;
    }
    return '?' . http_build_query($params);
}
?>

<?php
$dashboardPageClass = 'payments-admin-view payment-transactions-admin-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Financeiro';
$heroTitle = 'Transações de Pagamento';
$heroLead = 'Confirme, cancele e exporte o livro de transações de comissões, destaques, selos e subscrições.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-overview-grid dashboard-overview-grid-tight dashboard-kpi-section ptx-kpi-section">
        <div class="kpi-card kpi-yellow">
            <div class="kpi-label">Aguardam validação</div>
            <div class="kpi-value"><?php echo (int) ($summary['pending_count'] ?? 0); ?></div>
        </div>
        <div class="kpi-card kpi-yellow">
            <div class="kpi-label">Valor pendente</div>
            <div class="kpi-value kpi-value-prominent"><?php echo number_format((float) ($summary['pending_amount'] ?? 0), 0, ',', '.'); ?> Kz</div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-label">Confirmadas</div>
            <div class="kpi-value"><?php echo (int) ($summary['confirmed_count'] ?? 0); ?></div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Confirmado (30 dias)</div>
            <div class="kpi-value kpi-value-prominent"><?php echo number_format((float) ($summary['confirmed_amount_30d'] ?? 0), 0, ',', '.'); ?> Kz</div>
        </div>
    </div>

    <div class="dashboard-module-card ptx-module-card">
        <div class="dashboard-module-head compact ptx-module-head">
            <div>
                <span class="dashboard-module-kicker">Livro de transações</span>
                <h3>
                    <?php if ($filterStatus !== '' || $filterType !== ''): ?>
                        Resultados filtrados
                    <?php else: ?>
                        Todas as transações
                    <?php endif; ?>
                </h3>
                <?php if ($total > 0): ?>
                    <p class="ptx-results-meta">
                        A mostrar <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $total; ?>
                        <?php if ((int) ($summary['total_count'] ?? 0) !== $total): ?>
                            <span class="ptx-results-meta-muted">(<?php echo (int) $summary['total_count']; ?> no total)</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="ptx-module-head-actions">
                <a href="<?php echo DIRPAGE; ?>payment_transactions/exportTransactionsPdf?<?php echo htmlspecialchars($exportQuery); ?>" class="dashboard-btn dashboard-btn-small dashboard-btn-primary">
                    <i class="fa fa-file-pdf-o" aria-hidden="true"></i> PDF
                </a>
                <a href="<?php echo DIRPAGE; ?>payment_transactions/exportTransactionsCsv?<?php echo htmlspecialchars($exportQuery); ?>" class="dashboard-btn dashboard-btn-small dashboard-btn-primary">
                    <i class="fa fa-download" aria-hidden="true"></i> CSV
                </a>
            </div>
        </div>

        <form method="GET" action="<?php echo DIRPAGE; ?>payment_transactions" class="dashboard-form ptx-filters filter-toolbar-form">
            <div class="filter-toolbar filter-toolbar-sticky filter-toolbar-dashboard ptx-filter-toolbar">
                <div class="filter-toolbar-inline-fields">
                    <div class="filter-toolbar-field">
                        <label class="filter-toolbar-field-label" for="ptx-status">Estado</label>
                        <select name="status" id="ptx-status" class="filter-toolbar-select">
                            <option value="">Todos</option>
                            <option value="pendente" <?php echo $filterStatus === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="processando" <?php echo $filterStatus === 'processando' ? 'selected' : ''; ?>>Processando</option>
                            <option value="confirmado" <?php echo $filterStatus === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                            <option value="rejeitado" <?php echo $filterStatus === 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
                            <option value="cancelado" <?php echo $filterStatus === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            <option value="falhado" <?php echo $filterStatus === 'falhado' ? 'selected' : ''; ?>>Falhado</option>
                        </select>
                    </div>
                    <div class="filter-toolbar-field">
                        <label class="filter-toolbar-field-label" for="ptx-type">Tipo</label>
                        <select name="type" id="ptx-type" class="filter-toolbar-select">
                            <option value="">Todos</option>
                            <option value="commission_payout" <?php echo $filterType === 'commission_payout' ? 'selected' : ''; ?>>Comissão</option>
                            <option value="boost_fee" <?php echo $filterType === 'boost_fee' ? 'selected' : ''; ?>>Destaque</option>
                            <option value="trust_badge_fee" <?php echo $filterType === 'trust_badge_fee' ? 'selected' : ''; ?>>Selo</option>
                            <option value="subscription_fee" <?php echo $filterType === 'subscription_fee' ? 'selected' : ''; ?>>Subscrição</option>
                            <option value="manual_adjustment" <?php echo $filterType === 'manual_adjustment' ? 'selected' : ''; ?>>Ajuste manual</option>
                        </select>
                    </div>
                </div>
                <div class="filter-toolbar-actions">
                    <button type="submit" class="btn-primary filter-toolbar-submit dashboard-btn">Filtrar</button>
                    <a href="<?php echo DIRPAGE; ?>payment_transactions" class="btn-secondary dashboard-btn">Limpar</a>
                </div>
            </div>
        </form>

        <?php if (!empty($transactions)): ?>
            <div class="dashboard-table-wrap payment-transactions-table-wrap ptx-table-wrap">
                <table class="dashboard-table commissions-table payment-transactions-table ptx-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Utilizador</th>
                            <th>Montante</th>
                            <th>Método</th>
                            <th>Referência</th>
                            <th>Estado</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                                $txId = (int) ($tx['id'] ?? 0);
                                $txType = (string) ($tx['transaction_type'] ?? '');
                                $txStatus = (string) ($tx['status'] ?? '');
                                $typeLabel = $typeLabels[$txType] ?? 'Outro';
                                $pillClass = $typePillClass[$txType] ?? 'ptx-type-default';
                                $isActionable = in_array($txStatus, ['pendente', 'processando'], true);
                                $statusLabel = ucfirst($txStatus);
                            ?>
                            <tr class="payment-transactions-row ptx-row <?php echo $isActionable ? 'ptx-row-pending' : ''; ?>" data-ptx-id="<?php echo $txId; ?>">
                                <td class="payment-transactions-id ptx-cell-id" data-label="ID">#<?php echo $txId; ?></td>
                                <td data-label="Tipo">
                                    <span class="payment-transactions-type-pill ptx-type-pill <?php echo htmlspecialchars($pillClass); ?>">
                                        <?php echo htmlspecialchars($typeLabel); ?>
                                    </span>
                                </td>
                                <td class="ptx-cell-user" data-label="Utilizador">
                                    <?php if (!empty($tx['counterparty_name'])): ?>
                                        <?php if (!empty($tx['counterparty_user_id'])): ?>
                                            <strong class="payment-transactions-user-name">
                                                <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) $tx['counterparty_user_id']; ?>" class="table-name-link">
                                                    <?php echo htmlspecialchars((string) $tx['counterparty_name']); ?>
                                                </a>
                                            </strong>
                                        <?php else: ?>
                                            <strong class="payment-transactions-user-name"><?php echo htmlspecialchars((string) $tx['counterparty_name']); ?></strong>
                                        <?php endif; ?>
                                        <?php if (!empty($tx['counterparty_email'])): ?>
                                            <span class="payment-transactions-user-email"><?php echo htmlspecialchars((string) $tx['counterparty_email']); ?></span>
                                        <?php endif; ?>
                                    <?php elseif (!empty($tx['counterparty_user_id'])): ?>
                                        <span class="dashboard-inline-note">#<?php echo (int) $tx['counterparty_user_id']; ?></span>
                                    <?php else: ?>
                                        <span class="dashboard-inline-note">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="dashboard-value ptx-cell-amount" data-label="Montante">
                                    <strong><?php echo number_format((float) ($tx['amount'] ?? 0), 2, ',', '.'); ?></strong>
                                    <span class="ptx-currency"><?php echo htmlspecialchars((string) ($tx['currency'] ?? 'AOA')); ?></span>
                                </td>
                                <td class="dashboard-inline-note" data-label="Método"><?php echo htmlspecialchars((string) ($tx['method_name'] ?? 'N/A')); ?></td>
                                <td class="dashboard-inline-note ptx-cell-ref" data-label="Referência"><?php echo htmlspecialchars((string) ($tx['reference_code'] ?? '—')); ?></td>
                                <td data-label="Estado">
                                    <span class="dashboard-chip <?php echo ptxStatusChipClass($txStatus); ?>">
                                        <?php echo htmlspecialchars($statusLabel); ?>
                                    </span>
                                </td>
                                <td class="dashboard-cell-nowrap" data-label="Data">
                                    <?php echo !empty($tx['created_at']) ? date('d/m/Y H:i', strtotime((string) $tx['created_at'])) : '—'; ?>
                                </td>
                                <td class="ptx-cell-actions" data-label="Ações">
                                    <?php if ($isActionable): ?>
                                        <div class="request-actions ptx-row-actions">
                                            <button type="button" class="btn-primary dashboard-btn-small" data-doc-modal-open="confirmPtxModal<?php echo $txId; ?>">
                                                Confirmar
                                            </button>
                                            <button type="button" class="btn-secondary dashboard-btn-small" data-doc-modal-open="cancelPtxModal<?php echo $txId; ?>">
                                                Cancelar
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="dashboard-inline-note ptx-no-actions">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php if ($isActionable): ?>
                            <div class="doc-modal" id="confirmPtxModal<?php echo $txId; ?>" hidden>
                                <div class="doc-modal-panel" role="dialog" aria-modal="true" aria-labelledby="confirmPtxTitle<?php echo $txId; ?>">
                                    <div class="doc-modal-head">
                                        <h5 id="confirmPtxTitle<?php echo $txId; ?>">Confirmar transação #<?php echo $txId; ?></h5>
                                        <button type="button" class="doc-modal-close" data-doc-modal-close aria-label="Fechar">&times;</button>
                                    </div>
                                    <form method="POST" action="<?php echo DIRPAGE; ?>payment_transactions/confirmTransaction/<?php echo $txId; ?>">
                                        <div class="doc-modal-body">
                                            <div class="ptx-modal-summary">
                                                <span class="ptx-modal-summary-label">Utilizador</span>
                                                <strong><?php echo htmlspecialchars((string) ($tx['counterparty_name'] ?? '#' . (int) ($tx['counterparty_user_id'] ?? 0))); ?></strong>
                                                <?php if (!empty($tx['counterparty_email'])): ?>
                                                    <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) $tx['counterparty_email']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ptx-modal-summary">
                                                <span class="ptx-modal-summary-label">Montante</span>
                                                <strong><?php echo number_format((float) ($tx['amount'] ?? 0), 2, ',', '.'); ?> <?php echo htmlspecialchars((string) ($tx['currency'] ?? 'AOA')); ?></strong>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="ref-<?php echo $txId; ?>">Referência *</label>
                                                <input type="text" name="reference_code" id="ref-<?php echo $txId; ?>" class="form-control" placeholder="Código de referência bancária" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="notes-confirm-<?php echo $txId; ?>">Observação *</label>
                                                <textarea class="form-control" name="notes" id="notes-confirm-<?php echo $txId; ?>" rows="3" required placeholder="Detalhe da confirmação..."></textarea>
                                            </div>
                                        </div>
                                        <div class="doc-modal-foot">
                                            <button type="button" class="btn-secondary" data-doc-modal-close>Voltar</button>
                                            <?php echo $csrfField; ?>
                                            <button type="submit" class="btn-primary">Confirmar pagamento</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="doc-modal" id="cancelPtxModal<?php echo $txId; ?>" hidden>
                                <div class="doc-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cancelPtxTitle<?php echo $txId; ?>">
                                    <div class="doc-modal-head">
                                        <h5 id="cancelPtxTitle<?php echo $txId; ?>">Cancelar transação #<?php echo $txId; ?></h5>
                                        <button type="button" class="doc-modal-close" data-doc-modal-close aria-label="Fechar">&times;</button>
                                    </div>
                                    <form method="POST" action="<?php echo DIRPAGE; ?>payment_transactions/cancelTransaction/<?php echo $txId; ?>">
                                        <div class="doc-modal-body">
                                            <p class="dashboard-inline-note">O cancelamento fica registado no histórico e a transação deixa de estar pendente.</p>
                                            <div class="mb-3">
                                                <label class="form-label" for="notes-cancel-<?php echo $txId; ?>">Motivo do cancelamento *</label>
                                                <textarea class="form-control" name="notes" id="notes-cancel-<?php echo $txId; ?>" rows="4" required placeholder="Descreva o motivo..."></textarea>
                                            </div>
                                        </div>
                                        <div class="doc-modal-foot">
                                            <button type="button" class="btn-secondary" data-doc-modal-close>Voltar</button>
                                            <?php echo $csrfField; ?>
                                            <button type="submit" class="dashboard-btn dashboard-btn-danger" data-confirm="Cancelar esta transação?">Cancelar transação</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="dashboard-pagination ptx-pagination" aria-label="Paginação de transações">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo htmlspecialchars(ptxPaginationUrl($page - 1, $filterStatus, $filterType)); ?>" class="ptx-pagination-nav" aria-label="Página anterior">&lsaquo;</a>
                    <?php endif; ?>

                    <?php
                        $window = 2;
                        $start = max(1, $page - $window);
                        $end = min($totalPages, $page + $window);
                        if ($start > 1) {
                            echo '<a href="' . htmlspecialchars(ptxPaginationUrl(1, $filterStatus, $filterType)) . '">1</a>';
                            if ($start > 2) {
                                echo '<span class="ptx-pagination-ellipsis" aria-hidden="true">…</span>';
                            }
                        }
                        for ($p = $start; $p <= $end; $p++) {
                            $active = $p === $page ? ' active' : '';
                            echo '<a href="' . htmlspecialchars(ptxPaginationUrl($p, $filterStatus, $filterType)) . '" class="' . trim($active) . '"' . ($p === $page ? ' aria-current="page"' : '') . '>' . $p . '</a>';
                        }
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<span class="ptx-pagination-ellipsis" aria-hidden="true">…</span>';
                            }
                            echo '<a href="' . htmlspecialchars(ptxPaginationUrl($totalPages, $filterStatus, $filterType)) . '">' . $totalPages . '</a>';
                        }
                    ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo htmlspecialchars(ptxPaginationUrl($page + 1, $filterStatus, $filterType)); ?>" class="ptx-pagination-nav" aria-label="Página seguinte">&rsaquo;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state ptx-empty-state">
                <div class="empty-state-content">
                    <i class="fa fa-exchange" aria-hidden="true"></i>
                    <p>Nenhuma transação encontrada<?php echo ($filterStatus !== '' || $filterType !== '') ? ' para os filtros selecionados' : ''; ?>.</p>
                    <?php if ($filterStatus !== '' || $filterType !== ''): ?>
                        <a href="<?php echo DIRPAGE; ?>payment_transactions" class="dashboard-btn dashboard-btn-small btn-secondary">Limpar filtros</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
