<?php

namespace App\controller;

use App\model\Log;
use App\model\Notification;
use App\model\Property;
use App\model\PropertyAffiliate;
use App\model\Request;
use App\model\RequestChatMessage;
use App\services\CommissionClosingService;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;

class ControllerRequestWorkflow
{
    use RequestControllerSupport;

    public function updateStatus($id, $status = null)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ClassCsrf::failRedirect('dashboard', 'Token inválido');
            exit;
        }

        // Accept status from POST forms and keep URL-based status as fallback.
        $status = $_POST['status'] ?? $status;

        $allowed = Request::allowedStatuses();
        if (!in_array($status, $allowed, true)) {
            header('Location: ' . DIRPAGE . 'dashboard?error=Status inválido');
            exit;
        }

        $request = Request::findById((int) $id);
        if (!$request) {
            header('Location: ' . DIRPAGE . 'dashboard?error=Solicitação não encontrada');
            exit;
        }

        $currentStatus = (string) ($request['status'] ?? '');
        $disputeWindowOpen = Request::isDisputeWindowOpen($request);

        if (!Request::canTransition($currentStatus, (string) $status)) {
            $this->redirectBackOr('requests', 'error', 'Transição de status não permitida para esta solicitação');
        }

        // Check if user is the property owner or can manage all requests by permission.
        $property = Property::find((int) $request['property_id']);
        if (!$property || !ClassAccess::canManagePropertyRequests($user, $property)) {
            header('Location: ' . DIRPAGE . 'dashboard?error=Você não tem permissão para aprovar esta solicitação');
            exit;
        }

        if ($this->isPropertyCommerciallyClosed($property)) {
            header('Location: ' . DIRPAGE . 'dashboard?error=Esta solicitação não pode ser negociada porque o imóvel já está vendido ou alugado');
            exit;
        }

        $isRequestManager = $this->userCanManageAllRequests($user);

        if (!$isRequestManager && $currentStatus === 'em_disputa') {
            header('Location: ' . DIRPAGE . 'dashboard?error=Solicitação em disputa só pode ser decidida por perfis de moderação');
            exit;
        }

        $allowedByProfile = Request::nextStatusesForNegotiationActor($currentStatus, $isRequestManager, $disputeWindowOpen);
        if (!in_array((string) $status, $allowedByProfile, true)) {
            header('Location: ' . DIRPAGE . 'dashboard?error=Transição não permitida para o seu perfil');
            exit;
        }

        if ($status === 'fechado_ganho' && $isRequestManager && $currentStatus !== 'em_disputa') {
            $this->redirectBackOr('requests', 'error', 'Perfis de moderação não podem declarar ganho fora do fluxo de disputa');
        }

        $requiresActionNote = (
            (string) $status === 'em_disputa'
            || $currentStatus === 'em_disputa'
            || (string) $status === 'cancelado'
        );
        $actionContext = $this->collectActionContext($requiresActionNote);
        if (!empty($actionContext['error'])) {
            $this->redirectBackOr('requests', 'error', (string) $actionContext['error']);
        }

        $allowedWhenPropertyNotAvailable = $isRequestManager ? ['cancelado', 'em_disputa'] : ['cancelado'];
        if (($property['status'] ?? '') !== 'disponivel' && !in_array((string) $status, $allowedWhenPropertyNotAvailable, true)) {
            header('Location: ' . DIRPAGE . 'dashboard?error=Solicitacao bloqueada: o anuncio precisa estar moderado e disponivel');
            exit;
        }

        if ($status === 'fechado_ganho' && $isRequestManager && $currentStatus === 'em_disputa') {
            // Admin resolving a dispute: use resolveDispute() to update commercial_status + dispute_status.
            $updated = Request::resolveDispute((int) $id, 'fechado_ganho', (int) ($user['id'] ?? 0));
        } elseif ($status === 'cancelado' && $isRequestManager && $currentStatus === 'em_disputa') {
            $updated = Request::resolveDispute((int) $id, 'cancelado', (int) ($user['id'] ?? 0));
        } elseif ($status === 'fechado_ganho') {
            $updated = Request::declareClosingWon((int) $id, (int) ($user['id'] ?? 0));
        } else {
            $updated = Request::updateStatus((int) $id, $status);
        }

        if ($updated) {
            if ($status === 'fechado_ganho' && $isRequestManager && $currentStatus === 'em_disputa' && $property) {
                $closing = CommissionClosingService::finalizeModeratorDisputeWin(
                    (int) $id,
                    (int) ($user['id'] ?? 0),
                    $property
                );

                if (!$closing['ok']) {
                    $this->redirectBackOr('requests', 'error', (string) ($closing['error'] ?? 'Não foi possível consolidar o fecho.'));
                }

                CommissionClosingService::notifyCommissionCreated(
                    (int) $id,
                    (array) ($closing['request'] ?? []),
                    (array) ($closing['property'] ?? $property),
                    (int) ($user['id'] ?? 0),
                    (int) ($closing['commission_id'] ?? 0),
                    !empty($closing['commission_created']),
                    !empty($closing['has_valid_affiliate']),
                    (int) ($closing['affiliate_id'] ?? 0)
                );
            }

            Log::create([
                'user_id' => $user['id'],
                'action' => 'update_request_status',
                'entity_type' => 'request',
                'entity_id' => (int) $id,
                'details' => $this->composeActionLogDetails(
                    'Status atualizado para: ' . $status,
                    (string) ($actionContext['note'] ?? ''),
                    is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null
                ),
            ]);

            $this->appendChatSystemMessage(
                (int) $id,
                (int) ($user['id'] ?? 0),
                $this->systemMessageForStatusChange(
                    (string) $status,
                    $request,
                    $property ?: [],
                    $user,
                    (string) ($actionContext['note'] ?? '')
                ),
                is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null
            );

            $isOwnerDeclaredClosingWon = $status === 'fechado_ganho'
                && $currentStatus === 'em_contacto'
                && !$isRequestManager;

            if ($isOwnerDeclaredClosingWon) {
                RequestChatMessage::postClosingWonPlatformVisitMessage((int) $id, (int) ($user['id'] ?? 0));

                $propertyTitle = (string) ($property['title'] ?? '');
                $requesterId = (int) ($request['user_id'] ?? 0);
                $ownerId = (int) ($property['affiliate_id'] ?? 0);

                if ($requesterId > 0) {
                    Notification::notifyClosingWonPlatformVisit(
                        $requesterId,
                        (int) $id,
                        $propertyTitle,
                        true,
                        (int) ($user['id'] ?? 0)
                    );
                }

                if ($ownerId > 0) {
                    Notification::notifyClosingWonPlatformVisit(
                        $ownerId,
                        (int) $id,
                        $propertyTitle,
                        false,
                        (int) ($user['id'] ?? 0)
                    );
                }
            } else {
                $notifyUsers = [(int) $request['user_id']];
                if ($isRequestManager && $currentStatus === 'em_disputa' && !empty($property['affiliate_id'])) {
                    $notifyUsers[] = (int) $property['affiliate_id'];
                }

                foreach (array_values(array_unique(array_filter($notifyUsers))) as $targetUserId) {
                    Notification::notifyRequestStatusChanged(
                        (int) $targetUserId,
                        (int) $id,
                        (string) $status,
                        (string) ($property['title'] ?? ''),
                        (int) $user['id'],
                        $status === 'fechado_ganho'
                            ? ($currentStatus === 'em_disputa'
                                ? Request::CLOSING_CONFIRMATION_CONFIRMED
                                : Request::CLOSING_CONFIRMATION_PENDING)
                            : ($request['closing_confirmation_status'] ?? null)
                    );
                }
            }

            $this->redirectBackOr('dashboard', 'success', 'Status atualizado');
        }

        $this->redirectBackOr('dashboard', 'error', 'Não foi possível atualizar o status');
    }


    public function cancel($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectBackOr('requests?view=sent', 'error', 'Token inválido');
        }

        $request = Request::findById((int) $id);
        if (!$request) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Solicitação não encontrada');
        }

        if ((int) ($request['user_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Sem permissão para cancelar esta solicitação');
        }

        if (!in_array((string) ($request['status'] ?? ''), Request::CANCELLABLE_STATUSES, true)) {
            $this->redirectBackOr('requests?view=sent', 'error', 'So e possivel cancelar solicitacoes com negociacao ativa');
        }

        $actionContext = $this->collectActionContext(true);
        if (!empty($actionContext['error'])) {
            $this->redirectBackOr('requests?view=sent', 'error', (string) $actionContext['error']);
        }

        $cancelled = Request::updateStatus((int) $id, 'cancelado');

        if ($cancelled) {
            Log::create([
                'user_id' => $user['id'],
                'action' => 'cancel_request',
                'entity_type' => 'request',
                'entity_id' => (int) $id,
                'details' => $this->composeActionLogDetails(
                    'Solicitação cancelada pelo utilizador',
                    (string) ($actionContext['note'] ?? ''),
                    is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null
                ),
            ]);

            $this->appendChatSystemMessage(
                (int) $id,
                (int) ($user['id'] ?? 0),
                'O solicitante cancelou a negociação. Observação: ' . trim((string) ($actionContext['note'] ?? ''))
            );

            $property = Property::find((int) ($request['property_id'] ?? 0));
            if (!empty($property['affiliate_id'])) {
                Notification::notifyUser(
                    (int) $property['affiliate_id'],
                    'request_cancelled',
                    'Solicitação cancelada',
                    'Uma solicitação para o imóvel "' . ($property['title'] ?? '') . '" foi cancelada pelo cliente.',
                    ['request_id' => (int) $id, 'property_id' => (int) ($request['property_id'] ?? 0)],
                    (int) $user['id']
                );
            }

            $this->redirectBackOr('requests?view=sent', 'success', 'Solicitação cancelada com sucesso');
        }

        $this->redirectBackOr('requests?view=sent', 'error', 'Não foi possível cancelar a solicitação');
    }


    public function confirmClosing($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectBackOr('requests?view=sent', 'error', 'Token inválido');
        }

        $request = Request::findById((int) $id);
        if (!$request) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Solicitação não encontrada');
        }

        if ((int) ($request['user_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Sem permissão para confirmar este fecho');
        }

        if (($request['status'] ?? '') !== 'fechado_ganho' || ($request['closing_confirmation_status'] ?? '') !== Request::CLOSING_CONFIRMATION_PENDING) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Esta solicitação não está em fase de confirmação de pagamento');
        }

        $paymentConfirmationStatus = (string) ($request['payment_confirmation_status'] ?? Request::PAYMENT_CONFIRMATION_PENDING);
        if (!in_array($paymentConfirmationStatus, [
            Request::PAYMENT_CONFIRMATION_PENDING,
            Request::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER,
        ], true)) {
            $this->redirectBackOr('requests?view=sent', 'error', 'O pagamento desta solicitação não pode ser declarado neste momento');
        }

        $actionContext = $this->collectActionContext(false, true);
        if (!empty($actionContext['error'])) {
            $this->redirectBackOr('requests?view=sent', 'error', (string) ($actionContext['error'] ?? 'Erro ao registrar pagamento'));
        }

        $property = Property::find((int) ($request['property_id'] ?? 0));

        if ($property && $this->isPropertyCommerciallyClosed($property)) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Esta solicitação não pode ser negociada porque o imóvel já está vendido ou alugado');
        }

        $confirmed = Request::declarePaymentByRequester(
            (int) $id,
            (int) ($user['id'] ?? 0),
            is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null
        );

        if ($confirmed) {
            Log::create([
                'user_id' => (int) ($user['id'] ?? 0),
                'action' => 'declare_request_payment',
                'entity_type' => 'request',
                'entity_id' => (int) $id,
                'details' => $this->composeActionLogDetails(
                    'Pagamento declarado pelo interessado',
                    (string) ($actionContext['note'] ?? ''),
                    is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null
                ),
            ]);

            $proofPath = is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null;
            $this->appendChatSystemMessage(
                (int) $id,
                (int) ($user['id'] ?? 0),
                'O solicitante declarou o pagamento da negociação.' . ($proofPath ? ' Comprovativo em anexo.' : ''),
                $proofPath
            );

            if (!empty($property['affiliate_id'])) {
                Notification::notifyUser(
                    (int) $property['affiliate_id'],
                    'request_payment_declared',
                    'Pagamento declarado',
                    'O interessado declarou pagamento no imóvel "' . ((string) ($property['title'] ?? '')) . '". Confirme o recebimento para consolidar o fecho.',
                    ['request_id' => (int) $id, 'property_id' => (int) ($request['property_id'] ?? 0)],
                    (int) ($user['id'] ?? 0)
                );
            }

            $this->redirectBackOr('requests?view=sent', 'success', 'Pagamento declarado com sucesso');
        }

        $this->redirectBackOr('requests?view=sent', 'error', 'Não foi possível declarar o pagamento');
    }


    public function contestClosing($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectBackOr('requests?view=sent', 'error', 'Token inválido');
        }

        $request = Request::findById((int) $id);
        if (!$request) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Solicitação não encontrada');
        }

        if ((int) ($request['user_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Sem permissão para contestar este fecho');
        }

        if (($request['status'] ?? '') !== 'fechado_ganho' || ($request['closing_confirmation_status'] ?? '') !== Request::CLOSING_CONFIRMATION_PENDING) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Este fecho não está pendente de confirmação');
        }

        $actionContext = $this->collectActionContext(true);
        if (!empty($actionContext['error'])) {
            $this->redirectBackOr('requests?view=sent', 'error', (string) $actionContext['error']);
        }

        $contested = Request::contestPayment((int) $id, (int) ($user['id'] ?? 0));
        $property = Property::find((int) ($request['property_id'] ?? 0));

        if ($contested) {
            Log::create([
                'user_id' => (int) ($user['id'] ?? 0),
                'action' => 'contest_request_closing',
                'entity_type' => 'request',
                'entity_id' => (int) $id,
                'details' => $this->composeActionLogDetails(
                    'Fecho ganho contestado pelo interessado',
                    (string) ($actionContext['note'] ?? ''),
                    is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null
                ),
            ]);

            $this->appendChatSystemMessage(
                (int) $id,
                (int) ($user['id'] ?? 0),
                'O solicitante contestou o fecho ganho e levou a negociação para disputa. Observação: ' . trim((string) ($actionContext['note'] ?? ''))
            );

            if (!empty($property['affiliate_id'])) {
                Notification::notifyUser(
                    (int) $property['affiliate_id'],
                    'request_payment_contested',
                    'Pagamento contestado',
                    'O interessado contestou o pagamento do imóvel "' . ((string) ($property['title'] ?? '')) . '". O caso foi movido para disputa.',
                    ['request_id' => (int) $id, 'property_id' => (int) ($request['property_id'] ?? 0)],
                    (int) ($user['id'] ?? 0)
                );
            }

            $this->redirectBackOr('requests?view=sent', 'success', 'Contestação enviada. A solicitação está agora em disputa');
        }

        $this->redirectBackOr('requests?view=sent', 'error', 'Não foi possível contestar o fecho');
    }


    public function confirmPaymentReceipt($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectBackOr('requests', 'error', 'Token inválido');
        }

        $request = Request::findById((int) $id);
        if (!$request) {
            $this->redirectBackOr('requests', 'error', 'Solicitação não encontrada');
        }

        $property = Property::find((int) ($request['property_id'] ?? 0));
        if (!$property || (int) ($property['affiliate_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            $this->redirectBackOr('requests', 'error', 'Sem permissão para confirmar recebimento');
        }

        if (($request['status'] ?? '') !== 'fechado_ganho' || ($request['closing_confirmation_status'] ?? '') !== Request::CLOSING_CONFIRMATION_PENDING) {
            $this->redirectBackOr('requests', 'error', 'Esta solicitação não está em fase de confirmação de recebimento');
        }

        if (($request['payment_confirmation_status'] ?? '') !== Request::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER) {
            $this->redirectBackOr('requests', 'error', 'Ainda não há declaração de pagamento do interessado');
        }

        if ($this->isPropertyCommerciallyClosed($property)) {
            $this->redirectBackOr('requests', 'error', 'Esta solicitação não pode ser consolidada porque o imóvel já está vendido ou alugado');
        }

        $closing = CommissionClosingService::finalizeOwnerPaymentReceipt((int) $id, (int) ($user['id'] ?? 0));

        if (!empty($closing['ok'])) {
            $request = (array) ($closing['request'] ?? $request);
            $property = (array) ($closing['property'] ?? $property);

            CommissionClosingService::notifyCommissionCreated(
                (int) $id,
                $request,
                $property,
                (int) ($user['id'] ?? 0),
                (int) ($closing['commission_id'] ?? 0),
                !empty($closing['commission_created']),
                !empty($closing['has_valid_affiliate']),
                (int) ($closing['affiliate_id'] ?? 0)
            );

            Log::create([
                'user_id' => (int) ($user['id'] ?? 0),
                'action' => 'confirm_request_payment_receipt',
                'entity_type' => 'request',
                'entity_id' => (int) $id,
                'details' => 'Recebimento de pagamento confirmado pelo proprietário',
            ]);

            $this->appendChatSystemMessage(
                (int) $id,
                (int) ($user['id'] ?? 0),
                'O proprietário confirmou o recebimento do pagamento e consolidou o fecho da negociação.'
            );

            Notification::notifyUser(
                (int) ($request['user_id'] ?? 0),
                'request_payment_receipt_confirmed',
                'Recebimento confirmado',
                'O proprietário confirmou o recebimento do pagamento do imóvel "' . ((string) ($property['title'] ?? '')) . '".',
                ['request_id' => (int) $id, 'property_id' => (int) ($request['property_id'] ?? 0)],
                (int) ($user['id'] ?? 0)
            );

            $this->redirectBackOr('requests', 'success', 'Recebimento confirmado com sucesso');
        }

        $this->redirectBackOr('requests', 'error', (string) ($closing['error'] ?? 'Não foi possível confirmar o recebimento'));
    }


    public function contestPayment($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectBackOr('requests', 'error', 'Token inválido');
        }

        $request = Request::findById((int) $id);
        if (!$request) {
            $this->redirectBackOr('requests', 'error', 'Solicitação não encontrada');
        }

        $property = Property::find((int) ($request['property_id'] ?? 0));
        if (!$property || (int) ($property['affiliate_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            $this->redirectBackOr('requests', 'error', 'Sem permissão para contestar pagamento');
        }

        if (($request['status'] ?? '') !== 'fechado_ganho' || ($request['closing_confirmation_status'] ?? '') !== Request::CLOSING_CONFIRMATION_PENDING) {
            $this->redirectBackOr('requests', 'error', 'Esta solicitação não está apta para contestação de pagamento');
        }

        $actionContext = $this->collectActionContext(true);
        if (!empty($actionContext['error'])) {
            $this->redirectBackOr('requests', 'error', (string) ($actionContext['error'] ?? 'Erro ao contestar pagamento'));
        }

        $contested = Request::contestPayment((int) $id, (int) ($user['id'] ?? 0));
        if ($contested) {
            Log::create([
                'user_id' => (int) ($user['id'] ?? 0),
                'action' => 'contest_request_payment',
                'entity_type' => 'request',
                'entity_id' => (int) $id,
                'details' => $this->composeActionLogDetails(
                    'Pagamento contestado pelo proprietário',
                    (string) ($actionContext['note'] ?? ''),
                    is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null
                ),
            ]);

            $this->appendChatSystemMessage(
                (int) $id,
                (int) ($user['id'] ?? 0),
                'O proprietário contestou o pagamento e a solicitação foi enviada para disputa. Observação: ' . trim((string) ($actionContext['note'] ?? ''))
            );

            Notification::notifyUser(
                (int) ($request['user_id'] ?? 0),
                'request_payment_contested',
                'Pagamento contestado',
                'O proprietário contestou o pagamento do imóvel "' . ((string) ($property['title'] ?? '')) . '". O caso foi movido para disputa.',
                ['request_id' => (int) $id, 'property_id' => (int) ($request['property_id'] ?? 0)],
                (int) ($user['id'] ?? 0)
            );

            $this->redirectBackOr('requests', 'success', 'Contestação de pagamento enviada com sucesso');
        }

        $this->redirectBackOr('requests', 'error', 'Não foi possível contestar o pagamento');
    }


    public function openDispute($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectBackOr('requests?view=sent', 'error', 'Token inválido');
        }

        $request = Request::findById((int) $id);
        if (!$request) {
            $this->redirectBackOr('requests?view=sent', 'error', 'Solicitação não encontrada');
        }

        $isRequester = (int) ($request['user_id'] ?? 0) === (int) ($user['id'] ?? 0);
        $property = Property::find((int) ($request['property_id'] ?? 0));
        if (!$isRequester) {
            $this->redirectBackOr('requests', 'error', 'Apenas o solicitante pode abrir disputa nesta solicitação');
        }

        if (!Request::isDisputeWindowOpen($request)) {
            $this->redirectBackOr('requests', 'error', 'A janela de disputa desta solicitação já terminou');
        }

        $actionContext = $this->collectActionContext(true);
        if (!empty($actionContext['error'])) {
            $this->redirectBackOr('requests', 'error', (string) $actionContext['error']);
        }

        $opened = Request::openDisputeStatus((int) $id);
        if ($opened) {
            Log::create([
                'user_id' => (int) ($user['id'] ?? 0),
                'action' => 'open_request_dispute',
                'entity_type' => 'request',
                'entity_id' => (int) $id,
                'details' => $this->composeActionLogDetails(
                    'Solicitação enviada para disputa',
                    (string) ($actionContext['note'] ?? ''),
                    is_string($actionContext['image_path'] ?? null) ? $actionContext['image_path'] : null
                ),
            ]);

            $this->appendChatSystemMessage(
                (int) $id,
                (int) ($user['id'] ?? 0),
                'O solicitante abriu disputa para esta negociação. Observação: ' . trim((string) ($actionContext['note'] ?? ''))
            );

            $notifyUsers = array_unique(array_filter([
                (int) ($request['user_id'] ?? 0),
                (int) ($property['affiliate_id'] ?? 0),
            ]));
            foreach ($notifyUsers as $targetUserId) {
                if ($targetUserId === (int) ($user['id'] ?? 0)) {
                    continue;
                }

                Notification::notifyRequestStatusChanged(
                    (int) $targetUserId,
                    (int) $id,
                    'em_disputa',
                    (string) ($property['title'] ?? ''),
                    (int) ($user['id'] ?? 0)
                );
            }

            $this->redirectBackOr('requests', 'success', 'Disputa aberta com sucesso');
        }

        $this->redirectBackOr('requests', 'error', 'Não foi possível abrir a disputa');
    }


    public function approveAffiliate($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ClassCsrf::failRedirect('dashboard/afiliados?tab=affiliate_requests', 'Token inválido');
            exit;
        }

        $affiliate = PropertyAffiliate::find((int) $id);
        if (!$affiliate) {
            header('Location: ' . DIRPAGE . 'dashboard/afiliados?tab=affiliate_requests&error=Solicitação de afiliação não encontrada');
            exit;
        }

        // Check if user is the property owner
        $property = Property::find($affiliate['property_id']);
        if (!$property || $property['affiliate_id'] != $user['id']) {
            header('Location: ' . DIRPAGE . 'dashboard/afiliados?tab=affiliate_requests&error=Você não tem permissão para aprovar esta solicitação');
            exit;
        }

        $approved = PropertyAffiliate::approve((int) $id);

        if ($approved) {
            Log::create([
                'user_id' => $user['id'],
                'action' => 'approve_affiliate_request',
                'entity_type' => 'property_affiliate',
                'entity_id' => (int) $id,
                'details' => 'Afiliado aprovado para propriedade ID: ' . $property['id'],
            ]);
            Notification::notifyUser(
                (int) $affiliate['user_id'],
                'affiliate_approved',
                'Afiliação aprovada',
                'A sua solicitação de afiliação ao imóvel "' . ($property['title'] ?? '') . '" foi aprovada. Pode começar a indicar.',
                ['property_id' => (int) $property['id']],
                (int) $user['id']
            );
            $this->redirectBackOr('dashboard/afiliados?tab=affiliate_requests', 'success', 'Afiliado aprovado com sucesso', [
                'affiliate_request_id' => (int) $id,
                'action' => 'approve',
                'property_id' => (int) $property['id'],
            ]);
        }

        $this->redirectBackOr('dashboard/afiliados?tab=affiliate_requests', 'error', 'Não foi possível aprovar a solicitação');
    }


    public function rejectAffiliate($id)
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ClassCsrf::failRedirect('dashboard/afiliados?tab=affiliate_requests', 'Token inválido');
            exit;
        }

        $affiliate = PropertyAffiliate::find((int) $id);
        if (!$affiliate) {
            header('Location: ' . DIRPAGE . 'dashboard/afiliados?tab=affiliate_requests&error=Solicitação de afiliação não encontrada');
            exit;
        }

        // Check if user is the property owner
        $property = Property::find($affiliate['property_id']);
        if (!$property || $property['affiliate_id'] != $user['id']) {
            header('Location: ' . DIRPAGE . 'dashboard/afiliados?tab=affiliate_requests&error=Você não tem permissão para rejeitar esta solicitação');
            exit;
        }

        $rejected = PropertyAffiliate::reject((int) $id);

        if ($rejected) {
            Log::create([
                'user_id' => $user['id'],
                'action' => 'reject_affiliate_request',
                'entity_type' => 'property_affiliate',
                'entity_id' => (int) $id,
                'details' => 'Solicitação de afiliação rejeitada para propriedade ID: ' . $property['id'],
            ]);
            Notification::notifyUser(
                (int) $affiliate['user_id'],
                'affiliate_rejected',
                'Afiliação rejeitada',
                'A sua solicitação de afiliação ao imóvel "' . ($property['title'] ?? '') . '" foi rejeitada pelo proprietário.',
                ['property_id' => (int) $property['id']],
                (int) $user['id']
            );
            $this->redirectBackOr('dashboard/afiliados?tab=affiliate_requests', 'success', 'Solicitação rejeitada', [
                'affiliate_request_id' => (int) $id,
                'action' => 'reject',
                'property_id' => (int) $property['id'],
            ]);
        }

        $this->redirectBackOr('dashboard/afiliados?tab=affiliate_requests', 'error', 'Não foi possível rejeitar a solicitação');
    }

}
