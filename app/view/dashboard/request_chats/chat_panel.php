<?php
    $request = is_array($request ?? null) ? $request : [];
    $messages = is_array($messages ?? null) ? $messages : [];
    $chatWritable = !empty($chatWritable);
    $requestId = (int) ($request['id'] ?? 0);
    $commercialStatus = (string) ($request['commercial_status'] ?? ($request['status'] ?? ''));
    $status = (string) ($request['status'] ?? '');
    $closingConfirmationStatus = isset($request['closing_confirmation_status']) ? (string) $request['closing_confirmation_status'] : null;
    $paymentConfirmationStatus = isset($request['payment_confirmation_status']) ? (string) $request['payment_confirmation_status'] : null;
    $statusLabel = App\model\Request::statusLabel($commercialStatus, $closingConfirmationStatus);
    $currentUserId = (int) (($user['id'] ?? 0));
    $requesterId = (int) ($request['user_id'] ?? 0);
    $ownerId = (int) ($request['owner_id'] ?? 0);
    $isOwner = $ownerId > 0 && $ownerId === $currentUserId;
    $isRequester = $requesterId > 0 && $requesterId === $currentUserId;
    $disputeWindowOpen = App\model\Request::isDisputeWindowOpen($request);
    $ownerAllowedActions = App\model\Request::nextStatusesForNegotiationActor($status, false, $disputeWindowOpen);
    $canMarkClosingWon = $isOwner && in_array('fechado_ganho', $ownerAllowedActions, true);
    $requesterPaymentActions = App\model\Request::requesterActionsFor(
        $status,
        $closingConfirmationStatus,
        $paymentConfirmationStatus,
        $disputeWindowOpen
    );
    $ownerPaymentActions = App\model\Request::ownerPaymentActionsFor(
        $status,
        $closingConfirmationStatus,
        $paymentConfirmationStatus,
        $disputeWindowOpen
    );
    $requestTypeLabel = App\model\Request::requestTypeLabel((string) ($request['type'] ?? ''));
    $amountSummary = App\model\Request::negotiationAmountSummary($request);
    $listBackUrl = isset($listBackUrl) ? (string) $listBackUrl : '';
    $requesterProfileId = (int) ($request['requester_id'] ?? $request['user_id'] ?? 0);
    $ownerProfileId = (int) ($request['owner_id'] ?? 0);
    $requesterHandle = Src\classes\UserDisplay::publicHandleFromRow($request, 'requester_username', 'requester_name');
    $ownerHandle = Src\classes\UserDisplay::publicHandleFromRow($request, 'owner_username', 'owner_name');

    $hasMessagesFromOthers = false;
    foreach ($messages as $chatMessage) {
        if ((int) ($chatMessage['sender_user_id'] ?? 0) !== $currentUserId) {
            $hasMessagesFromOthers = true;
            break;
        }
    }
?>

<div class="request-chats-conversation-inner">
    <div class="request-chats-conversation-toolbar">
        <?php if ($listBackUrl !== ''): ?>
            <a href="<?php echo htmlspecialchars($listBackUrl); ?>" class="request-chats-back-list notification-inbox-text-btn">
                <i class="fa fa-arrow-left" aria-hidden="true"></i> Conversas
            </a>
        <?php endif; ?>
        <div class="request-chats-conversation-heading">
            <h3><?php echo htmlspecialchars((string) ($request['title'] ?? 'Negociação')); ?></h3>
            <span class="request-chats-conversation-id">#<?php echo $requestId; ?></span>
        </div>
        <div class="request-chats-conversation-toolbar-actions">
            <?php if ($hasMessagesFromOthers): ?>
                <button type="button"
                        class="request-chats-mark-unread-btn notification-inbox-text-btn"
                        data-request-id="<?php echo $requestId; ?>">
                    Marcar como não lida
                </button>
            <?php endif; ?>
            <button type="button"
                    class="request-chats-context-toggle"
                    id="requestChatsContextToggle"
                    aria-expanded="false"
                    aria-controls="requestChatsContextCard">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <span>Contexto</span>
            </button>
        </div>
    </div>

    <article class="dispute-summary-card request-chats-context-card" id="requestChatsContextCard">
        <span class="request-chats-context-kicker">Contexto</span>
        <p><strong>Solicitante:</strong>
            <?php if ($requesterProfileId > 0): ?>
                <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo $requesterProfileId; ?>" class="table-name-link"><?php echo htmlspecialchars($requesterHandle); ?></a>
            <?php else: ?>
                <?php echo htmlspecialchars($requesterHandle); ?>
            <?php endif; ?>
        </p>
        <p><strong>Proprietário:</strong>
            <?php if ($ownerProfileId > 0): ?>
                <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo $ownerProfileId; ?>" class="table-name-link"><?php echo htmlspecialchars($ownerHandle); ?></a>
            <?php else: ?>
                <?php echo htmlspecialchars($ownerHandle); ?>
            <?php endif; ?>
        </p>
        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($requestTypeLabel); ?></p>
        <?php
            $chatPropertyStatus = (string) ($request['property_status'] ?? '');
            if ($chatPropertyStatus === 'eliminado'):
        ?>
            <p><strong>Imóvel:</strong> <span class="request-status-badge request-status-cancelado">Eliminado</span> <span class="dashboard-inline-note">Indisponível ao público durante o período de conformidade.</span></p>
        <?php endif; ?>
        <?php include DIRREQ . 'app/view/partials/request_payment_amount.php'; ?>
        <div class="dispute-status-row">
            <span class="request-status-badge request-status-<?php echo htmlspecialchars($commercialStatus); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
            <?php if ($status === 'fechado_ganho'): ?>
                <span class="request-status-badge request-status-<?php echo htmlspecialchars((string) ($paymentConfirmationStatus ?: 'none')); ?>">
                    Pagamento: <?php echo htmlspecialchars(App\model\Request::paymentConfirmationLabel($paymentConfirmationStatus)); ?>
                </span>
            <?php endif; ?>
            <?php if (!$chatWritable): ?>
                <span class="request-status-badge request-status-expirado">Chat em modo leitura</span>
            <?php endif; ?>
        </div>
        <?php if (App\model\Request::hasVisiblePaymentProof($request)): ?>
            <?php
                $paymentProofPath = (string) ($request['payment_proof_path'] ?? '');
                $paymentDeclaredAt = $request['payment_declared_at'] ?? null;
                include DIRREQ . 'app/view/partials/request_payment_proof.php';
            ?>
        <?php endif; ?>
        <div class="dispute-detail-actions request-chats-context-actions">
            <?php if ($canMarkClosingWon): ?>
                <form action="<?php echo DIRPAGE; ?>request/updateStatus/<?php echo $requestId; ?>" method="POST" class="request-chat-mark-won-form">
                    <?php echo Src\classes\ClassCsrf::field(); ?>
                    <input type="hidden" name="status" value="fechado_ganho">
                    <button type="submit" class="btn-primary request-chat-mark-won-btn" data-confirm="Marcar esta negociação como fecho ganho?"><i class="fa fa-check" aria-hidden="true"></i> Marcar fecho ganho</button>
                </form>
            <?php endif; ?>
            <?php if ($isRequester && isset($requesterPaymentActions['confirm_closing'])): ?>
                <form action="<?php echo DIRPAGE; ?>request/confirmClosing/<?php echo $requestId; ?>" method="POST" class="request-chat-mark-won-form request-chat-declare-payment-form" enctype="multipart/form-data">
                    <?php echo Src\classes\ClassCsrf::field(); ?>
                    <label class="request-action-upload request-chat-payment-proof">
                        <span>Comprovativo de pagamento <span class="required-mark">*</span></span>
                        <input type="file" name="action_image" class="js-request-attachment-input" accept="image/*" required>
                        <small class="request-attachment-feedback" id="request-payment-proof-feedback"></small>
                    </label>
                    <button type="submit" class="btn-primary request-chat-mark-won-btn" data-confirm="Declarar que o pagamento foi efetuado? O comprovativo será enviado ao proprietário."><i class="fa fa-credit-card" aria-hidden="true"></i> Declarar pagamento</button>
                </form>
            <?php endif; ?>
            <?php if ($isOwner && isset($ownerPaymentActions['confirm_payment_receipt'])): ?>
                <form action="<?php echo DIRPAGE; ?>request/confirmPaymentReceipt/<?php echo $requestId; ?>" method="POST" class="request-chat-mark-won-form">
                    <?php echo Src\classes\ClassCsrf::field(); ?>
                    <button type="submit" class="btn-primary request-chat-mark-won-btn" data-confirm="Confirmar recebimento do pagamento e consolidar o fecho?"><i class="fa fa-check-circle" aria-hidden="true"></i> Confirmar recebimento</button>
                </form>
            <?php endif; ?>
        </div>
    </article>

    <article class="dispute-summary-card request-chat-card request-chats-messages-card">
        <div class="request-chat-messages"
             id="requestChatMessages"
             data-chat-feed-url="<?php echo DIRPAGE; ?>dashboard/requestChatFeed/<?php echo $requestId; ?>"
             data-chat-current-user-id="<?php echo $currentUserId; ?>"
             data-profile-url-prefix="<?php echo htmlspecialchars(DIRPAGE . 'property/owner/', ENT_QUOTES, 'UTF-8'); ?>">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $message): ?>
                    <?php
                        $isOwn = (int) ($message['sender_user_id'] ?? 0) === $currentUserId;
                        $isSystem = (string) ($message['message_type'] ?? 'text') === 'system';
                        $senderName = Src\classes\UserDisplay::publicHandleFromRow($message, 'sender_username', 'sender_name', 'Utilizador');
                        $senderUserId = (int) ($message['sender_user_id'] ?? 0);
                        $messageText = App\model\RequestChatMessage::displayText((string) ($message['message_text'] ?? ''));
                        $attachmentPath = (string) ($message['attachment_path'] ?? '');
                        $createdAt = (string) ($message['created_at'] ?? '');
                    ?>
                    <article class="request-chat-message <?php echo $isSystem ? 'is-system' : ($isOwn ? 'is-own' : 'is-other'); ?>">
                        <header class="request-chat-message-meta">
                            <strong>
                                <?php if ($isSystem): ?>
                                    Sistema
                                <?php elseif ($senderUserId > 0): ?>
                                    <a href="<?php echo DIRPAGE; ?>property/owner/<?php echo $senderUserId; ?>" class="table-name-link"><?php echo htmlspecialchars($senderName); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($senderName); ?>
                                <?php endif; ?>
                            </strong>
                            <small><?php echo $createdAt !== '' ? htmlspecialchars((string) date('d/m/Y H:i', strtotime($createdAt))) : ''; ?></small>
                        </header>
                        <div class="request-chat-message-body"><?php echo nl2br(htmlspecialchars($messageText)); ?></div>
                        <?php if ($attachmentPath !== ''): ?>
                            <div class="request-chat-message-attachment">
                                <?php $attachmentUrl = \Src\classes\ClassMediaUrl::upload($attachmentPath); ?>
                                <a href="<?php echo htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8'); ?>" class="attachment-link" target="_blank" rel="noopener noreferrer">
                                    <i class="fa fa-image"></i> Ver anexo
                                </a>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-content request-chat-empty">
                    <i class="fa fa-comments"></i>
                    <p>Nenhuma mensagem ainda. Use este canal para conduzir a negociação.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($chatWritable): ?>
            <form action="<?php echo DIRPAGE; ?>request/sendMessage/<?php echo $requestId; ?>" method="POST" class="request-chat-form" enctype="multipart/form-data">
                <?php echo Src\classes\ClassCsrf::field(); ?>
                <label for="request-chat-message" class="sr-only">Mensagem</label>
                <textarea id="request-chat-message" name="message_text" rows="3" maxlength="3000" placeholder="Escreva a sua mensagem de negociação..." required></textarea>
                <div class="request-chat-form-actions">
                    <div class="request-chat-form-attachment">
                        <label for="message-attachment" class="attachment-label">
                            <i class="fa fa-paperclip"></i> Anexar imagem (opcional)
                        </label>
                        <input type="file" id="message-attachment" name="message_attachment" class="attachment-input js-request-attachment-input" accept="image/*">
                        <small id="attachment-name" class="attachment-name"></small>
                    </div>
                    <div class="request-chat-form-submit">
                        <small>Máximo de 3000 caracteres.</small>
                        <button type="submit" class="btn-primary">Enviar mensagem</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="request-chat-readonly-note">
                O chat foi bloqueado para novas mensagens porque a solicitação já não está em negociação ativa.
            </div>
        <?php endif; ?>
    </article>
</div>
