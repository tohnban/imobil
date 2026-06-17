<?php
// settings are passed via $data by ClassRender
/** @var array $settings */
/** @var array $errors */
/** @var bool  $success */
/** @var string $csrf */

// Index by key for easy access
$map = [];
foreach ($settings as $row) {
    $map[$row['key']] = $row;
}

$labels = [
    'commission_system_pct'      => 'Taxa do sistema com afiliado (%)',
    'commission_affiliate_pct'   => 'Taxa do afiliado (%)',
    'commission_system_only_pct' => 'Taxa do sistema sem afiliado (%)',
    'commission_due_days'        => 'Prazo de vencimento (dias)',
    'rate_limit_post_max'        => 'Limite global de POST por janela',
    'rate_limit_post_window_seconds' => 'Janela do rate limit POST (segundos)',
    'trust_badge_monthly_fee'    => 'Selo confiança: valor por mês (Kz)',
    'trust_badge_min_months'     => 'Selo confiança: duração mínima (meses)',
    'trust_badge_max_months'     => 'Selo confiança: duração máxima (meses)',
    'trust_badge_default_months' => 'Selo confiança: duração padrão (meses)',
    'trust_badge_min_won_deals' => 'Selo confiança: negócios ganhos mínimos',
    'trust_badge_min_account_days' => 'Selo confiança: dias mínimos na plataforma',
    'trust_badge_require_confirmed_closing' => 'Selo confiança: exigir fecho confirmado (0/1)',
    'boost_daily_fee'            => 'Destaque: valor por dia (Kz)',
    'boost_min_days'             => 'Destaque: duração mínima (dias)',
    'boost_max_days'             => 'Destaque: duração máxima (dias)',
    'boost_default_days'         => 'Destaque: duração padrão (dias)',
    'behavior_ranking_enabled'   => 'Ranking comportamental ativado (0/1)',
    'behavior_ranking_lookback_days' => 'Ranking comportamental: janela (dias)',
    'behavior_weight_view'       => 'Ranking comportamental: peso visualização',
    'behavior_weight_favorite'   => 'Ranking comportamental: peso favorito',
    'behavior_weight_request'    => 'Ranking comportamental: peso solicitação',
    'behavior_max_score_per_property' => 'Ranking comportamental: teto de score por imóvel',
    'behavior_decay_lambda'          => 'Discovery: decaimento temporal (lambda)',
    'behavior_view_penalty_threshold'=> 'Discovery: limiar views sem conversão',
    'behavior_view_penalty_points'   => 'Discovery: penalização (pontos)',
    'behavior_explore_ratio'         => 'Discovery: % exploração (0-30)',
    'behavior_impression_cooldown_hours' => 'Discovery: cooldown impressões (h)',
    'behavior_home_carousel_size'    => 'Discovery: tamanho carrossel home',
    'behavior_continue_exploring_size' => 'Discovery: bloco continuar a explorar',
    'behavior_promoted_interval'       => 'Discovery: intervalo patrocinados na grelha',
    'account_deletion_grace_days'      => 'Eliminação de conta: dias de conformidade',
    'property_deletion_grace_days'     => 'Eliminação de imóvel: dias de conformidade',
    'deletion_reminder_days_before'    => 'Aviso por email antes da eliminação (dias)',
];
$descriptions = [
    'commission_system_pct'      => 'Percentagem retida pelo sistema quando existe afiliado válido na solicitação.',
    'commission_affiliate_pct'   => 'Percentagem paga ao afiliado quando existe afiliado válido na solicitação.',
    'commission_system_only_pct' => 'Percentagem total retida pelo sistema quando não existe afiliado válido (100% para o sistema).',
    'commission_due_days'        => 'Número de dias após o fecho da solicitação até à data de vencimento da comissão.',
    'rate_limit_post_max'        => 'Número máximo de pedidos POST por IP e rota durante a janela configurada.',
    'rate_limit_post_window_seconds' => 'Duração da janela temporal usada no rate limiting global de POST.',
    'trust_badge_monthly_fee'    => 'Preço cobrado por cada mês selecionado no pedido de selo.',
    'trust_badge_min_months'     => 'Menor duração permitida para solicitar o selo de confiança.',
    'trust_badge_max_months'     => 'Maior duração permitida para solicitar o selo de confiança.',
    'trust_badge_default_months' => 'Opção selecionada por padrão no formulário do perfil.',
    'trust_badge_min_won_deals' => 'Negociações com fecho ganho como proprietário ou promotor. Use 0 para desativar.',
    'trust_badge_min_account_days' => 'Dias desde o registo da conta. Use 0 para desativar.',
    'trust_badge_require_confirmed_closing' => '1 = só contam fechos confirmados; 0 = qualquer fecho ganho.',
    'boost_daily_fee'            => 'Preço cobrado por cada dia de destaque solicitado.',
    'boost_min_days'             => 'Menor duração permitida para solicitar destaque de imóvel.',
    'boost_max_days'             => 'Maior duração permitida para solicitar destaque de imóvel.',
    'boost_default_days'         => 'Número de dias selecionado por defeito no formulário de destaque.',
    'behavior_ranking_enabled'   => 'Safe mode: 0 desativado (recomendado no arranque), 1 ativado para ordenar por comportamento do utilizador dentro da prioridade comercial.',
    'behavior_ranking_lookback_days' => 'Quantidade de dias de histórico comportamental considerado por utilizador.',
    'behavior_weight_view'       => 'Peso do evento de visualização.',
    'behavior_weight_favorite'   => 'Peso do evento de favorito.',
    'behavior_weight_request'    => 'Peso do evento de solicitação de imóvel.',
    'behavior_max_score_per_property' => 'Limite máximo do score comportamental aplicado por imóvel para evitar dominância por repetição.',
    'behavior_decay_lambda'          => 'Quanto maior, mais rápido os eventos antigos perdem peso (ex.: 0.035).',
    'behavior_view_penalty_threshold'=> 'Visualizações sem favorito/pedido antes de penalizar.',
    'behavior_view_penalty_points'   => 'Pontos subtraídos do score quando o limiar é atingido.',
    'behavior_explore_ratio'         => 'Percentagem de slots com imóveis de exploração no perfil.',
    'behavior_impression_cooldown_hours' => 'Evita repetir o mesmo imóvel na mesma superfície durante N horas.',
    'behavior_home_carousel_size'    => 'Número de cards no carrossel da home.',
    'behavior_continue_exploring_size' => 'Número de cards no bloco Continuar a explorar.',
    'behavior_promoted_interval'       => 'A cada quantos imóveis orgânicos inserir um patrocinado (ex.: 4 = 1 a cada 4).',
    'account_deletion_grace_days'      => 'Dias entre o pedido do utilizador e a eliminação automática da conta.',
    'property_deletion_grace_days'     => 'Dias em que o imóvel fica «eliminado» (visível só em conversas) antes de ser removido do portfólio.',
    'deletion_reminder_days_before'    => 'Enviar email de lembrete quando faltar este número de dias para a eliminação automática (conta ou imóvel).',
];

$integerKeys = [
    'commission_due_days',
    'rate_limit_post_max',
    'rate_limit_post_window_seconds',
    'trust_badge_min_months',
    'trust_badge_max_months',
    'trust_badge_default_months',
    'boost_min_days',
    'boost_max_days',
    'boost_default_days',
    'behavior_ranking_lookback_days',
    'behavior_weight_view',
    'behavior_weight_favorite',
    'behavior_weight_request',
    'behavior_max_score_per_property',
    'behavior_view_penalty_threshold',
    'behavior_view_penalty_points',
    'behavior_explore_ratio',
    'behavior_impression_cooldown_hours',
    'behavior_home_carousel_size',
    'behavior_continue_exploring_size',
    'behavior_promoted_interval',
    'account_deletion_grace_days',
    'property_deletion_grace_days',
    'deletion_reminder_days_before',
];
?>
<?php
$dashboardPageClass = '';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Administração';
$heroTitle = 'Configurações do Sistema';
$heroLead = 'Ajuste os parâmetros operacionais da plataforma.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <?php if (!empty($success)): ?>
    <div class="alert-banner alert-banner-success" style="margin-bottom:1.5rem;">
        Configurações guardadas com sucesso.
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo DIRPAGE; ?>settings">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

        <?php
        // Group keys by section
        $groups = [
            ['kicker' => 'Comissões',  'title' => 'Percentuais e Prazos de Comissão', 'keys' => [
                'commission_system_pct', 'commission_affiliate_pct', 'commission_system_only_pct', 'commission_due_days',
            ]],
            ['kicker' => 'Segurança',  'title' => 'Rate Limiting de Requisições',      'keys' => [
                'rate_limit_post_max', 'rate_limit_post_window_seconds', 'account_deletion_grace_days', 'property_deletion_grace_days', 'deletion_reminder_days_before',
            ]],
            ['kicker' => 'Selo',       'title' => 'Selo de Utilizador de Confiança',   'keys' => [
                'trust_badge_monthly_fee', 'trust_badge_min_months', 'trust_badge_max_months', 'trust_badge_default_months',
                'trust_badge_min_won_deals', 'trust_badge_min_account_days', 'trust_badge_require_confirmed_closing',
            ]],
            ['kicker' => 'Destaque',   'title' => 'Destaque de Imóvel',               'keys' => [
                'boost_daily_fee', 'boost_min_days', 'boost_max_days', 'boost_default_days',
            ]],
            ['kicker' => 'Ranking',    'title' => 'Ranking Comportamental (Safe Mode)', 'keys' => [
                'behavior_ranking_enabled', 'behavior_ranking_lookback_days', 'behavior_weight_view', 'behavior_weight_favorite', 'behavior_weight_request', 'behavior_max_score_per_property',
                'behavior_decay_lambda', 'behavior_view_penalty_threshold', 'behavior_view_penalty_points', 'behavior_explore_ratio', 'behavior_impression_cooldown_hours', 'behavior_home_carousel_size', 'behavior_continue_exploring_size', 'behavior_promoted_interval',
            ]],
        ];
        ?>
        <?php foreach ($groups as $group): ?>
        <div class="dashboard-module-card" style="margin-bottom:2rem;">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker"><?php echo htmlspecialchars($group['kicker']); ?></span>
                    <h3><?php echo htmlspecialchars($group['title']); ?></h3>
                </div>
            </div>
            <div class="dashboard-form-grid" style="padding:1.5rem 2rem;">
                <?php foreach ($group['keys'] as $key):
                    $label = $labels[$key] ?? $key;
                    $val   = $map[$key]['value'] ?? '';
                ?>
                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label for="setting_<?php echo $key; ?>" style="font-weight:600;display:block;margin-bottom:.35rem;">
                        <?php echo htmlspecialchars($label); ?>
                    </label>
                    <?php if (!empty($descriptions[$key])): ?>
                    <p style="font-size:.85rem;color:var(--color-muted,#6c757d);margin:0 0 .5rem;">
                        <?php echo htmlspecialchars($descriptions[$key]); ?>
                    </p>
                    <?php endif; ?>
                    <input
                        type="number"
                        step="<?php echo in_array($key, $integerKeys, true) ? '1' : '0.01'; ?>"
                        min="0"
                        id="setting_<?php echo $key; ?>"
                        name="<?php echo $key; ?>"
                        value="<?php echo htmlspecialchars($val); ?>"
                        class="form-input<?php echo !empty($errors[$key]) ? ' input-error' : ''; ?>"
                        style="max-width:160px;"
                    >
                    <?php if (!empty($errors[$key])): ?>
                    <span class="input-error-msg" style="color:#c0392b;font-size:.83rem;">
                        <?php echo htmlspecialchars($errors[$key]); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="padding:.25rem 0 1.5rem;">
            <button type="submit" class="btn btn-primary">Guardar alterações</button>
        </div>
    </form>

<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
