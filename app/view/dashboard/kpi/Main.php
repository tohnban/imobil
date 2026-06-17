<?php
$dashboardPageClass = '';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Inteligência operacional';
$heroTitle = 'KPIs do Sistema';
$heroLead = 'Indicadores de desempenho da plataforma em tempo real.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-module-card dashboard-kpi-section">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Base ativa</span>
                <h3>Utilizadores</h3>
            </div>
        </div>

        <div class="dashboard-overview-grid dashboard-overview-grid-tight dashboard-kpi-grid-tight">
            <div class="kpi-card">
                <div class="kpi-label">Total</div>
                <div class="kpi-value"><?php echo (int) ($userStats['total'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-label">Ativos</div>
                <div class="kpi-value"><?php echo (int) ($userStats['active'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-yellow">
                <div class="kpi-label">Pendentes</div>
                <div class="kpi-value"><?php echo (int) ($userStats['pending'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-red">
                <div class="kpi-label">Rejeitados</div>
                <div class="kpi-value"><?php echo (int) ($userStats['rejected'] ?? 0); ?></div>
            </div>
            <?php if ((int) ($userStats['pending_deletion'] ?? 0) > 0): ?>
            <div class="kpi-card kpi-red">
                <div class="kpi-label">A eliminar</div>
                <div class="kpi-value"><?php echo (int) ($userStats['pending_deletion'] ?? 0); ?></div>
            </div>
            <?php endif; ?>
            <div class="kpi-card kpi-blue">
                <div class="kpi-label">Afiliados</div>
                <div class="kpi-value"><?php echo (int) ($userStats['affiliates'] ?? 0); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Novos este mês</div>
                <div class="kpi-value"><?php echo (int) ($userStats['new_this_month'] ?? 0); ?></div>
            </div>
        </div>
        <?php
            $totalUsers = (int) ($userStats['total'] ?? 0);
            $activeUsers = (int) ($userStats['active'] ?? 0);
            $activePct = $totalUsers > 0 ? round($activeUsers / $totalUsers * 100, 1) : 0;
        ?>
        <div class="dashboard-kpi-progress-wrap">
            <small>Taxa de activação: <strong><?php echo $activePct; ?>%</strong> dos utilizadores estão ativos.</small>
            <div class="dashboard-kpi-progress-bar">
                <div class="dashboard-kpi-progress-fill tone-green" style="width: <?php echo $activePct; ?>%;"></div>
            </div>
        </div>
    </div>

    <div class="dashboard-module-card dashboard-kpi-section">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Catálogo</span>
                <h3>Imóveis</h3>
            </div>
        </div>

        <div class="dashboard-overview-grid dashboard-overview-grid-tight dashboard-kpi-grid-tight">
            <div class="kpi-card">
                <div class="kpi-label">Total</div>
                <div class="kpi-value"><?php echo (int) ($propertyStats['total'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-yellow">
                <div class="kpi-label">Pendentes</div>
                <div class="kpi-value"><?php echo (int) ($propertyStats['pendente'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-label">Disponíveis</div>
                <div class="kpi-value"><?php echo (int) ($propertyStats['disponivel'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-blue">
                <div class="kpi-label">Vendidos</div>
                <div class="kpi-value"><?php echo (int) ($propertyStats['vendido'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-blue">
                <div class="kpi-label">Alugados</div>
                <div class="kpi-value"><?php echo (int) ($propertyStats['alugado'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-red">
                <div class="kpi-label">Rejeitados</div>
                <div class="kpi-value"><?php echo (int) ($propertyStats['rejeitado'] ?? 0); ?></div>
            </div>
            <?php if ((int) ($propertyStats['eliminado'] ?? 0) > 0): ?>
            <div class="kpi-card kpi-red">
                <div class="kpi-label">A eliminar</div>
                <div class="kpi-value"><?php echo (int) ($propertyStats['eliminado'] ?? 0); ?></div>
            </div>
            <?php endif; ?>
            <div class="kpi-card">
                <div class="kpi-label">Novos este mês</div>
                <div class="kpi-value"><?php echo (int) ($propertyStats['new_this_month'] ?? 0); ?></div>
            </div>
        </div>
        <div class="dashboard-kpi-progress-wrap">
            <small>Taxa de aprovação: <strong><?php echo $propertyStats['approval_rate'] ?? '–'; ?>%</strong></small>
            <?php if (($propertyStats['approval_rate'] ?? null) !== null): ?>
            <div class="dashboard-kpi-progress-bar">
                <div class="dashboard-kpi-progress-fill tone-blue" style="width: <?php echo min(100, (float) $propertyStats['approval_rate']); ?>%;"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-module-card dashboard-kpi-section">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Conversão</span>
                <h3>Solicitações</h3>
            </div>
        </div>

        <div class="dashboard-overview-grid dashboard-overview-grid-tight dashboard-kpi-grid-tight">
            <div class="kpi-card">
                <div class="kpi-label">Total</div>
                <div class="kpi-value"><?php echo (int) ($requestStats['total'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-yellow">
                <div class="kpi-label">Pendentes</div>
                <div class="kpi-value"><?php echo (int) ($requestStats['pendente'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-blue">
                <div class="kpi-label">Em análise</div>
                <div class="kpi-value"><?php echo (int) ($requestStats['analise'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-label">Fecho ganho</div>
                <div class="kpi-value"><?php echo (int) ($requestStats['fechado_ganho'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-red">
                <div class="kpi-label">Cancelados</div>
                <div class="kpi-value"><?php echo (int) ($requestStats['cancelado'] ?? 0); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Novos este mês</div>
                <div class="kpi-value"><?php echo (int) ($requestStats['new_this_month'] ?? 0); ?></div>
            </div>
            <div class="kpi-card kpi-blue">
                <div class="kpi-label">Resposta média</div>
                <div class="kpi-value"><?php echo number_format((float) ($requestStats['avg_response_hours'] ?? 0), 1, ',', '.'); ?>h</div>
            </div>
        </div>
        <div class="dashboard-kpi-progress-wrap">
            <small>Taxa de aceitação: <strong><?php echo $requestStats['acceptance_rate'] ?? '–'; ?>%</strong> | Tempo médio de resposta: <strong><?php echo number_format((float) ($requestStats['avg_response_hours'] ?? 0), 1, ',', '.'); ?>h</strong></small>
            <?php if (($requestStats['acceptance_rate'] ?? null) !== null): ?>
            <div class="dashboard-kpi-progress-bar">
                <div class="dashboard-kpi-progress-fill tone-green" style="width: <?php echo min(100, (float) $requestStats['acceptance_rate']); ?>%;"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-kpi-grid">
        <div class="dashboard-module-card">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Monetização</span>
                    <h3>Comissões</h3>
                </div>
            </div>
            <div class="dashboard-kpi-stack">
                <div class="kpi-card">
                    <div class="kpi-label">Total registado</div>
                    <div class="kpi-value"><?php echo (int) ($commissionStats['total'] ?? 0); ?></div>
                </div>
                <div class="kpi-card kpi-green">
                    <div class="kpi-label">Valor afiliados (total)</div>
                    <div class="kpi-value kpi-value-compact"><?php echo number_format((float) ($commissionStats['total_affiliate'] ?? 0), 0, ',', '.'); ?> Kz</div>
                </div>
                <div class="kpi-card kpi-blue">
                    <div class="kpi-label">Valor sistema (total)</div>
                    <div class="kpi-value kpi-value-compact"><?php echo number_format((float) ($commissionStats['total_system'] ?? 0), 0, ',', '.'); ?> Kz</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Afiliados este mês</div>
                    <div class="kpi-value kpi-value-compact"><?php echo number_format((float) ($commissionStats['affiliate_this_month'] ?? 0), 0, ',', '.'); ?> Kz</div>
                </div>
            </div>
        </div>

        <div class="dashboard-module-card">
            <div class="dashboard-module-head compact">
                <div>
                    <span class="dashboard-module-kicker">Ranking</span>
                    <h3>Top Afiliados</h3>
                </div>
            </div>
            <?php if (!empty($topAffiliates)): ?>
            <div class="dashboard-table-wrap">
                <table class="commissions-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Afiliado</th>
                            <th>Comissões</th>
                            <th>Valor (Kz)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topAffiliates as $i => $aff): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td>
                                    <?php if (!empty($aff['user_id'])): ?>
                                        <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) $aff['user_id']; ?>" class="table-name-link"><?php echo htmlspecialchars($aff['name'] ?? '-'); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($aff['name'] ?? '-'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int) ($aff['count'] ?? 0); ?></td>
                                <td><?php echo number_format((float) ($aff['total'] ?? 0), 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="dashboard-empty-copy">Sem comissões registadas ainda.</p>
            <?php endif; ?>
        </div>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
