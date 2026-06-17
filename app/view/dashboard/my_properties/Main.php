<?php
$propertyCount = is_array($properties ?? null) ? count($properties) : 0;
$availableCount = 0;
$pendingCount = 0;
$rejectedCount = 0;
$deletedCount = 0;
$soldCount = 0;
$rentedCount = 0;

foreach (($properties ?? []) as $propertyItem) {
    $status = (string) ($propertyItem['status'] ?? '');
    if ($status === 'disponivel') {
        $availableCount++;
    } elseif ($status === 'pendente' || $status === 'em_analise') {
        $pendingCount++;
    } elseif ($status === 'rejeitado') {
        $rejectedCount++;
    } elseif ($status === 'eliminado') {
        $deletedCount++;
    } elseif ($status === 'vendido') {
        $soldCount++;
    } elseif ($status === 'alugado') {
        $rentedCount++;
    }
}

$statusClassMap = [
    'disponivel' => 'dashboard-chip-success',
    'pendente' => 'dashboard-chip-warning',
    'em_analise' => 'dashboard-chip-warning',
    'rejeitado' => 'dashboard-chip-danger',
    'suspenso' => 'dashboard-chip-danger',
    'vendido' => 'dashboard-chip',
    'alugado' => 'dashboard-chip',
    'eliminado' => 'dashboard-chip-danger',
];

$propertyStatusLabels = [
    'disponivel' => 'Disponível',
    'vendido' => 'Vendido',
    'alugado' => 'Alugado',
    'pendente' => 'Pendente',
    'em_analise' => 'Em análise',
    'rejeitado' => 'Rejeitado',
    'suspenso' => 'Suspenso',
    'eliminado' => 'Eliminado',
];

$propertyDeletionGraceDays = max(1, (int) ($propertyDeletionGraceDays ?? \App\model\Property::getPropertyDeletionGraceDays()));

$boostPricing     = $boostPricing ?? \App\model\PropertyBoostRequest::getBoostPricingConfig();
$bpDailyFee       = (float) ($boostPricing['daily_fee'] ?? 2000);
$bpMinDays        = (int)   ($boostPricing['min_days']  ?? 7);
$bpMaxDays        = (int)   ($boostPricing['max_days']  ?? 90);
$bpDefaultDays    = (int)   ($boostPricing['default_days'] ?? 30);
$bpDefaultTotal   = number_format($bpDefaultDays * $bpDailyFee, 0, ',', '.');
$boostEligible = array_filter($properties ?? [], static function ($p) use ($pendingBoostIds) {
    return ($p['status'] ?? '') === 'disponivel' && empty($pendingBoostIds[(int) ($p['id'] ?? 0)]);
});

$summaryParts = [
    $propertyCount . ' imóvel' . ($propertyCount === 1 ? '' : 'is'),
];
if ($availableCount > 0) {
    $summaryParts[] = $availableCount . ' disponíve' . ($availableCount === 1 ? 'l' : 'is');
}
if ($pendingCount > 0) {
    $summaryParts[] = $pendingCount . ' pendente' . ($pendingCount === 1 ? '' : 's');
}
if ($deletedCount > 0) {
    $summaryParts[] = $deletedCount . ' a eliminar';
}
$portfolioSummary = implode(' · ', $summaryParts);

$rowContext = [
    'pendingBoostIds' => $pendingBoostIds ?? [],
    'statusClassMap' => $statusClassMap,
    'propertyStatusLabels' => $propertyStatusLabels,
    'propertyDeletionGraceDays' => $propertyDeletionGraceDays,
];
?>
<?php
$dashboardPageClass = 'notification-inbox-view my-properties-dashboard-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$inboxHeroTitle = 'Minhas Propriedades';
if ($propertyCount > 0) {
    $inboxHeroMeta = $portfolioSummary;
} else {
    $inboxHeroMeta = 'Publique e gira todos os seus anúncios num só lugar.';
}
$inboxHeroClass = 'my-properties-hero';
ob_start();
?>
<a href="<?php echo DIRPAGE; ?>property/create" class="btn-primary my-properties-hero-cta">
    <i class="fa fa-plus" aria-hidden="true"></i>
    Cadastrar imóvel
</a>
<?php
$inboxHeroActionsHtml = ob_get_clean();
$inboxHeroActionsClass = 'my-properties-hero-actions';
include DIRREQ . 'app/view/partials/dashboard_inbox_hero.php';
unset($inboxHeroActionsHtml, $inboxHeroActionsClass);
?>

    <?php if (empty($properties)): ?>
        <div class="dashboard-module-card my-properties-empty-card">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Começar</span>
                    <h3>Ainda não tem imóveis no portfólio</h3>
                </div>
            </div>
            <p class="dashboard-inline-note">Publique o primeiro anúncio para receber visualizações, contactos e pedidos de afiliação.</p>
            <div class="dashboard-inline-actions dashboard-empty-actions">
                <a href="<?php echo DIRPAGE; ?>property/create" class="btn-primary">Cadastrar primeira propriedade</a>
            </div>
        </div>
    <?php else: ?>
        <div class="my-properties-stack">
            <div class="dashboard-module-card my-properties-boost-card" id="boost-section">
                <div class="dashboard-module-head compact my-properties-boost-head">
                    <div>
                        <span class="dashboard-module-kicker">Visibilidade</span>
                        <h3>Solicitar destaque</h3>
                    </div>
                    <?php if (!empty($boostEligible)): ?>
                    <p class="dashboard-inline-note my-properties-boost-head-note">
                        <?php echo count($boostEligible); ?> imóvel<?php echo count($boostEligible) === 1 ? '' : 'is'; ?> elegíve<?php echo count($boostEligible) === 1 ? 'l' : 'is'; ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="dashboard-profile-summary">
                    <?php if (empty($boostEligible)): ?>
                        <p class="dashboard-inline-note">Não há imóveis disponíveis elegíveis para destaque neste momento.</p>
                    <?php else: ?>
                        <p class="my-properties-boost-intro">Destaque um anúncio disponível para aumentar a visibilidade na plataforma.</p>

                        <form id="boost-request-form"
                              action="<?php echo DIRPAGE; ?>property/requestBoost/0"
                              method="POST"
                              enctype="multipart/form-data"
                              class="dashboard-trust-form my-properties-boost-form">
                            <?php echo Src\classes\ClassCsrf::field(); ?>

                            <div class="my-properties-boost-layout">
                                <div class="my-properties-boost-fields">
                                    <div class="my-properties-boost-form-block">
                                        <div class="form-group">
                                            <label for="boost_property_id">Imóvel</label>
                                            <select id="boost_property_id" name="boost_property_id" required>
                                                <option value="">— Selecione um imóvel —</option>
                                                <?php foreach ($boostEligible as $bp): ?>
                                                    <option value="<?php echo (int) ($bp['id'] ?? 0); ?>">
                                                        <?php echo htmlspecialchars((string) ($bp['title'] ?? 'Sem título')); ?>
                                                        — <?php echo number_format((float) ($bp['price'] ?? 0), 0, ',', '.'); ?> Kz
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="boost_duration_days">Duração (dias)</label>
                                            <input type="number" id="boost_duration_days" name="duration_days"
                                                   min="<?php echo $bpMinDays; ?>" max="<?php echo $bpMaxDays; ?>"
                                                   value="<?php echo $bpDefaultDays; ?>" required
                                                   data-daily-fee="<?php echo htmlspecialchars(number_format($bpDailyFee, 2, '.', '')); ?>">
                                            <small class="dashboard-inline-note">
                                                <?php echo number_format($bpDailyFee, 0, ',', '.'); ?> Kz/dia ·
                                                mín. <?php echo $bpMinDays; ?> · máx. <?php echo $bpMaxDays; ?> dias
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="my-properties-boost-side">
                                    <div class="my-properties-boost-total">
                                        <span class="my-properties-boost-total-kicker">Valor total a pagar</span>
                                        <strong id="boostTotalValue"><?php echo $bpDefaultTotal; ?> Kz</strong>
                                    </div>

                                    <div class="my-properties-boost-form-block">
                                        <div class="form-group">
                                            <label for="boost_payment_proof">Comprovativo <span class="required-mark">*</span></label>
                                            <input type="file"
                                                   id="boost_payment_proof"
                                                   name="boost_payment_proof"
                                                   class="my-properties-file-input"
                                                   accept="image/*"
                                                   required>
                                            <small class="dashboard-inline-note" id="boostProofFeedback">
                                                JPG, PNG ou WebP, até <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?>.
                                            </small>
                                            <div id="boostProofPreviewWrap" class="my-properties-proof-preview" hidden>
                                                <img id="boostProofPreview" src="" alt="Pré-visualização">
                                                <small id="boostProofPreviewMeta"></small>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-primary my-properties-boost-submit">Solicitar destaque</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-module-card my-properties-portfolio-card">
                <div class="dashboard-module-head compact my-properties-portfolio-title">
                    <div>
                        <span class="dashboard-module-kicker">Portfólio</span>
                        <h3>Os seus anúncios</h3>
                    </div>
                    <p class="my-properties-results-count dashboard-inline-note" id="my-properties-results-count" aria-live="polite">
                        A mostrar <?php echo (int) $propertyCount; ?> de <?php echo (int) $propertyCount; ?> imóveis
                    </p>
                </div>

                <div class="my-properties-controls">
                    <label class="my-properties-search" for="my-properties-search">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <input type="search"
                               id="my-properties-search"
                               placeholder="Pesquisar por título, localização ou tipo…"
                               autocomplete="off"
                               enterkeyhint="search">
                    </label>

                    <div class="my-properties-filter-shell">
                        <span class="my-properties-filter-label" id="my-properties-filter-label">Estado</span>
                        <div class="my-properties-filter-bar"
                             role="toolbar"
                             aria-labelledby="my-properties-filter-label">
                            <button type="button" class="my-properties-filter-chip is-active" data-filter="all" aria-pressed="true">
                                Todos <span><?php echo $propertyCount; ?></span>
                            </button>
                            <?php if ($availableCount > 0): ?>
                            <button type="button" class="my-properties-filter-chip" data-filter="disponivel" aria-pressed="false">
                                Disponíveis <span><?php echo $availableCount; ?></span>
                            </button>
                            <?php endif; ?>
                            <?php if ($pendingCount > 0): ?>
                            <button type="button" class="my-properties-filter-chip" data-filter="pendente" aria-pressed="false">
                                Pendentes <span><?php echo $pendingCount; ?></span>
                            </button>
                            <?php endif; ?>
                            <?php if ($soldCount > 0): ?>
                            <button type="button" class="my-properties-filter-chip" data-filter="vendido" aria-pressed="false">
                                Vendidos <span><?php echo $soldCount; ?></span>
                            </button>
                            <?php endif; ?>
                            <?php if ($rentedCount > 0): ?>
                            <button type="button" class="my-properties-filter-chip" data-filter="alugado" aria-pressed="false">
                                Alugados <span><?php echo $rentedCount; ?></span>
                            </button>
                            <?php endif; ?>
                            <?php if ($rejectedCount > 0): ?>
                            <button type="button" class="my-properties-filter-chip" data-filter="rejeitado" aria-pressed="false">
                                Rejeitados <span><?php echo $rejectedCount; ?></span>
                            </button>
                            <?php endif; ?>
                            <?php if ($deletedCount > 0): ?>
                            <button type="button" class="my-properties-filter-chip" data-filter="eliminado" aria-pressed="false">
                                A eliminar <span><?php echo $deletedCount; ?></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="notification-inbox-panel my-properties-list-panel">
                    <div class="my-properties-list" id="my-properties-grid">
                        <?php foreach ($properties as $property): ?>
                            <?php
                            $property = $property;
                            extract($rowContext, EXTR_SKIP);
                            require DIRREQ . 'app/view/partials/my_property_portfolio_row.php';
                            ?>
                        <?php endforeach; ?>
                    </div>

                    <p class="my-properties-empty-filter dashboard-inline-note" id="my-properties-empty-filter" hidden>
                        Nenhum imóvel corresponde à pesquisa ou filtro seleccionado.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
