<?php
$dashboardPageClass = 'dispute-detail-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
        $request = is_array($request ?? null) ? $request : [];
        $timeline = is_array($timeline ?? null) ? $timeline : [];
        $decision = is_array($decision ?? null) ? $decision : null;
        $status = (string) ($request['status'] ?? 'em_disputa');
        $commercialStatus = (string) ($request['commercial_status'] ?? $status);
        $disputeStatus = (string) ($request['dispute_status'] ?? 'aberta');
        $closingConfirmationStatus = isset($request['closing_confirmation_status']) ? (string) $request['closing_confirmation_status'] : null;
        $statusLabel = App\model\Request::statusLabel($commercialStatus, $closingConfirmationStatus);
        $confirmationLabel = App\model\Request::closingConfirmationLabel($closingConfirmationStatus);
        $requestId = (int) ($request['id'] ?? 0);
$heroKicker = 'Disputa #' . $requestId;
$heroTitle = 'Detalhe da Disputa';
$heroLead = 'Linha do tempo completa da negociação, com fundamentação e evidências para decisão de moderação.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-module-card dispute-detail-grid">
        <article class="dispute-summary-card">
            <span class="dashboard-module-kicker">Solicitação</span>
            <h3><?php echo htmlspecialchars((string) ($request['title'] ?? 'Imóvel')); ?></h3>
            <p><strong>Solicitante:</strong>
                <?php if (!empty($request['requester_id'])): ?>
                    <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) ($request['requester_id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars((string) ($request['requester_name'] ?? '-')); ?></a>
                <?php else: ?>
                    <?php echo htmlspecialchars((string) ($request['requester_name'] ?? '-')); ?>
                <?php endif; ?>
                (<?php echo htmlspecialchars((string) ($request['requester_email'] ?? '-')); ?>)
            </p>
            <p><strong>Proprietário:</strong>
                <?php if (!empty($request['owner_id'])): ?>
                    <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) ($request['owner_id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars((string) ($request['owner_name'] ?? '-')); ?></a>
                <?php else: ?>
                    <?php echo htmlspecialchars((string) ($request['owner_name'] ?? '-')); ?>
                <?php endif; ?>
                (<?php echo htmlspecialchars((string) ($request['owner_email'] ?? '-')); ?>)
            </p>
            <p><strong>Tipo:</strong> <?php echo htmlspecialchars((string) ($request['type'] ?? '-')); ?></p>
            <p><strong>Criada em:</strong> <?php echo !empty($request['created_at']) ? htmlspecialchars((string) date('d/m/Y H:i', strtotime((string) $request['created_at']))) : '-'; ?></p>
            <p><strong>Última atualização:</strong> <?php echo !empty($request['updated_at']) ? htmlspecialchars((string) date('d/m/Y H:i', strtotime((string) $request['updated_at']))) : '-'; ?></p>
            <p><strong>Janela da disputa até:</strong> <?php echo !empty($request['dispute_open_until']) ? htmlspecialchars((string) date('d/m/Y H:i', strtotime((string) $request['dispute_open_until']))) : '-'; ?></p>
            <div class="dispute-status-row">
                <span class="request-status-badge request-status-<?php echo htmlspecialchars($commercialStatus); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                <?php
                    $dsLabels = [
                        'aberta'                => 'Disputa aberta',
                        'em_analise'            => 'Disputa em análise',
                        'julgada_procedente'    => 'Julgada: procedente',
                        'julgada_improcedente'  => 'Julgada: improcedente',
                    ];
                    $dsLabel = $dsLabels[$disputeStatus] ?? ucfirst(str_replace('_', ' ', $disputeStatus));
                ?>
                <span class="request-status-badge request-status-em_disputa dispute-status-chip">
                    <i class="fa fa-gavel"></i> <?php echo htmlspecialchars($dsLabel); ?>
                </span>
                <?php if ($closingConfirmationStatus): ?>
                    <span class="request-status-badge request-status-<?php echo htmlspecialchars($closingConfirmationStatus); ?>"><?php echo htmlspecialchars($confirmationLabel); ?></span>
                <?php endif; ?>
            </div>
            <div class="dispute-detail-actions">
                <a href="<?php echo DIRPAGE; ?>dashboard/disputes" class="btn-secondary">Voltar ao painel de disputas</a>
            </div>
        </article>

        <article class="dispute-summary-card">
            <span class="dashboard-module-kicker">Decisão de Moderação</span>
            <?php if ($decision): ?>
                <?php
                    $evidencePath = !empty($decision['evidence_path']) ? (string) $decision['evidence_path'] : '';
                    $evidenceUrl = $evidencePath !== '' ? App\model\Request::paymentProofPublicUrl($evidencePath) : '';
                ?>
                <div class="dispute-decision-highlight status-<?php echo htmlspecialchars((string) ($decision['status'] ?? 'em_disputa')); ?>">
                    <strong><?php echo htmlspecialchars((string) ($decision['status_label'] ?? 'Decisão registrada')); ?></strong>
                    <small>
                        <strong>Disputa decidida por:</strong>
                        <?php echo htmlspecialchars((string) ($decision['decided_by'] ?? 'Moderação')); ?>
                        <?php if (!empty($decision['decided_at'])): ?>
                            - <?php echo htmlspecialchars((string) date('d/m/Y H:i', strtotime((string) $decision['decided_at']))); ?>
                        <?php endif; ?>
                    </small>
                    <?php if (!empty($decision['note'])): ?>
                        <p><?php echo nl2br(htmlspecialchars((string) $decision['note'])); ?></p>
                    <?php else: ?>
                        <p>Nenhuma observação de fundamentação foi registrada na decisão.</p>
                    <?php endif; ?>
                    <?php if ($evidenceUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($evidenceUrl); ?>" target="_blank" rel="noopener noreferrer">Ver evidência anexada na decisão</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="dispute-decision-highlight status-em_disputa">
                    <strong>Sem decisão final ainda</strong>
                    <p>Esta disputa ainda está em análise. Use o painel de disputas para concluir o julgamento.</p>
                </div>
            <?php endif; ?>

            <form action="<?php echo DIRPAGE; ?>request/updateStatus/<?php echo $requestId; ?>" method="POST" enctype="multipart/form-data" class="request-action-select-form request-action-select-form--details dispute-action-form" data-note-required-actions="fechado_ganho,cancelado">
                <?php echo Src\classes\ClassCsrf::field(); ?>
                <select name="status" class="request-action-select" required>
                    <option value="">Selecionar decisão</option>
                    <option value="fechado_ganho">Julgar como fecho ganho</option>
                    <option value="cancelado">Julgar como cancelado</option>
                </select>
                <button type="submit" class="btn-primary request-action-apply">Aplicar decisão</button>
                <div class="request-action-extra-fields" hidden>
                    <textarea name="action_note" class="request-action-note" rows="2" placeholder="Descreva a fundamentação da decisão"></textarea>
                    <label class="request-action-upload">
                        <span>Anexar imagem (opcional)</span>
                        <input type="file" name="action_image" accept="image/jpeg,image/png,image/webp,image/gif">
                    </label>
                </div>
            </form>
        </article>
    </div>

    <div class="dashboard-module-card">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Histórico completo</span>
                <h3>Linha do Tempo da Disputa</h3>
            </div>
        </div>

        <?php if (!empty($timeline)): ?>
            <ol class="dispute-timeline-list">
                <?php foreach ($timeline as $logItem): ?>
                    <?php
                        $details = (string) ($logItem['details'] ?? '');
                        $note = '';
                        $evidencePath = '';

                        if (preg_match('/\|\s*Observação:\s*(.*?)(?:\s*\|\s*Evidência:|$)/u', $details, $matches)) {
                            $note = trim((string) ($matches[1] ?? ''));
                        }
                        if (preg_match('/\|\s*Evidência:\s*(.+)$/u', $details, $matches)) {
                            $evidencePath = trim((string) ($matches[1] ?? ''));
                        }

                        $baseDetails = trim((string) preg_replace('/\|\s*Observação:.*$/u', '', $details));
                        $baseDetails = trim((string) preg_replace('/\|\s*Evidência:.*$/u', '', $baseDetails));
                        $evidenceUrl = $evidencePath !== '' ? App\model\Request::paymentProofPublicUrl($evidencePath) : '';
                        $action = (string) ($logItem['action'] ?? '');
                        $actionLabelMap = [
                            'update_request_status' => 'Mudança de estado',
                            'open_request_dispute' => 'Abertura de disputa',
                            'contest_request_closing' => 'Contestação de fecho',
                            'confirm_request_closing' => 'Confirmação de fecho',
                            'cancel_request' => 'Cancelamento',
                        ];
                        $actionLabel = $actionLabelMap[$action] ?? ucfirst(str_replace('_', ' ', $action));
                    ?>
                    <li class="dispute-timeline-item">
                        <div class="dispute-timeline-meta">
                            <span class="dispute-action-chip"><?php echo htmlspecialchars($actionLabel); ?></span>
                            <small>
                                <?php if (!empty($logItem['actor_id'])): ?>
                                    <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo (int) ($logItem['actor_id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars((string) ($logItem['actor_name'] ?? 'Sistema')); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars((string) ($logItem['actor_name'] ?? 'Sistema')); ?>
                                <?php endif; ?>
                                •
                                <?php echo !empty($logItem['created_at']) ? htmlspecialchars((string) date('d/m/Y H:i', strtotime((string) $logItem['created_at']))) : ''; ?>
                            </small>
                        </div>
                        <p class="dispute-timeline-details"><?php echo htmlspecialchars($baseDetails !== '' ? $baseDetails : 'Atualização registrada.'); ?></p>
                        <?php if ($note !== ''): ?>
                            <p class="dispute-timeline-note"><?php echo nl2br(htmlspecialchars($note)); ?></p>
                        <?php endif; ?>
                        <?php if ($evidenceUrl !== ''): ?>
                            <a class="request-action-history-link" href="<?php echo htmlspecialchars($evidenceUrl); ?>" target="_blank" rel="noopener noreferrer">Ver evidência anexada</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="dashboard-inline-note">Ainda não existem registros no histórico desta solicitação.</p>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
