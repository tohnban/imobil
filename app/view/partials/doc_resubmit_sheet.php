<?php
/**
 * Resubmit document sheet (dashboard home).
 *
 * @var array<string, mixed> $doc
 * @var string $csrfField
 */

$doc = is_array($doc ?? null) ? $doc : [];
$docId = (int) ($doc['id'] ?? 0);
$docTypeLabel = htmlspecialchars(str_replace('_', ' ', (string) ($doc['type'] ?? 'documento')));
$docVersion = htmlspecialchars((string) ($doc['version'] ?? ''));
$nextVersion = 'v' . ((int) substr((string) ($doc['version'] ?? 'v0'), 1) + 1);
$rejectReason = htmlspecialchars((string) ($doc['rejection_reason'] ?? 'Não foi indicado um motivo específico.'));
$modalId = 'resubmitModal' . $docId;
?>

<div class="doc-modal sheet-modal" id="<?php echo $modalId; ?>" hidden>
    <div class="sheet-modal-backdrop doc-modal-backdrop" data-sheet-close aria-hidden="true"></div>
    <div class="doc-modal-panel sheet-modal-panel" role="dialog" aria-modal="true" aria-labelledby="resubmitTitle<?php echo $docId; ?>">
        <div class="sheet-modal-handle" aria-hidden="true"></div>
        <div class="doc-modal-head sheet-modal-head">
            <h2 id="resubmitTitle<?php echo $docId; ?>" class="doc-modal-title">Enviar documento de novo</h2>
            <button type="button" class="doc-modal-close sheet-modal-close" data-sheet-close data-doc-modal-close aria-label="Fechar">&times;</button>
        </div>
        <form method="POST"
              action="<?php echo DIRPAGE; ?>dashboard/resubmitDocument/<?php echo $docId; ?>"
              enctype="multipart/form-data"
              class="doc-modal-sheet-form">
            <div class="doc-modal-body sheet-modal-body">
                <p class="doc-resubmit-lead">
                    O documento <strong><?php echo $docTypeLabel; ?></strong> (<?php echo $docVersion; ?>) foi recusado.
                    Envie uma nova versão (<strong><?php echo htmlspecialchars($nextVersion); ?></strong>) para continuarmos a validação.
                </p>
                <div class="doc-resubmit-reason">
                    <strong>Motivo da recusa</strong>
                    <p><?php echo $rejectReason; ?></p>
                </div>
                <div class="form-group doc-resubmit-file-field">
                    <label class="form-label" for="docFile<?php echo $docId; ?>">Novo documento</label>
                    <input type="file"
                           class="form-control"
                           id="docFile<?php echo $docId; ?>"
                           name="document_file"
                           accept=".pdf,.jpg,.jpeg,.png"
                           required>
                    <small class="form-text text-muted">PDF, JPG ou PNG · máximo <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            </div>
            <div class="doc-modal-foot sheet-modal-foot">
                <button type="button" class="btn-secondary" data-sheet-close data-doc-modal-close>Cancelar</button>
                <?php echo $csrfField; ?>
                <button type="submit" class="btn-primary">Enviar documento</button>
            </div>
        </form>
    </div>
</div>
