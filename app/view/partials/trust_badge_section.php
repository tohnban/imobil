<?php
/**
 * Secção do selo de confiança — mesma regra para todos os utilizadores autenticados.
 *
 * @var array $user
 * @var array|null $trustPricing
 */
use Src\classes\ClassTrustBadgeEligibility;

$trustUserId = (int) ($user['id'] ?? 0);
$trustUi = ClassTrustBadgeEligibility::resolveProfileUiState($trustUserId);
$trustGate = is_array($trustUi['gate'] ?? null) ? $trustUi['gate'] : [];
$trust = is_array($trustUi['trust'] ?? null) ? $trustUi['trust'] : [];
$trustView = (string) ($trustUi['view'] ?? 'locked');
$trustCanSubmit = ($trustUi['can_submit'] ?? false) === true;
$trustPricing = $trustPricing ?? \App\model\User::getTrustedBadgePricingConfig();
$trustEligibility = is_array($trustGate['eligibility'] ?? null) ? $trustGate['eligibility'] : [];
$trustBlockers = is_array($trustGate['blockers'] ?? null) ? $trustGate['blockers'] : [];
$trustBadgeStatus = (string) ($trustGate['badge_status'] ?? 'nenhum');
$trustSuccessMsg = isset($_GET['success']) ? trim((string) $_GET['success']) : '';
$trustSuccessInvalid = $trustSuccessMsg !== ''
    && stripos($trustSuccessMsg, 'selo') !== false
    && !$trustCanSubmit;
$tbDefaultMonths = (int) ($trustPricing['default_months'] ?? 1);
$tbMonthlyFee = (float) ($trustPricing['monthly_fee'] ?? 0);
$tbDefaultTotal = number_format($tbDefaultMonths * $tbMonthlyFee, 0, ',', '.');
$config = ClassTrustBadgeEligibility::getConfig();
?>
<div class="dashboard-module-card trust-badge-profile-section<?php echo $trustCanSubmit ? '' : ' trust-badge-section--locked'; ?>"
     id="trust-badge-section"
     data-can-submit="<?php echo $trustCanSubmit ? '1' : '0'; ?>"
     data-ui-view="<?php echo htmlspecialchars($trustView, ENT_QUOTES, 'UTF-8'); ?>"
     data-blockers="<?php echo htmlspecialchars(implode('; ', $trustBlockers), ENT_QUOTES, 'UTF-8'); ?>"
     data-guard-version="<?php echo htmlspecialchars(ClassTrustBadgeEligibility::GUARD_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="dashboard-module-head compact">
        <div>
            <span class="dashboard-module-kicker">Confiança</span>
            <h3>Selo de Utilizador de Confiança</h3>
        </div>
    </div>

    <div class="dashboard-profile-summary">
        <?php if ($trustSuccessInvalid): ?>
            <div class="alert-banner alert-banner-error trust-badge-flash-error" style="margin-bottom:1rem;">
                O pedido de selo não foi registado porque ainda não cumpre os requisitos.
                <?php if (!empty($trustBlockers)): ?>
                    <?php echo htmlspecialchars(implode(' ', $trustBlockers)); ?>.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($trustView === 'pending'): ?>
            <div class="trust-badges" style="margin-bottom:.75rem;">
                <span class="request-status-badge request-status-pendente"><i class="fa fa-clock-o"></i> Pedido em análise</span>
            </div>
            <p class="dashboard-inline-note">A equipa irá analisar o seu pedido e comprovativo.</p>

        <?php elseif ($trustView === 'payment_pending'): ?>
            <div class="trust-badges" style="margin-bottom:.75rem;">
                <span class="request-status-badge request-status-aceite"><i class="fa fa-check"></i> Pedido aprovado</span>
            </div>
            <p class="dashboard-inline-note">Taxa pendente: <strong><?php echo number_format((float) ($trust['fee_required'] ?? 0), 0, ',', '.'); ?> Kz.</strong></p>

        <?php elseif ($trustView === 'active'): ?>
            <span class="owner-trust-badge"><i class="fa fa-shield"></i> Utilizador de confiança ativo</span>

        <?php elseif ($trustView === 'unverified'): ?>
            <p class="dashboard-inline-note">Conta ainda não verificada. Complete os dados e documentos para poder solicitar o selo.</p>
            <?php if (!empty($trustBlockers)): ?>
                <p class="dashboard-inline-note" style="margin-top:.5rem;"><?php echo htmlspecialchars(implode(' ', $trustBlockers)); ?>.</p>
            <?php endif; ?>

        <?php elseif ($trustView === 'form'): ?>
            <?php include DIRREQ . 'app/view/partials/trust_badge_request_form.php'; ?>

        <?php else: /* locked — critérios de negócio não cumpridos */ ?>
            <?php include DIRREQ . 'app/view/partials/trust_badge_eligibility_panel.php'; ?>
        <?php endif; ?>

        <?php if ($trustView === 'locked' && ((int) ($config['min_won_deals'] ?? 0) === 0 || (int) ($config['min_account_days'] ?? 0) === 0)): ?>
            <p class="dashboard-inline-note" style="margin-top:1rem;color:#856404;">
                Atenção (admin): um ou mais limites estão em 0 nas configurações — o sistema trata como requisito desativado.
            </p>
        <?php endif; ?>
    </div>
</div>
<?php if ($trustSuccessInvalid): ?>
<span data-trust-success-invalid="1" hidden aria-hidden="true"></span>
<script src="<?php echo DIRJS; ?>trust-badge-section.js?v=20260603"></script>
<?php endif; ?>
