<?php
$propertyCount = is_array($properties ?? null) ? count($properties) : 0;
$availableCount = 0;
$pendingCount = 0;
$rejectedCount = 0;
$pendingAffiliateCount = 0;
$propertiesWithAffiliateQueue = [];

foreach (($properties ?? []) as $propertyItem) {
    $propertyId = (int) ($propertyItem['id'] ?? 0);
    $status = (string) ($propertyItem['status'] ?? '');
    if ($status === 'disponivel') {
        $availableCount++;
    } elseif ($status === 'pendente') {
        $pendingCount++;
    } elseif ($status === 'rejeitado') {
        $rejectedCount++;
    }

    $affiliateQueue = $affiliateRequests[$propertyId] ?? [];
    $pendingAffiliateCount += count($affiliateQueue);

    if (!empty($affiliateQueue)) {
        $propertiesWithAffiliateQueue[] = [
            'property' => $propertyItem,
            'queue' => $affiliateQueue,
        ];
    }
}

$statusClassMap = [
    'disponivel' => 'dashboard-chip-success',
    'pendente' => 'dashboard-chip-warning',
    'rejeitado' => 'dashboard-chip-danger',
    'suspenso' => 'dashboard-chip-danger',
    'vendido' => 'dashboard-chip',
    'alugado' => 'dashboard-chip',
];

$boostPricing     = $boostPricing ?? \App\model\PropertyBoostRequest::getBoostPricingConfig();
$bpDailyFee       = (float) ($boostPricing['daily_fee'] ?? 2000);
$bpMinDays        = (int)   ($boostPricing['min_days']  ?? 7);
$bpMaxDays        = (int)   ($boostPricing['max_days']  ?? 90);
$bpDefaultDays    = (int)   ($boostPricing['default_days'] ?? 30);
$bpDefaultTotal   = number_format($bpDefaultDays * $bpDailyFee, 0, ',', '.');
$boostEligible = array_filter($properties ?? [], static function ($p) use ($pendingBoostIds) {
    return ($p['status'] ?? '') === 'disponivel' && empty($pendingBoostIds[(int) ($p['id'] ?? 0)]);
});
?>

<div class="container dashboard-view notification-inbox-view my-properties-dashboard-view">
    <section class="notification-inbox-hero my-properties-hero">
        <div class="notification-inbox-hero-main">
            <h1>Minhas Propriedades</h1>
            <p class="notification-inbox-hero-meta">
                <span><?php echo (int) $propertyCount; ?> no portfólio</span>
                <?php if ($availableCount > 0): ?>
                    <span class="notification-feed-dot" aria-hidden="true">·</span>
                    <span><?php echo (int) $availableCount; ?> disponíve<?php echo $availableCount === 1 ? 'l' : 'is'; ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php if (!empty($properties)): ?>
        <div class="notification-inbox-hero-actions">
            <a href="<?php echo DIRPAGE; ?>property/create" class="notification-inbox-text-btn">Cadastrar imóvel</a>
        </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($_GET['error'])): ?>
        <div class="sub-feedback error"><?php echo htmlspecialchars((string) $_GET['error']); ?></div>
    <?php elseif (!empty($_GET['success'])): ?>
        <div class="sub-feedback success"><?php echo htmlspecialchars((string) $_GET['success']); ?></div>
    <?php endif; ?>

    <section class="dashboard-overview-grid dashboard-overview-grid-tight dashboard-overview-section-gap my-properties-kpis">
        <div class="kpi-card">
            <div class="kpi-label">Total de imóveis</div>
            <div class="kpi-value"><?php echo $propertyCount; ?></div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-label">Disponíveis</div>
            <div class="kpi-value"><?php echo $availableCount; ?></div>
        </div>
        <div class="kpi-card kpi-yellow">
            <div class="kpi-label">Pendentes</div>
            <div class="kpi-value"><?php echo $pendingCount; ?></div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Pedidos de afiliação</div>
            <div class="kpi-value"><?php echo $pendingAffiliateCount; ?></div>
        </div>
    </section>

    <?php if (empty($properties)): ?>
        <div class="dashboard-module-card">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Começar</span>
                    <h3>Você ainda não tem propriedades cadastradas</h3>
                </div>
            </div>
            <p class="dashboard-inline-note">Crie o seu primeiro anúncio para começar a receber visualizações, contactos e solicitações de afiliação.</p>
            <div class="dashboard-inline-actions dashboard-empty-actions">
                <a href="<?php echo DIRPAGE; ?>property/create" class="btn-primary">Cadastrar primeira propriedade</a>
            </div>
        </div>
    <?php else: ?>
        <div class="my-properties-layout">
            <div class="my-properties-main">
                <div class="dashboard-properties-toolbar my-properties-toolbar">
                    <div class="my-properties-toolbar-copy">
                        <strong><?php echo $propertyCount; ?> imóvel<?php echo $propertyCount === 1 ? '' : 'is'; ?> no portfólio</strong>
                        <p class="dashboard-inline-note">Filtre por estado ou abra cada anúncio para gerir acções.</p>
                    </div>
                    <div class="my-properties-filter-bar" role="toolbar" aria-label="Filtrar imóveis por estado">
                        <button type="button" class="my-properties-filter-chip is-active" data-filter="all">
                            Todos <span><?php echo $propertyCount; ?></span>
                        </button>
                        <button type="button" class="my-properties-filter-chip" data-filter="disponivel">
                            Disponíveis <span><?php echo $availableCount; ?></span>
                        </button>
                        <button type="button" class="my-properties-filter-chip" data-filter="pendente">
                            Pendentes <span><?php echo $pendingCount; ?></span>
                        </button>
                        <?php if ($rejectedCount > 0): ?>
                        <button type="button" class="my-properties-filter-chip" data-filter="rejeitado">
                            Rejeitados <span><?php echo $rejectedCount; ?></span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-properties-grid my-properties-grid" id="my-properties-grid">
                    <?php foreach ($properties as $property): ?>
                        <?php
                        $propertyId = (int) ($property['id'] ?? 0);
                        $propertyStatus = (string) ($property['status'] ?? 'pendente');
                        $statusClass = $statusClassMap[$propertyStatus] ?? 'dashboard-chip';
                        $propertyImages = json_decode((string) ($property['images'] ?? '[]'), true);
                        $propertyFirstImage = (is_array($propertyImages) && !empty($propertyImages[0])) ? (string) $propertyImages[0] : '';
                        if ($propertyFirstImage !== '' && !preg_match('#^https?://#i', $propertyFirstImage)) {
                            $propertyFirstImage = DIRPAGE . ltrim($propertyFirstImage, '/');
                        }
                        $propertyCover = $propertyFirstImage !== '' ? $propertyFirstImage : (DIRIMG . 'apt20.avif');
                        $purposeLabel = ucfirst(str_replace('_', ' ', (string) ($property['purpose'] ?? 'nao informado')));
                        $typeLabel = Src\classes\PropertyTypeHelper::getLabel($property['type'] ?? null);
                        $affiliateQueue = $affiliateRequests[$propertyId] ?? [];
                        $hasAffiliateQueue = !empty($affiliateQueue);
                        $hasPendingBoost = !empty($pendingBoostIds[$propertyId]);
                        $isAvailable = $propertyStatus === 'disponivel';
                        $isFeatured = !empty($property['featured']);
                        ?>
                        <article class="dashboard-property-card my-properties-card" data-status="<?php echo htmlspecialchars($propertyStatus); ?>">
                            <div class="dashboard-property-media my-properties-card-media">
                                <img src="<?php echo htmlspecialchars($propertyCover); ?>" alt="<?php echo htmlspecialchars((string) ($property['title'] ?? 'Imóvel')); ?>">
                                <div class="my-properties-card-badges">
                                    <span class="dashboard-chip <?php echo $statusClass; ?>"><?php echo ucfirst($propertyStatus); ?></span>
                                    <?php if ($isFeatured): ?>
                                        <span class="dashboard-chip dashboard-chip-warning">★ Destaque</span>
                                    <?php elseif ($hasPendingBoost): ?>
                                        <span class="dashboard-chip dashboard-chip-warning">Destaque pendente</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="dashboard-property-head my-properties-card-head">
                                <div>
                                    <h5 class="card-title"><?php echo htmlspecialchars((string) ($property['title'] ?? 'Sem título')); ?></h5>
                                    <p class="dashboard-inline-note"><?php echo htmlspecialchars((string) ($property['location'] ?? 'Localização não informada')); ?></p>
                                </div>
                            </div>

                            <div class="dashboard-property-price"><?php echo number_format((float) ($property['price'] ?? 0), 0, ',', '.'); ?> Kz</div>

                            <div class="dashboard-property-meta-grid">
                                <div>
                                    <span>Tipo</span>
                                    <strong><?php echo htmlspecialchars($typeLabel); ?></strong>
                                </div>
                                <div>
                                    <span>Finalidade</span>
                                    <strong><?php echo htmlspecialchars($purposeLabel); ?></strong>
                                </div>
                                <div>
                                    <span>Quartos</span>
                                    <strong><?php echo (int) ($property['bedrooms'] ?? 0); ?></strong>
                                </div>
                                <div>
                                    <span>Banhos</span>
                                    <strong><?php echo (int) ($property['bathrooms'] ?? 0); ?></strong>
                                </div>
                            </div>

                            <div class="my-properties-actions">
                                <div class="dashboard-inline-actions my-properties-actions-primary">
                                    <a href="<?php echo DIRPAGE; ?>property/<?php echo $propertyId; ?>" class="btn-primary">Ver anúncio</a>
                                    <a href="<?php echo DIRPAGE; ?>property/edit/<?php echo $propertyId; ?>" class="btn-secondary">Editar</a>
                                </div>

                                <?php if ($isAvailable || $hasAffiliateQueue): ?>
                                <div class="dashboard-inline-actions my-properties-actions-secondary">
                                    <?php if ($isAvailable): ?>
                                        <form method="POST" action="<?php echo DIRPAGE; ?>property/setStatus/<?php echo $propertyId; ?>" class="request-actions">
                                            <?php echo Src\classes\ClassCsrf::field(); ?>
                                            <input type="hidden" name="new_status" value="vendido">
                                            <button type="submit" class="btn-secondary" data-confirm="Marcar este imóvel como vendido?">Marcar vendido</button>
                                        </form>

                                        <form method="POST" action="<?php echo DIRPAGE; ?>property/setStatus/<?php echo $propertyId; ?>" class="request-actions">
                                            <?php echo Src\classes\ClassCsrf::field(); ?>
                                            <input type="hidden" name="new_status" value="alugado">
                                            <button type="submit" class="btn-secondary" data-confirm="Marcar este imóvel como alugado?">Marcar alugado</button>
                                        </form>

                                        <?php if (!$hasPendingBoost && !$isFeatured): ?>
                                            <a href="#boost-section" class="btn-secondary">Solicitar destaque</a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($hasAffiliateQueue): ?>
                                        <a href="#affiliate-queue" class="btn-secondary">
                                            Pedidos de afiliação (<?php echo count($affiliateQueue); ?>)
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($hasAffiliateQueue): ?>
                                <div class="dashboard-property-queue-note">
                                    <span class="dashboard-affiliate-count"><?php echo count($affiliateQueue); ?></span>
                                    <p>Este imóvel tem solicitações de afiliação pendentes na fila abaixo.</p>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <p class="my-properties-empty-filter dashboard-inline-note" id="my-properties-empty-filter" hidden>
                    Nenhum imóvel corresponde a este filtro.
                </p>

                <?php if (!empty($propertiesWithAffiliateQueue)): ?>
                    <section id="affiliate-queue" class="dashboard-module-card dashboard-affiliate-queue-section my-properties-affiliate-queue">
                        <div class="dashboard-module-head compact">
                            <div>
                                <span class="dashboard-module-kicker">Operação</span>
                                <h3>Fila de afiliações pendentes</h3>
                            </div>
                        </div>
                        <p class="dashboard-inline-note">Concentre aqui a aprovação ou rejeição de afiliados sem alongar os cards do portfólio.</p>

                        <div class="dashboard-affiliate-queue-groups">
                            <?php foreach ($propertiesWithAffiliateQueue as $queueGroup): ?>
                                <?php
                                $queueProperty = $queueGroup['property'];
                                $queueItems = $queueGroup['queue'];
                                $queuePropertyId = (int) ($queueProperty['id'] ?? 0);
                                ?>
                                <section class="dashboard-affiliate-queue-card">
                                    <div class="dashboard-property-affiliates-head">
                                        <div>
                                            <h6><?php echo htmlspecialchars((string) ($queueProperty['title'] ?? 'Sem título')); ?></h6>
                                            <p class="dashboard-inline-note"><?php echo htmlspecialchars((string) ($queueProperty['location'] ?? 'Localização não informada')); ?></p>
                                        </div>
                                        <div class="dashboard-inline-actions">
                                            <span class="dashboard-affiliate-count"><?php echo count($queueItems); ?></span>
                                            <a href="<?php echo DIRPAGE; ?>property/<?php echo $queuePropertyId; ?>" class="btn-secondary">Abrir imóvel</a>
                                        </div>
                                    </div>

                                    <div class="dashboard-affiliate-list">
                                        <?php foreach ($queueItems as $req): ?>
                                            <div class="dashboard-affiliate-item">
                                                <div class="dashboard-affiliate-copy">
                                                    <strong><?php echo htmlspecialchars($req['name']); ?></strong>
                                                    <small><?php echo htmlspecialchars($req['email']); ?></small>
                                                    <?php if (!empty($req['phone'])): ?>
                                                        <small><?php echo htmlspecialchars($req['phone']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="dashboard-inline-actions">
                                                    <form method="POST" action="<?php echo DIRPAGE; ?>request/approveAffiliate/<?php echo $req['id']; ?>">
                                                        <?php echo Src\classes\ClassCsrf::field(); ?>
                                                        <button type="submit" class="btn-primary">Aprovar</button>
                                                    </form>
                                                    <form method="POST" action="<?php echo DIRPAGE; ?>request/rejectAffiliate/<?php echo $req['id']; ?>">
                                                        <?php echo Src\classes\ClassCsrf::field(); ?>
                                                        <button type="submit" class="btn-secondary">Rejeitar</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="my-properties-aside" aria-label="Solicitar destaque">
                <div class="dashboard-module-card my-properties-boost-card" id="boost-section">
                    <div class="dashboard-module-head compact">
                        <div>
                            <span class="dashboard-module-kicker">Destaque</span>
                            <h3>Solicitar destaque</h3>
                        </div>
                    </div>
                    <div class="dashboard-profile-summary">
                        <?php if (empty($boostEligible)): ?>
                            <p class="dashboard-inline-note">Não tem imóveis disponíveis sem destaque pendente neste momento.</p>
                        <?php else: ?>
                            <p class="my-properties-boost-intro">Escolha o imóvel, defina a duração e envie o comprovativo de pagamento.</p>

                            <form id="boost-request-form"
                                  action="<?php echo DIRPAGE; ?>property/requestBoost/0"
                                  method="POST"
                                  enctype="multipart/form-data"
                                  class="dashboard-trust-form my-properties-boost-form">
                                <?php echo Src\classes\ClassCsrf::field(); ?>

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
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>
