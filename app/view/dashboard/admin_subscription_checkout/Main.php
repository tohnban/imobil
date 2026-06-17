<?php
/** @var array $user */
/** @var array $targetUser */
/** @var array $plan */
/** @var string $planCode */
/** @var array|null $currentSubscription */
/** @var string $csrfField */

$referenceMonthly = (float) ($plan['monthly_price_aoa'] ?? 0);
$planName = (string) ($plan['name'] ?? ucfirst($planCode));
$targetName = (string) ($targetUser['name'] ?? 'Utilizador');
$targetEmail = (string) ($targetUser['email'] ?? '');

$durationOptions = [1, 3, 6, 12];
$dueDateByDuration = [];
foreach ($durationOptions as $months) {
    $dueDateByDuration[$months] = date('d/m/Y', strtotime('+' . $months . ' month'));
}

$backUrl = DIRPAGE . 'dashboard/adminSubscriptions';
$checkoutAction = DIRPAGE . 'dashboard/confirmAdminSubscriptionCheckout';
?>

<?php
$dashboardPageClass = '';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
?>
    <div class="sub-shell sub-checkout-shell">
        <?php
        $heroKicker = 'Administração';
        $heroTitle = 'Configurar Plano Empresarial';
        $heroLead = 'Defina o valor negociado, duração e renovação para o utilizador selecionado.';
        include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
        ?>

        <section class="sub-section">
            <div class="sub-section-header">
                <h2 class="sub-section-title">Utilizador</h2>
                <span class="sub-section-note">Conta que receberá o plano</span>
            </div>
            <div class="sub-checkout-summary-grid">
                <article class="sub-checkout-summary-card">
                    <div class="sub-checkout-label">Nome</div>
                    <div class="sub-checkout-value"><?php echo htmlspecialchars($targetName); ?></div>
                </article>
                <article class="sub-checkout-summary-card">
                    <div class="sub-checkout-label">E-mail</div>
                    <div class="sub-checkout-value"><?php echo htmlspecialchars($targetEmail); ?></div>
                </article>
                <article class="sub-checkout-summary-card">
                    <div class="sub-checkout-label">Plano actual</div>
                    <div class="sub-checkout-value"><?php echo htmlspecialchars((string) ($currentSubscription['plan_name'] ?? 'Sem plano activo')); ?></div>
                </article>
                <article class="sub-checkout-summary-card">
                    <div class="sub-checkout-label">Referência de tabela</div>
                    <div class="sub-checkout-value">
                        <?php echo $referenceMonthly > 0 ? number_format($referenceMonthly, 0, ',', '.') . ' Kz / mês' : 'Sob proposta'; ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="sub-section">
            <div class="sub-section-header">
                <h2 class="sub-section-title">Resumo do Plano</h2>
                <span class="sub-section-note"><?php echo htmlspecialchars($planName); ?></span>
            </div>
            <ul class="sub-list" style="margin:0 2rem 1rem;">
                <li><i class="fa fa-check" aria-hidden="true"></i>Imóveis activos: ilimitado</li>
                <li><i class="fa fa-check" aria-hidden="true"></i>Visibilidade premium e ranking máximo</li>
                <li><i class="fa fa-check" aria-hidden="true"></i>Relatórios avançados e página institucional</li>
            </ul>

            <div class="sub-checkout-due-table-wrap table-responsive">
                <table class="table table-modern sub-checkout-due-table" id="admin-enterprise-preview-table">
                    <thead>
                    <tr>
                        <th>Duração</th>
                        <th>Vencimento previsto</th>
                        <th>Referência (tabela × meses)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($durationOptions as $months): ?>
                        <tr data-months="<?php echo $months; ?>">
                            <td><?php echo $months; ?> mês(es)</td>
                            <td><?php echo $dueDateByDuration[$months]; ?></td>
                            <td class="admin-ref-total"><?php echo number_format($referenceMonthly * $months, 0, ',', '.'); ?> Kz</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="sub-section">
            <div class="sub-section-header">
                <h2 class="sub-section-title">Contrato negociado</h2>
                <span class="sub-section-note">Valores comerciais acordados com o cliente</span>
            </div>

            <form method="POST" action="<?php echo $checkoutAction; ?>" class="dashboard-form sub-checkout-form" id="admin-enterprise-checkout-form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="target_user_id" value="<?php echo (int) ($targetUser['id'] ?? 0); ?>">
                <input type="hidden" name="plan_code" value="<?php echo htmlspecialchars($planCode); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="billing_cycle_months">Duração do contrato *</label>
                        <select id="billing_cycle_months" name="billing_cycle_months" required>
                            <?php foreach ($durationOptions as $months): ?>
                                <option value="<?php echo $months; ?>"><?php echo $months; ?> mês(es) — vence <?php echo $dueDateByDuration[$months]; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="negotiated_price_aoa">Valor total negociado (Kz) *</label>
                        <input type="number" id="negotiated_price_aoa" name="negotiated_price_aoa" min="1" step="1" required
                               placeholder="Ex: 450000">
                        <small class="dashboard-inline-note">Montante total para o período seleccionado (não mensal). Usado em renovações com o mesmo valor.</small>
                    </div>
                </div>

                <?php if (!empty($plan['has_auto_renew'])): ?>
                <div class="form-group form-group-checkbox">
                    <label>
                        <input type="checkbox" name="auto_renew" value="1" checked>
                        Activar renovação automática
                    </label>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="admin_notes">Notas internas (opcional)</label>
                    <textarea id="admin_notes" name="notes" rows="3" maxlength="500"
                              placeholder="Ex: Proposta comercial #2026-042, desconto 10%, contacto João"></textarea>
                </div>

                <div class="sub-checkout-summary-card" style="margin-bottom:1rem;">
                    <div class="sub-checkout-label">Resumo</div>
                    <div class="sub-checkout-value" id="admin-enterprise-summary">Preencha o valor negociado.</div>
                </div>

                <div class="sub-checkout-actions">
                    <button class="sub-submit primary" type="submit">Activar plano empresarial</button>
                    <a href="<?php echo $backUrl; ?>" class="sub-submit current">Voltar à lista</a>
                </div>
            </form>
        </section>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
<script src="<?php echo DIRJS; ?>admin-subscription-checkout.js?v=20260603"></script>
