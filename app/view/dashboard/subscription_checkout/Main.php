<?php
/** @var array $user */
/** @var array $plan */
/** @var string $planCode */
/** @var array|null $currentSubscription */
/** @var bool $preferredAutoRenew */
/** @var array $paymentMethods */
/** @var array $channelsByMethod */
/** @var string $csrfField */

$monthlyPrice = (float) ($plan['monthly_price_aoa'] ?? 0);
$isPaidPlan = $monthlyPrice > 0;
$planName = (string) ($plan['name'] ?? ucfirst($planCode));
$planCodeLower = strtolower($planCode);

$planTheme = [
    'essential' => [
        'accent' => '#4f5d73',
        'soft' => 'linear-gradient(165deg, #f4f6fa 0%, #e6ebf2 100%)',
        'icon' => 'fa-home',
    ],
    'professional' => [
        'accent' => '#174f86',
        'soft' => 'linear-gradient(165deg, #ebf5ff 0%, #d4e7fb 100%)',
        'icon' => 'fa-bolt',
    ],
    'enterprise' => [
        'accent' => '#b07e00',
        'soft' => 'linear-gradient(165deg, #fff7de 0%, #fbe8b3 100%)',
        'icon' => 'fa-building',
    ],
];
$theme = $planTheme[$planCodeLower] ?? $planTheme['essential'];

$durationOptions = [1, 3, 6, 12];
$dueDateByDuration = [];
$totalByDuration = [];
foreach ($durationOptions as $months) {
    $dueDateByDuration[$months] = date('d/m/Y', strtotime('+' . $months . ' month'));
    $totalByDuration[$months] = $isPaidPlan ? $monthlyPrice * $months : 0;
}

$selectedMethodId = 0;
if (!empty($paymentMethods)) {
    $selectedMethodId = (int) ($paymentMethods[0]['id'] ?? 0);
}

$defaultDuration = 1;
$defaultTotal = $totalByDuration[$defaultDuration];
$defaultDue = $dueDateByDuration[$defaultDuration];
?>

<?php
$dashboardPageClass = 'subscription-checkout-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
?>
    <div class="sub-shell sub-checkout-shell">
        <?php
        $heroKicker = 'Subscrição';
        $heroTitle = 'Finalizar Plano';
        $heroLead = 'Revise o plano, escolha a duração e envie os dados de pagamento para validação financeira.';
        include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
        ?>

        <div class="sub-checkout-layout">
            <aside class="sub-checkout-aside" aria-label="Resumo do plano">
                <section class="sub-section sub-checkout-plan-card"
                         style="--sub-soft:<?php echo $theme['soft']; ?>; --sub-accent:<?php echo $theme['accent']; ?>;">
                    <div class="sub-checkout-plan-head">
                        <span class="sub-checkout-plan-icon"><i class="fa <?php echo htmlspecialchars($theme['icon']); ?>" aria-hidden="true"></i></span>
                        <div>
                            <span class="sub-checkout-label">Plano seleccionado</span>
                            <h2 class="sub-checkout-plan-name"><?php echo htmlspecialchars($planName); ?></h2>
                        </div>
                    </div>

                    <div class="sub-checkout-summary-grid sub-checkout-summary-grid-aside">
                        <article class="sub-checkout-summary-card">
                            <div class="sub-checkout-label">Preço mensal</div>
                            <div class="sub-checkout-value">
                                <?php if ($isPaidPlan): ?>
                                    <?php echo number_format($monthlyPrice, 0, ',', '.'); ?> Kz
                                <?php else: ?>
                                    Grátis
                                <?php endif; ?>
                            </div>
                        </article>
                        <article class="sub-checkout-summary-card">
                            <div class="sub-checkout-label">Renovação automática</div>
                            <div class="sub-checkout-value"><?php echo !empty($plan['has_auto_renew']) ? 'Disponível' : 'Não disponível'; ?></div>
                        </article>
                        <article class="sub-checkout-summary-card sub-checkout-summary-card-span">
                            <div class="sub-checkout-label">Subscrição actual</div>
                            <div class="sub-checkout-value"><?php echo htmlspecialchars((string) ($currentSubscription['plan_name'] ?? 'Sem plano activo')); ?></div>
                        </article>
                    </div>

                    <div class="sub-checkout-total-block" id="sub-checkout-total" aria-live="polite">
                        <span class="sub-checkout-total-kicker">Total estimado</span>
                        <strong class="sub-checkout-total-value" data-sub-total>
                            <?php echo $isPaidPlan ? number_format($defaultTotal, 0, ',', '.') . ' Kz' : 'Grátis'; ?>
                        </strong>
                        <small class="sub-checkout-total-note">
                            Vencimento previsto: <strong data-sub-due><?php echo htmlspecialchars($defaultDue); ?></strong>
                        </small>
                    </div>

                    <p class="sub-checkout-aside-note">
                        <a href="<?php echo DIRPAGE; ?>dashboard/subscription">← Voltar aos planos</a>
                    </p>
                </section>
            </aside>

            <div class="sub-checkout-main">
                <section class="sub-section">
                    <div class="sub-section-header">
                        <h2 class="sub-section-title">Duração e valores</h2>
                        <span class="sub-section-note">Compare opções antes de confirmar</span>
                    </div>

                    <div class="sub-checkout-due-table-wrap table-responsive">
                        <table class="table table-modern sub-checkout-due-table" id="sub-checkout-due-table">
                            <thead>
                            <tr>
                                <th>Duração</th>
                                <th>Vencimento previsto</th>
                                <th>Valor total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($durationOptions as $months): ?>
                                <tr class="sub-checkout-due-row<?php echo $months === $defaultDuration ? ' is-selected' : ''; ?>"
                                    data-duration="<?php echo $months; ?>"
                                    data-total="<?php echo (int) $totalByDuration[$months]; ?>"
                                    data-due="<?php echo htmlspecialchars($dueDateByDuration[$months]); ?>">
                                    <td data-label="Duração"><?php echo $months; ?> mês(es)</td>
                                    <td data-label="Vencimento"><?php echo $dueDateByDuration[$months]; ?></td>
                                    <td data-label="Valor total">
                                        <?php if ($isPaidPlan): ?>
                                            <?php echo number_format($totalByDuration[$months], 0, ',', '.'); ?> Kz
                                        <?php else: ?>
                                            Grátis
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="sub-section">
                    <div class="sub-section-header">
                        <h2 class="sub-section-title">Pagamento</h2>
                        <span class="sub-section-note">Dados para validação financeira</span>
                    </div>

                    <form method="POST"
                          action="<?php echo DIRPAGE; ?>dashboard/confirmSubscriptionCheckout"
                          enctype="multipart/form-data"
                          class="dashboard-form sub-checkout-form"
                          id="sub-checkout-form"
                          data-monthly-price="<?php echo htmlspecialchars((string) $monthlyPrice); ?>"
                          data-is-paid="<?php echo $isPaidPlan ? '1' : '0'; ?>">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="plan_code" value="<?php echo htmlspecialchars($planCode); ?>">

                        <div class="sub-checkout-form-block">
                            <div class="form-group">
                                <label for="duration_months">Duração da subscrição</label>
                                <select id="duration_months" name="duration_months" required>
                                    <?php foreach ($durationOptions as $months): ?>
                                        <option value="<?php echo $months; ?>"<?php echo $months === $defaultDuration ? ' selected' : ''; ?>>
                                            <?php echo $months; ?> mês<?php echo $months > 1 ? 'es' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (!empty($plan['has_auto_renew'])): ?>
                            <div class="form-group form-group-checkbox sub-checkout-autorenew">
                                <label>
                                    <input type="checkbox" name="auto_renew" value="1" <?php echo $preferredAutoRenew ? 'checked' : ''; ?>>
                                    Ativar renovação automática
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isPaidPlan): ?>
                        <div class="sub-checkout-form-block">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="payment_method_id">Tipo de pagamento</label>
                                    <select id="payment_method_id" name="payment_method_id" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($paymentMethods as $method): ?>
                                            <option value="<?php echo (int) $method['id']; ?>" <?php echo (int) $method['id'] === $selectedMethodId ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars((string) ($method['name'] ?? 'Método')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="system_channel_id">Canal de recebimento <span class="sub-checkout-optional">(opcional)</span></label>
                                    <select id="system_channel_id" name="system_channel_id">
                                        <option value="">Selecione</option>
                                        <?php foreach ($paymentMethods as $method): ?>
                                            <?php
                                            $methodId = (int) ($method['id'] ?? 0);
                                            $methodName = (string) ($method['name'] ?? 'Método');
                                            $channels = $channelsByMethod[$methodId] ?? [];
                                            ?>
                                            <?php foreach ($channels as $channel): ?>
                                                <option value="<?php echo (int) ($channel['id'] ?? 0); ?>"
                                                        data-method-id="<?php echo $methodId; ?>"
                                                        <?php echo $methodId !== $selectedMethodId ? ' hidden disabled' : ''; ?>>
                                                    <?php echo htmlspecialchars($methodName . ' — ' . (string) ($channel['channel_name'] ?? 'Canal')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="reference_code">Referência do pagamento <span class="sub-checkout-optional">(opcional)</span></label>
                                    <input type="text" id="reference_code" name="reference_code" maxlength="120" placeholder="Ex: TRX-2026-00091">
                                </div>

                                <div class="form-group sub-checkout-proof-group">
                                    <label for="payment_proof">Comprovativo de pagamento <span class="required-mark">*</span></label>
                                    <input type="file"
                                           id="payment_proof"
                                           name="payment_proof"
                                           class="sub-checkout-file-input"
                                           accept="image/jpeg,image/png,image/gif,image/webp"
                                           required>
                                    <small class="dashboard-inline-note">
                                        Obrigatório. JPG, PNG, GIF ou WebP, até <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?>.
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="sub-checkout-free-note">Este plano é gratuito — não é necessário enviar pagamento.</p>
                        <?php endif; ?>

                        <div class="sub-checkout-actions">
                            <button class="sub-submit primary" type="submit">Confirmar subscrição</button>
                            <a href="<?php echo DIRPAGE; ?>dashboard/subscription" class="sub-submit current">Cancelar</a>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
