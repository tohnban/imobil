<?php
/** @var array $plan */
/** @var int $occupancyRate */
/** @var int $requests30d */
/** @var int $requests90d */
?>
<section class="property-reports-hero">
    <div class="property-reports-banner">
        <span class="property-plan-chip">
            <i class="fa fa-chart-line" aria-hidden="true"></i>
            <?php echo htmlspecialchars((string) ($plan['name'] ?? 'Plano'), ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <h1>Relatórios de Imóveis</h1>
        <p>Monitore o desempenho da sua carteira, acompanhe ritmo de solicitações e identifique oportunidades de conversão num único painel.</p>
    </div>

    <aside class="property-reports-quick">
        <div class="property-quick-item">
            <div class="property-quick-label">Taxa de ocupação</div>
            <div class="property-quick-value"><?php echo (int) $occupancyRate; ?>%</div>
        </div>
        <div class="property-quick-item">
            <div class="property-quick-label">Solicitações (30 dias)</div>
            <div class="property-quick-value"><?php echo (int) $requests30d; ?></div>
        </div>
        <div class="property-quick-item">
            <div class="property-quick-label">Solicitações (90 dias)</div>
            <div class="property-quick-value"><?php echo (int) $requests90d; ?></div>
        </div>
    </aside>
</section>
