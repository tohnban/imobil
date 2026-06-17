<?php
    $page = max(1, (int) ($page ?? 1));
    $totalPages = max(1, (int) ($totalPages ?? 1));
    $disputesTotal = (int) ($disputesTotal ?? count($disputes ?? []));
    $statusFilter = (string) ($statusFilter ?? 'all');
    $statusFilters = is_array($statusFilters ?? null) ? $statusFilters : [
        'all' => 'Todos os estados',
        'aberta' => 'Aberta',
        'em_analise' => 'Em análise',
        'julgada_procedente' => 'Julgada procedente',
        'julgada_improcedente' => 'Julgada improcedente',
        'nenhuma' => 'Nenhuma',
    ];
    $paginationBaseQuery = (string) ($paginationBaseQuery ?? '');
    $paginationPrefix = $paginationBaseQuery !== '' ? ($paginationBaseQuery . '&') : '';
?>

<?php
$dashboardPageClass = 'disputes-dashboard-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Moderação';
$heroTitle = 'Painel de Disputas';
$heroLead = 'Resolva solicitações contestadas e conclua o desfecho comercial.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-module-card disputes-module-card">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Solicitações</span>
                <h3>Em disputa</h3>
            </div>
        </div>

        <form method="GET" action="<?php echo DIRPAGE; ?>dashboard/disputes" class="disputes-filters-bar filter-toolbar-form">
            <div class="filter-toolbar filter-toolbar-sticky filter-toolbar-dashboard">
                <div class="filter-toolbar-field filter-toolbar-field-grow">
                    <label class="filter-toolbar-field-label" for="disputeStatusFilter">Estado da disputa</label>
                    <select id="disputeStatusFilter" name="dispute_status" class="request-history-filter-select filter-toolbar-select">
                        <?php foreach ($statusFilters as $filterValue => $filterLabel): ?>
                            <option value="<?php echo htmlspecialchars((string) $filterValue); ?>" <?php echo $statusFilter === (string) $filterValue ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $filterLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-toolbar-actions">
                    <button type="submit" class="btn-primary filter-toolbar-submit">
                        <i class="fa fa-filter" aria-hidden="true"></i>
                        <span>Filtrar</span>
                    </button>
                </div>
            </div>
        </form>

        <div class="dashboard-table-wrap disputes-table-wrap">
            <table class="requests-table disputes-table">
                <thead>
                    <tr>
                        <th>Solicitante</th>
                        <th>Proprietário</th>
                        <th>Imóvel</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Confirmação</th>
                        <th class="col-actions">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($disputes)): ?>
                        <?php foreach ($disputes as $request): ?>
                            <?php
                                $status = (string) ($request['status'] ?? 'em_disputa');
                                $commercialStatus = (string) ($request['commercial_status'] ?? $status);
                                $disputeStatus    = (string) ($request['dispute_status'] ?? 'aberta');
                                $statusLabel = App\model\Request::statusLabel($commercialStatus, (string) ($request['closing_confirmation_status'] ?? ''));
                                $confirmationLabel = App\model\Request::closingConfirmationLabel((string) ($request['closing_confirmation_status'] ?? ''));
                                $disputeLabels = [
                                    'aberta'      => 'Disputa aberta',
                                    'em_analise'  => 'Em análise',
                                ];
                                $disputeLabel = $disputeLabels[$disputeStatus] ?? ucfirst(str_replace('_', ' ', $disputeStatus));
                                $requesterName = htmlspecialchars((string) ($request['requester_name'] ?? '-'));
                                $ownerName = htmlspecialchars((string) ($request['owner_name'] ?? '-'));
                            ?>
                            <tr class="dispute-row">
                                <td data-label="Solicitante">
                                    <?php if (!empty($request['requester_id'])): ?>
                                        <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) ($request['requester_id'] ?? 0); ?>" class="table-name-link"><?php echo $requesterName; ?></a>
                                    <?php else: ?>
                                        <?php echo $requesterName; ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Proprietário">
                                    <?php if (!empty($request['owner_id'])): ?>
                                        <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) ($request['owner_id'] ?? 0); ?>" class="table-name-link"><?php echo $ownerName; ?></a>
                                    <?php else: ?>
                                        <?php echo $ownerName; ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Imóvel">
                                    <a href="<?php echo DIRPAGE; ?>property/<?php echo (int)($request['property_id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars((string) ($request['title'] ?? '')); ?></a>
                                </td>
                                <td data-label="Tipo"><?php echo htmlspecialchars((string) ($request['type'] ?? '')); ?></td>
                                <td data-label="Status" class="col-status">
                                    <div class="status-cell">
                                        <span class="request-status-badge request-status-<?php echo htmlspecialchars($commercialStatus); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                        <span class="request-status-badge request-status-em_disputa dispute-status-chip">
                                            <i class="fa fa-gavel"></i> <?php echo htmlspecialchars($disputeLabel); ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Confirmação">
                                    <span class="request-status-badge request-status-<?php echo htmlspecialchars((string) ($request['closing_confirmation_status'] ?: 'none')); ?>"><?php echo htmlspecialchars($confirmationLabel); ?></span>
                                </td>
                                <td data-label="Ações" class="col-actions">
                                    <div class="dispute-row-actions">
                                        <a class="btn-secondary dispute-detail-link" href="<?php echo DIRPAGE; ?>dashboard/dispute/<?php echo (int) ($request['id'] ?? 0); ?>">Ver detalhe</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="disputes-empty-cell">
                                <div class="empty-state-content">
                                    <i class="fa fa-balance-scale"></i>
                                    <p>Nenhuma solicitação em disputa no momento.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($disputesTotal > 0): ?>
            <p class="dashboard-pagination-copy disputes-count-copy">
                A mostrar <?php echo count($disputes ?? []); ?> de <?php echo $disputesTotal; ?>.
            </p>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <div class="dashboard-pagination-wrap disputes-pagination-wrap">
                <?php if ($page > 1): ?>
                    <a href="<?php echo DIRPAGE; ?>dashboard/disputes?<?php echo $paginationPrefix; ?>page=<?php echo $page - 1; ?>" class="btn-secondary">&larr; Anterior</a>
                <?php endif; ?>
                <span class="dashboard-pagination-copy">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo DIRPAGE; ?>dashboard/disputes?<?php echo $paginationPrefix; ?>page=<?php echo $page + 1; ?>" class="btn-secondary">Próxima &rarr;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
