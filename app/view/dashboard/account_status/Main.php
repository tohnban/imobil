<?php
$user = is_array($user ?? null) ? $user : [];
$userId = (int) ($user['id'] ?? 0);

if ($userId > 0) {
    $freshUser = App\model\User::findById($userId);
    if (is_array($freshUser)) {
        $user = $freshUser;
    }
}

if (!is_array($accountState ?? null) || !array_key_exists('can_submit_documents_on_account_page', $accountState)) {
    $compliance = $userId > 0 ? App\model\Document::getComplianceStatus($userId) : 'missing';
    $rejectedCount = $userId > 0 ? count(App\model\Document::getRejectedByUser($userId)) : 0;
    $accountState = Src\classes\UserAccountState::resolveWithDocument($user, $compliance, $rejectedCount);
} else {
    $accountState = $accountState;
}

$canEditIdentificationFields = Src\classes\UserAccountState::canEditIdentificationOnAccountPage($user);
$canManageDocuments = !empty($accountState['can_submit_documents_on_account_page']);
$canSubmitAccountForm = $canEditIdentificationFields || $canManageDocuments;

$latestDocument = is_array($latestDocument ?? null) ? $latestDocument : null;
$rejectedDocuments = is_array($rejectedDocuments ?? null) ? $rejectedDocuments : [];
$csrfField = $csrfField ?? Src\classes\ClassCsrf::field();

$userTypeLabel = (($user['user_type'] ?? 'pessoa_fisica') === 'pessoa_juridica') ? 'Pessoa jurídica' : 'Pessoa física';
$document = is_array($accountState['document'] ?? null) ? $accountState['document'] : null;
$docCompliance = (string) ($document['compliance'] ?? 'missing');
$accountStatusLabel = (string) ($accountState['status_label'] ?? '—');

$hero = is_array($accountState['hero'] ?? null) ? $accountState['hero'] : [];
$capabilities = is_array($accountState['capabilities'] ?? null) ? $accountState['capabilities'] : [];

$documentPendingReview = $docCompliance === 'pending' && $latestDocument;
$showDocumentFileField = $canManageDocuments && !$documentPendingReview;
$documentFileRequired = $showDocumentFileField && $canSubmitAccountForm;
?>

<?php
$dashboardPageClass = 'dashboard-account-status-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = (string) ($hero['kicker'] ?? 'A sua conta');
$heroTitle = (string) ($hero['title'] ?? 'Estado da conta');
$heroLead = (string) ($hero['text'] ?? '');
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <?php require __DIR__ . '/../../partials/account_state_structure.php'; ?>

    <div class="dashboard-profile-layout dashboard-account-status-layout">
        <aside class="dashboard-home-side dashboard-account-status-sidebar" aria-label="Permissões da conta">
            <div class="dashboard-module-card">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Por agora</span>
                        <h3>O que pode fazer</h3>
                    </div>
                </div>
                <ul class="limited-account-checklist">
                    <?php foreach ($capabilities as $cap): ?>
                    <li>
                        <i class="fa <?php echo !empty($cap['allowed']) ? 'fa-check text-success' : 'fa-times text-muted'; ?>" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars((string) ($cap['text'] ?? '')); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>

        <div class="dashboard-profile-stack">
            <div class="dashboard-module-card dashboard-form-shell" id="identification-section">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Identificação</span>
                        <h3><?php echo $canEditIdentificationFields ? 'Actualizar os seus dados' : 'A sua identificação'; ?></h3>
                    </div>
                </div>

                <?php if ($canEditIdentificationFields): ?>
                    <p class="dashboard-inline-note">Estado da conta: <strong><?php echo htmlspecialchars($accountStatusLabel); ?></strong> — pode editar nome e número de BI.</p>
                <?php elseif ($canManageDocuments): ?>
                    <p class="dashboard-inline-note">Estado da conta: <strong><?php echo htmlspecialchars($accountStatusLabel); ?></strong> — só o documento pode ser enviado. Nome e BI só editam quando a equipa marcar a conta como <strong>A corrigir</strong>.</p>
                <?php else: ?>
                    <p class="dashboard-inline-note">Estado da conta: <strong><?php echo htmlspecialchars($accountStatusLabel); ?></strong> — dados só para consulta.</p>
                <?php endif; ?>

                <?php if (!empty($rejectedDocuments)): ?>
                <div class="dashboard-alert-reason-list dashboard-margin-bottom">
                    <?php foreach ($rejectedDocuments as $doc): ?>
                        <div class="dashboard-alert-reason-item">
                            <strong>O documento não passou na revisão</strong>
                            <?php if (!empty($doc['reviewed_at'])): ?>
                                <span class="dashboard-inline-note"> · <?php echo date('d/m/Y H:i', strtotime((string) $doc['reviewed_at'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($doc['rejection_reason'])): ?>
                                <p><?php echo htmlspecialchars((string) $doc['rejection_reason']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php elseif ($documentPendingReview): ?>
                <p class="dashboard-inline-note dashboard-identification-doc-status">Já recebemos o seu ficheiro e estamos a analisá-lo. Por agora não precisa de enviar outro.</p>
                <?php endif; ?>

                <form action="<?php echo DIRPAGE; ?>profile/update" method="POST" enctype="multipart/form-data" class="account-identification-form">
                    <?php echo $csrfField; ?>

                    <div class="form-group">
                        <label for="name">Nome completo<?php echo $canEditIdentificationFields ? ' *' : ''; ?></label>
                        <?php if ($canEditIdentificationFields): ?>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars((string) ($user['name'] ?? '')); ?>" required autocomplete="name">
                        <?php else: ?>
                        <div class="account-field-static" id="name"><?php echo htmlspecialchars((string) ($user['name'] ?? '')); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="document_number">Número de identificação<?php echo $canEditIdentificationFields ? ' *' : ''; ?></label>
                        <?php if ($canEditIdentificationFields): ?>
                        <input type="text" id="document_number" name="document_number" value="<?php echo htmlspecialchars((string) ($user['document_number'] ?? '')); ?>" required autocomplete="off">
                        <?php else: ?>
                        <div class="account-field-static" id="document_number"><?php echo htmlspecialchars((string) ($user['document_number'] ?? '')); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Tipo de conta</label>
                        <div class="account-field-static"><?php echo htmlspecialchars($userTypeLabel); ?></div>
                    </div>

                    <?php if ($showDocumentFileField): ?>
                    <div class="form-group account-identification-file-group">
                        <label for="document_file">Ficheiro de identificação<?php echo $documentFileRequired ? ' *' : ''; ?></label>
                        <input type="file" id="document_file" name="document_file" accept=".pdf,.jpg,.jpeg,.png"<?php echo $documentFileRequired ? ' required' : ''; ?>>
                        <small class="dashboard-inline-note">PDF ou foto legível (JPG ou PNG), até <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?>.</small>
                    </div>
                    <?php elseif ($documentPendingReview): ?>
                    <div class="form-group">
                        <label>Ficheiro de identificação</label>
                        <div class="account-field-static">Recebido — em análise pela equipa</div>
                    </div>
                    <?php endif; ?>

                    <?php if ($canSubmitAccountForm): ?>
                    <div class="account-status-form-actions">
                        <button type="submit" class="btn-primary"><?php echo $canEditIdentificationFields ? 'Enviar alterações' : 'Enviar documento'; ?></button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <div class="dashboard-module-card dashboard-form-shell">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Contacto</span>
                        <h3>Dados de contacto</h3>
                    </div>
                </div>
                <p class="dashboard-inline-note">Telefone e email só para consulta até a conta estar activa.</p>
                <div class="account-status-readonly-fields">
                    <div class="form-group">
                        <label for="phone">Telefone</label>
                        <div class="account-field-static" id="phone"><?php echo htmlspecialchars((string) ($user['phone'] ?? '')); ?></div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="account-field-static" id="email"><?php echo htmlspecialchars((string) ($user['email'] ?? '')); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
