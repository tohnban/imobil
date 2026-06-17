<?php
$user = is_array($user ?? null) ? $user : [];
$userId = (int) ($user['id'] ?? 0);

if ($userId > 0) {
    $freshUser = App\model\User::findById($userId);
    if (is_array($freshUser)) {
        $user = $freshUser;
    }
}

$accountState = is_array($accountState ?? null)
    ? $accountState
    : Src\classes\UserAccountState::resolve($user);
$hero = is_array($accountState['hero'] ?? null) ? $accountState['hero'] : [];
$capabilities = is_array($accountState['capabilities'] ?? null) ? $accountState['capabilities'] : [];
$csrfField = $csrfField ?? Src\classes\ClassCsrf::field();

$requestedAt = (string) ($user['deletion_requested_at'] ?? '');
$scheduledAt = (string) ($user['deletion_scheduled_at'] ?? '');
$daysRemaining = max(0, (int) ($daysRemaining ?? 0));
$deletionGraceDays = max(1, (int) ($deletionGraceDays ?? App\model\User::getAccountDeletionGraceDays()));
?>

<?php
$dashboardPageClass = 'dashboard-account-status-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = (string) ($hero['kicker'] ?? 'Eliminação de conta');
$heroTitle = (string) ($hero['title'] ?? 'Estado da eliminação');
$heroLead = (string) ($hero['text'] ?? '');
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-profile-layout dashboard-account-status-layout">
        <aside class="dashboard-home-side dashboard-account-status-sidebar" aria-label="Permissões durante o período">
            <div class="dashboard-module-card">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Por agora</span>
                        <h3>O que pode fazer</h3>
                    </div>
                </div>
                <ul class="limited-account-checklist">
                    <?php foreach ($capabilities as $cap): ?>
                    <li>
                        <i class="fa <?php echo !empty($cap['allowed']) ? 'fa-check text-success' : 'fa-times text-muted'; ?>" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars((string) ($cap['text'] ?? '')); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>

        <div class="dashboard-profile-stack">
            <div class="dashboard-module-card">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Conformidade</span>
                        <h3>Período de verificação</h3>
                    </div>
                </div>
                <div class="dashboard-form-grid" style="padding:0 1.5rem 1.5rem;">
                    <p class="dashboard-inline-note" style="margin:0 0 1rem;">
                        A sua conta está marcada para eliminação. Durante <strong><?php echo (int) $deletionGraceDays; ?> dias</strong>
                        (configurável pela equipa), o acesso fica limitado e os seus imóveis, perfil público e outras informações
                        ficam indisponíveis para outros utilizadores. A equipa pode eliminar a conta antes do prazo ou o sistema
                        conclui a eliminação automaticamente.
                    </p>
                    <dl class="account-deletion-timeline">
                        <?php if ($requestedAt !== ''): ?>
                        <div class="account-deletion-timeline-row">
                            <dt>Pedido registado</dt>
                            <dd><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($requestedAt))); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($scheduledAt !== ''): ?>
                        <div class="account-deletion-timeline-row">
                            <dt>Eliminação prevista</dt>
                            <dd><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($scheduledAt))); ?></dd>
                        </div>
                        <?php endif; ?>
                        <div class="account-deletion-timeline-row">
                            <dt>Dias restantes</dt>
                            <dd><strong><?php echo (int) $daysRemaining; ?></strong></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="dashboard-module-card dashboard-form-shell">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Reverter</span>
                        <h3>Cancelar pedido de eliminação</h3>
                    </div>
                </div>
                <form action="<?php echo DIRPAGE; ?>dashboard/cancelAccountDeletion" method="POST" class="profile-update-form">
                    <?php echo $csrfField; ?>
                    <p class="dashboard-inline-note">Se mudou de ideias, pode cancelar o pedido. A conta volta a ficar activa com acesso completo.</p>
                    <div class="form-group">
                        <label for="cancel_deletion_password">Palavra-passe actual *</label>
                        <input type="password" id="cancel_deletion_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-primary" data-confirm="Cancelar o pedido de eliminação e restaurar o acesso completo?">Cancelar eliminação</button>
                </form>
            </div>

            <p class="dashboard-inline-note">
                <a href="<?php echo DIRPAGE; ?>logout">Terminar sessão</a>
            </p>
        </div>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
