<?php
/** @var array $user */
/** @var array $subscriptions */
/** @var array $plans */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var string $status */
/** @var string $planFilter */
/** @var string $csrfField */

$totalPages = max(1, (int) ceil($total / $perPage));
$baseUrl = DIRPAGE . 'dashboard/adminSubscriptions?';

$statusMeta = [
    'pending_activation' => ['label' => 'Pendente', 'class' => 'admin-subscriptions-status-pending'],
    'active' => ['label' => 'Ativo', 'class' => 'admin-subscriptions-status-active'],
    'past_due' => ['label' => 'Em atraso', 'class' => 'admin-subscriptions-status-past-due'],
    'cancelled' => ['label' => 'Cancelado', 'class' => 'admin-subscriptions-status-inactive'],
    'expired' => ['label' => 'Expirado', 'class' => 'admin-subscriptions-status-inactive'],
];

function buildUrl(array $extra = []): string {
    global $search, $status, $planFilter;
    $params = [];
    if ($search !== '') { $params['search'] = $search; }
    if ($status !== '') { $params['status'] = $status; }
    if ($planFilter !== '') { $params['plan'] = $planFilter; }
    foreach ($extra as $k => $v) { $params[$k] = $v; }
    return DIRPAGE . 'dashboard/adminSubscriptions?' . http_build_query($params);
}
?>
<?php
$dashboardPageClass = '';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Administração';
$heroTitle = 'Subscrições de Utilizadores';
$heroLead = 'Um registo por utilizador (subscrição aberta actual). Histórico completo permanece na base de dados.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-module-card admin-subscriptions-card-spacing">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Filtros</span>
                <h3>Pesquisa</h3>
            </div>
        </div>
        <div class="admin-subscriptions-card-body">
            <form method="GET" action="<?php echo DIRPAGE; ?>dashboard/adminSubscriptions" class="admin-subscriptions-filters">
                <div class="admin-subscriptions-field admin-subscriptions-field-search">
                    <label class="admin-subscriptions-label">Utilizador</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nome ou e-mail" class="input-field">
                </div>
                <div class="admin-subscriptions-field">
                    <label class="admin-subscriptions-label">Status</label>
                    <select name="status" class="input-field">
                        <option value="">Todos</option>
                        <?php foreach (['pending_activation', 'active', 'past_due', 'cancelled', 'expired'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($statusMeta[$s]['label'] ?? ucfirst($s))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-subscriptions-field">
                    <label class="admin-subscriptions-label">Plano</label>
                    <select name="plan" class="input-field">
                        <option value="">Todos</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?php echo htmlspecialchars((string) ($p['code'] ?? '')); ?>" <?php echo $planFilter === ($p['code'] ?? '') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($p['name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-subscriptions-actions">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="<?php echo DIRPAGE; ?>dashboard/adminSubscriptions" class="btn btn-secondary">Limpar</a>
                </div>
            </form>
            <p class="admin-subscriptions-filter-note">Refine por utilizador, situação comercial do plano e catálogo activo.</p>
        </div>
    </div>

    <div class="dashboard-module-card">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker"><?php echo number_format($total); ?> resultado(s)</span>
                <h3>Subscrições</h3>
            </div>
        </div>
        <div class="admin-subscriptions-card-body">
            <div class="dashboard-table-wrap">
            <table class="table table-modern admin-subscriptions-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Utilizador</th>
                    <th>Plano</th>
                    <th>Status</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Auto-renovar</th>
                    <th>Alterar plano</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($subscriptions)): ?>
                    <tr><td colspan="8">Nenhuma subscrição encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($subscriptions as $sub): ?>
                        <?php
                            $st = (string) ($sub['status'] ?? '');
                            $statusConfig = $statusMeta[$st] ?? ['label' => ucfirst($st), 'class' => 'admin-subscriptions-status-neutral'];
                            $isAutoRenew = !empty($sub['auto_renew']);
                        ?>
                        <tr class="admin-subscriptions-row">
                            <td class="admin-subscriptions-id-cell">#<?php echo (int) ($sub['id'] ?? 0); ?></td>
                            <td>
                                <div class="admin-subscriptions-user-cell">
                                    <?php if (!empty($sub['user_id'])): ?>
                                        <strong class="admin-subscriptions-user-name">
                                            <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) ($sub['user_id'] ?? 0); ?>" class="table-name-link">
                                                <?php echo htmlspecialchars((string) ($sub['user_name'] ?? '-')); ?>
                                            </a>
                                        </strong>
                                    <?php else: ?>
                                        <strong class="admin-subscriptions-user-name"><?php echo htmlspecialchars((string) ($sub['user_name'] ?? '-')); ?></strong>
                                    <?php endif; ?>
                                    <small class="admin-subscriptions-user-email"><?php echo htmlspecialchars((string) ($sub['user_email'] ?? '')); ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="admin-subscriptions-plan-badge"><?php echo htmlspecialchars((string) ($sub['plan_name'] ?? '-')); ?></span>
                            </td>
                            <td>
                                <span class="sub-status-chip admin-subscriptions-status-chip <?php echo htmlspecialchars((string) $statusConfig['class']); ?>">
                                    <span class="sub-status-dot"></span><?php echo htmlspecialchars((string) $statusConfig['label']); ?>
                                </span>
                            </td>
                            <td class="admin-subscriptions-date-cell"><?php echo !empty($sub['starts_at']) ? date('d/m/Y', strtotime((string) $sub['starts_at'])) : '-'; ?></td>
                            <td class="admin-subscriptions-date-cell"><?php echo !empty($sub['ends_at']) ? date('d/m/Y', strtotime((string) $sub['ends_at'])) : '∞'; ?></td>
                            <td>
                                <span class="admin-subscriptions-renew-pill <?php echo $isAutoRenew ? 'is-auto' : 'is-manual'; ?>">
                                    <?php echo $isAutoRenew ? 'Automática' : 'Manual'; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $targetUid = (int) ($sub['user_id'] ?? 0);
                                $checkoutBase = DIRPAGE . 'dashboard/adminSubscriptionCheckout';
                                $selectedPlanCode = strtolower((string) ($sub['plan_code'] ?? ''));
                                $isRowCustomPlan = false;
                                foreach ($plans as $p) {
                                    if (strtolower((string) ($p['code'] ?? '')) === $selectedPlanCode && !empty($p['is_custom_pricing'])) {
                                        $isRowCustomPlan = true;
                                        break;
                                    }
                                }
                                $configureHref = $checkoutBase . '?' . http_build_query([
                                    'target_user_id' => $targetUid,
                                    'plan_code' => $selectedPlanCode !== '' ? $selectedPlanCode : 'enterprise',
                                ]);
                                ?>
                                <form method="POST" action="<?php echo DIRPAGE; ?>dashboard/adminSetSubscription"
                                      class="admin-subscriptions-inline-form admin-subscription-set-form"
                                      data-checkout-url="<?php echo htmlspecialchars($checkoutBase); ?>"
                                      data-target-user-id="<?php echo (int) $targetUid; ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="target_user_id" value="<?php echo $targetUid; ?>">
                                    <select name="plan_code" class="input-field admin-subscriptions-plan-select" data-plan-select>
                                        <?php foreach ($plans as $p): ?>
                                            <?php $pCode = strtolower((string) ($p['code'] ?? '')); ?>
                                            <option value="<?php echo htmlspecialchars($pCode); ?>"
                                                data-custom="<?php echo !empty($p['is_custom_pricing']) ? '1' : '0'; ?>"
                                                <?php echo $pCode === $selectedPlanCode ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars((string) ($p['name'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="admin-subscriptions-autorenew<?php echo $isRowCustomPlan ? ' admin-plan-action-hidden' : ''; ?>" data-standard-only>
                                        <input type="checkbox" name="auto_renew" value="1" <?php echo $isAutoRenew ? 'checked' : ''; ?>>
                                        Auto-renovar
                                    </label>
                                    <button type="submit"
                                            class="btn btn-primary admin-subscriptions-save-btn<?php echo $isRowCustomPlan ? ' admin-plan-action-hidden' : ''; ?>"
                                            data-action-save>Salvar</button>
                                    <a href="<?php echo $isRowCustomPlan ? htmlspecialchars($configureHref) : '#'; ?>"
                                       class="btn btn-secondary admin-subscriptions-configure-btn<?php echo $isRowCustomPlan ? '' : ' admin-plan-action-hidden'; ?>"
                                       data-action-configure
                                       data-navigate-url="<?php echo htmlspecialchars($configureHref); ?>">Configurar plano</a>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="admin-subscriptions-pagination">
            <?php if ($page > 1): ?>
                <a href="<?php echo buildUrl(['page' => $page - 1]); ?>" class="btn btn-secondary">Anterior</a>
            <?php endif; ?>
            <span class="admin-subscriptions-pagination-copy">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?php echo buildUrl(['page' => $page + 1]); ?>" class="btn btn-secondary">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
<script src="<?php echo DIRJS; ?>admin-subscriptions.js?v=20260603"></script>
