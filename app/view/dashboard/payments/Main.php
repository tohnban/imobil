<?php
$allowedTabs = ['trust', 'boosts', 'commissions', 'subscriptions', 'history'];

$activeTab = (string) ($activeTab ?? '');
if (!in_array($activeTab, $allowedTabs, true)) {
    $requestedTab = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
    if (!in_array($requestedTab, $allowedTabs, true)) {
        if (!empty($_GET['boost_id'])) {
            $requestedTab = 'boosts';
        } elseif (!empty($_GET['highlight'])) {
            $requestedTab = 'commissions';
        } elseif (!empty($_GET['user'])) {
            $requestedTab = 'trust';
        } else {
            $requestedTab = 'trust';
        }
    }
    $activeTab = $requestedTab;
}

$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));

$pendingCommissionsCount = (int) ($pendingCommissionsCount ?? count($pendingCommissions ?? []));
$affiliatePayoutCount = (int) ($affiliatePayoutCount ?? 0);
$commissionsTabPendingCount = (int) ($commissionsTabPendingCount ?? ($pendingCommissionsCount + $affiliatePayoutCount));
$pendingBoostsCount      = (int) ($pendingBoostsCount ?? count($pendingBoosts ?? []));
$pendingTrustCount       = (int) ($pendingTrustCount ?? count($pendingTrust ?? []));
$pendingSubscriptionFeesCount = (int) ($pendingSubscriptionFeesCount ?? 0);
$subscriptionTransactionsCount = (int) ($subscriptionTransactionsCount ?? count($subscriptionTransactions ?? []));

function paymentsTabUrl(string $tab, int $targetPage = 1, string $section = ''): string {
    $params = ['tab' => $tab];
    if ($targetPage > 1) {
        $params['page'] = $targetPage;
    }
    if ($section !== '' && $tab === 'commissions') {
        $params['section'] = $section;
    }
    $highlight = trim((string) ($_GET['highlight'] ?? ''));
    if ($highlight !== '' && $tab === 'commissions') {
        $params['highlight'] = $highlight;
    }
    return '?' . http_build_query($params);
}
?>

<div class="container dashboard-view payments-admin-view">
    <section class="dashboard-view-hero compact">
        <div>
            <span class="dashboard-hero-kicker">Financeiro</span>
            <h1>Central de Pagamentos</h1>
            <p>Gerencie confirmações financeiras de comissões, destaques de imóveis e selo de confiança.</p>
        </div>
    </section>

    <?php if (!empty($_GET['error'])): ?>
        <div class="sub-feedback error"><?php echo htmlspecialchars((string) $_GET['error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['success'])): ?>
        <div class="sub-feedback success"><?php echo htmlspecialchars((string) $_GET['success']); ?></div>
    <?php endif; ?>

    <div class="dashboard-overview-grid dashboard-overview-grid-tight dashboard-kpi-section">
        <div class="kpi-card kpi-yellow">
            <div class="kpi-label">Comissões pendentes</div>
            <div class="kpi-value"><?php echo $commissionsTabPendingCount; ?></div>
        </div>
        <div class="kpi-card kpi-yellow">
            <div class="kpi-label">Valor em validação</div>
            <div class="kpi-value kpi-value-prominent"><?php echo number_format((float)$pendingTotal, 0, ',', '.'); ?> Kz</div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Destaques pendentes</div>
            <div class="kpi-value"><?php echo $pendingBoostsCount; ?></div>
        </div>
        <div class="kpi-card kpi-yellow">
            <div class="kpi-label">Selos pendentes</div>
            <div class="kpi-value"><?php echo $pendingTrustCount; ?></div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Subscrições pendentes</div>
            <div class="kpi-value"><?php echo $pendingSubscriptionFeesCount; ?></div>
        </div>
    </div>

    <div class="dashboard-tab-nav payments-admin-tabs">
        <a href="<?php echo paymentsTabUrl('trust'); ?>" class="dashboard-tab-link <?php echo $activeTab === 'trust' ? 'is-active' : ''; ?>">
            <i class="fa fa-shield"></i> Selo de Confiança (<?php echo $pendingTrustCount; ?>)
        </a>
        <a href="<?php echo paymentsTabUrl('boosts'); ?>" class="dashboard-tab-link <?php echo $activeTab === 'boosts' ? 'is-active' : ''; ?>">
            <i class="fa fa-star"></i> Destaques (<?php echo $pendingBoostsCount; ?>)
        </a>
        <a href="<?php echo paymentsTabUrl('commissions'); ?>" class="dashboard-tab-link <?php echo $activeTab === 'commissions' ? 'is-active' : ''; ?>">
            <i class="fa fa-money"></i> Comissões (<?php echo $commissionsTabPendingCount; ?>)
        </a>
        <a href="<?php echo paymentsTabUrl('subscriptions'); ?>" class="dashboard-tab-link <?php echo $activeTab === 'subscriptions' ? 'is-active' : ''; ?>">
            <i class="fa fa-id-card"></i> Subscrições (<?php echo $pendingSubscriptionFeesCount; ?>)
        </a>
        <a href="<?php echo paymentsTabUrl('history'); ?>" class="dashboard-tab-link <?php echo $activeTab === 'history' ? 'is-active' : ''; ?>">
            <i class="fa fa-archive"></i> Histórico
        </a>
    </div>

    <?php if ($activeTab === 'trust'): ?>
    <div class="dashboard-module-card dashboard-kpi-section payments-admin-card">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Fila</span>
                <h3>Pagamentos de selo de confiança</h3>
            </div>
        </div>

        <?php if (!empty($pendingTrust)): ?>
            <div class="dashboard-table-wrap">
                <table class="commissions-table moderation-table moderation-table-trust">
                    <thead>
                        <tr>
                            <th>Utilizador</th>
                            <th>Email</th>
                            <th>Pedido</th>
                            <th>Duração</th>
                            <th>Taxa (Kz)</th>
                            <th>Comprovativo</th>
                            <th>Pagamento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingTrust as $trustUser): ?>
                            <?php
                                $proofPath = trim((string) ($trustUser['trust_badge_payment_proof'] ?? ''));
                                $proofUrl = $proofPath !== '' ? \Src\classes\ClassMediaUrl::proof($proofPath) : '';
                                $feePaid = !empty($trustUser['trust_badge_fee_paid']);
                            ?>
                            <tr data-focus-user-id="<?php echo (int) ($trustUser['id'] ?? 0); ?>">
                                <td class="moderation-cell-text"><a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int)($trustUser['id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars($trustUser['name'] ?? '—'); ?></a></td>
                                <td class="moderation-cell-text"><?php echo htmlspecialchars($trustUser['email'] ?? '—'); ?></td>
                                <td class="dashboard-inline-note dashboard-cell-nowrap"><?php echo !empty($trustUser['trust_badge_requested_at']) ? date('d/m/Y H:i', strtotime($trustUser['trust_badge_requested_at'])) : '—'; ?></td>
                                <td><?php echo !empty($trustUser['trust_badge_duration_months']) ? ((int) $trustUser['trust_badge_duration_months'] . ' mes(es)') : '—'; ?></td>
                                <td><?php echo number_format((float) ($trustUser['trust_badge_fee_required'] ?? 0), 0, ',', '.'); ?></td>
                                <td>
                                    <?php if ($proofUrl !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($proofUrl); ?>" target="_blank" rel="noopener" class="moderation-proof-link" title="Abrir comprovativo">
                                            <img src="<?php echo htmlspecialchars($proofUrl); ?>" alt="Comprovativo" class="moderation-proof-thumb">
                                        </a>
                                    <?php else: ?>
                                        <span class="dashboard-inline-note">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="request-status-badge <?php echo $feePaid ? 'request-status-aceite' : 'request-status-pendente'; ?>">
                                        <?php echo $feePaid ? 'Pago' : 'Pendente'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="request-actions">
                                        <?php if (!$feePaid): ?>
                                            <form action="<?php echo DIRPAGE; ?>dashboard/confirmTrustedBadgePayment/<?php echo (int) ($trustUser['id'] ?? 0); ?>" method="POST" class="request-actions">
                                                <?php echo Src\classes\ClassCsrf::field(); ?>
                                                <button type="submit" class="btn-primary">Confirmar Pgto</button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="<?php echo DIRPAGE; ?>dashboard/rejectTrustedBadge/<?php echo (int) ($trustUser['id'] ?? 0); ?>" method="POST" class="request-actions">
                                            <?php echo Src\classes\ClassCsrf::field(); ?>
                                            <button type="submit" class="btn-secondary" data-confirm="Rejeitar esta solicitação de selo?">Rejeitar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="dashboard-pagination-copy payments-admin-count-copy">
                A mostrar <?php echo count($pendingTrust); ?> de <?php echo $pendingTrustCount; ?>.
            </p>
            <?php if ($totalPages > 1): ?>
                <div class="dashboard-pagination-wrap dashboard-pagination-wrap-start">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo paymentsTabUrl('trust', $page - 1); ?>" class="btn-secondary">&larr; Anterior</a>
                    <?php endif; ?>
                    <span class="dashboard-pagination-copy">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo paymentsTabUrl('trust', $page + 1); ?>" class="btn-secondary">Próxima &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="dashboard-empty-copy">Nenhuma solicitação de selo pendente de pagamento.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'boosts'): ?>
    <div class="dashboard-module-card dashboard-kpi-section payments-admin-card">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Fila</span>
                <h3>Solicitações de destaque pendentes</h3>
            </div>
        </div>

        <?php if (!empty($pendingBoosts)): ?>
            <div class="dashboard-table-wrap">
                <table class="commissions-table">
                    <thead>
                        <tr>
                            <th>Imóvel</th>
                            <th>Proprietário</th>
                            <th>Tipo</th>
                            <th>Duração</th>
                            <th>Referência</th>
                            <th>Comprovativo</th>
                            <th>Solicitado em</th>
                            <th>Aprovar</th>
                            <th>Rejeitar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingBoosts as $boost): ?>
                            <tr data-focus-boost-id="<?php echo (int) ($boost['id'] ?? 0); ?>">
                                <td><a href="<?php echo DIRPAGE; ?>property/<?php echo (int) ($boost['property_id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars($boost['property_title'] ?? '–'); ?></a></td>
                                <td class="dashboard-inline-note"><?php echo !empty($boost['requester_id']) ? '<a href="' . DIRPAGE . 'property/owner/' . (int)$boost['requester_id'] . '" class="table-name-link">' . htmlspecialchars($boost['requester_name'] ?? '–') . '</a>' : htmlspecialchars($boost['requester_name'] ?? '–'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst((string) ($boost['boost_type'] ?? 'destaque'))); ?></td>
                                <td><?php echo (int) ($boost['duration_days'] ?? 30); ?> dias</td>
                                <td class="dashboard-inline-note"><?php echo htmlspecialchars($boost['payment_reference'] ?? '–'); ?></td>
                                <td>
                                    <?php
                                        $proofPath = trim((string) ($boost['payment_proof'] ?? ''));
                                        $proofUrl = $proofPath !== '' ? \Src\classes\ClassMediaUrl::proof($proofPath) : '';
                                    ?>
                                    <?php if ($proofUrl !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($proofUrl); ?>" target="_blank" rel="noopener" class="moderation-proof-link" title="Abrir comprovativo">
                                            <img src="<?php echo htmlspecialchars($proofUrl); ?>" alt="Comprovativo" class="moderation-proof-thumb">
                                        </a>
                                    <?php else: ?>
                                        <span class="dashboard-inline-note">–</span>
                                    <?php endif; ?>
                                </td>
                                <td class="dashboard-inline-note dashboard-cell-nowrap"><?php echo !empty($boost['requested_at']) ? date('d/m/Y H:i', strtotime($boost['requested_at'])) : '–'; ?></td>
                                <td>
                                    <form action="<?php echo DIRPAGE; ?>property/approveBoost/<?php echo (int) $boost['id']; ?>" method="POST" class="request-actions">
                                        <?php echo Src\classes\ClassCsrf::field(); ?>
                                        <button type="submit" class="btn-primary">Aprovar</button>
                                    </form>
                                </td>
                                <td>
                                    <form action="<?php echo DIRPAGE; ?>property/rejectBoost/<?php echo (int) $boost['id']; ?>" method="POST" class="request-actions">
                                        <?php echo Src\classes\ClassCsrf::field(); ?>
                                        <input type="text" name="reject_reason" placeholder="Motivo (opcional)" class="referral-link-input dashboard-inline-input-compact">
                                        <button type="submit" class="btn-secondary" data-confirm="Rejeitar esta solicitação de destaque?">Rejeitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="dashboard-pagination-copy payments-admin-count-copy">
                A mostrar <?php echo count($pendingBoosts); ?> de <?php echo $pendingBoostsCount; ?>.
            </p>
            <?php if ($totalPages > 1): ?>
                <div class="dashboard-pagination-wrap dashboard-pagination-wrap-start">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo paymentsTabUrl('boosts', $page - 1); ?>" class="btn-secondary">&larr; Anterior</a>
                    <?php endif; ?>
                    <span class="dashboard-pagination-copy">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo paymentsTabUrl('boosts', $page + 1); ?>" class="btn-secondary">Próxima &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="dashboard-empty-copy">Nenhuma solicitação de destaque pendente.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'commissions'): ?>
    <?php
        $affiliatePaymentQueue = is_array($affiliatePayoutQueue ?? null) ? $affiliatePayoutQueue : [];
        $affiliatePayoutCount = (int) ($affiliatePayoutCount ?? count($affiliatePaymentQueue));
        $commissionSection = in_array($commissionSection ?? '', ['owner', 'affiliate'], true) ? $commissionSection : 'owner';
    ?>
    <div class="payments-commissions-panel">
        <div class="dashboard-overview-grid dashboard-overview-grid-tight payments-commissions-kpis">
            <div class="kpi-card kpi-yellow">
                <div class="kpi-label">Comprovativos a validar</div>
                <div class="kpi-value"><?php echo $pendingCommissionsCount; ?></div>
            </div>
            <div class="kpi-card kpi-blue">
                <div class="kpi-label">Pagamentos ao afiliado</div>
                <div class="kpi-value"><?php echo $affiliatePayoutCount; ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Valor total pendente</div>
                <div class="kpi-value kpi-value-prominent"><?php echo number_format((float) ($commissionsPendingTotal ?? 0), 0, ',', '.'); ?> Kz</div>
                <small class="dashboard-inline-note payments-commissions-total-note">
                    <?php echo number_format((float) ($pendingTotal ?? 0), 0, ',', '.'); ?> Kz a validar
                    + <?php echo number_format((float) ($commissionsAffiliatePendingAmount ?? 0), 0, ',', '.'); ?> Kz ao afiliado
                </small>
            </div>
        </div>

        <div class="dashboard-tab-nav payments-commissions-subtabs">
            <a href="<?php echo paymentsTabUrl('commissions', 1, 'owner'); ?>"
               class="dashboard-tab-link <?php echo $commissionSection === 'owner' ? 'is-active' : ''; ?>">
                <i class="fa fa-check-square-o"></i> Validar comprovativo
                <?php if ($pendingCommissionsCount > 0): ?>
                    <span class="dashboard-tab-badge"><?php echo $pendingCommissionsCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo paymentsTabUrl('commissions', 1, 'affiliate'); ?>"
               class="dashboard-tab-link <?php echo $commissionSection === 'affiliate' ? 'is-active' : ''; ?>">
                <i class="fa fa-user"></i> Pagar afiliado
                <?php if ($affiliatePayoutCount > 0): ?>
                    <span class="dashboard-tab-badge"><?php echo $affiliatePayoutCount; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if ($commissionSection === 'owner'): ?>
        <div class="dashboard-module-card dashboard-kpi-section payments-admin-card payments-commissions-card">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Fila</span>
                    <h3>Validar pagamento do proprietário</h3>
                    <p class="dashboard-inline-note">Confirme o comprovativo enviado pelo proprietário do imóvel antes de liquidar a comissão.</p>
                </div>
            </div>

            <?php if (!empty($pendingCommissions)): ?>
                <div class="dashboard-table-wrap payments-commissions-table-wrap">
                <table class="commissions-table payments-commissions-table payments-commissions-owner-table">
                    <thead>
                        <tr>
                            <th>Proprietário</th>
                            <th>Imóvel</th>
                            <th>Valor</th>
                            <th>Comprovativo</th>
                            <th>Enviado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingCommissions as $c): ?>
                            <?php
                                $commissionId = (int) ($c['id'] ?? 0);
                                $hasAffiliate = App\model\Commission::hasValidAffiliate($c);
                                $isSystemOnly = !$hasAffiliate;
                                $ownerProofPath = trim((string) ($c['owner_payment_proof_path'] ?? ''));
                                $ownerProofUrl = $ownerProofPath !== '' ? App\model\Commission::ownerPaymentProofPublicUrl($ownerProofPath) : '';
                                $ownerRef = trim((string) ($c['owner_payment_reference'] ?? ''));
                                $canValidate = App\model\Commission::canValidateOwnerPayment($c);
                                $submittedAt = trim((string) ($c['owner_payment_submitted_at'] ?? ''));
                                $submittedLabel = $submittedAt !== '' ? date('d/m/Y H:i', strtotime($submittedAt)) : '—';
                                $ownerId = (int) ($c['owner_id'] ?? 0);
                            ?>
                            <tr class="payments-commissions-row" data-focus-commission-id="<?php echo $commissionId; ?>">
                                <td data-label="Proprietário" class="col-stack ptx-cell-user">
                                    <?php if ($ownerId > 0): ?>
                                        <strong>
                                            <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo $ownerId; ?>" class="table-name-link">
                                                <?php echo htmlspecialchars((string) ($c['owner_name'] ?? 'Proprietário')); ?>
                                            </a>
                                        </strong>
                                    <?php else: ?>
                                        <strong><?php echo htmlspecialchars((string) ($c['owner_name'] ?? 'Proprietário')); ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($c['owner_phone'])): ?>
                                        <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) $c['owner_phone']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($c['owner_email'])): ?>
                                        <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) $c['owner_email']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($hasAffiliate): ?>
                                        <span class="dashboard-inline-note">
                                            Afiliado: <?php echo htmlspecialchars((string) ($c['affiliate_name'] ?? '')); ?>
                                            <?php if (!empty($c['affiliate_phone'])): ?>
                                                · <?php echo htmlspecialchars((string) $c['affiliate_phone']); ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Imóvel" class="col-stack">
                                    <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) ($c['property_id'] ?? 0); ?>" class="table-name-link">
                                        <?php echo htmlspecialchars((string) ($c['property_title'] ?? '')); ?>
                                    </a>
                                    <span class="dashboard-inline-note">#<?php echo $commissionId; ?> · <?php echo number_format((float) ($c['total_pct'] ?? 0), 1, ',', '.'); ?>%</span>
                                </td>
                                <td data-label="Valor" class="col-stack">
                                    <strong><?php echo number_format((float) ($c['amount'] ?? 0), 0, ',', '.'); ?> Kz</strong>
                                    <?php if ($isSystemOnly): ?>
                                        <span class="commission-status-badge commission-system-only-badge">Só taxa sistema</span>
                                    <?php else: ?>
                                        <small class="dashboard-inline-note">
                                            Sistema: <?php echo number_format((float) ($c['system_amount'] ?? 0), 0, ',', '.'); ?> Kz
                                            · Afiliado: <?php echo number_format((float) ($c['affiliate_amount'] ?? 0), 0, ',', '.'); ?> Kz
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Comprovativo" class="col-stack">
                                    <?php if ($ownerProofUrl !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($ownerProofUrl); ?>" target="_blank" rel="noopener" class="moderation-proof-link" title="Abrir comprovativo do proprietário">
                                            <img src="<?php echo htmlspecialchars($ownerProofUrl); ?>" alt="Comprovativo" class="moderation-proof-thumb">
                                        </a>
                                        <?php if ($ownerRef !== ''): ?>
                                            <small class="dashboard-inline-note">Ref: <?php echo htmlspecialchars($ownerRef); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="request-status-badge request-status-pendente">Sem comprovativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="dashboard-cell-nowrap" data-label="Enviado em"><?php echo htmlspecialchars($submittedLabel); ?></td>
                                <td data-label="Ações" class="col-actions ptx-cell-actions">
                                    <div class="payments-commissions-actions">
                                        <?php if ($canValidate): ?>
                                            <form action="<?php echo DIRPAGE; ?>dashboard/confirmPayment/<?php echo $commissionId; ?>" method="POST" class="request-actions payments-commissions-action-form">
                                                <?php echo Src\classes\ClassCsrf::field(); ?>
                                                <input type="text" name="payment_reference" value="<?php echo htmlspecialchars($ownerRef); ?>" placeholder="Ref. transferência" class="referral-link-input dashboard-inline-input-compact">
                                                <button type="submit" class="btn-primary">Aprovar pagamento</button>
                                            </form>
                                            <form action="<?php echo DIRPAGE; ?>dashboard/rejectCommissionOwnerPayment/<?php echo $commissionId; ?>" method="POST" class="request-actions payments-commissions-action-form">
                                                <?php echo Src\classes\ClassCsrf::field(); ?>
                                                <input type="text" name="rejection_reason" placeholder="Motivo da rejeição (opcional)" class="referral-link-input dashboard-inline-input-compact">
                                                <button type="submit" class="btn-secondary" data-confirm="Rejeitar comprovativo? O proprietário poderá reenviar.">Rejeitar comprovativo</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="request-status-badge request-status-pendente">Validação indisponível</span>
                                        <?php endif; ?>
                                        <form action="<?php echo DIRPAGE; ?>dashboard/cancelPayment/<?php echo $commissionId; ?>" method="POST" class="payments-commissions-action-form">
                                            <?php echo Src\classes\ClassCsrf::field(); ?>
                                            <button type="submit" class="btn-secondary" data-confirm="Cancelar esta comissão?">Cancelar comissão</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <p class="dashboard-pagination-copy payments-admin-count-copy">
                    A mostrar <?php echo count($pendingCommissions); ?> de <?php echo $pendingCommissionsCount; ?>.
                </p>
                <?php if ($totalPages > 1): ?>
                    <div class="dashboard-pagination-wrap dashboard-pagination-wrap-start">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo paymentsTabUrl('commissions', $page - 1, 'owner'); ?>" class="btn-secondary">&larr; Anterior</a>
                        <?php endif; ?>
                        <span class="dashboard-pagination-copy">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo paymentsTabUrl('commissions', $page + 1, 'owner'); ?>" class="btn-secondary">Próxima &rarr;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="dashboard-empty-copy">Nenhum comprovativo de proprietário aguardando validação.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($commissionSection === 'affiliate'): ?>
        <div class="dashboard-module-card dashboard-kpi-section payments-admin-card payments-commissions-card">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Pagamentos</span>
                    <h3>Pagar comissão ao afiliado</h3>
                    <p class="dashboard-inline-note">Após aprovar o proprietário, registe a transferência ao afiliado com comprovativo.</p>
                </div>
            </div>

            <?php if (!empty($affiliatePaymentQueue)): ?>
                <div class="dashboard-table-wrap payments-commissions-table-wrap">
                <table class="commissions-table payments-commissions-table payments-commissions-affiliate-table">
                    <thead>
                        <tr>
                            <th>Afiliado</th>
                            <th>Imóvel</th>
                            <th>Valor afiliado</th>
                            <th>Conta de recebimento</th>
                            <th>Confirmar transferência</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($affiliatePaymentQueue as $c): ?>
                            <?php
                                $commissionId = (int) ($c['id'] ?? 0);
                                $affiliateId = (int) ($c['affiliate_id'] ?? 0);
                                $accounts = $affiliateId > 0
                                    ? App\model\UserPaymentAccount::getShareableActiveByUser($affiliateId)
                                    : [];
                                $defaultAccount = $accounts[0] ?? null;
                            ?>
                            <tr class="payments-commissions-row" data-focus-commission-id="<?php echo $commissionId; ?>">
                                <td data-label="Afiliado" class="col-stack ptx-cell-user">
                                    <strong><?php echo htmlspecialchars((string) ($c['affiliate_name'] ?? '')); ?></strong>
                                    <?php if (!empty($c['affiliate_phone'])): ?>
                                        <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) $c['affiliate_phone']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Imóvel" class="col-stack">
                                    <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) ($c['property_id'] ?? 0); ?>" class="table-name-link">
                                        <?php echo htmlspecialchars((string) ($c['property_title'] ?? '')); ?>
                                    </a>
                                    <span class="dashboard-inline-note">Comissão #<?php echo $commissionId; ?></span>
                                </td>
                                <td data-label="Valor afiliado">
                                    <strong><?php echo number_format((float) ($c['affiliate_amount'] ?? 0), 0, ',', '.'); ?> Kz</strong>
                                </td>
                                <td data-label="Conta" class="col-stack dashboard-inline-note">
                                    <?php if ($defaultAccount): ?>
                                        <strong><?php echo htmlspecialchars((string) ($defaultAccount['method_name'] ?? '')); ?></strong>
                                        <?php if (!empty($defaultAccount['account_number'])): ?>
                                            <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) $defaultAccount['account_number']); ?></span>
                                        <?php elseif (!empty($defaultAccount['phone_number'])): ?>
                                            <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) $defaultAccount['phone_number']); ?></span>
                                        <?php elseif (!empty($defaultAccount['iban'])): ?>
                                            <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) $defaultAccount['iban']); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="request-status-badge request-status-expirado">Sem conta registada</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Transferência" class="col-actions ptx-cell-actions">
                                    <div class="request-actions affiliate-payout-actions payments-commissions-actions">
                                        <form action="<?php echo DIRPAGE; ?>dashboard/confirmAffiliatePayout/<?php echo $commissionId; ?>"
                                              method="POST"
                                              enctype="multipart/form-data"
                                              class="affiliate-payout-form payments-commissions-action-form">
                                            <?php echo Src\classes\ClassCsrf::field(); ?>
                                            <input type="text" name="payout_reference" placeholder="Ref. transferência (opcional)" class="referral-link-input dashboard-inline-input-compact" autocomplete="off">
                                            <label class="request-action-upload" for="payout_proof_<?php echo $commissionId; ?>">
                                                <span class="request-action-upload-label">Comprovativo *</span>
                                                <input type="file"
                                                       id="payout_proof_<?php echo $commissionId; ?>"
                                                       name="payout_proof"
                                                       class="js-request-attachment-input js-affiliate-payout-proof"
                                                       accept="image/jpeg,image/png,image/webp,image/gif"
                                                       required>
                                                <span class="request-attachment-feedback dashboard-inline-note" id="payout_proof_feedback_<?php echo $commissionId; ?>"></span>
                                            </label>
                                            <?php if (!$defaultAccount): ?>
                                                <small class="dashboard-inline-note">O afiliado precisa de registar uma conta de recebimento antes de confirmar o pagamento.</small>
                                            <?php endif; ?>
                                            <button type="submit"
                                                    class="btn-primary js-affiliate-payout-submit"
                                                    <?php echo $defaultAccount ? '' : 'disabled'; ?>
                                                    <?php echo $defaultAccount ? '' : 'title="Sem conta de recebimento registada"'; ?>>
                                                Confirmar pagamento
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <p class="dashboard-empty-copy">Nenhum pagamento de comissão ao afiliado pendente.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'subscriptions'): ?>
    <div class="dashboard-module-card dashboard-kpi-section payments-admin-card">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Subscrições</span>
                <h3>Pagamentos de planos</h3>
            </div>
        </div>

        <?php if (!empty($subscriptionTransactions)): ?>
            <div class="dashboard-table-wrap">
            <table class="commissions-table moderation-table-trust">
                <thead>
                    <tr>
                        <th>Utilizador</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Referência</th>
                        <th>Comprovativo</th>
                        <th>Status</th>
                        <th>Criada em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptionTransactions as $tx): ?>
                        <?php
                            $proofPath = trim((string) ($tx['proof_file'] ?? ''));
                            $proofUrl = $proofPath !== '' ? \Src\classes\ClassMediaUrl::proof($proofPath) : '';
                            $isPending = in_array((string) ($tx['status'] ?? ''), ['pendente', 'processando'], true);
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($tx['counterparty_name'])): ?>
                                    <?php if (!empty($tx['counterparty_user_id'])): ?>
                                        <strong><a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) ($tx['counterparty_user_id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars((string) $tx['counterparty_name']); ?></a></strong><br>
                                    <?php else: ?>
                                        <strong><?php echo htmlspecialchars((string) $tx['counterparty_name']); ?></strong><br>
                                    <?php endif; ?>
                                    <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) ($tx['counterparty_email'] ?? '')); ?></span>
                                <?php else: ?>
                                    #<?php echo (int) ($tx['counterparty_user_id'] ?? 0); ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo number_format((float) ($tx['amount'] ?? 0), 0, ',', '.'); ?> <?php echo htmlspecialchars((string) ($tx['currency'] ?? 'AOA')); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) ($tx['method_name'] ?? 'N/A')); ?></td>
                            <td class="dashboard-inline-note"><?php echo htmlspecialchars((string) ($tx['reference_code'] ?? '—')); ?></td>
                            <td>
                                <?php if ($proofUrl !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($proofUrl); ?>" target="_blank" rel="noopener" class="moderation-proof-link" title="Abrir comprovativo">
                                        <img src="<?php echo htmlspecialchars($proofUrl); ?>" alt="Comprovativo" class="moderation-proof-thumb">
                                    </a>
                                <?php else: ?>
                                    <span class="dashboard-inline-note">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="request-status-badge <?php echo $isPending ? 'request-status-pendente' : (((string) ($tx['status'] ?? '') === 'confirmado') ? 'request-status-aceite' : 'request-status-none'); ?>">
                                    <?php echo htmlspecialchars(ucfirst((string) ($tx['status'] ?? '—'))); ?>
                                </span>
                            </td>
                            <td class="dashboard-inline-note dashboard-cell-nowrap">
                                <?php echo !empty($tx['created_at']) ? date('d/m/Y H:i', strtotime((string) $tx['created_at'])) : '—'; ?>
                            </td>
                            <td>
                                <?php if ($isPending): ?>
                                    <div class="request-actions">
                                        <button type="button" class="btn-primary" data-doc-modal-open="approveSubModal<?php echo (int) ($tx['id'] ?? 0); ?>">Aprovar</button>
                                        <button type="button" class="btn-secondary" data-doc-modal-open="rejectSubModal<?php echo (int) ($tx['id'] ?? 0); ?>">Rejeitar</button>
                                    </div>
                                <?php else: ?>
                                    <span class="dashboard-inline-note">Sem ações</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php if ($isPending): ?>
                        <!-- Approve Modal -->
                        <div class="doc-modal" id="approveSubModal<?php echo (int) ($tx['id'] ?? 0); ?>" hidden>
                            <div class="doc-modal-panel" role="dialog" aria-modal="true" aria-labelledby="approveSubTitle<?php echo (int) ($tx['id'] ?? 0); ?>">
                                <div class="doc-modal-head">
                                    <h5 id="approveSubTitle<?php echo (int) ($tx['id'] ?? 0); ?>">Aprovar Pagamento</h5>
                                    <button type="button" class="doc-modal-close" data-doc-modal-close aria-label="Fechar">&times;</button>
                                </div>
                                <form method="POST" action="<?php echo DIRPAGE; ?>payment_transactions/confirmTransaction/<?php echo (int) ($tx['id'] ?? 0); ?>">
                                    <div class="doc-modal-body">
                                        <p>
                                            <strong><?php echo htmlspecialchars((string) ($tx['counterparty_name'] ?? '#' . (int) ($tx['counterparty_user_id'] ?? 0))); ?></strong><br>
                                            <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) ($tx['counterparty_email'] ?? '')); ?></span>
                                        </p>
                                        <div class="mb-3">
                                            <label class="form-label">Referência *</label>
                                            <input type="text" name="reference_code" class="form-control" placeholder="Código de referência" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observação *</label>
                                            <textarea class="form-control" name="notes" rows="3" required placeholder="Observação sobre a confirmação..."></textarea>
                                        </div>
                                    </div>
                                    <div class="doc-modal-foot">
                                        <button type="button" class="btn-secondary" data-doc-modal-close>Cancelar</button>
                                        <?php echo Src\classes\ClassCsrf::field(); ?>
                                        <button type="submit" class="btn-primary">Aprovar</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Reject Modal -->
                        <div class="doc-modal" id="rejectSubModal<?php echo (int) ($tx['id'] ?? 0); ?>" hidden>
                            <div class="doc-modal-panel" role="dialog" aria-modal="true" aria-labelledby="rejectSubTitle<?php echo (int) ($tx['id'] ?? 0); ?>">
                                <div class="doc-modal-head">
                                    <h5 id="rejectSubTitle<?php echo (int) ($tx['id'] ?? 0); ?>">Rejeitar Pagamento</h5>
                                    <button type="button" class="doc-modal-close" data-doc-modal-close aria-label="Fechar">&times;</button>
                                </div>
                                <form method="POST" action="<?php echo DIRPAGE; ?>payment_transactions/rejectTransaction/<?php echo (int) ($tx['id'] ?? 0); ?>">
                                    <div class="doc-modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Utilizador</label>
                                            <p class="form-control-plaintext">
                                                <strong><?php echo htmlspecialchars((string) ($tx['counterparty_name'] ?? '#' . (int) ($tx['counterparty_user_id'] ?? 0))); ?></strong>
                                                <?php if (!empty($tx['counterparty_email'])): ?>
                                                    (<?php echo htmlspecialchars((string) $tx['counterparty_email']); ?>)
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Motivo da Rejeição *</label>
                                            <textarea class="form-control" name="notes" rows="4" required placeholder="Descreva o motivo da rejeição..."></textarea>
                                            <small class="form-text text-muted">Este motivo será registado no histórico da transação.</small>
                                        </div>
                                    </div>
                                    <div class="doc-modal-foot">
                                        <button type="button" class="btn-secondary" data-doc-modal-close>Cancelar</button>
                                        <?php echo Src\classes\ClassCsrf::field(); ?>
                                        <button type="submit" class="btn-primary">Rejeitar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="dashboard-pagination-copy payments-admin-count-copy">
                A mostrar <?php echo count($subscriptionTransactions); ?> de <?php echo $subscriptionTransactionsCount; ?>.
            </p>
            <?php if ($totalPages > 1): ?>
                <div class="dashboard-pagination-wrap dashboard-pagination-wrap-start">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo paymentsTabUrl('subscriptions', $page - 1); ?>" class="btn-secondary">&larr; Anterior</a>
                    <?php endif; ?>
                    <span class="dashboard-pagination-copy">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo paymentsTabUrl('subscriptions', $page + 1); ?>" class="btn-secondary">Próxima &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="dashboard-empty-copy">Nenhum pagamento de subscrição registado.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'history'): ?>
    <div class="dashboard-module-card payments-admin-card">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Arquivo</span>
                <h3>Histórico completo</h3>
            </div>
            <div class="request-actions">
                <a href="<?php echo DIRPAGE; ?>dashboard/exportPaymentsHistoryPdf" class="btn-primary">Exportar PDF</a>
                <a href="<?php echo DIRPAGE; ?>dashboard/exportPaymentsHistoryCsv" class="btn-secondary">Exportar CSV</a>
            </div>
        </div>

        <?php
            $allPaymentTransactions = $allPaymentTransactions ?? [];
            $allPaymentTransactionsCount = (int) ($allPaymentTransactionsCount ?? count($allPaymentTransactions));
        ?>

        <?php if (!empty($allPaymentTransactions)): ?>
            <div class="dashboard-table-wrap">
            <table class="commissions-table moderation-table-trust">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Utilizador</th>
                        <th>Direção</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Status</th>
                        <th>Referência</th>
                        <th>Confirmada em</th>
                        <th>Criado em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allPaymentTransactions as $tx): ?>
                        <?php
                            $typeMap = [
                                'commission_payout' => 'Pagamento ao afiliado',
                                'system_commission' => 'Taxa do sistema',
                                'boost_fee' => 'Destaque',
                                'trust_badge_fee' => 'Selo',
                                'subscription_fee' => 'Subscrição',
                                'manual_adjustment' => 'Ajuste manual',
                            ];
                            $directionMap = [
                                'incoming' => 'Entrada',
                                'outgoing' => 'Saída',
                            ];
                            $statusValue = (string) ($tx['status'] ?? 'pendente');
                            $statusClass = 'request-status-pendente';
                            if ($statusValue === 'confirmado') {
                                $statusClass = 'request-status-aceite';
                            } elseif (in_array($statusValue, ['cancelado', 'falhado'], true)) {
                                $statusClass = 'request-status-none';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($typeMap[$tx['transaction_type'] ?? ''] ?? 'Outro')); ?></td>
                            <td>
                                <?php if (!empty($tx['counterparty_name'])): ?>
                                    <?php if (!empty($tx['counterparty_user_id'])): ?>
                                        <strong><a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) ($tx['counterparty_user_id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars((string) $tx['counterparty_name']); ?></a></strong><br>
                                    <?php else: ?>
                                        <strong><?php echo htmlspecialchars((string) $tx['counterparty_name']); ?></strong><br>
                                    <?php endif; ?>
                                    <span class="dashboard-inline-note"><?php echo htmlspecialchars((string) ($tx['counterparty_email'] ?? '')); ?></span>
                                <?php elseif (!empty($tx['counterparty_user_id'])): ?>
                                    #<?php echo (int) ($tx['counterparty_user_id'] ?? 0); ?>
                                <?php else: ?>
                                    <span class="dashboard-inline-note">Sistema</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($directionMap[$tx['direction'] ?? ''] ?? '—')); ?></td>
                            <td><strong><?php echo number_format((float) ($tx['amount'] ?? 0), 0, ',', '.'); ?> <?php echo htmlspecialchars((string) ($tx['currency'] ?? 'AOA')); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) ($tx['method_name'] ?? 'N/A')); ?></td>
                            <td><span class="request-status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($statusValue)); ?></span></td>
                            <td class="dashboard-inline-note"><?php echo htmlspecialchars((string) ($tx['reference_code'] ?? '—')); ?></td>
                            <td class="dashboard-inline-note dashboard-cell-nowrap">
                                <?php echo !empty($tx['confirmed_at']) ? date('d/m/Y H:i', strtotime((string) $tx['confirmed_at'])) : '—'; ?>
                            </td>
                            <td class="dashboard-cell-meta dashboard-cell-nowrap"><?php echo !empty($tx['created_at']) ? date('d/m/Y H:i', strtotime((string) $tx['created_at'])) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="dashboard-pagination-copy payments-admin-count-copy">
                A mostrar <?php echo count($allPaymentTransactions); ?> de <?php echo $allPaymentTransactionsCount; ?> pagamentos.
            </p>
            <?php if ($totalPages > 1): ?>
                <div class="dashboard-pagination-wrap dashboard-pagination-wrap-start">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo paymentsTabUrl('history', $page - 1); ?>" class="btn-secondary">&larr; Anterior</a>
                    <?php endif; ?>
                    <span class="dashboard-pagination-copy">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo paymentsTabUrl('history', $page + 1); ?>" class="btn-secondary">Próxima &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="dashboard-empty-copy">Sem pagamentos registados.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
