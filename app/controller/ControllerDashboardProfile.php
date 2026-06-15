<?php

namespace App\controller;

use App\model\Document;
use App\model\Log;
use App\model\Notification;
use App\model\User;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\ClassDocumentValidator;
use Src\classes\ClassPlan;
use Src\classes\ClassRender;
use Src\classes\ClassTrustBadgeEligibility;

class ControllerDashboardProfile
{

    public function profile()
    {
        ClassAuth::requireAuth();

        $user = ClassAuth::user();
        if (ClassAccess::canUseAccountStatusPage($user)) {
            header('Location: ' . DIRPAGE . 'dashboard/accountStatus');
            exit;
        }
        $userId = (int) ($user['id'] ?? 0);
        $isAdminProfile = ClassAccess::isAdmin($user);
        $trustGate = $isAdminProfile ? ['allowed' => false, 'blockers' => []] : ClassTrustBadgeEligibility::assertCanRequest($userId);
        $trust = $isAdminProfile ? [] : User::getTrustMetrics($userId);
        $trustCanSubmit = !$isAdminProfile && ($trustGate['allowed'] ?? false) === true;
        $trustPricing = $isAdminProfile ? [] : User::getTrustedBadgePricingConfig();
        $officialPlan = (!$isAdminProfile && empty($user['is_admin']))
            ? ClassPlan::getOfficialPlanByUser($userId)
            : null;

        $render = new ClassRender();
        $render->setTitle($isAdminProfile ? 'Segurança da conta' : 'Meu Perfil');
        $render->setDescription($isAdminProfile ? 'Altere a palavra-passe da sua conta administrativa' : 'Gerencie os dados da sua conta');
        $render->setKeywords('perfil, conta, usuário');
        $render->setData([
            'user' => $user,
            'isAdminProfile' => $isAdminProfile,
            'trust' => $trust,
            'trustGate' => $trustGate,
            'trustCanSubmit' => $trustCanSubmit,
            'trustPricing' => $trustPricing,
            'officialPlan' => $officialPlan,
            'usernameCanChange' => !$isAdminProfile && \Src\classes\UsernameHelper::canChangeUsername($user),
            'usernameNextChangeAt' => $isAdminProfile ? null : \Src\classes\UsernameHelper::nextChangeEligibleAt($user),
            'pendingEmailChange' => $isAdminProfile ? null : \Src\classes\EmailVerificationService::getPendingEmailChange($userId),
        ]);
        $render->setDir('dashboard/profile');
        $render->renderLayout();
    }


    public function accountStatus()
    {
        $sessionUser = ClassAccess::requireAuthenticatedAccount();
        $user = User::findById((int) ($sessionUser['id'] ?? 0)) ?: $sessionUser;

        if (ClassAccess::hasFullPlatformAccess($user)) {
            header('Location: ' . DIRPAGE . 'profile');
            exit;
        }

        if (!ClassAccess::canUseAccountStatusPage($user)) {
            header('Location: ' . DIRPAGE . 'login?error=' . rawurlencode('Não foi possível abrir a página da conta.'));
            exit;
        }

        $userId = (int) ($user['id'] ?? 0);
        $compliance = Document::getComplianceStatus($userId);
        $latestDocument = Document::getLatestByUser($userId);
        $rejectedDocuments = Document::getRejectedByUser($userId);
        $accountState = \Src\classes\UserAccountState::resolveWithDocument(
            $user,
            $compliance,
            count($rejectedDocuments)
        );

        $render = new ClassRender();
        $render->setTitle('A sua conta');
        $render->setDescription('Veja como está o seu registo e o que pode fazer na Imobil Fácil');
        $render->setKeywords('conta, documentos, registo');
        $render->setData([
            'user' => $user,
            'accountState' => $accountState,
            'canEditIdentificationFields' => \Src\classes\UserAccountState::canEditIdentificationOnAccountPage($user),
            'canManageDocuments' => $accountState['can_submit_documents_on_account_page'],
            'latestDocument' => $latestDocument,
            'rejectedDocuments' => $rejectedDocuments,
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/account_status');
        $render->renderLayout();
    }


    public function getPromoterTerms()
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();
        $userType = (string) ($user['user_type'] ?? 'pessoa_fisica');
        if (!in_array($userType, ['pessoa_fisica', 'pessoa_juridica'], true)) {
            $userType = 'pessoa_fisica';
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(\App\model\User::getPromoterProgramTerms($userType), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }


    public function becomeAffiliate()
    {
        ClassAuth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'profile?error=Token inválido');
            exit;
        }

        $user = ClassAuth::user();

        if (!empty($user['is_admin'])) {
            header('Location: ' . DIRPAGE . 'profile?error=Administradores não podem activar o perfil de promotor');
            exit;
        }

        if (!empty($user['is_affiliate'])) {
            header('Location: ' . DIRPAGE . 'profile?error=Já é promotor de imóveis');
            exit;
        }

        $acceptedTerms = filter_var($_POST['accept_promoter_terms'] ?? '', FILTER_VALIDATE_BOOLEAN)
            || (string) ($_POST['accept_promoter_terms'] ?? '') === '1';
        if (!$acceptedTerms) {
            header('Location: ' . DIRPAGE . 'profile?error=' . rawurlencode('Deve aceitar os Termos e Condições da plataforma para activar o perfil de promotor'));
            exit;
        }

        $enabled = \App\model\User::enableAffiliate((int) $user['id']);

        if ($enabled) {
            \App\model\Log::create([
                'user_id'     => $user['id'],
                'action'      => 'become_affiliate',
                'entity_type' => 'user',
                'entity_id'   => $user['id'],
                'details'     => json_encode([
                    'message' => 'Perfil de promotor activado com aceitação dos termos',
                    'user_type' => (string) ($user['user_type'] ?? 'pessoa_fisica'),
                    'terms_version' => '2026-05-24',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            header('Location: ' . DIRPAGE . 'profile?success=Perfil de promotor de imóveis activado com sucesso');
            exit;
        }

        header('Location: ' . DIRPAGE . 'profile?error=Não foi possível activar o perfil de promotor');
        exit;
    }


    public function update()
    {
        ClassAccess::requireAuthenticatedAccount();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/accountStatus');
            exit;
        }


        $sessionUser = ClassAuth::user();
        $currentUser = User::findById((int) ($sessionUser['id'] ?? 0)) ?: $sessionUser;
        $accountStatusFlow = ClassAccess::canUseAccountStatusPage($currentUser);
        $redirectBase = $accountStatusFlow ? 'dashboard/accountStatus' : 'profile';

        if ($accountStatusFlow) {
            $canEditIdentification = \Src\classes\UserAccountState::canEditIdentificationOnAccountPage($currentUser);
            $canSubmitDocuments = ClassAccess::canSubmitDocumentsOnAccountStatusPage($currentUser);

            if (!$canEditIdentification && !$canSubmitDocuments) {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Por agora estes dados estão só para consulta. Se precisarmos que corrija algo, avisamos nesta página.'));
                exit;
            }

            $userId = (int) $currentUser['id'];
            $docCompliance = Document::getComplianceStatus($userId);
            $latestDocument = Document::getLatestByUser($userId);
            $documentPendingReview = $docCompliance === 'pending' && $latestDocument !== null;
            $profileUpdated = false;

            if ($canEditIdentification) {
                if (!\Src\classes\UserAccountState::canEditIdentificationOnAccountPage($currentUser)) {
                    header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Só pode alterar nome e número de BI quando a conta estiver rejeitada para correcção.'));
                    exit;
                }

                $name = trim($_POST['name'] ?? '');
                $documentNumber = trim($_POST['document_number'] ?? '');

                if ($name === '') {
                    header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Indique o seu nome completo.'));
                    exit;
                }
                if ($documentNumber === '') {
                    header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Indique o número do BI ou documento de identificação.'));
                    exit;
                }
                if (User::findByDocumentNumberExceptId($documentNumber, $userId)) {
                    header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Este número de BI já está associado a outra conta.'));
                    exit;
                }

                User::updateProfile($userId, [
                    'name' => $name,
                    'document_number' => $documentNumber,
                ]);
                $profileUpdated = true;
            }

            $uploadResult = ['uploaded' => false, 'error' => null];
            $mustUploadDocument = $canSubmitDocuments && !$documentPendingReview;

            if ($mustUploadDocument) {
                $documentFile = $_FILES['document_file'] ?? null;
                $noFile = !$documentFile
                    || (int) ($documentFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
                if ($noFile) {
                    header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Envie o ficheiro do BI ou do documento da empresa.'));
                    exit;
                }

                $uploadResult = $this->processIdentificationDocumentUpload($currentUser);
                if ($uploadResult['error'] !== null) {
                    header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode($uploadResult['error']));
                    exit;
                }
                if (empty($uploadResult['uploaded'])) {
                    header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Não conseguimos receber o ficheiro. Tente outra vez ou escolha outro formato.'));
                    exit;
                }
            }

            if (!$profileUpdated && empty($uploadResult['uploaded'])) {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Não há alterações para guardar neste momento.'));
                exit;
            }

            $successParts = [];
            if ($profileUpdated) {
                $successParts[] = 'Guardámos as suas alterações.';
            }
            if (!empty($uploadResult['uploaded'])) {
                $successParts[] = 'Recebemos o documento — vamos analisá-lo em breve.';
            }

            header('Location: ' . DIRPAGE . $redirectBase . '?success=' . rawurlencode(implode(' ', $successParts)));
            exit;
        }

        if (ClassAccess::isAdmin($currentUser)) {
            $this->updateAdminProfile($currentUser, $redirectBase);
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        if (!ClassAuth::verifyCurrentPassword($currentUser, $currentPassword)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Indique a senha actual correcta para guardar alterações.'));
            exit;
        }

        $submittedName = trim((string) ($_POST['name'] ?? ''));
        if ($submittedName !== '' && $submittedName !== trim((string) ($currentUser['name'] ?? ''))) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode(
                'Nome e documento de identificação só podem ser alterados na página Estado da conta, quando a plataforma o solicitar.'
            ));
            exit;
        }

        $phone = \Src\classes\PhoneHelper::normalize(trim((string) ($_POST['phone'] ?? '')));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $profilePhoto = $_FILES['profile_photo'] ?? null;

        $currentUsername = \Src\classes\UsernameHelper::normalize((string) ($currentUser['username'] ?? ''));
        $submittedUsername = array_key_exists('username', $_POST)
            ? \Src\classes\UsernameHelper::normalize(trim((string) $_POST['username']))
            : $currentUsername;
        $usernameWillChange = $submittedUsername !== $currentUsername;

        if ($usernameWillChange) {
            if (!\Src\classes\UsernameHelper::canChangeUsername($currentUser)) {
                $nextChange = \Src\classes\UsernameHelper::nextChangeEligibleAt($currentUser);
                $detail = $nextChange !== null
                    ? ' Pode voltar a alterar a partir de ' . $nextChange->format('d/m/Y') . '.'
                    : '';
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode(
                    \Src\classes\UsernameHelper::profileErrorMessage('username_cooldown') . $detail
                ));
                exit;
            }

            $usernameValidation = \Src\classes\UsernameHelper::validate($submittedUsername);
            if ($usernameValidation !== null) {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode(
                    \Src\classes\UsernameHelper::profileErrorMessage($usernameValidation)
                ));
                exit;
            }

            if (\Src\classes\UsernameHelper::isTaken($submittedUsername, (int) $currentUser['id'])) {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode(
                    \Src\classes\UsernameHelper::profileErrorMessage('username_taken')
                ));
                exit;
            }
        }

        $storedPhone = \Src\classes\PhoneHelper::normalize(trim((string) ($currentUser['phone'] ?? '')));
        $storedEmail = \Src\classes\EmailVerificationService::normalizeEmail((string) ($currentUser['email'] ?? ''));
        $phoneWillChange = $phone !== $storedPhone;
        $emailWillChange = $email !== $storedEmail;
        $passwordWillChange = $newPassword !== '';
        $photoWillChange = $profilePhoto && (int) ($profilePhoto['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $emailChangeRequested = false;

        if (!$usernameWillChange && !$phoneWillChange && !$emailWillChange && !$passwordWillChange && !$photoWillChange) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Não há alterações para guardar.'));
            exit;
        }

        if ($phone === '') {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Telefone é obrigatório');
            exit;
        }
        if ($email === '') {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Email é obrigatório.'));
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Email inválido');
            exit;
        }
        if (User::findByPhoneExceptId($phone, $currentUser['id'])) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Telefone já está em uso');
            exit;
        }
        if ($emailWillChange) {
            $emailChangeResult = \Src\classes\EmailVerificationService::requestEmailChange(
                (int) $currentUser['id'],
                $email,
                (string) ($currentUser['name'] ?? '')
            );

            if ($emailChangeResult['status'] === 'taken') {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Este email já está registado noutra conta.'));
                exit;
            }
            if ($emailChangeResult['status'] === 'invalid' || $emailChangeResult['status'] === 'failed') {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Não foi possível enviar a confirmação para o novo email.'));
                exit;
            }

            $emailChangeRequested = ($emailChangeResult['status'] === 'sent');
        }
        if ($newPassword !== '' && strlen($newPassword) < 6) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Nova senha deve ter pelo menos 6 caracteres');
            exit;
        }
        if ($newPassword !== '' && $newPassword !== $confirmPassword) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Confirmação de senha não coincide');
            exit;
        }

        // Handle profile photo upload
        $photoUpload = $this->processProfilePhotoUpload($currentUser, $_FILES['profile_photo'] ?? null);
        if ($photoUpload['error'] !== null) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode($photoUpload['error']));
            exit;
        }
        $photoPath = $photoUpload['path'];

        if ($usernameWillChange && !User::updateUsername((int) $currentUser['id'], $submittedUsername)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode(
                \Src\classes\UsernameHelper::profileErrorMessage('username_locked')
            ));
            exit;
        }

        $updateData = [];
        if ($phoneWillChange) {
            $updateData['phone'] = $phone;
        }
        if ($emailWillChange) {
            $updateData['email'] = $email;
        }
        if ($passwordWillChange) {
            $updateData['password'] = $newPassword;
        }
        if ($photoPath !== null) {
            $updateData['profile_photo'] = DIRPAGE . $photoPath;
        }

        if (!empty($updateData)) {
            $updated = User::updateProfile($currentUser['id'], $updateData);
            if (!$updated) {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=Não foi possível atualizar o perfil');
                exit;
            }
        }

        $successParts = [];
        if ($emailChangeRequested) {
            $successParts[] = 'Enviámos um link de confirmação para o novo email. O email actual mantém-se até validar o link.';
        }
        if ($phoneWillChange || $passwordWillChange || $photoWillChange || $usernameWillChange) {
            $successParts[] = 'Os restantes dados foram guardados.';
        }
        if (empty($successParts)) {
            $successParts[] = 'Pedido registado.';
        }

        header('Location: ' . DIRPAGE . $redirectBase . '?success=' . rawurlencode(implode(' ', $successParts)));
        exit;
    }


    private function processIdentificationDocumentUpload(array $user): array
    {
        $file = $_FILES['document_file'] ?? null;
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['error' => null, 'uploaded' => false];
        }

        $userId = (int) ($user['id'] ?? 0);
        $uploadDir = DIRREQ . 'storage/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $rejectedDocuments = Document::getRejectedByUser($userId);
        if (!empty($rejectedDocuments)) {
            $rejectedDoc = $rejectedDocuments[0];
            $validation = ClassDocumentValidator::validateFile($file, (string) ($rejectedDoc['type'] ?? ClassDocumentValidator::TYPE_USER_REGISTRATION));
            if (!$validation['valid']) {
                return ['error' => (string) $validation['error'], 'uploaded' => false];
            }

            $tmpPath = (string) ($file['tmp_name'] ?? '');
            $originalName = (string) ($file['name'] ?? '');
            $nextVersion = ClassDocumentValidator::getNextVersion((string) ($rejectedDoc['version'] ?? 'v1'));
            $filename = ClassDocumentValidator::generateFilename($originalName, $nextVersion);

            if (!move_uploaded_file($tmpPath, $uploadDir . $filename)) {
                return ['error' => 'Falha ao guardar o documento.', 'uploaded' => false];
            }

            Document::create($userId, null, (string) $rejectedDoc['type'], $filename, $nextVersion);

            Log::create([
                'user_id' => $userId,
                'action' => 'resubmit_document',
                'entity_type' => 'document',
                'entity_id' => (int) ($rejectedDoc['id'] ?? 0),
                'details' => 'Documento resubmetido na versão ' . $nextVersion,
            ]);

            Notification::notifyUsers(
                User::getActiveAdminIds(),
                'document_resubmitted',
                'Documento resubmetido',
                'Um utilizador resubmeteu um documento de identificação (' . $nextVersion . ').',
                ['user_id' => $userId],
                $userId
            );

            return ['error' => null, 'uploaded' => true];
        }

        $compliance = Document::getComplianceStatus($userId);
        if ($compliance === 'compliant') {
            return ['error' => 'O seu documento já foi aceite — não é necessário enviar outro.', 'uploaded' => false];
        }

        $latest = Document::getLatestByUser($userId);
        if ($latest && (string) ($latest['status'] ?? '') === 'pendente') {
            return ['error' => 'Já estamos a analisar o último envio — aguarde antes de enviar outro ficheiro.', 'uploaded' => false];
        }

        $docType = ClassDocumentValidator::TYPE_USER_REGISTRATION;
        $validation = ClassDocumentValidator::validateFile($file, $docType);
        if (!$validation['valid']) {
            return ['error' => (string) $validation['error'], 'uploaded' => false];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        $version = $latest
            ? ClassDocumentValidator::getNextVersion((string) ($latest['version'] ?? 'v1'))
            : 'v1';
        $filename = ClassDocumentValidator::generateFilename($originalName, $version);

        if (!move_uploaded_file($tmpPath, $uploadDir . $filename)) {
            return ['error' => 'Falha ao guardar o documento.', 'uploaded' => false];
        }

        Document::create($userId, null, $docType, $filename, $version);

        Log::create([
            'user_id' => $userId,
            'action' => 'submit_account_document',
            'entity_type' => 'document',
            'entity_id' => null,
            'details' => 'Documento enviado na área de identificação (' . $version . ')',
        ]);

        Notification::notifyUsers(
            User::getActiveAdminIds(),
            'document_resubmitted',
            'Novo documento para análise',
            'Um utilizador enviou documento de identificação (' . $version . ').',
            ['user_id' => $userId],
            $userId
        );

        return ['error' => null, 'uploaded' => true];
    }

    /**
     * @return array{path: ?string, error: ?string}
     */
    private function processProfilePhotoUpload(array $user, ?array $profilePhoto): array
    {
        if (!$profilePhoto || (int) ($profilePhoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => null];
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $tmpName = (string) ($profilePhoto['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['path' => null, 'error' => 'Arquivo de imagem inválido'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!in_array($detectedMime, $allowedMimes, true)) {
            return ['path' => null, 'error' => 'Formato de imagem inválido. Aceitos: JPG, PNG, GIF, WebP'];
        }
        if (\Src\classes\UploadLimits::exceedsServerMax((int) ($profilePhoto['size'] ?? 0))) {
            return ['path' => null, 'error' => \Src\classes\UploadLimits::serverMaxError('A foto de perfil')];
        }

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $extMap[$detectedMime] ?? 'jpg';

        $uploadDirRelative = 'storage/uploads/profiles/';
        $uploadDirAbs = rtrim((string) DIRREQ, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadDirRelative);
        if (!is_dir($uploadDirAbs) && !mkdir($uploadDirAbs, 0755, true) && !is_dir($uploadDirAbs)) {
            return ['path' => null, 'error' => 'Não foi possível preparar a pasta da foto'];
        }

        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            $suffix = substr(md5(uniqid('', true)), 0, 12);
        }
        $filename = 'profile_' . (int) ($user['id'] ?? 0) . '_' . time() . '_' . $suffix . '.' . $ext;

        if (!move_uploaded_file($tmpName, $uploadDirAbs . $filename)) {
            return ['path' => null, 'error' => 'Erro ao fazer upload da foto'];
        }

        return ['path' => $uploadDirRelative . $filename, 'error' => null];
    }

    private function updateAdminProfile(array $currentUser, string $redirectBase): void
    {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        if (!ClassAuth::verifyCurrentPassword($currentUser, $currentPassword)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Indique a palavra-passe actual correcta.'));
            exit;
        }

        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $photoUpload = $this->processProfilePhotoUpload($currentUser, $_FILES['profile_photo'] ?? null);
        if ($photoUpload['error'] !== null) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode($photoUpload['error']));
            exit;
        }

        $passwordWillChange = $newPassword !== '';
        $photoWillChange = $photoUpload['path'] !== null;

        if (!$passwordWillChange && !$photoWillChange) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Não há alterações para guardar.'));
            exit;
        }

        if ($passwordWillChange) {
            if (strlen($newPassword) < 6) {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('A nova palavra-passe deve ter pelo menos 6 caracteres.'));
                exit;
            }
            if ($newPassword !== $confirmPassword) {
                header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('A confirmação da palavra-passe não coincide.'));
                exit;
            }
        }

        $updateData = [];
        if ($passwordWillChange) {
            $updateData['password'] = $newPassword;
        }
        if ($photoWillChange) {
            $updateData['profile_photo'] = DIRPAGE . $photoUpload['path'];
        }

        if (!User::updateProfile((int) $currentUser['id'], $updateData)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Não foi possível actualizar o perfil.'));
            exit;
        }

        $successParts = [];
        if ($photoWillChange) {
            $successParts[] = 'Foto de perfil actualizada.';
        }
        if ($passwordWillChange) {
            $successParts[] = 'Palavra-passe actualizada.';
        }

        header('Location: ' . DIRPAGE . $redirectBase . '?success=' . rawurlencode(implode(' ', $successParts)));
        exit;
    }

}
