<?php
$dashboardPageClass = '';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Compliance';
$heroTitle = 'Revisão de Documentos';
$heroLead = 'Análise de conformidade documental dos utilizadores.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <section class="dashboard-overview-grid dashboard-overview-grid-tight">
        <article class="dashboard-overview-card tone-yellow">
            <div class="dashboard-overview-icon"><i class="fa fa-clock-o"></i></div>
            <div class="dashboard-overview-body">
                <span>Pendentes</span>
                <strong><?php echo $totalPending; ?></strong>
                <small>Aguardando revisão</small>
            </div>
        </article>
        <article class="dashboard-overview-card tone-green">
            <div class="dashboard-overview-icon"><i class="fa fa-check-circle"></i></div>
            <div class="dashboard-overview-body">
                <span>Aprovados</span>
                <strong><?php echo $stats['total_approved'] ?? 0; ?></strong>
                <small>Cumprindo conformidade</small>
            </div>
        </article>
        <article class="dashboard-overview-card tone-red">
            <div class="dashboard-overview-icon"><i class="fa fa-times-circle"></i></div>
            <div class="dashboard-overview-body">
                <span>Rejeitados</span>
                <strong><?php echo $stats['total_rejected'] ?? 0; ?></strong>
                <small>Aguardando resubmissão</small>
            </div>
        </article>
        <article class="dashboard-overview-card tone-blue">
            <div class="dashboard-overview-icon"><i class="fa fa-hourglass-half"></i></div>
            <div class="dashboard-overview-body">
                <span>Tempo Médio</span>
                <strong><?php echo $stats['avg_review_time_days'] ?? 0; ?></strong>
                <small>Dias para revisão</small>
            </div>
        </article>
    </section>

    <div class="dashboard-module-card">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Fila documental</span>
                <h3>Documentos Pendentes de Revisão</h3>
            </div>
        </div>

        <?php
            $lateCount = 0;
            $urgentCount = 0;
            $normalCount = 0;
            foreach ($pendingDocuments as $pendingDoc) {
                $ageDaysCount = (int) ((new DateTime())->diff(new DateTime($pendingDoc['created_at']))->format('%a'));
                if ($ageDaysCount >= 7) {
                    $lateCount++;
                } elseif ($ageDaysCount >= 3) {
                    $urgentCount++;
                } else {
                    $normalCount++;
                }
            }
        ?>

        <?php if (!empty($pendingDocuments)): ?>
            <div class="doc-queue-toolbar">
                <div class="doc-queue-filters" role="group" aria-label="Filtros da fila documental">
                    <button type="button" class="doc-filter-btn is-active" data-doc-filter="all">Todos (<?php echo (int) $totalPending; ?>)</button>
                    <button type="button" class="doc-filter-btn" data-doc-filter="atrasado">Atrasados (<?php echo (int) $lateCount; ?>)</button>
                    <button type="button" class="doc-filter-btn" data-doc-filter="urgente">Urgentes (<?php echo (int) $urgentCount; ?>)</button>
                    <button type="button" class="doc-filter-btn" data-doc-filter="pendente">Novos (<?php echo (int) $normalCount; ?>)</button>
                </div>
            </div>

            <div class="doc-queue-head" aria-hidden="true">
                <span>Utilizador</span>
                <span>Documento</span>
                <span>Data de envio</span>
                <span>Prioridade</span>
                <span>Ações</span>
            </div>
        <?php endif; ?>

        <div class="doc-queue">
            <?php if (empty($pendingDocuments)): ?>
                <div class="doc-queue-empty">
                    <i class="fa fa-check-circle"></i>
                    <p>Nenhum documento pendente de revisão.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendingDocuments as $doc): ?>
                    <?php
                        $created = new DateTime($doc['created_at']);
                        $now = new DateTime();
                        $ageDays = (int) $now->diff($created)->format('%a');
                        $priority = $ageDays >= 7 ? 'atrasado' : ($ageDays >= 3 ? 'urgente' : 'pendente');
                    ?>
                    <div class="doc-queue-item priority-<?php echo $priority; ?>" data-doc-priority="<?php echo $priority; ?>" data-focus-document-id="<?php echo (int) ($doc['id'] ?? 0); ?>">
                        <div class="doc-queue-user">
                            <div class="doc-queue-avatar"><?php echo strtoupper(substr(trim((string) ($doc['user_name'] ?? 'U')), 0, 1)); ?></div>
                            <div class="doc-queue-user-info">
                                <small class="doc-queue-label">Utilizador</small>
                                <strong><?php echo htmlspecialchars($doc['user_name'] ?? 'N/A'); ?></strong>
                                <small><?php echo htmlspecialchars($doc['user_email'] ?? 'N/A'); ?></small>
                            </div>
                        </div>

                        <div class="doc-queue-meta">
                            <small class="doc-queue-label">Documento</small>
                            <span class="doc-queue-type"><?php echo htmlspecialchars(str_replace('_', ' ', $doc['type'])); ?></span>
                            <span class="doc-queue-version"><?php echo htmlspecialchars($doc['version']); ?></span>
                        </div>

                        <div class="doc-queue-date">
                            <small class="doc-queue-label">Data de envio</small>
                            <strong><?php echo (new DateTime($doc['created_at']))->format('d/m/Y H:i'); ?></strong>
                        </div>

                        <div class="doc-queue-age">
                            <small class="doc-queue-label">Prioridade</small>
                            <span class="doc-queue-priority-chip chip-<?php echo $priority; ?>">
                                <?php if ($priority === 'atrasado'): ?>
                                    <i class="fa fa-exclamation-circle"></i> <?php echo $ageDays; ?> dias &mdash; Atrasado
                                <?php elseif ($priority === 'urgente'): ?>
                                    <i class="fa fa-clock-o"></i> <?php echo $ageDays; ?> dias &mdash; Urgente
                                <?php else: ?>
                                    <i class="fa fa-hourglass-half"></i> <?php echo $ageDays; ?> dia<?php echo $ageDays !== 1 ? 's' : ''; ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="doc-queue-actions">
                            <small class="doc-queue-label">Ações</small>
                            <a href="<?php echo DIRPAGE; ?>file/serve?path=<?php echo rawurlencode('storage/documents/' . $doc['filename']); ?>"
                               target="_blank" class="doc-action-btn doc-action-view" title="Ver documento">
                                <i class="fa fa-eye"></i> Ver
                            </a>
                            <button type="button" class="doc-action-btn doc-action-approve"
                                    data-doc-modal-open="approveModal<?php echo $doc['id']; ?>">
                                <i class="fa fa-check"></i> Aprovar
                            </button>
                            <button type="button" class="doc-action-btn doc-action-reject"
                                    data-doc-modal-open="rejectModal<?php echo $doc['id']; ?>">
                                <i class="fa fa-times"></i> Rejeitar
                            </button>
                        </div>
                    </div>

                    <!-- Approve Modal -->
                    <div class="doc-modal" id="approveModal<?php echo $doc['id']; ?>" hidden>
                        <div class="doc-modal-panel" role="dialog" aria-modal="true" aria-labelledby="approveTitle<?php echo $doc['id']; ?>">
                            <div class="doc-modal-head">
                                <h5 id="approveTitle<?php echo $doc['id']; ?>">Aprovar Documento</h5>
                                <button type="button" class="doc-modal-close" data-doc-modal-close aria-label="Fechar">&times;</button>
                            </div>
                            <form method="POST" action="<?php echo DIRPAGE; ?>dashboard/approveDocument/<?php echo $doc['id']; ?>">
                                <div class="doc-modal-body">
                                    <p class="mb-0">
                                        <strong><?php echo htmlspecialchars($doc['user_name'] ?? 'Utilizador'); ?></strong><br>
                                        Documento: <?php echo htmlspecialchars(str_replace('_', ' ', $doc['type'])); ?> (<?php echo htmlspecialchars($doc['version']); ?>)
                                    </p>
                                    <p class="text-muted small mt-2">O documento será aprovado e o utilizador será notificado.</p>
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
                    <div class="doc-modal" id="rejectModal<?php echo $doc['id']; ?>" hidden>
                        <div class="doc-modal-panel" role="dialog" aria-modal="true" aria-labelledby="rejectTitle<?php echo $doc['id']; ?>">
                            <div class="doc-modal-head">
                                <h5 id="rejectTitle<?php echo $doc['id']; ?>">Rejeitar Documento</h5>
                                <button type="button" class="doc-modal-close" data-doc-modal-close aria-label="Fechar">&times;</button>
                            </div>
                            <form method="POST" action="<?php echo DIRPAGE; ?>dashboard/rejectDocument/<?php echo $doc['id']; ?>">
                                <div class="doc-modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Utilizador</label>
                                        <p class="form-control-plaintext">
                                            <strong><?php echo htmlspecialchars($doc['user_name'] ?? 'N/A'); ?></strong>
                                            (<?php echo htmlspecialchars($doc['user_email'] ?? 'N/A'); ?>)
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Motivo da Rejeição *</label>
                                        <textarea class="form-control" name="rejection_reason" rows="4" required
                                                  placeholder="Descreva por que o documento foi rejeitado..."></textarea>
                                        <small class="form-text text-muted">Este motivo será enviado ao utilizador</small>
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
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($pendingDocuments)): ?>
            <div class="doc-queue-empty doc-queue-empty-filter" id="docQueueEmptyFilter" hidden>
                <i class="fa fa-filter"></i>
                <p>Nenhum documento corresponde ao filtro selecionado.</p>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="dashboard-pagination-wrap">
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1">Primeira</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Anterior</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Próxima</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $totalPages; ?>">Última</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>

