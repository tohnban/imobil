<?php

namespace App\controller;

use App\model\Log;
use App\model\PaymentMethod;
use App\model\PaymentTransaction;
use App\model\SubscriptionPlan;
use App\model\SystemPaymentChannel;
use App\model\User;
use App\model\UserSubscription;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\ClassRender;
use Src\classes\UploadLimits;

class ControllerDashboardSubscriptions
{
    public function subscription()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não possuem subscrição comercial.');

        $currentSubscription = UserSubscription::getCurrentByUser((int) $user['id']);
        $history = UserSubscription::getHistoryByUser((int) $user['id'], 24);
        $plans = SubscriptionPlan::getActiveCatalog();

        $render = new ClassRender();
        $render->setTitle('Meu Plano');
        $render->setDescription('Gerencie seu plano de publicação e renovação');
        $render->setKeywords('plano, subscrição, assinatura');
        $render->setData([
            'user' => $user,
            'currentSubscription' => $currentSubscription,
            'plans' => $plans,
            'history' => $history,
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/subscription');
        $render->renderLayout();
    }

    public function subscriptionCheckout()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não possuem subscrição comercial.');

        $planCode = trim(strtolower((string) ($_GET['plan_code'] ?? '')));
        if ($planCode === '') {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Selecione um plano válido');
            exit;
        }

        $plan = SubscriptionPlan::findByCode($planCode);
        if (!$plan || empty($plan['is_active'])) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Plano inválido');
            exit;
        }

        if (!empty($plan['is_custom_pricing'])) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Plano empresarial requer proposta comercial. Contacte o suporte.');
            exit;
        }

        $currentSubscription = UserSubscription::getCurrentByUser((int) $user['id']);
        $preferredAutoRenew = !empty($_GET['auto_renew']);

        $methodsRaw = PaymentMethod::getActive('user');
        $paymentMethods = [];
        $channelsByMethod = [];
        foreach ($methodsRaw as $method) {
            $direction = (string) ($method['direction'] ?? 'both');
            if (!in_array($direction, ['incoming', 'both'], true)) {
                continue;
            }

            $methodId = (int) ($method['id'] ?? 0);
            if ($methodId <= 0) {
                continue;
            }

            $paymentMethods[] = $method;
            $channelsByMethod[$methodId] = SystemPaymentChannel::getActiveByMethodId($methodId);
        }

        $isPaidPlan = (float) ($plan['monthly_price_aoa'] ?? 0) > 0;
        if ($isPaidPlan && empty($paymentMethods)) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=' . rawurlencode('Nenhum método de recebimento está disponível no momento.'));
            exit;
        }

        $render = new ClassRender();
        $render->setTitle('Finalizar subscrição');
        $render->setDescription('Informe os dados de duração e pagamento do plano selecionado');
        $render->setKeywords('subscrição, pagamento, plano, checkout');
        $render->setData([
            'user' => $user,
            'plan' => $plan,
            'planCode' => $planCode,
            'currentSubscription' => $currentSubscription,
            'preferredAutoRenew' => $preferredAutoRenew,
            'paymentMethods' => $paymentMethods,
            'channelsByMethod' => $channelsByMethod,
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/subscription_checkout');
        $render->renderLayout();
    }

    public function confirmSubscriptionCheckout()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não possuem subscrição comercial.');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Token inválido');
            exit;
        }

        $planCode = trim(strtolower((string) ($_POST['plan_code'] ?? '')));
        if ($planCode === '') {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Selecione um plano válido');
            exit;
        }

        $plan = SubscriptionPlan::findByCode($planCode);
        if (!$plan || empty($plan['is_active'])) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Plano inválido');
            exit;
        }

        if (!empty($plan['is_custom_pricing'])) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Plano empresarial requer proposta comercial. Contacte o suporte.');
            exit;
        }

        $durationMonths = (int) ($_POST['duration_months'] ?? 1);
        $allowedDurations = [1, 3, 6, 12];
        if (!in_array($durationMonths, $allowedDurations, true)) {
            header('Location: ' . DIRPAGE . 'dashboard/subscriptionCheckout?plan_code=' . rawurlencode($planCode) . '&error=' . rawurlencode('Duração inválida'));
            exit;
        }

        $isPaidPlan = (float) ($plan['monthly_price_aoa'] ?? 0) > 0;
        $autoRenew = !empty($_POST['auto_renew']);
        $paymentMethodId = (int) ($_POST['payment_method_id'] ?? 0);
        $systemChannelId = (int) ($_POST['system_channel_id'] ?? 0);
        $referenceCode = trim((string) ($_POST['reference_code'] ?? ''));

        $amount = (float) ($plan['monthly_price_aoa'] ?? 0) * $durationMonths;
        $dueDate = date('Y-m-d', strtotime('+' . $durationMonths . ' month'));

        $paymentMethod = null;
        $channel = null;
        if ($isPaidPlan) {
            if ($paymentMethodId <= 0) {
                header('Location: ' . DIRPAGE . 'dashboard/subscriptionCheckout?plan_code=' . rawurlencode($planCode) . '&error=' . rawurlencode('Selecione a forma de pagamento'));
                exit;
            }

            $paymentMethod = PaymentMethod::findById($paymentMethodId);
            $methodDirection = (string) ($paymentMethod['direction'] ?? 'both');
            $methodActive = !empty($paymentMethod['is_active']);
            if (!$paymentMethod || !$methodActive || !in_array($methodDirection, ['incoming', 'both'], true)) {
                header('Location: ' . DIRPAGE . 'dashboard/subscriptionCheckout?plan_code=' . rawurlencode($planCode) . '&error=' . rawurlencode('Forma de pagamento inválida'));
                exit;
            }

            if ($systemChannelId > 0) {
                $channel = SystemPaymentChannel::findById($systemChannelId);
                if (!$channel || (int) ($channel['method_id'] ?? 0) !== $paymentMethodId || empty($channel['is_active'])) {
                    header('Location: ' . DIRPAGE . 'dashboard/subscriptionCheckout?plan_code=' . rawurlencode($planCode) . '&error=' . rawurlencode('Canal de pagamento inválido'));
                    exit;
                }
            }
        }

        $proofPath = null;
        if ($isPaidPlan) {
            $proofFile = $_FILES['payment_proof'] ?? null;
            if (empty($proofFile['tmp_name']) || ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                header('Location: ' . DIRPAGE . 'dashboard/subscriptionCheckout?plan_code=' . rawurlencode($planCode) . '&error=' . rawurlencode('Comprovativo de pagamento é obrigatório'));
                exit;
            }

            $proofMime = \Src\classes\ClassImageUpload::detectMime((string) $proofFile['tmp_name']);
            if (!\Src\classes\ClassImageUpload::isStandardMime($proofMime)) {
                header('Location: ' . DIRPAGE . 'dashboard/subscriptionCheckout?plan_code=' . rawurlencode($planCode) . '&error=' . rawurlencode(\Src\classes\ClassImageUpload::INVALID_STANDARD_FORMAT));
                exit;
            }

            if (UploadLimits::exceedsServerMax((int) ($proofFile['size'] ?? 0))) {
                header('Location: ' . DIRPAGE . 'dashboard/subscriptionCheckout?plan_code=' . rawurlencode($planCode) . '&error=' . rawurlencode(UploadLimits::serverMaxError('O comprovativo')));
                exit;
            }

            $proofUploadDirRelative = 'public/storage/uploads/subscription_proofs/';
            $proofUploadDir = DIRREQ . $proofUploadDirRelative;
            if (!is_dir($proofUploadDir)) {
                mkdir($proofUploadDir, 0755, true);
            }

            $proofExt = \Src\classes\ClassImageUpload::extensionForMime($proofMime);
            $proofFilename = 'sub_' . (int) $user['id'] . '_' . time() . '.' . $proofExt;
            if (!move_uploaded_file((string) $proofFile['tmp_name'], $proofUploadDir . $proofFilename)) {
                header('Location: ' . DIRPAGE . 'dashboard/subscriptionCheckout?plan_code=' . rawurlencode($planCode) . '&error=' . rawurlencode('Erro ao guardar o comprovativo'));
                exit;
            }

            $proofPath = $proofUploadDirRelative . $proofFilename;
        }

        $notes = 'Checkout do plano pelo utilizador | duração: ' . $durationMonths . ' mês(es) | método: ' . (string) ($paymentMethod['code'] ?? 'n/a');

        $subscriptionId = 0;

        if ($isPaidPlan) {
            $pendingId = UserSubscription::createPendingActivationForUser(
                (int) $user['id'],
                $planCode,
                $durationMonths,
                $autoRenew,
                (int) $user['id'],
                $notes
            );

            if (!$pendingId) {
                header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Não foi possível criar a solicitação de subscrição');
                exit;
            }

            $subscriptionId = (int) $pendingId;

            PaymentTransaction::create([
                'transaction_type' => 'subscription_fee',
                'direction' => 'incoming',
                'status' => 'pendente',
                'amount' => $amount,
                'currency' => 'AOA',
                'method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
                'system_channel_id' => $systemChannelId > 0 ? $systemChannelId : null,
                'counterparty_user_id' => (int) $user['id'],
                'related_entity_type' => 'user_subscription',
                'related_entity_id' => $subscriptionId,
                'reference_code' => $referenceCode !== '' ? $referenceCode : null,
                'proof_file' => $proofPath,
                'notes' => 'Subscrição pendente de validação financeira: ' . (string) ($plan['name'] ?? $planCode) . ' por ' . $durationMonths . ' mês(es). Vencimento previsto: ' . $dueDate,
                'created_by' => (int) $user['id'],
            ]);

            Log::create([
                'user_id' => (int) $user['id'],
                'action' => 'request_subscription_plan_change',
                'entity_type' => 'user_subscription',
                'entity_id' => $subscriptionId,
                'details' => 'Solicitação enviada para ' . (string) ($plan['name'] ?? $planCode) . ' | duração: ' . $durationMonths . ' mês(es) | método: ' . (string) ($paymentMethod['name'] ?? 'N/A') . ' | vencimento: ' . $dueDate,
            ]);

            header('Location: ' . DIRPAGE . 'dashboard/subscription?success=' . rawurlencode('Solicitação enviada. O plano será ativado após validação financeira do pagamento.'));
            exit;
        }

        $ok = UserSubscription::activatePlanForUser(
            (int) $user['id'],
            $planCode,
            $autoRenew,
            (int) $user['id'],
            $notes,
            $durationMonths
        );

        if (!$ok) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Não foi possível atualizar o plano');
            exit;
        }

        $currentSubscription = UserSubscription::getCurrentByUser((int) $user['id']);
        $subscriptionId = (int) ($currentSubscription['id'] ?? 0);

        Log::create([
            'user_id' => (int) $user['id'],
            'action' => 'change_subscription_plan',
            'entity_type' => 'user_subscription',
            'entity_id' => $subscriptionId,
            'details' => 'Plano alterado para ' . (string) ($plan['name'] ?? $planCode) . ' | duração: ' . $durationMonths . ' mês(es) | vencimento: ' . $dueDate,
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/subscription?success=' . rawurlencode('Plano atualizado com sucesso'));
        exit;
    }

    public function changeSubscription()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não possuem subscrição comercial.');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Token inválido');
            exit;
        }

        $planCode = trim(strtolower((string) ($_POST['plan_code'] ?? '')));
        if ($planCode === '') {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Selecione um plano válido');
            exit;
        }

        $plan = SubscriptionPlan::findByCode($planCode);
        if (!$plan || empty($plan['is_active'])) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Plano inválido');
            exit;
        }

        if (!empty($plan['is_custom_pricing'])) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Plano empresarial requer proposta comercial. Contacte o suporte.');
            exit;
        }

        $autoRenew = !empty($_POST['auto_renew']);
        $ok = UserSubscription::activatePlanForUser(
            (int) $user['id'],
            $planCode,
            $autoRenew,
            (int) $user['id'],
            'Alteração solicitada pelo utilizador no dashboard'
        );

        if (!$ok) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=Não foi possível atualizar o plano');
            exit;
        }

        Log::create([
            'user_id' => (int) $user['id'],
            'action' => 'change_subscription_plan',
            'entity_type' => 'user_subscription',
            'entity_id' => 0,
            'details' => 'Plano alterado para ' . (string) ($plan['name'] ?? $planCode),
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/subscription?success=Plano atualizado com sucesso');
        exit;
    }
    public function adminSubscriptions()
    {
        $user = ClassAccess::requireSuperAdmin('dashboard', 'Acesso disponível apenas para Admin Total');

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        $search  = trim((string) ($_GET['search'] ?? ''));
        $status  = trim((string) ($_GET['status'] ?? ''));
        $planFilter = trim((string) ($_GET['plan'] ?? ''));

        $db     = new \App\model\UserSubscription();
        $conn   = $db->ConexaoDB();

        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($status !== '') {
            $where[]  = 'us.status = ?';
            $params[] = $status;
        }
        if ($planFilter !== '') {
            $where[]  = 'sp.code = ?';
            $params[] = $planFilter;
        }

        $whereClause = implode(' AND ', $where);
        $pickSub = \App\model\UserSubscription::sqlPrimaryOpenSubscriptionPickSubquery();

        $countSql = "SELECT COUNT(*) FROM user_subscriptions us
                     INNER JOIN subscription_plans sp ON sp.id = us.plan_id
                     INNER JOIN users u ON u.id = us.user_id
                     INNER JOIN {$pickSub} us_primary
                        ON us_primary.user_id = us.user_id AND us_primary.subscription_id = us.id
                     WHERE {$whereClause}";
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT us.*, sp.code AS plan_code, sp.name AS plan_name, sp.ranking_weight,
                       u.name AS user_name, u.email AS user_email
                FROM user_subscriptions us
                INNER JOIN subscription_plans sp ON sp.id = us.plan_id
                INNER JOIN users u ON u.id = us.user_id
                INNER JOIN {$pickSub} us_primary
                    ON us_primary.user_id = us.user_id AND us_primary.subscription_id = us.id
                WHERE {$whereClause}
                ORDER BY us.updated_at DESC, us.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $plans = \App\model\SubscriptionPlan::getActiveCatalog();

        $render = new \Src\classes\ClassRender();
        $render->setData([
            'user'          => $user,
            'subscriptions' => $subscriptions,
            'plans'         => $plans,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => $perPage,
            'search'        => $search,
            'status'        => $status,
            'planFilter'    => $planFilter,
            'csrfField'     => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/admin_subscriptions');
        $render->renderLayout();
    }

    public function adminSetSubscription()
    {
        $user = ClassAccess::requireSuperAdmin('dashboard', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions');
            exit;
        }

        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $planCode     = trim(strtolower((string) ($_POST['plan_code'] ?? '')));
        $autoRenew    = !empty($_POST['auto_renew']);
        $notes        = trim(strip_tags((string) ($_POST['notes'] ?? '')));
        $billingMonths = max(1, min(12, (int) ($_POST['billing_cycle_months'] ?? 1)));

        if ($targetUserId <= 0 || $planCode === '') {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=Dados inválidos');
            exit;
        }

        $plan = SubscriptionPlan::findByCode($planCode);
        if (!$plan || empty($plan['is_active'])) {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=Plano inválido');
            exit;
        }

        if (!empty($plan['is_custom_pricing'])) {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptionCheckout?'
                . http_build_query(['target_user_id' => $targetUserId, 'plan_code' => $planCode]));
            exit;
        }

        $ok = UserSubscription::activatePlanForUser(
            $targetUserId,
            $planCode,
            $autoRenew,
            (int) $user['id'],
            $notes !== '' ? $notes : 'Alteração manual pelo administrador',
            $billingMonths
        );

        if (!$ok) {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=Não foi possível atualizar o plano');
            exit;
        }

        Log::create([
            'user_id'     => (int) $user['id'],
            'action'      => 'admin_set_subscription_plan',
            'entity_type' => 'user_subscription',
            'entity_id'   => $targetUserId,
            'details'     => 'Admin alterou plano do utilizador #' . $targetUserId . ' para ' . $planCode,
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?success=Plano atualizado');
        exit;
    }

    public function adminSubscriptionCheckout()
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard', 'Acesso disponível apenas para Admin Total');

        $targetUserId = (int) ($_GET['target_user_id'] ?? 0);
        $planCode = trim(strtolower((string) ($_GET['plan_code'] ?? '')));

        if ($targetUserId <= 0 || $planCode === '') {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=' . urlencode('Utilizador e plano são obrigatórios'));
            exit;
        }

        $targetUser = User::findById($targetUserId);
        if (!$targetUser) {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=' . urlencode('Utilizador não encontrado'));
            exit;
        }

        $plan = SubscriptionPlan::findByCode($planCode);
        if (!$plan || empty($plan['is_active'])) {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=' . urlencode('Plano inválido'));
            exit;
        }

        if (empty($plan['is_custom_pricing'])) {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=' . urlencode('Este checkout é apenas para planos com preço negociado (Empresarial)'));
            exit;
        }

        $currentSubscription = UserSubscription::getCurrentByUser($targetUserId);

        $render = new ClassRender();
        $render->setTitle('Configurar plano empresarial');
        $render->setDescription('Definir contrato negociado para utilizador');
        $render->setKeywords('admin, subscrição, empresarial');
        $render->setData([
            'user' => $admin,
            'targetUser' => $targetUser,
            'plan' => $plan,
            'planCode' => $planCode,
            'currentSubscription' => $currentSubscription,
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/admin_subscription_checkout');
        $render->renderLayout();
    }

    public function confirmAdminSubscriptionCheckout()
    {
        $admin = ClassAccess::requireSuperAdmin('dashboard', 'Acesso disponível apenas para Admin Total');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=' . urlencode('Token inválido'));
            exit;
        }

        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $planCode = trim(strtolower((string) ($_POST['plan_code'] ?? '')));
        $billingMonths = max(1, min(12, (int) ($_POST['billing_cycle_months'] ?? 1)));
        $allowedDurations = [1, 3, 6, 12];
        $autoRenew = !empty($_POST['auto_renew']);
        $notes = trim(strip_tags((string) ($_POST['notes'] ?? '')));
        $negotiatedPrice = (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['negotiated_price_aoa'] ?? '0'));

        $checkoutBack = DIRPAGE . 'dashboard/adminSubscriptionCheckout?'
            . http_build_query(['target_user_id' => $targetUserId, 'plan_code' => $planCode]);

        if ($targetUserId <= 0 || $planCode === '') {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=' . urlencode('Dados inválidos'));
            exit;
        }

        if (!in_array($billingMonths, $allowedDurations, true)) {
            header('Location: ' . $checkoutBack . '&error=' . urlencode('Duração inválida'));
            exit;
        }

        if ($negotiatedPrice <= 0) {
            header('Location: ' . $checkoutBack . '&error=' . urlencode('Indique o valor total negociado (Kz)'));
            exit;
        }

        $plan = SubscriptionPlan::findByCode($planCode);
        if (!$plan || empty($plan['is_active']) || empty($plan['is_custom_pricing'])) {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=' . urlencode('Plano inválido para este checkout'));
            exit;
        }

        if (!User::findById($targetUserId)) {
            header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?error=' . urlencode('Utilizador não encontrado'));
            exit;
        }

        $noteLine = $notes !== ''
            ? $notes
            : 'Plano empresarial atribuído pelo administrador';
        $noteLine .= ' | valor negociado: ' . number_format($negotiatedPrice, 0, ',', '.') . ' Kz / ' . $billingMonths . ' mês(es)';

        $ok = UserSubscription::activatePlanForUser(
            $targetUserId,
            $planCode,
            $autoRenew,
            (int) $admin['id'],
            $noteLine,
            $billingMonths,
            true,
            $negotiatedPrice
        );

        if (!$ok) {
            header('Location: ' . $checkoutBack . '&error=' . urlencode('Não foi possível activar o plano'));
            exit;
        }

        Log::create([
            'user_id' => (int) $admin['id'],
            'action' => 'admin_set_subscription_plan',
            'entity_type' => 'user_subscription',
            'entity_id' => $targetUserId,
            'details' => 'Empresarial activado para #' . $targetUserId . ' | ' . number_format($negotiatedPrice, 0, ',', '.') . ' Kz / ' . $billingMonths . ' mês(es)',
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/adminSubscriptions?success=' . urlencode('Plano empresarial activado com sucesso'));
        exit;
    }
}
