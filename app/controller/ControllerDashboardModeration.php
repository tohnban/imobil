<?php

namespace App\controller;

use App\model\Document;
use App\model\Log;
use App\model\Notification;
use App\model\Property;
use App\model\Request;
use App\model\User;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\ClassDocumentValidator;
use Src\classes\UploadLimits;
use Src\classes\ClassRender;
use Src\classes\ClassTrustBadgeEligibility;
use Src\classes\PhoneHelper;

class ControllerDashboardModeration
{
    public function moderateUsers()
    {
        $user = ClassAccess::requirePermission('users.review', 'dashboard', 'Acesso disponível apenas para moderação');

        $allowedTabs = ['fila', 'pendentes', 'confianca', 'acessos', 'equipa'];
        $activeTab = (string) ($_GET['tab'] ?? 'pendentes');
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'pendentes';
        }
        $canManageSuperAdminTabs = ClassAccess::isSuperAdmin($user);
        if ($activeTab === 'acessos' && !$canManageSuperAdminTabs) {
            $activeTab = 'pendentes';
        }
        if ($activeTab === 'equipa' && !$canManageSuperAdminTabs) {
            $activeTab = 'pendentes';
        }

        $perPage          = 20;
        $page             = max(1, (int) ($_GET['page'] ?? 1));
        $offset           = ($page - 1) * $perPage;
        $pendingUsersTotal = \App\model\User::countPendingUsers();
        $allPendingUsersForQueue = \App\model\User::getPendingUsers();
        $pendingUsers     = \App\model\User::getPendingUsers($perPage, $offset);
        $totalPages       = (int) ceil($pendingUsersTotal / max(1, $perPage));

        $allPendingTrust    = \App\model\User::getTrustBadgePendingUsers();
        $pendingTrustTotal  = count($allPendingTrust);
        $pendingTrust       = array_slice($allPendingTrust, 0, 5);

        $accessStatusFilter = (string) ($_GET['access_status'] ?? 'all');
        $allowedAccessStatusFilters = ['all', 'ativo', 'rejeitado', 'pendente', 'suspenso'];
        if (!in_array($accessStatusFilter, $allowedAccessStatusFilters, true)) {
            $accessStatusFilter = 'all';
        }
        $accessSearch = trim((string) ($_GET['access_search'] ?? ''));
        $accessPage = max(1, (int) ($_GET['access_page'] ?? 1));
        $accessOffset = ($accessPage - 1) * $perPage;
        if ($canManageSuperAdminTabs) {
            $manageableUsersTotal = \App\model\User::countManageableUsers($accessStatusFilter, $accessSearch);
            $manageableUsers = \App\model\User::getManageableUsers($perPage, $accessOffset, $accessStatusFilter, $accessSearch);
            $manageableUsersTotalPages = (int) ceil($manageableUsersTotal / max(1, $perPage));
            $administrativeUsers = User::getAdministrativeUsers();
        } else {
            $manageableUsersTotal = 0;
            $manageableUsers = [];
            $manageableUsersTotalPages = 1;
            $administrativeUsers = [];
        }

        $pendingProperties = Property::getPending();  // used only for queue, not displayed as table
        $openRequests = Request::getOpenForAdmin();
        $pendingDocuments = Document::getPending(200, 0);
        $queueData = $this->buildAdminQueue($allPendingUsersForQueue, $pendingTrust, $pendingProperties, $openRequests, $pendingDocuments);

        $userCompliance = [];
        foreach ($pendingUsers as $pendingUser) {
            $uid = (int) ($pendingUser['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            $latestDoc = Document::getLatestByUser($uid);
            $compliance = Document::getComplianceStatus($uid);

            $stage = 'documental_validacao';
            if ($compliance === 'compliant') {
                $stage = 'aprovacao_final';
            }

            $userCompliance[$uid] = [
                'status' => $compliance,
                'stage' => $stage,
                'latest_document' => $latestDoc,
            ];
        }

        $render = new ClassRender();
        $render->setTitle('Moderação de Usuários');
        $render->setDescription('Aprovar ou rejeitar usuários pendentes');
        $render->setKeywords('moderação, usuários');
        $render->setData([
            'pendingUsers' => $pendingUsers,
            'pendingTrust'      => $pendingTrust,
            'pendingTrustTotal' => $pendingTrustTotal,
            'activeTab' => $activeTab,
            'canManageSuperAdminTabs' => $canManageSuperAdminTabs,
            'pendingProperties' => $pendingProperties,
            'openRequests' => $openRequests,
            'adminQueue' => $queueData['items'],
            'queueSummary' => $queueData['summary'],
            'userCompliance' => $userCompliance,
            'pendingUsersTotal' => $pendingUsersTotal,
            'page'              => $page,
            'totalPages'        => $totalPages,
            'manageableUsers' => $manageableUsers,
            'manageableUsersTotal' => $manageableUsersTotal,
            'manageableUsersPage' => $accessPage,
            'manageableUsersTotalPages' => $manageableUsersTotalPages,
            'accessStatusFilter' => $accessStatusFilter,
            'accessSearch' => $accessSearch,
            'administrativeUsers' => $administrativeUsers,
            'adminRoleOptions' => [
                'super_admin' => 'Admin Total',
                'moderador' => 'Admin Moderação',
                'suporte' => 'Admin Suporte',
                'financeiro' => 'Admin Financeiro',
            ],
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/moderate_users');
        $render->renderLayout();

        // Audit: record that an admin viewed the moderation users list (sensitive)
        \App\model\Log::sensitiveRead((int) ($user['id'] ?? null), 'user_list', null, 'Viewed moderation users list, tab=' . $activeTab);
    }

    public function blockUserAccess($id)
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Token inválido');
            exit;
        }

        $targetId = (int) $id;
        if ($targetId <= 0) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Utilizador inválido');
            exit;
        }

        if ($targetId === (int) ($admin['id'] ?? 0)) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Não é permitido bloquear o próprio acesso');
            exit;
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser || !empty($targetUser['is_admin']) || (string) ($targetUser['role'] ?? 'utilizador') !== 'utilizador') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Ação não permitida para este perfil');
            exit;
        }

        if (!empty($targetUser['suspended_until']) && strtotime((string) $targetUser['suspended_until']) > time()) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Utilizador já está com acesso suspenso');
            exit;
        }

        if (User::blockAccessByAdmin($targetId)) {
            Log::create([
                'user_id' => (int) ($admin['id'] ?? 0),
                'action' => 'block_user_access',
                'entity_type' => 'user',
                'entity_id' => $targetId,
                'details' => 'Acesso suspenso por moderação (users.status mantido: ' . (string) ($targetUser['status'] ?? '') . ')',
            ]);

            Notification::notifyUser(
                $targetId,
                'user_blocked',
                'Acesso suspenso',
                'O seu acesso à plataforma foi temporariamente suspenso pela equipa de moderação.',
                ['user_id' => $targetId],
                (int) ($admin['id'] ?? 0)
            );

            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&success=Acesso suspenso com sucesso');
            exit;
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Não foi possível suspender o acesso');
        exit;
    }

    public function unblockUserAccess($id)
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Token inválido');
            exit;
        }

        $targetId = (int) $id;
        if ($targetId <= 0) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Utilizador inválido');
            exit;
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser || !empty($targetUser['is_admin']) || (string) ($targetUser['role'] ?? 'utilizador') !== 'utilizador') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Ação não permitida para este perfil');
            exit;
        }

        if (empty($targetUser['suspended_until']) || strtotime((string) $targetUser['suspended_until']) <= time()) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Utilizador não está com acesso suspenso');
            exit;
        }

        if (User::unsuspendByAdmin($targetId)) {
            Log::create([
                'user_id' => (int) ($admin['id'] ?? 0),
                'action' => 'unblock_user_access',
                'entity_type' => 'user',
                'entity_id' => $targetId,
                'details' => 'Suspensão de acesso levantada (users.status: ' . (string) ($targetUser['status'] ?? '') . ')',
            ]);

            Notification::notifyUser(
                $targetId,
                'user_unblocked',
                'Suspensão levantada',
                'Já pode voltar a aceder à plataforma.',
                ['user_id' => $targetId],
                (int) ($admin['id'] ?? 0)
            );

            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&success=Suspensão levantada com sucesso');
            exit;
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Não foi possível levantar a suspensão');
        exit;
    }

    public function suspendUserAccess($id)
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Token inválido');
            exit;
        }

        $targetId = (int) $id;
        if ($targetId <= 0) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Utilizador inválido');
            exit;
        }

        if ($targetId === (int) ($admin['id'] ?? 0)) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Não é permitido suspender o próprio acesso');
            exit;
        }

        $suspendDays = max(1, min(365, (int) ($_POST['suspend_days'] ?? 0)));
        if ($suspendDays <= 0) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Número de dias inválido');
            exit;
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser || !empty($targetUser['is_admin']) || (string) ($targetUser['role'] ?? 'utilizador') !== 'utilizador') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Ação não permitida para este perfil');
            exit;
        }

        if ((string) ($targetUser['status'] ?? '') !== 'ativo') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Somente utilizadores ativos podem ser suspensos');
            exit;
        }

        if (User::suspendByAdmin($targetId, $suspendDays)) {
            $until = date('d/m/Y', strtotime('+' . $suspendDays . ' days'));
            Log::create([
                'user_id' => (int) ($admin['id'] ?? 0),
                'action' => 'suspend_user_access',
                'entity_type' => 'user',
                'entity_id' => $targetId,
                'details' => 'Acesso suspenso por ' . $suspendDays . ' dias até ' . $until,
            ]);

            Notification::notifyUser(
                $targetId,
                'user_suspended',
                'Acesso suspenso',
                'O seu acesso foi suspenso por ' . $suspendDays . ' dias, até ' . $until . '.',
                ['user_id' => $targetId, 'suspended_until' => $until],
                (int) ($admin['id'] ?? 0)
            );

            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&success=Utilizador suspenso com sucesso');
            exit;
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Não foi possível suspender o utilizador');
        exit;
    }

    public function unsuspendUserAccess($id)
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Token inválido');
            exit;
        }

        $targetId = (int) $id;
        if ($targetId <= 0) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Utilizador inválido');
            exit;
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser || !empty($targetUser['is_admin']) || (string) ($targetUser['role'] ?? 'utilizador') !== 'utilizador') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Ação não permitida para este perfil');
            exit;
        }

        if (empty($targetUser['suspended_until']) || strtotime((string) $targetUser['suspended_until']) <= time()) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Utilizador não está suspenso');
            exit;
        }

        if (User::unsuspendByAdmin($targetId)) {
            Log::create([
                'user_id' => (int) ($admin['id'] ?? 0),
                'action' => 'unsuspend_user_access',
                'entity_type' => 'user',
                'entity_id' => $targetId,
                'details' => 'Suspensão de acesso levantada manualmente',
            ]);

            Notification::notifyUser(
                $targetId,
                'user_unsuspended',
                'Suspensão levantada',
                'A sua suspensão foi levantada e o acesso voltou a ficar normal.',
                ['user_id' => $targetId],
                (int) ($admin['id'] ?? 0)
            );

            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&success=Suspensão levantada com sucesso');
            exit;
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=acessos&error=Não foi possível levantar a suspensão');
        exit;
    }

    public function setAdminRole($id)
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Token inválido');
            exit;
        }

        $targetId = (int) $id;
        if ($targetId <= 0) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Utilizador inválido');
            exit;
        }

        $newRole = strtolower(trim((string) ($_POST['role'] ?? '')));
        $allowedRoles = ['super_admin', 'moderador', 'suporte', 'financeiro'];
        if (!in_array($newRole, $allowedRoles, true)) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Papel inválido');
            exit;
        }

        if ($targetId === (int) ($admin['id'] ?? 0) && $newRole !== 'super_admin') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Não pode reduzir o seu próprio nível de acesso por este ecrã');
            exit;
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Utilizador não encontrado');
            exit;
        }

        $currentRole = (string) ($targetUser['role'] ?? 'utilizador');
        $targetIsActive = (string) ($targetUser['status'] ?? '') === 'ativo';
        if ($currentRole === 'super_admin' && $newRole !== 'super_admin' && $targetIsActive) {
            $activeSuperAdmins = User::countActiveSuperAdmins();
            if ($activeSuperAdmins <= 1) {
                header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Não é permitido rebaixar o último Admin Total ativo');
                exit;
            }
        }

        if (User::setAdministrativeRole($targetId, $newRole)) {
            Log::create([
                'user_id' => (int) ($admin['id'] ?? 0),
                'action' => 'set_admin_role',
                'entity_type' => 'user',
                'entity_id' => $targetId,
                'details' => 'Papel administrativo alterado para: ' . $newRole,
            ]);

            Notification::notifyUser(
                $targetId,
                'admin_role_updated',
                'Perfil administrativo atualizado',
                'O seu papel administrativo foi atualizado para ' . ClassAccess::roleLabel(['role' => $newRole, 'is_admin' => 1]) . '.',
                ['user_id' => $targetId, 'role' => $newRole],
                (int) ($admin['id'] ?? 0)
            );

            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&success=Papel administrativo atualizado com sucesso');
            exit;
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Não foi possível atualizar o papel');
        exit;
    }

    public function createAdministrativeUser()
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Token inválido');
            exit;
        }

        $result = User::createAdministrativeUser([
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'phone' => PhoneHelper::normalize(trim((string) ($_POST['phone'] ?? ''))),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
            'role' => strtolower(trim((string) ($_POST['role'] ?? ''))),
        ]);

        if (empty($result['ok'])) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=' . urlencode((string) ($result['error'] ?? 'Não foi possível criar a conta')));
            exit;
        }

        $userId = (int) ($result['user_id'] ?? 0);
        Log::create([
            'user_id' => (int) ($admin['id'] ?? 0),
            'action' => 'create_admin_user',
            'entity_type' => 'user',
            'entity_id' => $userId > 0 ? $userId : null,
            'details' => json_encode([
                'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
                'role' => strtolower(trim((string) ($_POST['role'] ?? ''))),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if ($userId > 0) {
            Notification::notifyUser(
                $userId,
                'admin_account_created',
                'Conta administrativa criada',
                'Foi criada uma conta administrativa para si na Imobil. Utilize o email e a palavra-passe definidos pelo Admin Total para entrar.',
                ['user_id' => $userId],
                (int) ($admin['id'] ?? 0)
            );
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&success=Conta administrativa criada com sucesso');
        exit;
    }

    public function revokeAdministrativeAccess($id)
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Token inválido');
            exit;
        }

        $targetId = (int) $id;
        $guardError = $this->guardAdministrativeTargetMutation($admin, $targetId, 'revoke');
        if ($guardError !== null) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=' . urlencode($guardError));
            exit;
        }

        if (User::revokeAdministrativeAccess($targetId)) {
            Log::create([
                'user_id' => (int) ($admin['id'] ?? 0),
                'action' => 'revoke_admin_access',
                'entity_type' => 'user',
                'entity_id' => $targetId,
                'details' => 'Acesso administrativo revogado; conta convertida em utilizador normal',
            ]);

            Notification::notifyUser(
                $targetId,
                'admin_access_revoked',
                'Acesso administrativo revogado',
                'O seu perfil administrativo foi removido. A conta passa a ser de utilizador normal.',
                ['user_id' => $targetId],
                (int) ($admin['id'] ?? 0)
            );

            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&success=Acesso administrativo revogado');
            exit;
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Não foi possível revogar o acesso administrativo');
        exit;
    }

    public function suspendAdministrativeAccess($id)
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Token inválido');
            exit;
        }

        $targetId = (int) $id;
        $guardError = $this->guardAdministrativeTargetMutation($admin, $targetId, 'suspend');
        if ($guardError !== null) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=' . urlencode($guardError));
            exit;
        }

        $suspendDays = max(1, min(3650, (int) ($_POST['suspend_days'] ?? 0)));
        if ($suspendDays <= 0) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Número de dias inválido');
            exit;
        }

        if (User::suspendAdministrativeAccess($targetId, $suspendDays)) {
            $until = date('d/m/Y', strtotime('+' . $suspendDays . ' days'));
            Log::create([
                'user_id' => (int) ($admin['id'] ?? 0),
                'action' => 'suspend_admin_access',
                'entity_type' => 'user',
                'entity_id' => $targetId,
                'details' => 'Acesso administrativo suspenso por ' . $suspendDays . ' dias até ' . $until,
            ]);

            Notification::notifyUser(
                $targetId,
                'admin_access_suspended',
                'Acesso administrativo suspenso',
                'O seu acesso administrativo foi suspenso por ' . $suspendDays . ' dias, até ' . $until . '.',
                ['user_id' => $targetId, 'suspended_until' => $until],
                (int) ($admin['id'] ?? 0)
            );

            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&success=Acesso administrativo suspenso');
            exit;
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Não foi possível suspender o administrador');
        exit;
    }

    public function unsuspendAdministrativeAccess($id)
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard/moderateUsers', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Token inválido');
            exit;
        }

        $targetId = (int) $id;
        if ($targetId <= 0) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Utilizador inválido');
            exit;
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser || !User::isAdministrativeUser($targetUser)) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Administrador não encontrado');
            exit;
        }

        if (empty($targetUser['suspended_until']) || strtotime((string) $targetUser['suspended_until']) <= time()) {
            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Este administrador não está suspenso');
            exit;
        }

        if (User::unsuspendAdministrativeAccess($targetId)) {
            Log::create([
                'user_id' => (int) ($admin['id'] ?? 0),
                'action' => 'unsuspend_admin_access',
                'entity_type' => 'user',
                'entity_id' => $targetId,
                'details' => 'Suspensão de acesso administrativo levantada',
            ]);

            Notification::notifyUser(
                $targetId,
                'admin_access_unsuspended',
                'Suspensão levantada',
                'A suspensão do seu acesso administrativo foi levantada.',
                ['user_id' => $targetId],
                (int) ($admin['id'] ?? 0)
            );

            header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&success=Suspensão administrativa levantada');
            exit;
        }

        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=equipa&error=Não foi possível levantar a suspensão');
        exit;
    }

    private function guardAdministrativeTargetMutation(array $admin, int $targetId, string $action): ?string
    {
        if ($targetId <= 0) {
            return 'Utilizador inválido';
        }

        if ($targetId === (int) ($admin['id'] ?? 0)) {
            return 'Não é permitido alterar o seu próprio acesso administrativo por este ecrã';
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser || !User::isAdministrativeUser($targetUser)) {
            return 'Administrador não encontrado';
        }

        $currentRole = (string) ($targetUser['role'] ?? 'utilizador');
        $targetIsActive = (string) ($targetUser['status'] ?? '') === 'ativo';
        if ($currentRole === 'super_admin' && $targetIsActive && User::countActiveSuperAdmins() <= 1) {
            if ($action === 'revoke' || $action === 'suspend') {
                return 'Não é permitido restringir o último Admin Total ativo';
            }
        }

        return null;
    }

    private function redirectModerateUsersPendentes(string $type, string $message): void
    {
        $param = $type === 'success' ? 'success' : 'error';
        header('Location: ' . DIRPAGE . 'dashboard/moderateUsers?tab=pendentes&' . $param . '=' . rawurlencode($message));
        exit;
    }

    public function approveUser($id)
    {
        $user = ClassAccess::requirePermission('users.review', 'dashboard', 'Acesso disponível apenas para moderação');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectModerateUsersPendentes('error', 'Token inválido');
        }

        $targetId = (int) $id;
        if ($targetId <= 0) {
            $this->redirectModerateUsersPendentes('error', 'Utilizador inválido');
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser || (string) ($targetUser['status'] ?? '') !== 'pendente') {
            $this->redirectModerateUsersPendentes('error', 'Utilizador não encontrado ou já não está pendente');
        }

        $compliance = Document::getComplianceStatus($targetId);
        if ($compliance !== 'compliant') {
            $this->redirectModerateUsersPendentes('error', 'Aprovação final bloqueada: validação documental pendente');
        }

        if (!\App\model\User::approveUser($targetId)) {
            $this->redirectModerateUsersPendentes('error', 'Não foi possível aprovar o utilizador');
        }

        \App\model\Log::create([
            'user_id' => $user['id'],
            'action' => 'approve_user',
            'entity_type' => 'user',
            'entity_id' => $targetId,
            'details' => 'Aprovação final concluída após validação documental',
        ]);

        Notification::notifyUser(
            $targetId,
            'user_approved',
            'Conta aprovada',
            'Sua conta foi aprovada e já está ativa na plataforma.',
            ['user_id' => $targetId],
            (int) $user['id']
        );

        $this->redirectModerateUsersPendentes('success', 'Utilizador aprovado com sucesso');
    }

    public function requestTrustedBadge()
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'profile');
            exit;
        }

        $userId = (int) ($user['id'] ?? 0);
        $trustGate = ClassTrustBadgeEligibility::assertCanRequest($userId);
        if (($trustGate['allowed'] ?? false) !== true) {
            $blockers = $trustGate['blockers'] ?? [];
            $errorMsg = !empty($blockers)
                ? implode('. ', $blockers)
                : 'Não é possível solicitar o selo neste momento';
            header('Location: ' . DIRPAGE . 'profile?error=' . urlencode($errorMsg) . '#trust-badge-section');
            exit;
        }

        $months = (int) ($_POST['trust_badge_months'] ?? 0);
        $feeRequired = User::calculateTrustedBadgeFeeByMonths($months);
        if ($feeRequired <= 0) {
            header('Location: ' . DIRPAGE . 'profile#trust-badge-section?error=Duração do selo inválida');
            exit;
        }

        // Handle payment proof upload (required)
        $proofPath = '';
        $proofFile = $_FILES['payment_proof'] ?? null;
        if (empty($proofFile['tmp_name']) || ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            header('Location: ' . DIRPAGE . 'profile#trust-badge-section?error=Comprovativo de pagamento obrigatório');
            exit;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $proofMime = (string) $finfo->file((string) $proofFile['tmp_name']);
        $allowedProofMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($proofMime, $allowedProofMimes, true)) {
            header('Location: ' . DIRPAGE . 'profile#trust-badge-section?error=Formato inválido (use JPG, PNG ou WebP)');
            exit;
        }
        if (UploadLimits::exceedsServerMax((int) ($proofFile['size'] ?? 0))) {
            header('Location: ' . DIRPAGE . 'profile#trust-badge-section?error=' . rawurlencode(UploadLimits::serverMaxError('O comprovativo')));
            exit;
        }

        $proofUploadDirRelative = 'public/storage/uploads/trust_badge_proofs/';
        $proofUploadDir = DIRREQ . $proofUploadDirRelative;
        if (!is_dir($proofUploadDir)) {
            mkdir($proofUploadDir, 0755, true);
        }
        $proofExtMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $proofExt = $proofExtMap[$proofMime] ?? 'jpg';
        $proofFilename = 'proof_' . $user['id'] . '_' . time() . '.' . $proofExt;
        $proofAbsolutePath = $proofUploadDir . $proofFilename;
        if (!move_uploaded_file((string) $proofFile['tmp_name'], $proofAbsolutePath)) {
            header('Location: ' . DIRPAGE . 'profile#trust-badge-section?error=Erro ao guardar comprovativo');
            exit;
        }
        $proofPath = DIRPAGE . $proofUploadDirRelative . $proofFilename;

        if (!User::requestTrustedBadge($userId, $months, $feeRequired, $proofPath)) {
            @unlink($proofAbsolutePath);
            $blockers = ClassTrustBadgeEligibility::assertCanRequest($userId)['blockers'] ?? [];
            $errorMsg = !empty($blockers)
                ? implode('. ', $blockers)
                : 'Não foi possível registar o pedido. Verifique os requisitos do selo.';
            header('Location: ' . DIRPAGE . 'profile?error=' . urlencode($errorMsg) . '#trust-badge-section');
            exit;
        }

        Notification::notifyUsers(
            User::getActiveAdminIds(),
            'trusted_badge_requested',
            'Nova solicitação de selo',
            'Um utilizador solicitou análise para o selo de confiança (' . $months . ' mes(es), ' . number_format($feeRequired, 0, ',', '.') . ' Kz).',
            ['user_id' => (int) $user['id'], 'months' => $months, 'fee' => $feeRequired],
            (int) $user['id']
        );

        header('Location: ' . DIRPAGE . 'profile?success=Solicitação do selo de confiança enviada para análise (' . $months . ' mes(es))');
        exit;
    }

    public function approveTrustedBadge($id)
    {
        ClassAuth::requireAuth();
        $admin = ClassAuth::user();
        if (!ClassAccess::can('payments.manage', $admin) && !ClassAccess::can('users.review', $admin)) {
            ClassAccess::requireSuperAdmin('dashboard', 'Sem permissão para aprovar selo de confiança');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/payments?error=Token inválido');
            exit;
        }

        $targetUser = User::findById((int) $id);
        if (!$targetUser || empty($targetUser['trust_badge_fee_paid'])) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?error=Confirme o pagamento antes de aprovar o selo');
            exit;
        }

        $fee = (float) ($targetUser['trust_badge_fee_required'] ?? 0);

        User::approveTrustedBadge((int) $id);
        \App\model\Log::create([
            'user_id' => $admin['id'],
            'action' => 'approve_trusted_badge',
            'entity_type' => 'user',
            'entity_id' => $id,
            'details' => 'Selo de confiança aprovado (pagamento já confirmado, taxa: ' . $fee . ')',
        ]);

        Notification::notifyUser(
            (int) $id,
            'trusted_badge_approved',
            'Selo de confiança aprovado',
            'Seu selo de confiança foi aprovado. Taxa paga: ' . number_format($fee, 0, ',', '.') . ' Kz.',
            ['user_id' => (int) $id, 'fee' => $fee],
            (int) $admin['id']
        );

        header('Location: ' . DIRPAGE . 'dashboard/payments?success=Selo de confiança aprovado com sucesso');
        exit;
    }

    public function rejectTrustedBadge($id)
    {
        ClassAuth::requireAuth();
        $admin = ClassAuth::user();
        if (!ClassAccess::can('payments.manage', $admin) && !ClassAccess::can('users.review', $admin)) {
            ClassAccess::requireSuperAdmin('dashboard', 'Sem permissão para rejeitar selo de confiança');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/payments?error=Token inválido');
            exit;
        }

        User::rejectTrustedBadge($id);
        \App\model\Log::create([
            'user_id' => $admin['id'],
            'action' => 'reject_trusted_badge',
            'entity_type' => 'user',
            'entity_id' => $id,
            'details' => 'Selo de confiança rejeitado',
        ]);

        Notification::notifyUser(
            (int) $id,
            'trusted_badge_rejected',
            'Selo de confiança rejeitado',
            'Sua solicitação de selo de confiança foi rejeitada. Você pode solicitar novamente depois.',
            ['user_id' => (int) $id],
            (int) $admin['id']
        );

        header('Location: ' . DIRPAGE . 'dashboard/payments?success=Solicitação de selo rejeitada');
        exit;
    }

    public function confirmTrustedBadgePayment($id)
    {
        $admin = ClassAccess::requirePermission('payments.manage', 'dashboard', 'Acesso disponível apenas para a equipa financeira');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/payments?error=Token inválido');
            exit;
        }

        if (isset($_POST['fee'])) {
            $fee = max(0.0, (float) $_POST['fee']);
            User::setTrustBadgeFeeRequired((int) $id, $fee);
        }

        User::markTrustedBadgeFeePaid($id);
        \App\model\Log::create([
            'user_id' => $admin['id'],
            'action' => 'confirm_trusted_badge_payment',
            'entity_type' => 'user',
            'entity_id' => $id,
            'details' => 'Pagamento do selo de confiança confirmado',
        ]);

        Notification::notifyUser(
            (int) $id,
            'trusted_badge_payment_confirmed',
            'Pagamento confirmado',
            'O pagamento da taxa do selo de confiança foi confirmado.',
            ['user_id' => (int) $id],
            (int) $admin['id']
        );

        header('Location: ' . DIRPAGE . 'dashboard/payments?success=Pagamento do selo confirmado');
        exit;
    }

    public function rejectUser($id)
    {
        $user = ClassAccess::requirePermission('users.review', 'dashboard', 'Acesso disponível apenas para moderação');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectModerateUsersPendentes('error', 'Token inválido');
        }

        $targetId = (int) $id;
        if ($targetId <= 0) {
            $this->redirectModerateUsersPendentes('error', 'Utilizador inválido');
        }

        $targetUser = User::findById($targetId);
        if (!$targetUser || (string) ($targetUser['status'] ?? '') !== 'pendente') {
            $this->redirectModerateUsersPendentes('error', 'Utilizador não encontrado ou já não está pendente');
        }

        if (!\App\model\User::rejectUser($targetId)) {
            $this->redirectModerateUsersPendentes('error', 'Não foi possível rejeitar o utilizador');
        }

        \App\model\Log::create([
            'user_id' => $user['id'],
            'action' => 'reject_user',
            'entity_type' => 'user',
            'entity_id' => $targetId,
            'details' => 'Usuário rejeitado',
        ]);

        Notification::notifyUser(
            $targetId,
            'user_rejected',
            'Conta rejeitada',
            'Sua conta foi rejeitada após análise documental.',
            ['user_id' => $targetId],
            (int) $user['id']
        );

        $this->redirectModerateUsersPendentes('success', 'Utilizador rejeitado');
    }

    public function reviewDocuments()
    {
        $admin = ClassAccess::requirePermission('documents.review', 'dashboard', 'Acesso disponível apenas para moderação documental');

        $perPage = 20;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $pendingDocs = Document::getPending($perPage, $offset);
        $totalPending = Document::countPending();
        $totalPages = (int) ceil($totalPending / $perPage);
        $stats = Document::getComplianceStats();

        $render = new ClassRender();
        $render->setTitle('Revisão de Documentos');
        $render->setDescription('Revise documentos enviados por utilizadores');
        $render->setKeywords('documentos, conformidade, revisão');
        $render->setData([
            'user' => $admin,
            'pendingDocuments' => $pendingDocs,
            'totalPending' => $totalPending,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'stats' => $stats,
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/review_documents');
        $render->renderLayout();

        // Audit: record that an admin viewed documents pending review
        \App\model\Log::sensitiveRead((int) ($admin['id'] ?? null), 'document_list', null, 'Viewed pending documents list');
    }

    public function approveDocument($id)
    {
        $admin = ClassAccess::requirePermission('documents.review', 'dashboard', 'Acesso disponível apenas para moderação documental');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?error=Token inválido');
            exit;
        }

        $document = Document::findById((int) $id);
        if (!$document) {
            header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?error=Documento não encontrado');
            exit;
        }

        if (!Document::approve((int) $id, (int) $admin['id'])) {
            header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?error=Erro ao aprovar documento');
            exit;
        }

        Log::create([
            'user_id' => (int) $admin['id'],
            'action' => 'approve_document',
            'entity_type' => 'document',
            'entity_id' => (int) $id,
            'details' => 'Documento aprovado: ' . $document['type'] . ' (' . $document['version'] . ')',
        ]);

        if ($document['user_id']) {
            Notification::notifyUser(
                (int) $document['user_id'],
                'document_approved',
                'Documento aprovado',
                'Seu documento (' . $document['type'] . ') foi aprovado após análise.',
                ['document_id' => (int) $id, 'doc_type' => $document['type']],
                (int) $admin['id']
            );
        }

        header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?success=Documento aprovado');
        exit;
    }

    public function rejectDocument($id)
    {
        $admin = ClassAccess::requirePermission('documents.review', 'dashboard', 'Acesso disponível apenas para moderação documental');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?error=Token inválido');
            exit;
        }

        $document = Document::findById((int) $id);
        if (!$document) {
            header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?error=Documento não encontrado');
            exit;
        }

        $rejectionReason = trim($_POST['rejection_reason'] ?? '');
        if (empty($rejectionReason)) {
            header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?error=Motivo da rejeição é obrigatório');
            exit;
        }

        try {
            Document::reject((int) $id, $rejectionReason, (int) $admin['id']);
        } catch (\Exception $e) {
            header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?error=' . urlencode($e->getMessage()));
            exit;
        }

        Log::create([
            'user_id' => (int) $admin['id'],
            'action' => 'reject_document',
            'entity_type' => 'document',
            'entity_id' => (int) $id,
            'details' => 'Documento rejeitado: ' . $document['type'] . ' (' . $document['version'] . '). Motivo: ' . $rejectionReason,
        ]);

        if ($document['user_id']) {
            Notification::notifyUser(
                (int) $document['user_id'],
                'document_rejected',
                'Documento rejeitado',
                'Seu documento foi rejeitado. Motivo: ' . $rejectionReason . '. Por favor, resubmeta um novo documento.',
                ['document_id' => (int) $id, 'doc_type' => $document['type'], 'reason' => $rejectionReason],
                (int) $admin['id']
            );
        }

        header('Location: ' . DIRPAGE . 'dashboard/reviewDocuments?success=Documento rejeitado');
        exit;
    }

    public function resubmitDocument($documentId)
    {
        $user = ClassAccess::requireAuthenticatedAccount();
        $redirectBase = 'dashboard/accountStatus';

        if (!ClassAccess::canSubmitDocumentsOnAccountStatusPage($user)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Só pode reenviar documentos quando a identificação precisar de correcção.'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Token inválido');
            exit;
        }

        if (!isset($_FILES['document_file'])) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Nenhum ficheiro foi enviado');
            exit;
        }

        $rejectedDoc = Document::findById((int) $documentId);
        if (!$rejectedDoc || $rejectedDoc['user_id'] !== (int) $user['id'] || $rejectedDoc['status'] !== 'rejeitado') {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Documento não encontrado ou não é passível de resubmissão');
            exit;
        }

        // Validate new file
        $validation = ClassDocumentValidator::validateFile(
            $_FILES['document_file'],
            $rejectedDoc['type']
        );

        if (!$validation['valid']) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . urlencode($validation['error']));
            exit;
        }

        // Move file
        $uploadDir = DIRREQ . 'storage/documents/';
        $tmpPath = (string) ($_FILES['document_file']['tmp_name'] ?? '');
        $originalName = (string) ($_FILES['document_file']['name'] ?? '');

        $nextVersion = ClassDocumentValidator::getNextVersion($rejectedDoc['version']);
        $filename = ClassDocumentValidator::generateFilename($originalName, $nextVersion);

        if (!move_uploaded_file($tmpPath, $uploadDir . $filename)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Falha ao guardar o documento');
            exit;
        }

        // Create new document record with next version
        Document::create(
            (int) $user['id'],
            null,
            $rejectedDoc['type'],
            $filename,
            $nextVersion
        );

        Log::create([
            'user_id' => (int) $user['id'],
            'action' => 'resubmit_document',
            'entity_type' => 'document',
            'entity_id' => (int) $documentId,
            'details' => 'Documento resubmetido na versão ' . $nextVersion . '. Anterior: ' . $rejectedDoc['version'],
        ]);

        Notification::notifyUsers(
            User::getActiveAdminIds(),
            'document_resubmitted',
            'Documento resubmetido',
            'Um utilizador resubmeteu um documento rejeitado (' . $rejectedDoc['type'] . ' v' . $nextVersion . ').',
            ['user_id' => (int) $user['id'], 'doc_type' => $rejectedDoc['type']],
            (int) $user['id']
        );

        header('Location: ' . DIRPAGE . $redirectBase . '?success=Recebemos o novo documento — vamos analisar em breve');
        exit;
    }

    public function submitAccountDocument()
    {
        $user = ClassAccess::requireAuthenticatedAccount();
        $redirectBase = 'dashboard/accountStatus';

        if (!ClassAccess::canSubmitDocumentsOnAccountStatusPage($user)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . rawurlencode('Só pode enviar documentos quando a identificação precisar de correcção.'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Token inválido');
            exit;
        }

        if (!isset($_FILES['document_file']) || (int) ($_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Selecione um documento para enviar');
            exit;
        }

        $compliance = Document::getComplianceStatus((int) $user['id']);
        if ($compliance === 'compliant') {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=O seu documento já foi aceite');
            exit;
        }

        $latest = Document::getLatestByUser((int) $user['id']);
        if ($latest && (string) ($latest['status'] ?? '') === 'pendente') {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Já estamos a analisar o seu último envio — aguarde um pouco');
            exit;
        }

        $docType = ClassDocumentValidator::TYPE_USER_REGISTRATION;
        $validation = ClassDocumentValidator::validateFile($_FILES['document_file'], $docType);
        if (!$validation['valid']) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=' . urlencode($validation['error']));
            exit;
        }

        $uploadDir = DIRREQ . 'storage/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $tmpPath = (string) ($_FILES['document_file']['tmp_name'] ?? '');
        $originalName = (string) ($_FILES['document_file']['name'] ?? '');
        $version = $latest ? ClassDocumentValidator::getNextVersion((string) ($latest['version'] ?? 'v1')) : 'v1';
        $filename = ClassDocumentValidator::generateFilename($originalName, $version);

        if (!move_uploaded_file($tmpPath, $uploadDir . $filename)) {
            header('Location: ' . DIRPAGE . $redirectBase . '?error=Falha ao guardar o documento');
            exit;
        }

        Document::create((int) $user['id'], null, $docType, $filename, $version);

        Log::create([
            'user_id' => (int) $user['id'],
            'action' => 'submit_account_document',
            'entity_type' => 'document',
            'entity_id' => null,
            'details' => 'Documento enviado na área de estado da conta (' . $version . ')',
        ]);

        Notification::notifyUsers(
            User::getActiveAdminIds(),
            'document_resubmitted',
            'Novo documento para análise',
            'Um utilizador enviou documento para validação (' . $version . ').',
            ['user_id' => (int) $user['id']],
            (int) $user['id']
        );

        header('Location: ' . DIRPAGE . $redirectBase . '?success=Documento recebido — avisamos quando houver novidades');
        exit;
    }

    private function buildAdminQueue(array $pendingUsers, array $pendingTrust, array $pendingProperties, array $openRequests, array $pendingDocuments = []): array
    {
        $items = [];

        foreach ($pendingUsers as $entry) {
            $items[] = $this->queueItemFromDate(
                'user_verification',
                (int) ($entry['id'] ?? 0),
                (string) ($entry['created_at'] ?? ''),
                'Verificação de perfil pendente',
                (string) ($entry['name'] ?? 'Utilizador')
            );
        }

        foreach ($pendingTrust as $entry) {
            $items[] = $this->queueItemFromDate(
                'trusted_badge',
                (int) ($entry['id'] ?? 0),
                (string) ($entry['trust_badge_requested_at'] ?? $entry['created_at'] ?? ''),
                'Solicitação de selo pendente',
                (string) ($entry['name'] ?? 'Utilizador')
            );
        }

        foreach ($pendingProperties as $entry) {
            $items[] = $this->queueItemFromDate(
                'property_moderation',
                (int) ($entry['id'] ?? 0),
                (string) ($entry['created_at'] ?? ''),
                'Imóvel aguardando moderação',
                (string) ($entry['title'] ?? 'Imóvel sem título')
            );
        }

        foreach ($openRequests as $entry) {
            $items[] = $this->queueItemFromDate(
                'request_followup',
                (int) ($entry['id'] ?? 0),
                (string) ($entry['created_at'] ?? ''),
                'Solicitação aberta sem desfecho',
                (string) ($entry['title'] ?? 'Solicitação')
            );
        }

        foreach ($pendingDocuments as $entry) {
            $items[] = $this->queueItemFromDate(
                'document_review',
                (int) ($entry['id'] ?? 0),
                (string) ($entry['created_at'] ?? ''),
                'Validação documental pendente',
                (string) ($entry['type'] ?? 'documento')
            );
        }

        usort($items, function ($a, $b) {
            $priorityOrder = ['atrasado' => 3, 'urgente' => 2, 'pendente' => 1];
            $pa = $priorityOrder[$a['priority']] ?? 0;
            $pb = $priorityOrder[$b['priority']] ?? 0;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            return strcmp($a['created_at'], $b['created_at']);
        });

        $summary = [
            'pendente' => 0,
            'urgente' => 0,
            'atrasado' => 0,
            'total' => count($items),
        ];

        foreach ($items as $item) {
            $summary[$item['priority']]++;
        }

        return ['items' => $items, 'summary' => $summary];
    }

    private function queueItemFromDate(string $type, int $entityId, string $createdAt, string $title, string $subject): array
    {
        $now = new \DateTimeImmutable('now');
        try {
            $created = new \DateTimeImmutable($createdAt);
        } catch (\Exception $e) {
            $created = $now;
        }

        $ageDays = (int) $created->diff($now)->format('%a');
        $priority = 'pendente';
        if ($ageDays >= 7) {
            $priority = 'atrasado';
        } elseif ($ageDays >= 3) {
            $priority = 'urgente';
        }

        return [
            'type' => $type,
            'entity_id' => $entityId,
            'title' => $title,
            'subject' => $subject,
            'created_at' => $created->format('Y-m-d H:i:s'),
            'age_days' => $ageDays,
            'priority' => $priority,
        ];
    }
}
