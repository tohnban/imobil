<?php
/** @var array $user */
/** @var array $stats */
/** @var array $plan */
/** @var bool $isAdvanced */

$summary     = $stats['summary']     ?? [];
$perProperty = $stats['per_property'] ?? [];
$trend       = $stats['trend']        ?? [];

$totalPublished = (int) ($summary['total_properties'] ?? 0);
$totalAvailable = (int) ($summary['available'] ?? 0);
$totalSold = (int) ($summary['sold'] ?? 0);
$totalRented = (int) ($summary['rented'] ?? 0);
$totalFeatured = (int) ($summary['featured'] ?? 0);
$totalPendingDeletion = (int) ($summary['pending_deletion'] ?? 0);
$totalNewMonth = (int) ($summary['new_this_month'] ?? 0);

$totalClosed = $totalSold + $totalRented;
$occupancyRate = $totalPublished > 0 ? (int) round(($totalClosed / $totalPublished) * 100) : 0;

$requests30d = 0;
$requests90d = 0;
foreach ($perProperty as $propRow) {
    $requests30d += (int) ($propRow['requests_last_30d'] ?? 0);
    $requests90d += (int) ($propRow['requests_last_90d'] ?? 0);
}

$statusLabelMap = [
    'available' => 'Disponível',
    'sold' => 'Vendido',
    'rented' => 'Alugado',
    'pending' => 'Pendente',
    'inactive' => 'Inativo',
];

$purposeLabelMap = [
    'sale' => 'Venda',
    'rent' => 'Arrendamento',
    'both' => 'Venda/Arrendamento',
];
?>
<?php
$dashboardPageClass = 'property-reports-dashboard-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
?>

    <div class="property-reports-shell">

    <?php include DIRREQ . 'app/view/partials/property_reports_hero.php'; ?>

    <div class="property-kpi-grid">
        <div class="property-kpi-card kpi-accent">
            <div class="property-kpi-label">Total publicados</div>
            <div class="property-kpi-value"><?php echo $totalPublished; ?></div>
            <div class="property-kpi-helper">Base ativa do seu portfólio</div>
        </div>

        <div class="property-kpi-card">
            <div class="property-kpi-label">Disponíveis</div>
            <div class="property-kpi-value"><?php echo $totalAvailable; ?></div>
            <div class="property-kpi-helper">Prontos para novas propostas</div>
        </div>

        <div class="property-kpi-card">
            <div class="property-kpi-label">Vendidos</div>
            <div class="property-kpi-value"><?php echo $totalSold; ?></div>
            <div class="property-kpi-helper">Conversões em compra</div>
        </div>

        <div class="property-kpi-card">
            <div class="property-kpi-label">Alugados</div>
            <div class="property-kpi-value"><?php echo $totalRented; ?></div>
            <div class="property-kpi-helper">Conversões em arrendamento</div>
        </div>

        <div class="property-kpi-card">
            <div class="property-kpi-label">Em destaque</div>
            <div class="property-kpi-value"><?php echo $totalFeatured; ?></div>
            <div class="property-kpi-helper">Imóveis com maior visibilidade</div>
        </div>

        <?php if ($totalPendingDeletion > 0): ?>
        <div class="property-kpi-card">
            <div class="property-kpi-label">A eliminar</div>
            <div class="property-kpi-value"><?php echo $totalPendingDeletion; ?></div>
            <div class="property-kpi-helper">Em período de conformidade</div>
        </div>
        <?php endif; ?>

        <div class="property-kpi-card">
            <div class="property-kpi-label">Novos este mês</div>
            <div class="property-kpi-value"><?php echo $totalNewMonth; ?></div>
            <div class="property-kpi-helper">Novas entradas na carteira</div>
        </div>
    </div>

    <?php if (!empty($trend)): ?>
    <div class="property-module-card">
        <div class="property-module-head">
            <div>
                <span class="property-module-kicker">Últimos 6 meses</span>
                <h3>Tendência de solicitações</h3>
            </div>
            <div class="property-kpi-helper">Comparativo mensal de demanda</div>
        </div>
        <div class="property-trend-wrap">
            <div class="property-trend-chart">
                <?php
                $maxCount = max(1, ...array_column($trend, 'request_count'));
                foreach ($trend as $point):
                    $count = (int) ($point['request_count'] ?? 0);
                    $barTier = max(1, min(24, (int) ceil(($count / $maxCount) * 24)));
                ?>
                <div class="property-trend-col">
                    <div
                        class="property-trend-bar bar-tier-<?php echo $barTier; ?>"
                        title="<?php echo $count; ?> solicitações"
                    ></div>
                    <div class="property-trend-month"><?php echo htmlspecialchars((string) ($point['month_label'] ?? '-')); ?></div>
                    <div class="property-trend-value"><?php echo (int) ($point['request_count'] ?? 0); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="property-module-card property-performance-module">
        <div class="property-module-head">
            <div>
                <span class="property-module-kicker">Por imóvel</span>
                <h3>Desempenho por propriedade</h3>
            </div>
        </div>
        <div class="property-table-wrap property-performance-table-wrap">
            <table class="table table-modern property-performance-table">
                <thead>
                <tr>
                    <th>Imóvel</th>
                    <th>Status</th>
                    <th>Finalidade</th>
                    <th>Solicitações totais</th>
                    <th>Últimos 30d</th>
                    <th>Últimos 90d</th>
                    <?php if ($isAdvanced): ?>
                    <th>Confirmadas</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($perProperty)): ?>
                    <tr class="property-performance-empty">
                        <td colspan="<?php echo $isAdvanced ? 7 : 6; ?>">
                            <div class="empty-state-content">
                                <i class="fa fa-building-o"></i>
                                <p>Sem imóveis publicados ainda.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($perProperty as $row): ?>
                    <?php
                        $statusCode = strtolower((string) ($row['property_status'] ?? ''));
                        $purposeCode = strtolower((string) ($row['purpose'] ?? ''));

                        $statusLabel = $statusLabelMap[$statusCode] ?? ((string) ($row['property_status'] ?? '-'));
                        $purposeLabel = $purposeLabelMap[$purposeCode] ?? ((string) ($row['purpose'] ?? '-'));

                        $statusClass = 'status-default';
                        if (isset($statusLabelMap[$statusCode])) {
                            $statusClass = 'status-' . $statusCode;
                        }

                        $purposeClass = 'purpose-default';
                        if (isset($purposeLabelMap[$purposeCode])) {
                            $purposeClass = 'purpose-' . $purposeCode;
                        }
                    ?>
                    <tr class="property-performance-row">
                        <td class="property-title-cell" data-label="Imóvel"><?php echo htmlspecialchars((string) ($row['title'] ?? '-')); ?></td>
                        <td data-label="Status">
                            <span class="property-tag <?php echo htmlspecialchars($statusClass); ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </td>
                        <td data-label="Finalidade">
                            <span class="property-tag <?php echo htmlspecialchars($purposeClass); ?>">
                                <?php echo htmlspecialchars($purposeLabel); ?>
                            </span>
                        </td>
                        <td data-label="Total"><span class="property-metric-pill"><?php echo (int) ($row['total_requests'] ?? 0); ?></span></td>
                        <td data-label="30 dias"><span class="property-metric-pill"><?php echo (int) ($row['requests_last_30d'] ?? 0); ?></span></td>
                        <td data-label="90 dias"><span class="property-metric-pill"><?php echo (int) ($row['requests_last_90d'] ?? 0); ?></span></td>
                        <?php if ($isAdvanced): ?>
                        <td data-label="Confirmadas"><span class="property-metric-pill"><?php echo (int) ($row['confirmed_requests'] ?? 0); ?></span></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$isAdvanced): ?>
        <div class="property-upgrade-note">
            <small>
                Para relatórios avançados (conversão, receita por imóvel), actualize para o Plano Empresarial.
                <a href="<?php echo DIRPAGE; ?>dashboard/subscription">Ver planos</a>
            </small>
        </div>
        <?php endif; ?>
    </div>

    </div>

<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
