<?php

namespace App\controller;

use App\model\Commission;
use App\model\Log;
use App\model\Notification;
use App\model\PaymentMethod;
use App\model\PaymentTransaction;
use App\model\PropertyBoostRequest;
use App\model\SystemPaymentChannel;
use App\model\User;
use App\services\CommissionSettlementService;
use Dompdf\Dompdf;
use Src\classes\UploadLimits;
use Dompdf\Options;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\ClassRender;

class ControllerDashboardPayments
{
    public function payments()
    {
        $admin = ClassAccess::requirePermission('payments.manage', 'dashboard', 'Acesso disponível apenas para a equipa financeira');

        // Resolve active tab (mirrors view logic so controller can load only needed data)
        $requestedTab  = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
        $allowedTabs   = ['trust', 'boosts', 'commissions', 'subscriptions', 'history'];
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

        $perPage = 20;
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        // Always load counts for KPIs and tab labels
        $pendingCommissionsCount = Commission::countAllPending();
        $affiliatePayoutCount = Commission::countAwaitingAffiliatePayout();
        $commissionsTabPendingCount = $pendingCommissionsCount + $affiliatePayoutCount;
        $pendingBoostsCount      = PropertyBoostRequest::countPending();
        $pendingTrustCount       = \App\model\User::countTrustBadgePendingUsers();
        $pendingSubscriptionFeesCount =
            \App\model\PaymentTransaction::countList('pendente', 'subscription_fee') +
            \App\model\PaymentTransaction::countList('processando', 'subscription_fee');
        $pendingTotal            = Commission::sumAllPendingAmount();
        $commissionsAffiliatePendingAmount = Commission::sumAwaitingAffiliatePayoutAmount();
        $commissionsPendingTotal = $pendingTotal + $commissionsAffiliatePendingAmount;

        // Load only the active tab's paginated data
        $pendingCommissions = [];
        $affiliatePayoutQueue = [];
        $pendingBoosts      = [];
        $pendingTrust       = [];
        $subscriptionTransactions = [];
        $subscriptionTransactionsCount = 0;
        $allCommissions     = [];
        $allCommissionsCount = 0;
        $allPaymentTransactions = [];
        $allPaymentTransactionsCount = 0;
        $totalPages         = 1;

        if ($activeTab === 'trust') {
            $pendingTrust = \App\model\User::getTrustBadgePendingUsers($perPage, $offset);
            $totalPages   = (int) ceil($pendingTrustCount / max(1, $perPage));
        } elseif ($activeTab === 'boosts') {
            $pendingBoosts = PropertyBoostRequest::getPending($perPage, $offset);
            $totalPages    = (int) ceil($pendingBoostsCount / max(1, $perPage));
        } elseif ($activeTab === 'commissions') {
            $pendingCommissions = Commission::getAllPending($perPage, $offset);
            $affiliatePayoutQueue = array_values(array_filter(
                Commission::getAwaitingAffiliatePayout(50),
                static fn (array $row): bool => Commission::needsAffiliatePayout($row)
            ));
            $totalPages         = (int) ceil($pendingCommissionsCount / max(1, $perPage));
        } elseif ($activeTab === 'subscriptions') {
            $subscriptionTransactionsCount = \App\model\PaymentTransaction::countList(null, 'subscription_fee', ['rejeitado']);
            $subscriptionTransactions = \App\model\PaymentTransaction::getList(null, 'subscription_fee', $perPage, $offset, ['rejeitado']);
            $totalPages = (int) ceil($subscriptionTransactionsCount / max(1, $perPage));
        } elseif ($activeTab === 'history') {
            $allPaymentTransactionsCount = \App\model\PaymentTransaction::countList(null, null);
            $allPaymentTransactions = \App\model\PaymentTransaction::getList(null, null, $perPage, $offset);
            $totalPages = (int) ceil($allPaymentTransactionsCount / max(1, $perPage));
        }

        $render = new ClassRender();
        $render->setTitle('Central de Pagamentos');
        $render->setDescription('Gestão financeira de comissões, destaques e selo de confiança');
        $render->setKeywords('pagamentos, comissões, destaques, selo de confiança');
        $commissionSection = 'owner';
        $highlightCommissionId = max(0, (int) ($_GET['highlight'] ?? 0));
        if ($activeTab === 'commissions') {
            $requestedSection = trim((string) ($_GET['section'] ?? ''));
            if (in_array($requestedSection, ['owner', 'affiliate'], true)) {
                $commissionSection = $requestedSection;
            } elseif ($highlightCommissionId > 0) {
                $highlightInOwnerQueue = false;
                foreach ($pendingCommissions as $row) {
                    if ((int) ($row['id'] ?? 0) === $highlightCommissionId) {
                        $highlightInOwnerQueue = true;
                        break;
                    }
                }
                $highlightInAffiliateQueue = false;
                if (!$highlightInOwnerQueue) {
                    foreach ($affiliatePayoutQueue as $row) {
                        if ((int) ($row['id'] ?? 0) === $highlightCommissionId) {
                            $highlightInAffiliateQueue = true;
                            break;
                        }
                    }
                }
                if ($highlightInOwnerQueue) {
                    $commissionSection = 'owner';
                } elseif ($highlightInAffiliateQueue) {
                    $commissionSection = 'affiliate';
                } else {
                    $commissionSection = $pendingCommissionsCount > 0 ? 'owner' : 'affiliate';
                }
            } elseif ($pendingCommissionsCount > 0) {
                $commissionSection = 'owner';
            } elseif ($affiliatePayoutCount > 0) {
                $commissionSection = 'affiliate';
            }
        }

        $render->setData([
            'user'               => $admin,
            'pendingCommissions' => $pendingCommissions,
            'affiliatePayoutQueue' => $affiliatePayoutQueue,
            'affiliatePayoutCount' => $affiliatePayoutCount,
            'commissionSection' => $commissionSection,
            'highlightCommissionId' => $highlightCommissionId,
            'pendingBoosts'      => $pendingBoosts,
            'pendingTrust'       => $pendingTrust,
            'subscriptionTransactions' => $subscriptionTransactions,
            'subscriptionTransactionsCount' => $subscriptionTransactionsCount,
            'allCommissions'     => $allCommissions,
            'allCommissionsCount' => $allCommissionsCount,
            'allPaymentTransactions' => $allPaymentTransactions,
            'allPaymentTransactionsCount' => $allPaymentTransactionsCount,
            'pendingTotal'       => $pendingTotal,
            'commissionsPendingTotal' => $commissionsPendingTotal,
            'commissionsAffiliatePendingAmount' => $commissionsAffiliatePendingAmount,
            'pendingCommissionsCount' => $pendingCommissionsCount,
            'commissionsTabPendingCount' => $commissionsTabPendingCount,
            'pendingBoostsCount'      => $pendingBoostsCount,
            'pendingTrustCount'       => $pendingTrustCount,
            'pendingSubscriptionFeesCount' => $pendingSubscriptionFeesCount,
            'activeTab'               => $activeTab,
            'page'                    => $page,
            'totalPages'              => $totalPages,
        ]);
        $render->setDir('dashboard/payments');
        $render->renderLayout();
    }

    public function confirmPayment($id)
    {
        $admin = ClassAccess::requirePermission('payments.manage', 'dashboard', 'Acesso disponível apenas para a equipa financeira');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/payments?error=Token inválido');
            exit;
        }

        $commission = Commission::findById((int) $id);
        if (!$commission) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=Comissão não encontrada');
            exit;
        }

        if (!Commission::canValidateOwnerPayment($commission)) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode('Comprovativo em falta ou comissão não está aguardando validação.'));
            exit;
        }

        $reference = trim($_POST['payment_reference'] ?? '');
        if ($reference === '') {
            $reference = trim((string) ($commission['owner_payment_reference'] ?? ''));
        }

        if (!CommissionSettlementService::approveOwnerPayment((int) $id, (int) $admin['id'], $reference)) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode('Não foi possível aprovar o pagamento.'));
            exit;
        }

        Log::create([
            'user_id'     => $admin['id'],
            'action'      => 'confirm_payment',
            'entity_type' => 'commission',
            'entity_id'   => (int) $id,
            'details'     => 'Pagamento do proprietário aprovado. Ref: ' . ($reference ?: 'N/A'),
        ]);

        $ownerId = (int) ($commission['owner_id'] ?? 0);
        if ($ownerId > 0) {
            Notification::notifyUser(
                $ownerId,
                'commission_owner_payment_confirmed',
                'Comissão confirmada',
                'O pagamento da comissão de ' . number_format((float) ($commission['amount'] ?? 0), 0, ',', '.') . ' Kz foi confirmado pela equipa.'
                . ($reference ? ' Ref: ' . $reference : ''),
                ['commission_id' => (int) $id, 'amount' => (float) ($commission['amount'] ?? 0)],
                (int) $admin['id']
            );
        }

        if (Commission::hasValidAffiliate($commission)) {
            $affiliateUserId = (int) ($commission['affiliate_id'] ?? 0);
            Notification::notifyUser(
                $affiliateUserId,
                'commission_payout_pending',
                'Comissão a receber',
                'O pagamento do proprietário foi validado. A sua comissão de '
                . number_format((float) ($commission['affiliate_amount'] ?? 0), 0, ',', '.')
                . ' Kz será transferida para a conta registada na plataforma.',
                ['commission_id' => (int) $id, 'amount' => (float) ($commission['affiliate_amount'] ?? 0)],
                (int) $admin['id']
            );
        }

        header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&success=' . rawurlencode('Pagamento aprovado e liquidado no sistema.'));
        exit;
    }

    public function rejectCommissionOwnerPayment($id)
    {
        $admin = ClassAccess::requirePermission('payments.manage', 'dashboard', 'Acesso disponível apenas para a equipa financeira');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=Token inválido');
            exit;
        }

        $commission = Commission::findById((int) $id);
        if (!$commission || !Commission::canValidateOwnerPayment($commission)) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode('Não foi possível rejeitar este pagamento.'));
            exit;
        }

        $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
        if (!CommissionSettlementService::rejectOwnerPayment((int) $id, (int) $admin['id'], $reason)) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode('Não foi possível rejeitar o pagamento.'));
            exit;
        }

        Log::create([
            'user_id' => (int) $admin['id'],
            'action' => 'reject_commission_owner_payment',
            'entity_type' => 'commission',
            'entity_id' => (int) $id,
            'details' => 'Pagamento rejeitado' . ($reason !== '' ? ': ' . $reason : ''),
        ]);

        $ownerId = (int) ($commission['owner_id'] ?? 0);
        if ($ownerId > 0) {
            Notification::notifyUser(
                $ownerId,
                'commission_owner_payment_rejected',
                'Comprovativo rejeitado',
                'O comprovativo da comissão de ' . number_format((float) ($commission['amount'] ?? 0), 0, ',', '.') . ' Kz foi rejeitado.'
                . ($reason !== '' ? ' Motivo: ' . $reason : ' Envie um novo comprovativo.'),
                ['commission_id' => (int) $id],
                (int) $admin['id']
            );
        }

        header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&success=' . rawurlencode('Pagamento rejeitado. O proprietário pode reenviar o comprovativo.'));
        exit;
    }

    public function confirmAffiliatePayout($id)
    {
        $admin = ClassAccess::requirePermission('payments.manage', 'dashboard', 'Acesso disponível apenas para a equipa financeira');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=Token inválido');
            exit;
        }

        $commissionId = (int) $id;
        $commission = Commission::findById($commissionId);
        if (!$commission || !Commission::needsAffiliatePayout($commission)) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode('Pagamento ao afiliado indisponível para esta comissão.'));
            exit;
        }

        $affiliateId = (int) ($commission['affiliate_id'] ?? 0);
        if ($affiliateId <= 0 || !\App\model\UserPaymentAccount::getDefaultActiveForUser($affiliateId)) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode('O afiliado deve registar uma conta de recebimento antes de confirmar o pagamento.'));
            exit;
        }

        $proofFile = $_FILES['payout_proof'] ?? [];
        $proofError = (int) ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($proofError === UPLOAD_ERR_NO_FILE || trim((string) ($proofFile['tmp_name'] ?? '')) === '') {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode('Comprovativo obrigatório. Anexe a imagem do pagamento ao afiliado.'));
            exit;
        }

        $upload = $this->uploadCommissionPayoutProof($proofFile, $commissionId);
        if (!empty($upload['error']) || empty($upload['path'])) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode((string) ($upload['error'] ?? 'Comprovativo obrigatório.')));
            exit;
        }

        $reference = trim((string) ($_POST['payout_reference'] ?? ''));
        if (!CommissionSettlementService::confirmAffiliatePayout($commissionId, (int) $admin['id'], (string) $upload['path'], $reference)) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&error=' . rawurlencode('Não foi possível confirmar o pagamento ao afiliado.'));
            exit;
        }

        Log::create([
            'user_id' => (int) $admin['id'],
            'action' => 'confirm_affiliate_payout',
            'entity_type' => 'commission',
            'entity_id' => $commissionId,
            'details' => 'Pagamento ao afiliado confirmado. Ref: ' . ($reference !== '' ? $reference : 'N/A'),
        ]);

        $affiliateUserId = (int) ($commission['affiliate_id'] ?? 0);
        if ($affiliateUserId > 0) {
            Notification::notifyUser(
                $affiliateUserId,
                'commission_paid',
                'Comissão paga',
                'O pagamento da sua comissão de ' . number_format((float) ($commission['affiliate_amount'] ?? 0), 0, ',', '.') . ' Kz foi confirmado.'
                . ($reference !== '' ? ' Ref: ' . $reference : ''),
                ['commission_id' => $commissionId, 'amount' => (float) ($commission['affiliate_amount'] ?? 0)],
                (int) $admin['id']
            );
        }

        header('Location: ' . DIRPAGE . 'dashboard/payments?tab=commissions&success=' . rawurlencode('Pagamento ao afiliado registado. Comissão marcada como paga.'));
        exit;
    }

    public function cancelPayment($id)
    {
        $admin = ClassAccess::requirePermission('payments.manage', 'dashboard', 'Acesso disponível apenas para a equipa financeira');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/payments?error=Token inválido');
            exit;
        }

        $commission = Commission::findById((int) $id);
        if (!$commission) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?error=Comissão não encontrada');
            exit;
        }

        if (!Commission::markAsCancelled((int) $id)) {
            header('Location: ' . DIRPAGE . 'dashboard/payments?error=Não+foi+possível+cancelar+a+comissão+(estado+inválido+ou+já+processado)');
            exit;
        }

        Log::create([
            'user_id'     => $admin['id'],
            'action'      => 'cancel_payment',
            'entity_type' => 'commission',
            'entity_id'   => (int) $id,
            'details'     => 'Comissão cancelada',
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/payments?success=Comissão cancelada');
        exit;
    }

    private function uploadCommissionOwnerProof(array $file, int $userId): array
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => 'Comprovativo de pagamento obrigatório.'];
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => \Src\classes\ClassImageUpload::uploadErrorMessage($errorCode, 'O comprovativo')];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['path' => null, 'error' => 'Comprovativo inválido.'];
        }
        if ($size <= 0 || UploadLimits::exceedsServerMax($size)) {
            return ['path' => null, 'error' => UploadLimits::serverMaxError('O comprovativo')];
        }

        $mime = \Src\classes\ClassImageUpload::detectMime($tmpName);
        if (!\Src\classes\ClassImageUpload::isStandardMime($mime)) {
            return ['path' => null, 'error' => \Src\classes\ClassImageUpload::INVALID_STANDARD_FORMAT];
        }

        $ext = \Src\classes\ClassImageUpload::extensionForMime($mime);

        $uploadDirRelative = 'public/storage/uploads/commission_proofs/';
        $uploadDir = DIRREQ . $uploadDirRelative;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['path' => null, 'error' => 'Não foi possível preparar a pasta para comprovativos.'];
        }

        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Exception $e) {
            $suffix = substr(md5(uniqid('', true)), 0, 8);
        }

        $filename = 'commission_' . max(0, $userId) . '_' . time() . '_' . $suffix . '.' . $ext;
        $destination = $uploadDir . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            return ['path' => null, 'error' => 'Falha ao guardar o comprovativo.'];
        }

        return ['path' => $uploadDirRelative . $filename, 'error' => null];
    }

    private function uploadCommissionPayoutProof(array $file, int $commissionId): array
    {
        $upload = $this->uploadCommissionOwnerProof($file, 0);
        if (!empty($upload['error']) || empty($upload['path'])) {
            return $upload;
        }

        $uploadDirRelative = 'public/storage/uploads/commission_payout_proofs/';
        $uploadDir = DIRREQ . $uploadDirRelative;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['path' => null, 'error' => 'Não foi possível preparar a pasta para comprovativos de pagamento.'];
        }

        $basename = basename((string) $upload['path']);
        $destination = $uploadDir . 'payout_' . max(0, $commissionId) . '_' . $basename;
        $source = DIRREQ . ltrim((string) $upload['path'], '/');

        if (!@rename($source, $destination)) {
            if (!@copy($source, $destination)) {
                return ['path' => null, 'error' => 'Falha ao guardar o comprovativo de pagamento.'];
            }
            @unlink($source);
        }

        return ['path' => $uploadDirRelative . basename($destination), 'error' => null];
    }

    private function resolveIncomingPaymentMethods(): array
    {
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

        return [$paymentMethods, $channelsByMethod];
    }

    public function commissionPayments()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Área disponível para proprietários.');
        $ownerId = (int) ($user['id'] ?? 0);

        $activeTab = trim((string) ($_GET['tab'] ?? 'pendentes'));
        if (!in_array($activeTab, ['pendentes', 'pago', 'cancelado'], true)) {
            $activeTab = 'pendentes';
        }

        $pending = [];
        $historyCommissions = [];
        $historyCounts = [
            'pago' => Commission::countHistoryByOwner($ownerId, 'pago'),
            'cancelado' => Commission::countHistoryByOwner($ownerId, 'cancelado'),
        ];

        if ($activeTab === 'pendentes') {
            $pending = Commission::getPayableByOwner($ownerId);
        } else {
            $historyCommissions = Commission::getHistoryByOwner($ownerId, $activeTab);
        }

        $render = new ClassRender();
        $render->setTitle('Pagar comissões');
        $render->setDescription('Regularize as comissões pendentes dos seus fechos comerciais');
        $render->setKeywords('comissão, pagamento, proprietário');
        $render->setData([
            'user' => $user,
            'activeTab' => $activeTab,
            'pendingCommissions' => $pending,
            'historyCommissions' => $historyCommissions,
            'historyCounts' => $historyCounts,
            'pendingCount' => Commission::countPendingByOwner($ownerId),
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/commission_payments');
        $render->renderLayout();
    }

    public function commissionPayment($id)
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Área disponível para proprietários.');
        $commissionId = (int) $id;
        $commission = Commission::findPayableForOwner($commissionId, (int) ($user['id'] ?? 0));
        if (!$commission) {
            header('Location: ' . DIRPAGE . 'dashboard/commissionPayments?error=' . rawurlencode('Comissão não encontrada ou já regularizada.'));
            exit;
        }

        if (!Commission::canOwnerSubmitPayment($commission)) {
            if (Commission::hasOwnerPaymentSubmitted($commission)) {
                header('Location: ' . DIRPAGE . 'dashboard/commissionPayments?success=' . rawurlencode('Comprovativo já enviado. Aguarde validação da equipa financeira.'));
            } else {
                header('Location: ' . DIRPAGE . 'dashboard/commissionPayments?error=' . rawurlencode('Esta comissão não está disponível para pagamento.'));
            }
            exit;
        }

        [$paymentMethods, $channelsByMethod] = $this->resolveIncomingPaymentMethods();
        if (empty($paymentMethods)) {
            header('Location: ' . DIRPAGE . 'dashboard/commissionPayments?error=' . rawurlencode('Nenhum método de pagamento está disponível no momento. Contacte o suporte.'));
            exit;
        }

        $dueAt = (string) ($commission['due_at'] ?? '');
        $isOverdue = $dueAt !== '' && strtotime($dueAt) < time();

        $render = new ClassRender();
        $render->setTitle('Pagar comissão');
        $render->setDescription('Envie o comprovativo de pagamento da comissão');
        $render->setKeywords('comissão, pagamento, comprovativo');
        $render->setData([
            'user' => $user,
            'commission' => $commission,
            'dueAtFormatted' => $dueAt !== '' ? date('d/m/Y H:i', strtotime($dueAt)) : '—',
            'isOverdue' => $isOverdue,
            'paymentMethods' => $paymentMethods,
            'channelsByMethod' => $channelsByMethod,
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/commission_payment');
        $render->renderLayout();
    }

    public function submitCommissionPayment($id)
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Área disponível para proprietários.');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/commissionPayments?error=Token inválido');
            exit;
        }

        $commissionId = (int) $id;
        $commission = Commission::findPayableForOwner($commissionId, (int) ($user['id'] ?? 0));
        if (!$commission) {
            header('Location: ' . DIRPAGE . 'dashboard/commissionPayments?error=' . rawurlencode('Comissão não encontrada ou já regularizada.'));
            exit;
        }

        if (!Commission::canOwnerSubmitPayment($commission)) {
            header('Location: ' . DIRPAGE . 'dashboard/commissionPayments?success=' . rawurlencode('Comprovativo já enviado. Aguarde validação.'));
            exit;
        }

        $upload = $this->uploadCommissionOwnerProof($_FILES['payment_proof'] ?? [], (int) ($user['id'] ?? 0));
        if (!empty($upload['error']) || empty($upload['path'])) {
            header('Location: ' . DIRPAGE . 'dashboard/commissionPayment/' . $commissionId . '?error=' . rawurlencode((string) ($upload['error'] ?? 'Comprovativo obrigatório.')));
            exit;
        }

        $reference = trim((string) ($_POST['payment_reference'] ?? ''));
        $methodId = (int) ($_POST['payment_method_id'] ?? 0);
        $channelId = (int) ($_POST['system_channel_id'] ?? 0);
        if (!Commission::submitOwnerPayment($commissionId, (int) ($user['id'] ?? 0), (string) $upload['path'], $reference, $methodId, $channelId)) {
            header('Location: ' . DIRPAGE . 'dashboard/commissionPayment/' . $commissionId . '?error=' . rawurlencode('Não foi possível registar o comprovativo.'));
            exit;
        }

        Log::create([
            'user_id' => (int) ($user['id'] ?? 0),
            'action' => 'submit_commission_owner_payment',
            'entity_type' => 'commission',
            'entity_id' => $commissionId,
            'details' => 'Comprovativo enviado pelo proprietário. Ref: ' . ($reference !== '' ? $reference : 'N/A'),
        ]);

        Notification::notifyUsers(
            User::getActiveAdminIds(),
            'commission_owner_payment_submitted',
            'Comprovativo de comissão',
            'O proprietário enviou comprovativo de ' . number_format((float) ($commission['amount'] ?? 0), 0, ',', '.') . ' Kz para o imóvel "' . ((string) ($commission['property_title'] ?? '')) . '".',
            ['commission_id' => $commissionId, 'property_id' => (int) ($commission['property_id'] ?? 0)],
            (int) ($user['id'] ?? 0)
        );

        header('Location: ' . DIRPAGE . 'dashboard/commissionPayments?success=' . rawurlencode('Comprovativo enviado. As funcionalidades da plataforma voltam a ficar disponíveis após validação e aprovação pela equipa financeira.'));
        exit;
    }
    public function paymentAccounts()
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        $accounts = \App\model\UserPaymentAccount::getByUser((int) $user['id']);
        $methods = \App\model\PaymentMethod::getActive('user');

        $render = new ClassRender();
        $render->setTitle('Meus Dados de Pagamento');
        $render->setDescription('Adicione e gerencie suas contas para receber pagamentos');
        $render->setKeywords('pagamentos, contas, recebimentos');
        $render->setData([
            'user' => $user,
            'accounts' => $accounts,
            'methods' => $methods,
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/payment_accounts');
        $render->renderLayout();
    }

    public function addPaymentAccount()
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Token+inválido');
            exit;
        }

        $methodId = (int) ($_POST['method_id'] ?? 0);
        $method = \App\model\PaymentMethod::findById($methodId);

        if (
            !$method
            || (int) $method['is_active'] !== 1
            || !in_array($method['audience'], ['user', 'both'], true)
        ) {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Método+de+pagamento+inválido');
            exit;
        }

        $fieldsConfig = \App\model\PaymentMethod::parseFieldsConfig($method['fields_config'] ?? null);
        $allowedFieldNames = ['account_name', 'account_number', 'iban', 'bank_name', 'wallet_provider', 'phone_number'];

        $filteredFields = [];
        foreach ($allowedFieldNames as $fieldName) {
            $isAllowed = !empty($fieldsConfig[$fieldName]);
            $filteredFields[$fieldName] = $isAllowed
                ? (($_POST[$fieldName] ?? null) !== '' ? trim((string) ($_POST[$fieldName] ?? '')) : null)
                : null;
        }

        $accountData = [
            'method_id' => $methodId,
            'account_label' => $_POST['account_label'] ?? null,
            'account_name' => $filteredFields['account_name'],
            'account_number' => $filteredFields['account_number'],
            'iban' => $filteredFields['iban'],
            'bank_name' => $filteredFields['bank_name'],
            'wallet_provider' => $filteredFields['wallet_provider'],
            'phone_number' => $filteredFields['phone_number'],
            'is_default' => !empty($_POST['is_default']) ? 1 : 0,
        ];

        $result = \App\model\UserPaymentAccount::createForUser((int) $user['id'], $accountData);

        if ($result === false) {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Erro+ao+criar+conta');
            exit;
        }

        Log::create([
            'user_id' => (int) $user['id'],
            'action' => 'add_payment_account',
            'entity_type' => 'user_payment_account',
            'entity_id' => is_int($result) ? $result : 0,
            'details' => 'Conta de pagamento adicionada: ' . $method['name'],
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?success=' . urlencode('Conta adicionada com sucesso'));
        exit;
    }

    public function setDefaultPaymentAccount($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Token+inválido');
            exit;
        }

        $account = \App\model\UserPaymentAccount::findByIdForUser((int) $id, (int) $user['id']);
        if (!$account) {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Conta+não+encontrada');
            exit;
        }

        if (!\App\model\UserPaymentAccount::setDefault((int) $id, (int) $user['id'])) {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Erro+ao+atualizar');
            exit;
        }

        Log::create([
            'user_id' => (int) $user['id'],
            'action' => 'set_default_payment_account',
            'entity_type' => 'user_payment_account',
            'entity_id' => (int) $id,
            'details' => 'Conta de pagamento marcada como padrão',
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?success=' . urlencode('Conta definida como padrão'));
        exit;
    }

    public function deactivatePaymentAccount($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Token+inválido');
            exit;
        }

        $account = \App\model\UserPaymentAccount::findByIdForUser((int) $id, (int) $user['id']);
        if (!$account) {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Conta+não+encontrada');
            exit;
        }

        if (!\App\model\UserPaymentAccount::deactivate((int) $id, (int) $user['id'])) {
            header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?error=Erro+ao+desativar');
            exit;
        }

        Log::create([
            'user_id' => (int) $user['id'],
            'action' => 'deactivate_payment_account',
            'entity_type' => 'user_payment_account',
            'entity_id' => (int) $id,
            'details' => 'Conta de pagamento desativada',
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/paymentAccounts?success=' . urlencode('Conta desativada'));
        exit;
    }

    public function paymentHistory()
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        $status = trim((string) ($_GET['status'] ?? ''));
        $type = trim((string) ($_GET['type'] ?? ''));

        $allowedStatuses = ['pendente', 'processando', 'confirmado', 'cancelado', 'falhado', 'rejeitado'];
        $allowedTypes = ['commission_payout', 'system_commission', 'boost_fee', 'trust_badge_fee', 'manual_adjustment', 'subscription_fee'];
        $status = in_array($status, $allowedStatuses, true) ? $status : '';
        $type = in_array($type, $allowedTypes, true) ? $type : '';

        $transactions = \App\model\PaymentTransaction::getByCounterpartyUserFiltered(
            (int) $user['id'],
            $status !== '' ? $status : null,
            $type !== '' ? $type : null,
            200
        );

        $render = new ClassRender();
        $render->setTitle('Histórico de Pagamentos');
        $render->setDescription('Consulte seu histórico de transações');
        $render->setKeywords('pagamentos, histórico, transações');
        $render->setData([
            'user' => $user,
            'transactions' => $transactions,
            'filterStatus' => $status,
            'filterType' => $type,
        ]);
        $render->setDir('dashboard/payment_history');
        $render->renderLayout();
    }

    public function exportPaymentHistoryCsv()
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        $status = trim((string) ($_GET['status'] ?? ''));
        $type = trim((string) ($_GET['type'] ?? ''));
        $allowedStatuses = ['pendente', 'processando', 'confirmado', 'cancelado', 'falhado', 'rejeitado'];
        $allowedTypes = ['commission_payout', 'system_commission', 'boost_fee', 'trust_badge_fee', 'manual_adjustment', 'subscription_fee'];
        $status = in_array($status, $allowedStatuses, true) ? $status : '';
        $type = in_array($type, $allowedTypes, true) ? $type : '';

        $rows = PaymentTransaction::getByCounterpartyUserFiltered(
            (int) $user['id'],
            $status !== '' ? $status : null,
            $type !== '' ? $type : null,
            5000
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="payment-history-' . (int) $user['id'] . '-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        fputcsv($out, ['id', 'tipo', 'estado', 'direcao', 'montante', 'moeda', 'metodo', 'referencia', 'criada_em', 'confirmada_em']);

        foreach ($rows as $row) {
            fputcsv($out, [
                    (int) ($row['id'] ?? 0),
                    (string) ($row['transaction_type'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['direction'] ?? ''),
                    (string) ($row['amount'] ?? ''),
                    (string) ($row['currency'] ?? ''),
                    (string) ($row['method_name'] ?? ''),
                    (string) ($row['reference_code'] ?? ''),
                    (string) ($row['created_at'] ?? ''),
                    (string) ($row['confirmed_at'] ?? ''),
            ]);
        }

        fclose($out);
        exit;
    }

    public function exportPaymentHistoryPdf()
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        $status = trim((string) ($_GET['status'] ?? ''));
        $type = trim((string) ($_GET['type'] ?? ''));
        $allowedStatuses = ['pendente', 'processando', 'confirmado', 'cancelado', 'falhado', 'rejeitado'];
        $allowedTypes = ['commission_payout', 'system_commission', 'boost_fee', 'trust_badge_fee', 'manual_adjustment', 'subscription_fee'];
        $status = in_array($status, $allowedStatuses, true) ? $status : '';
        $type = in_array($type, $allowedTypes, true) ? $type : '';

        $rows = PaymentTransaction::getByCounterpartyUserFiltered(
            (int) $user['id'],
            $status !== '' ? $status : null,
            $type !== '' ? $type : null,
            5000
        );
        $this->streamPaymentHistoryPdf(
            $rows,
            'Meu Histórico de Pagamentos',
            'Utilizador: ' . (string) ($user['name'] ?? ('#' . (int) $user['id']))
            . ' | Estado: ' . ($status !== '' ? $status : 'todos')
            . ' | Tipo: ' . ($type !== '' ? $type : 'todos'),
            'payment-history-' . (int) $user['id'] . '-' . date('Ymd-His') . '.pdf'
        );
    }

    public function exportPaymentsHistoryCsv()
    {
        ClassAccess::requirePermission('payments.manage', 'dashboard', 'Acesso disponível apenas para a equipa financeira');

        $rows = PaymentTransaction::getListForExport(null, null, 10000);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="payments-history-admin-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        fputcsv($out, ['id', 'tipo', 'estado', 'direcao', 'montante', 'moeda', 'metodo', 'utilizador_id', 'utilizador_nome', 'referencia', 'criada_em', 'confirmada_em']);

        foreach ($rows as $row) {
            fputcsv($out, [
                    (int) ($row['id'] ?? 0),
                    (string) ($row['transaction_type'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['direction'] ?? ''),
                    (string) ($row['amount'] ?? ''),
                    (string) ($row['currency'] ?? ''),
                    (string) ($row['method_name'] ?? ''),
                    (string) ($row['counterparty_user_id'] ?? ''),
                    (string) ($row['counterparty_name'] ?? ''),
                    (string) ($row['reference_code'] ?? ''),
                    (string) ($row['created_at'] ?? ''),
                    (string) ($row['confirmed_at'] ?? ''),
            ]);
        }

        fclose($out);
        exit;
    }

    public function exportPaymentsHistoryPdf()
    {
        ClassAccess::requirePermission('payments.manage', 'dashboard', 'Acesso disponível apenas para a equipa financeira');

        $rows = PaymentTransaction::getListForExport(null, null, 10000);
        $this->streamPaymentHistoryPdf(
            $rows,
            'Histórico de Pagamentos (Admin)',
            'Escopo: Central de Pagamentos',
            'payments-history-admin-' . date('Ymd-His') . '.pdf'
        );
    }

    private function streamPaymentHistoryPdf(array $rows, string $title, string $scope, string $filename): void
    {
        $typeLabels = [
                'commission_payout' => 'Comissão',
                'system_commission' => 'Taxa do sistema',
                'boost_fee' => 'Destaque',
                'trust_badge_fee' => 'Selo',
                'manual_adjustment' => 'Ajuste manual',
                'subscription_fee' => 'Subscrição',
        ];

        $statusLabels = [
                'pendente' => 'Pendente',
                'processando' => 'Processando',
                'confirmado' => 'Confirmado',
                'cancelado' => 'Cancelado',
                'falhado' => 'Falhado',
                'rejeitado' => 'Rejeitado',
        ];

        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $generatedAt = date('d/m/Y H:i');

        $statusTotals = [];
        $typeTotals = [];
        $grandTotalAmount = 0.0;
        foreach ($rows as $row) {
            $rowStatus = (string) ($row['status'] ?? '');
            $rowType = (string) ($row['transaction_type'] ?? '');
            $statusTotals[$rowStatus] = ($statusTotals[$rowStatus] ?? 0) + 1;
            $typeTotals[$rowType] = ($typeTotals[$rowType] ?? 0) + 1;
            $grandTotalAmount += (float) ($row['amount'] ?? 0);
        }

        $statusItems = '';
        foreach ($statusTotals as $key => $count) {
            $statusItems .= '<li>' . $esc($statusLabels[$key] ?? ucfirst($key)) . ': ' . (int) $count . '</li>';
        }

        $typeItems = '';
        foreach ($typeTotals as $key => $count) {
            $typeItems .= '<li>' . $esc($typeLabels[$key] ?? $key) . ': ' . (int) $count . '</li>';
        }

        $tableRows = '';
        foreach ($rows as $row) {
            $tableRows .= '<tr>'
                    . '<td>#' . (int) ($row['id'] ?? 0) . '</td>'
                    . '<td>' . $esc((string) ($typeLabels[$row['transaction_type'] ?? ''] ?? ($row['transaction_type'] ?? 'Outro'))) . '</td>'
                    . '<td>' . $esc((string) ($statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?? ''))) . '</td>'
                    . '<td>' . $esc((string) ($row['direction'] ?? '')) . '</td>'
                    . '<td>' . $esc(number_format((float) ($row['amount'] ?? 0), 2, ',', '.') . ' ' . (string) ($row['currency'] ?? 'AOA')) . '</td>'
                    . '<td>' . $esc((string) ($row['method_name'] ?? 'N/A')) . '</td>'
                    . '<td>' . $esc((string) ($row['counterparty_name'] ?? 'N/A')) . '</td>'
                    . '<td>' . $esc((string) ($row['reference_code'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($row['created_at'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($row['confirmed_at'] ?? '')) . '</td>'
                    . '</tr>';
        }

        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="10" style="text-align:center;">Sem transações para exportar.</td></tr>';
        }

        $html = '<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px 20px 36px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        .header { margin-bottom: 10px; }
        .brand { display: table; width: 100%; margin-bottom: 8px; }
        .brand-left, .brand-right { display: table-cell; vertical-align: middle; }
        .brand-right { text-align: right; }
        .brand-text { font-size: 24px; font-weight: 700; }
        .brand-imobil { color: #0b2f7a; }
        .brand-facil { color: #f2b705; }
        .title { margin: 0; font-size: 16px; }
        .meta { margin: 4px 0 0; color: #4b5563; }
        .summary { margin: 10px 0 12px; border: 1px solid #d1d5db; background: #f9fafb; }
        .summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .summary-table td { border: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        .summary-label { width: 150px; font-weight: 700; background: #f3f4f6; }
        .summary-list { margin: 0; padding-left: 16px; }
        .summary-list li { margin: 0 0 2px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: top; word-wrap: break-word; }
        th { background: #f3f4f6; text-align: left; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            <div class="brand-left"><div class="brand-text"><span class="brand-imobil">Imobil</span><span class="brand-facil">Fácil</span></div></div>
            <div class="brand-right">
                <h1 class="title">' . $esc($title) . '</h1>
                <p class="meta">Gerado em ' . $esc($generatedAt) . '</p>
            </div>
        </div>
        <p class="meta">' . $esc($scope) . ' | Registos: ' . count($rows) . '</p>
    </div>

    <div class="summary">
        <table class="summary-table">
            <tr>
                <td class="summary-label">Total financeiro</td>
                <td>' . $esc(number_format($grandTotalAmount, 2, ',', '.') . ' AOA') . '</td>
            </tr>
            <tr>
                <td class="summary-label">Totais por estado</td>
                <td>' . ($statusItems !== '' ? '<ul class="summary-list">' . $statusItems . '</ul>' : 'Sem dados') . '</td>
            </tr>
            <tr>
                <td class="summary-label">Totais por tipo</td>
                <td>' . ($typeItems !== '' ? '<ul class="summary-list">' . $typeItems . '</ul>' : 'Sem dados') . '</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Direção</th>
                <th>Montante</th>
                <th>Método</th>
                <th>Utilizador</th>
                <th>Referência</th>
                <th>Criada em</th>
                <th>Confirmada em</th>
            </tr>
        </thead>
        <tbody>' . $tableRows . '</tbody>
    </table>
</body>
</html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $footerFont = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(20, 575, 'ImobilFácil - Simples para anunciar, seguro para negociar', $footerFont, 8, [0.45, 0.45, 0.45]);
        $canvas->page_text(760, 575, 'Página {PAGE_NUM} de {PAGE_COUNT}', $footerFont, 8, [0.45, 0.45, 0.45]);

        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }
}
