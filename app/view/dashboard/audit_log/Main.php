<?php
$actionLabels = [
    'approve_user'                    => 'Aprovação de utilizador',
    'reject_user'                     => 'Rejeição de utilizador',
    'approve_trusted_badge'           => 'Aprovação de selo',
    'reject_trusted_badge'            => 'Rejeição de selo',
    'confirm_trusted_badge_payment'   => 'Pagamento de selo confirmado',
    'create_property'                 => 'Imóvel criado',
    'approve_property'                => 'Imóvel aprovado',
    'reject_property'                 => 'Imóvel rejeitado',
    'update_request_status'           => 'Status de solicitação actualizado',
    'create_request'                  => 'Solicitação criada',
];
$entityLabels = [
    'user'     => 'Utilizador',
    'property' => 'Imóvel',
    'request'  => 'Solicitação',
];

$logs = is_array($logs ?? null) ? $logs : [];
$total = (int) ($total ?? 0);
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$perPage = max(1, (int) ($perPage ?? 40));
$filterType = (string) ($filterType ?? '');
$filterId = (int) ($filterId ?? 0);
$isFiltered = $filterType !== '' && $filterId > 0;
$visibleCount = count($logs);
$rangeStart = $total > 0 && !$isFiltered ? (($page - 1) * $perPage) + 1 : ($visibleCount > 0 ? 1 : 0);
$rangeEnd = $isFiltered ? $visibleCount : min($total, $rangeStart + $visibleCount - 1);
?>

<?php
$dashboardPageClass = 'audit-log-dashboard-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Rastreabilidade';
$heroTitle = 'Registo de Auditoria';
$heroClass = 'audit-log-hero';
if ($isFiltered) {
    ob_start();
    ?>
    <p>
        Historial de
        <strong><?php echo htmlspecialchars($entityLabels[$filterType] ?? $filterType); ?> #<?php echo $filterId; ?></strong>
        — <?php echo $visibleCount; ?> entrada<?php echo $visibleCount === 1 ? '' : 's'; ?>.
    </p>
    <?php
    $heroLeadHtml = ob_get_clean();
    ob_start();
    ?>
    <div class="audit-log-hero-actions">
        <a href="<?php echo DIRPAGE; ?>dashboard/auditLog" class="btn-secondary">Ver todos os registos</a>
    </div>
    <?php
    $heroActionsHtml = ob_get_clean();
} else {
    $heroLead = number_format($total, 0, ',', '.') . ' entradas registadas no sistema.';
}
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-module-card audit-log-panel">
        <div class="dashboard-module-head compact audit-log-panel-head">
            <div>
                <span class="dashboard-module-kicker">Eventos</span>
                <h3>Histórico de acções</h3>
                <?php if (!empty($logs)): ?>
                    <p class="dashboard-inline-note audit-log-panel-meta">
                        <?php if ($isFiltered): ?>
                            A mostrar <?php echo $visibleCount; ?> registo(s) desta entidade.
                        <?php else: ?>
                            A mostrar <?php echo number_format($rangeStart, 0, ',', '.'); ?>–<?php echo number_format($rangeEnd, 0, ',', '.'); ?>
                            de <?php echo number_format($total, 0, ',', '.'); ?>.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($logs)): ?>
            <div class="dashboard-table-wrap audit-log-table-wrap">
                <table class="commissions-table audit-log-table">
                    <colgroup>
                        <col class="audit-log-col audit-log-col-date">
                        <col class="audit-log-col audit-log-col-actor">
                        <col class="audit-log-col audit-log-col-action">
                        <col class="audit-log-col audit-log-col-entity">
                        <col class="audit-log-col audit-log-col-details">
                        <col class="audit-log-col audit-log-col-ip">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Data/Hora</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Acção</th>
                            <th scope="col">Entidade</th>
                            <th scope="col">Detalhe</th>
                            <th scope="col">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $detailsText = trim((string) ($log['details'] ?? ''));
                            ?>
                            <tr class="audit-log-row">
                                <td class="dashboard-inline-note audit-log-cell-date" data-label="Data/Hora">
                                    <?php if (!empty($log['created_at'])): ?>
                                        <time datetime="<?php echo htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8'); ?>" class="audit-log-datetime">
                                            <span class="audit-log-date-part"><?php echo date('d/m/Y', strtotime($log['created_at'])); ?></span>
                                            <span class="audit-log-time-part"><?php echo date('H:i', strtotime($log['created_at'])); ?></span>
                                        </time>
                                    <?php else: ?>
                                        –
                                    <?php endif; ?>
                                </td>
                                <td class="audit-log-cell-actor" data-label="Actor">
                                    <?php echo htmlspecialchars($log['actor_name'] ?? '–'); ?>
                                </td>
                                <td class="audit-log-cell-action" data-label="Acção">
                                    <span class="audit-action-badge">
                                        <?php echo htmlspecialchars($actionLabels[$log['action'] ?? ''] ?? ($log['action'] ?? '–')); ?>
                                    </span>
                                </td>
                                <td class="dashboard-cell-nowrap audit-log-cell-entity" data-label="Entidade">
                                    <?php if (!empty($log['entity_type'])): ?>
                                        <span class="audit-entity-type"><?php echo htmlspecialchars($entityLabels[$log['entity_type']] ?? $log['entity_type']); ?></span>
                                        <?php if (!empty($log['entity_id'])): ?>
                                            <a href="<?php echo DIRPAGE; ?>dashboard/auditLog/<?php echo htmlspecialchars($log['entity_type']); ?>/<?php echo (int) $log['entity_id']; ?>"
                                               title="Filtrar por esta entidade"
                                               class="dashboard-entity-link">#<?php echo (int) $log['entity_id']; ?></a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        –
                                    <?php endif; ?>
                                </td>
                                <td class="audit-log-cell-details" data-label="Detalhe">
                                    <?php if ($detailsText !== ''): ?>
                                        <div class="audit-log-details-text" title="<?php echo htmlspecialchars($detailsText, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($detailsText); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="dashboard-inline-note">–</span>
                                    <?php endif; ?>
                                </td>
                                <td class="dashboard-inline-note dashboard-cell-nowrap audit-log-cell-ip" data-label="IP">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? '–'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$isFiltered && $totalPages > 1): ?>
                <div class="dashboard-pagination-wrap dashboard-pagination-wrap-start audit-log-pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo DIRPAGE; ?>dashboard/auditLog?page=<?php echo $page - 1; ?>" class="btn-secondary">&larr; Anterior</a>
                    <?php endif; ?>
                    <span class="dashboard-pagination-copy">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo DIRPAGE; ?>dashboard/auditLog?page=<?php echo $page + 1; ?>" class="btn-secondary">Próxima &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="dashboard-empty-copy dashboard-empty-copy-spaced">Sem entradas de auditoria registadas.</p>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
