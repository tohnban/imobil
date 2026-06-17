<?php
/** @var array $commissions */
/** @var string $affiliateCommissionEmptyMessage */
/** @var bool $affiliateCommissionResponsive */
$commissions = is_array($commissions ?? null) ? $commissions : [];
$affiliateCommissionEmptyMessage = isset($affiliateCommissionEmptyMessage)
    ? (string) $affiliateCommissionEmptyMessage
    : 'Nenhuma comissão encontrada.';
$affiliateCommissionResponsive = !isset($affiliateCommissionResponsive) || (bool) $affiliateCommissionResponsive;
$tableWrapClass = isset($affiliateCommissionTableWrapClass)
    ? trim((string) $affiliateCommissionTableWrapClass)
    : ($affiliateCommissionResponsive ? 'dashboard-table-wrap afiliados-table-wrap' : 'dashboard-table-wrap');
$tableClass = isset($affiliateCommissionTableClass)
    ? trim((string) $affiliateCommissionTableClass)
    : ($affiliateCommissionResponsive ? 'commissions-table afiliados-table' : 'commissions-table');
$rowClass = $affiliateCommissionResponsive ? 'afiliados-row' : '';
$emptyRowClass = $affiliateCommissionResponsive ? 'afiliados-empty-row' : '';
?>
<div class="dashboard-module-card">
    <div class="dashboard-module-head compact">
        <div>
            <span class="dashboard-module-kicker">Histórico</span>
            <h3>Lançamentos de Comissão</h3>
        </div>
    </div>

    <div class="<?php echo htmlspecialchars($tableWrapClass, ENT_QUOTES, 'UTF-8'); ?>">
    <table class="<?php echo htmlspecialchars($tableClass, ENT_QUOTES, 'UTF-8'); ?>">
        <thead>
            <tr>
                <th>Imóvel</th>
                <th>Meu valor</th>
                <th>Sistema</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Referência</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($commissions)): ?>
                <?php foreach ($commissions as $commission): ?>
                    <tr class="<?php echo htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8'); ?>"<?php echo !$affiliateCommissionResponsive ? ' data-focus-commission-id="' . (int) ($commission['id'] ?? 0) . '"' : ''; ?>>
                        <td<?php echo $affiliateCommissionResponsive ? ' data-label="Imóvel"' : ''; ?>><?php echo htmlspecialchars((string) ($commission['title'] ?? '')); ?></td>
                        <td<?php echo $affiliateCommissionResponsive ? ' data-label="Meu valor"' : ''; ?>>
                            <?php echo number_format((float) ($commission['affiliate_amount'] ?? 0), 0, ',', '.'); ?> Kz
                            <span class="dashboard-inline-note">(<?php echo number_format((float) ($commission['affiliate_pct'] ?? 0), 2, ',', '.'); ?>%)</span>
                        </td>
                        <td<?php echo $affiliateCommissionResponsive ? ' data-label="Sistema"' : ''; ?>><?php echo number_format((float) ($commission['system_amount'] ?? 0), 0, ',', '.'); ?> Kz</td>
                        <td<?php echo $affiliateCommissionResponsive ? ' data-label="Total"' : ''; ?>><?php echo number_format((float) ($commission['amount'] ?? 0), 0, ',', '.'); ?> Kz</td>
                        <td<?php echo $affiliateCommissionResponsive ? ' data-label="Estado"' : ''; ?>>
                            <?php
                                $affiliateSt = App\model\Commission::affiliateDisplayStatus($commission);
                                $stLabel = App\model\Commission::affiliateDisplayStatusLabel($affiliateSt);
                                $stKey = in_array($affiliateSt, ['pago', 'pendente', 'aguardando_pagamento', 'cancelado'], true)
                                    ? ($affiliateSt === 'aguardando_pagamento' ? 'em_analise' : $affiliateSt)
                                    : 'pendente';
                            ?>
                            <span class="commission-status-badge commission-status-<?php echo htmlspecialchars($stKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td class="dashboard-inline-note"<?php echo $affiliateCommissionResponsive ? ' data-label="Referência"' : ''; ?>><?php echo htmlspecialchars((string) ($commission['payment_reference'] ?? '–')); ?></td>
                        <td class="dashboard-cell-nowrap"<?php echo $affiliateCommissionResponsive ? ' data-label="Data"' : ''; ?>><?php echo !empty($commission['created_at']) ? date('d/m/Y', strtotime((string) $commission['created_at'])) : '–'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="<?php echo htmlspecialchars($emptyRowClass, ENT_QUOTES, 'UTF-8'); ?>">
                    <td colspan="7"<?php echo !$affiliateCommissionResponsive ? ' class="empty-state"' : ''; ?>>
                        <div class="empty-state-content">
                            <i class="fa fa-money"></i>
                            <p><?php echo htmlspecialchars($affiliateCommissionEmptyMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php include __DIR__ . '/dashboard_pagination.php'; ?>
</div>
