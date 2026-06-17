<?php
/** @var array $summary */
$summary = is_array($summary ?? null) ? $summary : [];
$kpiSectionClass = isset($affiliateKpiSectionClass) ? trim((string) $affiliateKpiSectionClass) : 'dashboard-overview-grid dashboard-overview-grid-tight dashboard-kpi-section afiliados-kpis';
?>
<div class="<?php echo htmlspecialchars($kpiSectionClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="kpi-card">
        <div class="kpi-label">Total gerado</div>
        <div class="kpi-value"><?php echo number_format((float) ($summary['earned_total'] ?? 0), 0, ',', '.'); ?> Kz</div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-label">Já recebido</div>
        <div class="kpi-value"><?php echo number_format((float) ($summary['earned_paid'] ?? 0), 0, ',', '.'); ?> Kz</div>
    </div>
    <div class="kpi-card kpi-yellow">
        <div class="kpi-label">Pendente</div>
        <div class="kpi-value"><?php echo number_format((float) ($summary['earned_pending'] ?? 0), 0, ',', '.'); ?> Kz</div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-label">Este mês</div>
        <div class="kpi-value"><?php echo number_format((float) ($summary['earned_this_month'] ?? 0), 0, ',', '.'); ?> Kz</div>
    </div>
</div>
