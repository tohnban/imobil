<?php
/** @var array $user */
/** @var array|null $currentSubscription */
/** @var array $plans */
/** @var array $history */
/** @var string $csrfField */

$currentPlanCode = strtolower((string) ($currentSubscription['plan_code'] ?? 'essential'));
$currentStatus = strtolower((string) ($currentSubscription['status'] ?? 'active'));
$endsAt = !empty($currentSubscription['ends_at'])
    ? date('d/m/Y', strtotime((string) $currentSubscription['ends_at']))
    : null;
$autoRenew = !empty($currentSubscription['auto_renew']);

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

$statusLabel = [
    'pending_activation' => ['label' => 'Pendente', 'color' => '#1e40af'],
    'active' => ['label' => 'Ativo', 'color' => '#14803c'],
    'past_due' => ['label' => 'Em atraso', 'color' => '#b05c00'],
    'cancelled' => ['label' => 'Cancelado', 'color' => '#a1202c'],
    'expired' => ['label' => 'Expirado', 'color' => '#a1202c'],
][$currentStatus] ?? ['label' => ucfirst($currentStatus), 'color' => '#6d7787'];

$currentTheme = $planTheme[$currentPlanCode] ?? $planTheme['essential'];

$historyStatusLabels = [
    'pending_activation' => 'Pendente',
    'active' => 'Ativo',
    'past_due' => 'Em atraso',
    'cancelled' => 'Cancelado',
    'expired' => 'Expirado',
];
?>

<?php
$dashboardPageClass = 'subscription-dashboard-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
?>
<div class="sub-shell">
    <?php
    $heroKicker = 'Comercial';
    $heroTitle = 'Meu Plano';
    $heroLead = 'Gere limites, renovação e visibilidade dos seus imóveis.';
    include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
    ?>

    <section class="sub-section">
        <div class="sub-section-header">
            <h2 class="sub-section-title">Plano Atual</h2>
            <span class="sub-section-note">Visão rápida da sua assinatura</span>
        </div>
        <div class="sub-overview" style="--sub-soft:<?php echo $currentTheme['soft']; ?>; --sub-accent:<?php echo $currentTheme['accent']; ?>;">
            <div class="sub-overview-top">
                <div class="sub-overview-plan">
                    <i class="fa <?php echo htmlspecialchars($currentTheme['icon']); ?>" aria-hidden="true"></i>
                    <strong><?php echo htmlspecialchars((string) ($currentSubscription['plan_name'] ?? 'Plano Essencial')); ?></strong>
                </div>
                <span class="sub-status-chip" style="--dot:<?php echo $statusLabel['color']; ?>; color:<?php echo $statusLabel['color']; ?>;">
                    <span class="sub-status-dot"></span><?php echo htmlspecialchars($statusLabel['label']); ?>
                </span>
            </div>
            <div class="sub-kpis">
                <div class="sub-kpi">
                    <div class="sub-kpi-label">Renovação</div>
                    <div class="sub-kpi-value"><?php echo $autoRenew ? 'Automática' : 'Manual'; ?></div>
                </div>
                <div class="sub-kpi">
                    <div class="sub-kpi-label">Próximo Vencimento</div>
                    <div class="sub-kpi-value"><?php echo $endsAt ?: 'Sem data'; ?></div>
                </div>
                <div class="sub-kpi">
                    <div class="sub-kpi-label">Acesso</div>
                    <div class="sub-kpi-value"><?php echo htmlspecialchars((string) ($currentSubscription['plan_code'] ?? 'essential')); ?></div>
                </div>
            </div>
        </div>
    </section>

    <section class="sub-section">
        <div class="sub-section-header">
            <h2 class="sub-section-title">Planos Disponiveis</h2>
            <span class="sub-section-note">Compare e escolha com clareza</span>
        </div>
        <h3 class="sub-headline">Comparativo de Benefícios</h3>
        <div class="sub-grid">
        <?php foreach ($plans as $plan): ?>
            <?php
            $planCode = strtolower((string) ($plan['code'] ?? 'essential'));
            $isCurrent = $planCode === $currentPlanCode;
            $isCustom = !empty($plan['is_custom_pricing']);
            $theme = $planTheme[$planCode] ?? $planTheme['essential'];
            $hex = ltrim((string) $theme['accent'], '#');
            $rgb = hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
            $maxProps = isset($plan['max_active_properties']) && $plan['max_active_properties'] !== null
                ? (string) ((int) $plan['max_active_properties'])
                : 'Ilimitado';
            $price = (float) ($plan['monthly_price_aoa'] ?? 0);
            ?>
            <div class="sub-card <?php echo $isCurrent ? 'current' : ''; ?>" style="--sub-soft:<?php echo $theme['soft']; ?>; --sub-accent:<?php echo $theme['accent']; ?>; --sub-accent-rgb:<?php echo $rgb; ?>;">
                <div class="sub-card-strip"></div>
                <?php if ($isCurrent): ?>
                    <span class="sub-card-current-badge">Atual</span>
                <?php endif; ?>
                <div class="sub-card-head">
                    <h4 class="sub-card-title"><?php echo htmlspecialchars((string) ($plan['name'] ?? 'Plano')); ?></h4>
                    <span class="sub-card-icon"><i class="fa <?php echo htmlspecialchars($theme['icon']); ?>" aria-hidden="true"></i></span>
                </div>
                <div class="sub-card-price">
                    <?php if ($isCustom): ?>
                        Sob proposta
                    <?php elseif ($price <= 0): ?>
                        Grátis
                    <?php else: ?>
                        <?php echo number_format($price, 0, ',', '.'); ?> Kz <small>/mês</small>
                    <?php endif; ?>
                </div>
                <ul class="sub-list">
                    <li><i class="fa fa-check" aria-hidden="true"></i><?php echo htmlspecialchars($maxProps); ?> imóveis ativos</li>
                    <li class="<?php echo empty($plan['has_featured_in_results']) ? 'off' : ''; ?>"><i class="fa <?php echo empty($plan['has_featured_in_results']) ? 'fa-minus' : 'fa-check'; ?>" aria-hidden="true"></i>Posicionamento prioritário</li>
                    <li class="<?php echo empty($plan['has_reports']) ? 'off' : ''; ?>"><i class="fa <?php echo empty($plan['has_reports']) ? 'fa-minus' : 'fa-check'; ?>" aria-hidden="true"></i>Relatórios comerciais</li>
                    <li class="<?php echo empty($plan['has_priority_support']) ? 'off' : ''; ?>"><i class="fa <?php echo empty($plan['has_priority_support']) ? 'fa-minus' : 'fa-check'; ?>" aria-hidden="true"></i>Suporte prioritário</li>
                    <li class="<?php echo empty($plan['has_institutional_page']) ? 'off' : ''; ?>"><i class="fa <?php echo empty($plan['has_institutional_page']) ? 'fa-minus' : 'fa-check'; ?>" aria-hidden="true"></i>Página institucional</li>
                </ul>
                <div class="sub-actions">
                    <?php if ($isCustom): ?>
                        <a href="mailto:suporte@imobil.ao?subject=Proposta+Plano+Empresarial" class="sub-submit contact">Contactar equipa comercial</a>
                    <?php else: ?>
                        <form method="GET" action="<?php echo DIRPAGE; ?>dashboard/subscriptionCheckout">
                            <input type="hidden" name="plan_code" value="<?php echo htmlspecialchars($planCode); ?>">
                            <?php if (!empty($plan['has_auto_renew'])): ?>
                                <label class="sub-autorenew">
                                    <input type="checkbox" name="auto_renew" value="1" <?php echo ($isCurrent && $autoRenew) || !$isCurrent ? 'checked' : ''; ?>>
                                    Renovação automática
                                </label>
                            <?php endif; ?>
                            <button class="sub-submit <?php echo $isCurrent ? 'current' : 'primary'; ?>" type="submit">
                                <?php echo $isCurrent ? 'Configurar plano' : 'Selecionar plano'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </section>

    <section class="sub-section sub-history">
        <div class="sub-section-header">
            <h2 class="sub-section-title">Histórico</h2>
            <span class="sub-section-note">Últimas alterações da subscrição</span>
        </div>
        <div class="sub-history-table-wrap">
            <table class="table table-modern sub-history-table">
                <thead>
                <tr>
                    <th>Plano</th>
                    <th>Status</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Renovação</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="5" class="sub-history-empty">
                            <div class="empty-state-content">
                                <i class="fa fa-history"></i>
                                <p>Sem histórico de subscrição.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $item): ?>
                        <?php
                            $itemStatus = strtolower((string) ($item['status'] ?? ''));
                            $itemStatusLabel = $historyStatusLabels[$itemStatus] ?? ucfirst(str_replace('_', ' ', $itemStatus));
                        ?>
                        <tr class="sub-history-row">
                            <td data-label="Plano"><strong><?php echo htmlspecialchars((string) ($item['plan_name'] ?? '-')); ?></strong></td>
                            <td data-label="Status">
                                <span class="sub-history-status"><?php echo htmlspecialchars($itemStatusLabel); ?></span>
                            </td>
                            <td data-label="Início"><?php echo !empty($item['starts_at']) ? date('d/m/Y', strtotime((string) $item['starts_at'])) : '-'; ?></td>
                            <td data-label="Fim"><?php echo !empty($item['ends_at']) ? date('d/m/Y', strtotime((string) $item['ends_at'])) : '-'; ?></td>
                            <td data-label="Renovação"><?php echo !empty($item['auto_renew']) ? 'Sim' : 'Não'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
