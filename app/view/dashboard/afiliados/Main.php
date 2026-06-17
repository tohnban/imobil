<?php
$activeTab      = $active_tab ?? 'my_affiliates';
$isAffiliate    = !empty($user['is_affiliate']);
$hasProperties  = !empty($has_properties);
$page           = max(1, (int) ($page ?? 1));
$totalPages     = max(1, (int) ($totalPages ?? 1));
$commissionsTotal = (int) ($commissionsTotal ?? count($commissions ?? []));
$myAffiliatesTotal = (int) ($myAffiliatesTotal ?? count($my_affiliates ?? []));
$isRequestsTab  = $activeTab === 'affiliate_requests';

function afiliadosTabUrl(string $tab, int $targetPage = 1): string {
    $params = ['tab' => $tab];
    if ($targetPage > 1) {
        $params['page'] = $targetPage;
    }
    return '?' . http_build_query($params);
}

$propertyStatusLabels = [
    'disponivel' => 'Disponível',
    'vendido' => 'Vendido',
    'alugado' => 'Alugado',
    'pendente' => 'Pendente',
    'em_analise' => 'Em análise',
    'rejeitado' => 'Rejeitado',
];
$propertyStatusChipClass = static function (string $status) use ($propertyStatusLabels): array {
    $chipMap = [
        'disponivel' => 'dashboard-chip-success',
        'pendente' => 'dashboard-chip-warning',
        'em_analise' => 'dashboard-chip-warning',
        'rejeitado' => 'dashboard-chip-danger',
        'vendido' => 'dashboard-chip',
        'alugado' => 'dashboard-chip',
    ];

    return [
        $propertyStatusLabels[$status] ?? ucfirst($status),
        $chipMap[$status] ?? 'dashboard-chip',
    ];
};
$affiliateStatusChipClass = static function (string $status): array {
    $map = [
        'ativo' => ['Aprovado', 'dashboard-chip-success'],
        'pendente' => ['Pendente', 'dashboard-chip-warning'],
        'rejeitado' => ['Rejeitado', 'dashboard-chip-danger'],
    ];

    return $map[$status] ?? ['–', 'dashboard-chip'];
};
?>
<?php
$dashboardPageClass = 'notification-inbox-view afiliados-dashboard-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$inboxHeroTitle = 'Afiliados';
$inboxHeroMeta = 'Indicações, comissões e promotores dos seus imóveis';
include DIRREQ . 'app/view/partials/dashboard_inbox_hero.php';
?>

    <div class="requests-scope-navigation afiliados-scope-nav">
        <div class="requests-scope-pills afiliados-tab-pills">
            <?php if ($isAffiliate): ?>
                <a href="<?php echo afiliadosTabUrl('referrals'); ?>"
                   class="requests-scope-pill <?php echo $activeTab === 'referrals' ? 'is-active' : ''; ?>"
                   aria-current="<?php echo $activeTab === 'referrals' ? 'page' : 'false'; ?>">
                    <i class="fa fa-link" aria-hidden="true"></i>
                    <span>Minhas Indicações</span>
                </a>
                <a href="<?php echo afiliadosTabUrl('commissions'); ?>"
                   class="requests-scope-pill <?php echo $activeTab === 'commissions' ? 'is-active' : ''; ?>"
                   aria-current="<?php echo $activeTab === 'commissions' ? 'page' : 'false'; ?>">
                    <i class="fa fa-money" aria-hidden="true"></i>
                    <span>Comissões</span>
                </a>
            <?php endif; ?>
            <?php if ($hasProperties): ?>
                <a href="<?php echo afiliadosTabUrl('affiliate_requests'); ?>"
                   class="requests-scope-pill <?php echo $activeTab === 'affiliate_requests' ? 'is-active' : ''; ?>"
                   aria-current="<?php echo $activeTab === 'affiliate_requests' ? 'page' : 'false'; ?>">
                    <i class="fa fa-inbox" aria-hidden="true"></i>
                    <span>Solicitações</span>
                </a>
                <a href="<?php echo afiliadosTabUrl('my_affiliates'); ?>"
                   class="requests-scope-pill <?php echo $activeTab === 'my_affiliates' ? 'is-active' : ''; ?>"
                   aria-current="<?php echo $activeTab === 'my_affiliates' ? 'page' : 'false'; ?>">
                    <i class="fa fa-users" aria-hidden="true"></i>
                    <span>Meus Afiliados</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($activeTab === 'referrals' && $isAffiliate): ?>

        <!-- ── MINHAS INDICAÇÕES ── -->
        <div class="dashboard-module-card dashboard-kpi-section">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Como funciona</span>
                    <h3>Programa de Indicação</h3>
                </div>
            </div>
            <p class="dashboard-inline-note">Os links abaixo só geram comissão para imóveis em que a sua afiliação foi aprovada pelo proprietário. Partilhe o link; quando o negócio fechar, a comissão é lançada automaticamente.</p>
        </div>

        <div class="dashboard-module-card">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Afiliações activas</span>
                    <h3>Imóveis que está a indicar</h3>
                </div>
            </div>

            <div class="dashboard-table-wrap afiliados-table-wrap">
            <table class="commissions-table afiliados-table">
                <thead>
                    <tr>
                        <th>Imóvel</th>
                        <th>Proprietário</th>
                        <th>Preço</th>
                        <th>Indicações</th>
                        <th>Estado</th>
                        <th>Link de Indicação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($affiliated_properties)): ?>
                        <?php foreach ($affiliated_properties as $prop): ?>
                            <tr class="afiliados-row">
                                <td data-label="Imóvel">
                                    <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $prop['property_id']; ?>" class="table-name-link" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($prop['title']); ?>
                                    </a>
                                    <?php if (!empty($prop['location'])): ?>
                                        <br><small class="dashboard-inline-note"><?php echo htmlspecialchars($prop['location']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Proprietário">
                                    <?php
                                        $ownerHandle = htmlspecialchars(Src\classes\UserDisplay::publicHandleFromRow($prop, 'owner_username', 'owner_name', '–'));
                                    ?>
                                    <?php if (!empty($prop['owner_id'])): ?>
                                        <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) $prop['owner_id']; ?>" class="table-name-link">
                                            <?php echo $ownerHandle; ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo $ownerHandle; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($prop['owner_phone'])): ?>
                                        <br><small class="dashboard-inline-note"><?php echo htmlspecialchars($prop['owner_phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="dashboard-cell-nowrap" data-label="Preço"><?php echo number_format((float) $prop['price'], 0, ',', '.'); ?> Kz</td>
                                <td data-label="Indicações"><?php echo (int) ($prop['referral_count'] ?? 0); ?></td>
                                <td data-label="Estado">
                                    <?php [$pLabel, $pChip] = $propertyStatusChipClass((string) ($prop['property_status'] ?? '')); ?>
                                    <span class="dashboard-chip <?php echo htmlspecialchars($pChip, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($pLabel); ?></span>
                                </td>
                                <td data-label="Link" class="col-referral-link">
                                    <div class="referral-link-wrap">
                                        <input type="text"
                                               id="ref-aff-<?php echo (int) $prop['property_id']; ?>"
                                               readonly
                                               value="<?php echo DIRPAGE; ?>property/<?php echo (int) $prop['property_id']; ?>?ref=<?php echo htmlspecialchars($affiliate_code ?? ($user['affiliate_code'] ?? '')); ?>"
                                               class="referral-link-input">
                                        <button type="button"
                                                class="btn-secondary referral-copy-btn"
                                                data-copy-target="ref-aff-<?php echo (int) $prop['property_id']; ?>"
                                                title="Copiar link">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="afiliados-empty-row">
                            <td colspan="6">
                                <div class="empty-state-content">
                                    <i class="fa fa-link"></i>
                                    <p>Nenhum imóvel afiliado activo. Navegue pelos imóveis e solicite afiliação.</p>
                                    <a href="<?php echo DIRPAGE; ?>properties" class="btn-primary afiliados-empty-cta">Ver Imóveis</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

    <?php elseif ($activeTab === 'commissions' && $isAffiliate): ?>

        <!-- ── COMISSÕES ── -->
        <?php
            include __DIR__ . '/../../partials/affiliate_commission_kpis.php';
            $affiliateCommissionEmptyMessage = 'Nenhuma comissão registada ainda. Indique imóveis para começar a ganhar.';
            $affiliateCommissionResponsive = true;
            $paginationCountCopy = $commissionsTotal > 0
                ? 'A mostrar ' . count($commissions ?? []) . ' de ' . $commissionsTotal . '.'
                : null;
            $paginationPage = $page;
            $paginationTotalPages = $totalPages;
            $paginationPrevUrl = $page > 1 ? afiliadosTabUrl('commissions', $page - 1) : null;
            $paginationNextUrl = $page < $totalPages ? afiliadosTabUrl('commissions', $page + 1) : null;
            include __DIR__ . '/../../partials/affiliate_commission_history_table.php';
        ?>

    <?php else: ?>

        <!-- ── SOLICITAÇÕES / MEUS AFILIADOS ── -->
        <?php
            $byProperty = [];
            foreach (($my_affiliates ?? []) as $row) {
                $pid = (int) $row['property_id'];
                if (!isset($byProperty[$pid])) {
                    $byProperty[$pid] = [
                        'id'       => $pid,
                        'title'    => $row['property_title'],
                        'price'    => $row['property_price'],
                        'location' => $row['property_location'],
                        'status'   => $row['property_status'],
                        'rows'     => [],
                    ];
                }
                $byProperty[$pid]['rows'][] = $row;
            }
        ?>

        <?php if (empty($byProperty)): ?>
            <div class="dashboard-module-card">
                <div class="empty-state-content">
                    <i class="fa fa-users"></i>
                    <?php if ($isRequestsTab): ?>
                        <p>Sem solicitações pendentes no momento. Quando houver novos pedidos, eles aparecerão aqui.</p>
                    <?php else: ?>
                        <p>Ainda não há afiliados ligados aos seus imóveis. Os pedidos aprovados e o histórico de afiliações aparecem aqui.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php
                $affiliateColLabel = $isRequestsTab ? 'Promotor' : 'Afiliado';
            ?>
            <?php foreach ($byProperty as $property): ?>
                <div class="dashboard-module-card afiliados-property-group">
                    <div class="dashboard-module-head compact">
                        <div>
                            <span class="dashboard-module-kicker">
                                <?php [$pLabel, $pChip] = $propertyStatusChipClass((string) ($property['status'] ?? '')); ?>
                                <span class="dashboard-chip <?php echo htmlspecialchars($pChip, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($pLabel); ?></span>
                            </span>
                            <h3>
                                <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) $property['id']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($property['title']); ?>
                                </a>
                            </h3>
                            <?php if (!empty($property['location'])): ?>
                                <p class="dashboard-inline-note"><?php echo htmlspecialchars($property['location']); ?> &middot; <?php echo number_format((float) $property['price'], 0, ',', '.'); ?> Kz</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dashboard-table-wrap afiliados-table-wrap">
                        <table class="commissions-table afiliados-table">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars($affiliateColLabel); ?></th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Indicações</th>
                                    <?php if (!$isRequestsTab): ?>
                                        <th>Comissões geradas</th>
                                        <th>Código</th>
                                    <?php endif; ?>
                                    <th>Pedido em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($property['rows'] as $aff): ?>
                                    <tr class="afiliados-row" data-affiliate-request-id="<?php echo (int) ($aff['affiliate_request_id'] ?? 0); ?>">
                                        <td data-label="<?php echo htmlspecialchars($affiliateColLabel); ?>">
                                            <?php
                                                $affiliateHandle = htmlspecialchars(Src\classes\UserDisplay::publicHandleFromRow($aff, 'affiliate_username', 'affiliate_name', '–'));
                                            ?>
                                            <?php if (!empty($aff['affiliate_user_id'])): ?>
                                                <strong><a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) $aff['affiliate_user_id']; ?>" class="table-name-link"><?php echo $affiliateHandle; ?></a></strong>
                                            <?php else: ?>
                                                <strong><?php echo $affiliateHandle; ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Contacto">
                                            <?php if (!empty($aff['affiliate_email'])): ?>
                                                <?php echo htmlspecialchars($aff['affiliate_email']); ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($aff['affiliate_phone'])): ?>
                                                <small class="dashboard-inline-note"><?php echo htmlspecialchars($aff['affiliate_phone']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Estado">
                                            <?php [$stLabel2, $stChip2] = $affiliateStatusChipClass((string) ($aff['affiliate_status'] ?? 'pendente')); ?>
                                            <span class="dashboard-chip <?php echo htmlspecialchars($stChip2, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($stLabel2); ?></span>
                                            <?php if (!empty($aff['approved_at'])): ?>
                                                <br><small class="dashboard-inline-note">desde <?php echo date('d/m/Y', strtotime($aff['approved_at'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Indicações"><?php echo (int) ($aff['referral_count'] ?? 0); ?></td>
                                        <?php if (!$isRequestsTab): ?>
                                            <td class="dashboard-cell-nowrap" data-label="Comissões"><?php echo number_format((float) ($aff['commission_total'] ?? 0), 0, ',', '.'); ?> Kz</td>
                                            <td data-label="Código"><code class="afiliados-code"><?php echo htmlspecialchars($aff['affiliate_code'] ?? '–'); ?></code></td>
                                        <?php endif; ?>
                                        <td class="dashboard-cell-nowrap dashboard-inline-note" data-label="Pedido em">
                                            <?php echo !empty($aff['requested_at']) ? date('d/m/Y', strtotime($aff['requested_at'])) : '–'; ?>
                                        </td>
                                        <td class="col-actions" data-label="Ações">
                                            <?php if ($aff['affiliate_status'] === 'pendente'): ?>
                                                <div class="afiliados-row-actions">
                                                    <form action="<?php echo DIRPAGE; ?>request/approveAffiliate/<?php echo (int) $aff['affiliate_request_id']; ?>" method="POST" class="ajax-form-inline" data-ajax-action="affiliate-decision">
                                                        <?php echo $csrfField; ?>
                                                        <button type="submit" class="btn-primary">Aprovar</button>
                                                    </form>
                                                    <form action="<?php echo DIRPAGE; ?>request/rejectAffiliate/<?php echo (int) $aff['affiliate_request_id']; ?>" method="POST" class="ajax-form-inline" data-ajax-action="affiliate-decision" data-confirm="Rejeitar esta solicitação de afiliação?">
                                                        <?php echo $csrfField; ?>
                                                        <button type="submit" class="btn-secondary">Rejeitar</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="dashboard-inline-note">–</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php
                $paginationCountCopy = null;
                if ($myAffiliatesTotal > 0) {
                    $paginationCountCopy = $isRequestsTab
                        ? 'A mostrar ' . count($my_affiliates ?? []) . ' pedido(s) pendente(s) de ' . $myAffiliatesTotal . '.'
                        : 'A mostrar ' . count($my_affiliates ?? []) . ' afiliação(ões) de ' . $myAffiliatesTotal . '.';
                }
                $paginationPage = $page;
                $paginationTotalPages = $totalPages;
                $paginationPrevUrl = $page > 1 ? afiliadosTabUrl($activeTab, $page - 1) : null;
                $paginationNextUrl = $page < $totalPages ? afiliadosTabUrl($activeTab, $page + 1) : null;
                include __DIR__ . '/../../partials/dashboard_pagination.php';
            ?>
        <?php endif; ?>

    <?php endif; ?>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
