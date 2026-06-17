<?php
/**
 * Social-style request row for /requests dashboard feed.
 *
 * @var array<string, mixed> $request
 * @var bool $showManagementColumns
 * @var bool $showSentContextColumns
 * @var bool $canManageAllRequests
 * @var array<int, array<string, mixed>>|null $requestChatSummaries
 */

use App\model\Notification;
use App\model\Request as RequestModel;

$request = is_array($request ?? null) ? $request : [];
$showManagementColumns = !empty($showManagementColumns);
$showSentContextColumns = !empty($showSentContextColumns);
$canManageAllRequests = !empty($canManageAllRequests);
$requestChatSummaries = is_array($requestChatSummaries ?? null) ? $requestChatSummaries : [];

$requestId = (int) ($request['id'] ?? 0);
$status = (string) ($request['status'] ?? 'pendente');
$closingConfirmationStatus = $request['closing_confirmation_status'] ?? null;
$paymentConfirmationStatus = isset($request['payment_confirmation_status']) ? (string) $request['payment_confirmation_status'] : null;
$commercialStatus = (string) ($request['commercial_status'] ?? $status);
$propertyStatus = (string) ($request['property_status'] ?? 'disponivel');
$disputeStatus = (string) ($request['dispute_status'] ?? RequestModel::DISPUTE_STATUS_NONE);
$disputeWindowOpen = RequestModel::isDisputeWindowOpen($request);
$statusLabel = RequestModel::statusLabel($commercialStatus, is_string($closingConfirmationStatus) ? $closingConfirmationStatus : null);
$statusClass = 'request-status-' . strtolower($commercialStatus);
$propertyStatusMap = [
    'disponivel' => 'Disponível',
    'vendido' => 'Vendido',
    'alugado' => 'Alugado',
    'pendente' => 'Pendente',
    'em_analise' => 'Em análise',
    'rejeitado' => 'Rejeitado',
    'eliminado' => 'Eliminado',
];
$propertyStatusLabel = $propertyStatusMap[$propertyStatus] ?? ucfirst(str_replace('_', ' ', $propertyStatus));
$propertyCommerciallyClosed = in_array($propertyStatus, ['vendido', 'alugado'], true);
$propertyPubliclyUnavailable = in_array($propertyStatus, ['eliminado'], true) || $propertyCommerciallyClosed;
$propertyId = (int) ($request['property_id'] ?? 0);
$propertyTitle = (string) ($request['title'] ?? '');
$propertyUrl = $propertyPubliclyUnavailable ? '' : (DIRPAGE . 'property/' . $propertyId);
$createdAt = (string) ($request['created_at'] ?? '');
$relativeTime = Notification::relativeTime($createdAt);
$absoluteTime = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '';

$visualMap = [
    'em_contacto' => ['fa-comments', 'tone-request'],
    'fechado_ganho' => ['fa-check-circle', 'tone-payment'],
    'em_disputa' => ['fa-gavel', 'tone-alert'],
    'cancelado' => ['fa-ban', 'tone-document'],
    'expirado' => ['fa-clock-o', 'tone-document'],
];
$visual = $visualMap[strtolower($commercialStatus)] ?? ['fa-home', 'tone-default'];
[$feedIcon, $feedTone] = $visual;

$messageParts = [];
if ($showManagementColumns) {
    $requesterHandle = htmlspecialchars(Src\classes\UserDisplay::publicHandleFromRow($request, 'requester_username', 'requester_name'));
    $messageParts[] = $requesterHandle;
}
if ($showSentContextColumns) {
    $ownerHandle = htmlspecialchars(Src\classes\UserDisplay::publicHandleFromRow($request, 'owner_username', 'owner_name', 'Não informado'));
    $messageParts[] = $ownerHandle;
}
$messageParts[] = htmlspecialchars((string) ($request['type'] ?? ''));
if ($showSentContextColumns) {
    $paymentTerm = (string) ($request['payment_term'] ?? '');
    $termLabel = [
        'mensal' => 'Mensal',
        'trimestral' => 'Trimestral',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
    ][$paymentTerm] ?? '';
    if ($termLabel !== '') {
        $messageParts[] = htmlspecialchars($termLabel);
    }
}
$feedMessage = implode(' · ', array_filter($messageParts, static fn ($part): bool => $part !== ''));

$chatSummary = isset($requestChatSummaries[$requestId]) ? $requestChatSummaries[$requestId] : null;
$chatCount = (int) ($chatSummary['total_messages'] ?? 0);
$chatUnread = (int) ($chatSummary['unread_count'] ?? 0);
$chatPreview = trim((string) ($chatSummary['last_message_text'] ?? ''));
if ($chatPreview !== '' && function_exists('mb_strimwidth')) {
    $chatPreview = mb_strimwidth($chatPreview, 0, 70, '...');
} elseif ($chatPreview !== '') {
    $chatPreview = substr($chatPreview, 0, 70) . (strlen($chatPreview) > 70 ? '...' : '');
}
?>

<article class="request-feed-item notification-feed-item request-row request-row-<?php echo htmlspecialchars(strtolower($status), ENT_QUOTES, 'UTF-8'); ?>"
         data-status="<?php echo htmlspecialchars(strtolower($status), ENT_QUOTES, 'UTF-8'); ?>"
         data-property-status="<?php echo htmlspecialchars(strtolower($propertyStatus), ENT_QUOTES, 'UTF-8'); ?>"
         data-payment-status="<?php echo htmlspecialchars(strtolower((string) ($paymentConfirmationStatus ?: 'none')), ENT_QUOTES, 'UTF-8'); ?>"
         data-request-id="<?php echo $requestId; ?>">
    <?php if ($propertyUrl !== ''): ?>
    <a href="<?php echo htmlspecialchars($propertyUrl, ENT_QUOTES, 'UTF-8'); ?>"
       class="notification-feed-link request-feed-link">
    <?php else: ?>
    <div class="notification-feed-link request-feed-link is-static"
         role="group"
         aria-label="<?php echo htmlspecialchars($propertyTitle . ' — imóvel indisponível ao público', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
        <span class="notification-feed-icon <?php echo htmlspecialchars($feedTone, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
            <i class="fa <?php echo htmlspecialchars($feedIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
        </span>
        <span class="notification-feed-body">
            <span class="notification-feed-text">
                <strong><?php echo htmlspecialchars($propertyTitle); ?></strong>
                <?php if ($feedMessage !== ''): ?>
                    <span class="notification-feed-message"><?php echo $feedMessage; ?></span>
                <?php endif; ?>
            </span>
            <span class="notification-feed-meta request-feed-meta">
                <span class="request-status-badge <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                <?php require __DIR__ . '/request_feed_meta_chips.php'; ?>
                <span class="notification-feed-dot request-feed-meta-extra" aria-hidden="true">·</span>
                <time class="notification-feed-time" datetime="<?php echo htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8'); ?>"
                      title="<?php echo htmlspecialchars($absoluteTime, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($relativeTime !== '' ? $relativeTime : $absoluteTime); ?>
                </time>
            </span>
        </span>
        <?php if ($chatUnread > 0): ?>
            <span class="notification-feed-unread-dot" aria-label="<?php echo (int) $chatUnread; ?> mensagem(ns) nova(s)"></span>
        <?php endif; ?>
    <?php if ($propertyUrl !== ''): ?>
    </a>
    <?php else: ?>
    </div>
    <?php endif; ?>

    <div class="request-feed-mobile-bar">
        <a class="request-feed-mobile-chat" href="<?php echo DIRPAGE; ?>dashboard/requestChat/<?php echo $requestId; ?>">
            <i class="fa fa-comments" aria-hidden="true"></i>
            <span>Chat</span>
            <?php if ($chatUnread > 0): ?>
                <span class="request-feed-mobile-chat-badge"><?php echo (int) $chatUnread; ?></span>
            <?php endif; ?>
        </a>
        <button type="button"
                class="request-feed-actions-toggle"
                aria-expanded="false"
                aria-label="Mostrar ou esconder ações da solicitação">
            <span>Ações</span>
            <i class="fa fa-chevron-down" aria-hidden="true"></i>
        </button>
    </div>

    <div class="request-feed-actions col-actions">
        <div class="request-feed-actions-meta" aria-label="Detalhes da solicitação">
            <?php $forActionsPanel = true; require __DIR__ . '/request_feed_meta_chips.php'; unset($forActionsPanel); ?>
        </div>
        <?php if ($propertyCommerciallyClosed): ?>
            <span class="request-action-empty">Negociação encerrada: imóvel <?php echo htmlspecialchars(strtolower($propertyStatusLabel)); ?>.</span>
        <?php elseif ($propertyPubliclyUnavailable && $propertyStatus === 'eliminado'): ?>
            <span class="request-action-empty">Imóvel eliminado — indisponível ao público, mas o chat mantém-se activo durante o período de conformidade.</span>
        <?php endif; ?>
        <div class="request-chat-summary" data-chat-summary-for-request="<?php echo $requestId; ?>" <?php echo $chatCount > 0 ? '' : 'hidden'; ?>>
            <strong>
                <span class="request-chat-summary-count"><?php echo $chatCount; ?></span> mensagem(ns)
                <span class="request-chat-unread-badge" <?php echo $chatUnread > 0 ? '' : 'hidden'; ?>><?php echo $chatUnread; ?> nova(s)</span>
            </strong>
            <small class="request-chat-summary-preview" <?php echo $chatPreview !== '' ? '' : 'hidden'; ?>><?php echo htmlspecialchars($chatPreview); ?></small>
        </div>
        <?php if ($showManagementColumns && !$propertyCommerciallyClosed): ?>
            <?php
                $actionOptions = RequestModel::managementActionsFor($status, $canManageAllRequests, $disputeWindowOpen);
                $ownerPaymentActions = !$canManageAllRequests
                    ? RequestModel::ownerPaymentActionsFor(
                        $status,
                        is_string($closingConfirmationStatus) ? $closingConfirmationStatus : null,
                        is_string($paymentConfirmationStatus) ? $paymentConfirmationStatus : null,
                        $disputeWindowOpen
                    )
                    : [];
            ?>

            <?php if (!empty($actionOptions)): ?>
                <form action="<?php echo DIRPAGE; ?>request/updateStatus/<?php echo $requestId; ?>" method="POST"
                      class="request-action-select-form request-action-select-form--details"
                      enctype="multipart/form-data"
                      data-note-required-actions="em_disputa,cancelado">
                    <?php echo Src\classes\ClassCsrf::field(); ?>
                    <select name="status" class="request-action-select" required>
                        <option value="">Selecionar ação</option>
                        <?php foreach ($actionOptions as $optionValue => $optionLabel): ?>
                            <option value="<?php echo htmlspecialchars($optionValue); ?>"><?php echo htmlspecialchars($optionLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="request-action-extra-fields" hidden>
                        <textarea name="action_note" class="request-action-note" placeholder="Observação (obrigatória para esta ação)..." rows="3" maxlength="2000"></textarea>
                        <label class="request-action-upload">
                            <span>Evidência (opcional)</span>
                            <input type="file" name="action_image" class="js-request-attachment-input" accept="image/*">
                            <small class="request-attachment-feedback"></small>
                        </label>
                    </div>
                    <div class="request-form-buttons" hidden>
                        <button type="button" class="btn-secondary request-action-cancel-btn">Cancelar</button>
                        <a class="btn-secondary dispute-detail-link request-chat-link" href="<?php echo DIRPAGE; ?>dashboard/requestChat/<?php echo $requestId; ?>"><i class="fa fa-comments" aria-hidden="true"></i> Chat</a>
                        <button type="submit" class="btn-primary request-action-apply">Confirmar</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="request-form-buttons">
                    <a class="btn-secondary dispute-detail-link request-chat-link" href="<?php echo DIRPAGE; ?>dashboard/requestChat/<?php echo $requestId; ?>"><i class="fa fa-comments" aria-hidden="true"></i> Chat</a>
                    <span class="request-action-empty">Sem ações disponíveis</span>
                </div>
            <?php endif; ?>
            <?php if (!empty($ownerPaymentActions)): ?>
                <form method="POST"
                      class="request-action-select-form request-action-select-form--details user-action-select-form"
                      enctype="multipart/form-data"
                      data-note-required-actions="contest_payment"
                      data-confirm-payment-receipt-url="<?php echo DIRPAGE; ?>request/confirmPaymentReceipt/<?php echo $requestId; ?>"
                      data-contest-payment-url="<?php echo DIRPAGE; ?>request/contestPayment/<?php echo $requestId; ?>">
                    <?php echo Src\classes\ClassCsrf::field(); ?>
                    <select name="user_action" class="request-action-select" required>
                        <option value="">Selecionar ação de pagamento</option>
                        <?php foreach ($ownerPaymentActions as $optionValue => $optionLabel): ?>
                            <option value="<?php echo htmlspecialchars($optionValue); ?>"><?php echo htmlspecialchars($optionLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="request-action-extra-fields" hidden>
                        <textarea name="action_note" class="request-action-note" placeholder="Observação (obrigatória para esta ação)..." rows="3" maxlength="2000"></textarea>
                        <label class="request-action-upload">
                            <span>Evidência (opcional)</span>
                            <input type="file" name="action_image" class="js-request-attachment-input" accept="image/*">
                            <small class="request-attachment-feedback"></small>
                        </label>
                    </div>
                    <div class="request-form-buttons" hidden>
                        <button type="button" class="btn-secondary request-action-cancel-btn">Cancelar</button>
                        <a class="btn-secondary dispute-detail-link request-chat-link" href="<?php echo DIRPAGE; ?>dashboard/requestChat/<?php echo $requestId; ?>"><i class="fa fa-comments" aria-hidden="true"></i> Chat</a>
                        <button type="submit" class="btn-primary request-action-apply">Confirmar</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php elseif (!$propertyCommerciallyClosed): ?>
            <?php
                $userActionOptions = RequestModel::requesterActionsFor(
                    $status,
                    is_string($closingConfirmationStatus) ? $closingConfirmationStatus : null,
                    is_string($paymentConfirmationStatus) ? $paymentConfirmationStatus : null,
                    $disputeWindowOpen
                );
                $noteRequiredActions = [];
                if (isset($userActionOptions['contest_closing'])) {
                    $noteRequiredActions[] = 'contest_closing';
                }
                if (isset($userActionOptions['cancel'])) {
                    $noteRequiredActions[] = 'cancel';
                }
                if (isset($userActionOptions['open_dispute'])) {
                    $noteRequiredActions[] = 'open_dispute';
                }
                $noteRequiredActionsAttr = implode(',', $noteRequiredActions);
            ?>
            <?php if (!empty($userActionOptions)): ?>
                <form method="POST"
                      class="request-action-select-form request-action-select-form--details user-action-select-form"
                      enctype="multipart/form-data"
                      data-note-required-actions="<?php echo htmlspecialchars($noteRequiredActionsAttr); ?>"
                      data-proof-required-actions="confirm_closing"
                      data-confirm-url="<?php echo DIRPAGE; ?>request/confirmClosing/<?php echo $requestId; ?>"
                      data-contest-url="<?php echo DIRPAGE; ?>request/contestClosing/<?php echo $requestId; ?>"
                      <?php if (isset($userActionOptions['open_dispute'])): ?>data-open-dispute-url="<?php echo DIRPAGE; ?>request/openDispute/<?php echo $requestId; ?>"<?php endif; ?>
                      data-cancel-url="<?php echo DIRPAGE; ?>request/cancel/<?php echo $requestId; ?>">
                    <?php echo Src\classes\ClassCsrf::field(); ?>
                    <select name="user_action" class="request-action-select" required>
                        <option value="">Selecionar ação</option>
                        <?php foreach ($userActionOptions as $optionValue => $optionLabel): ?>
                            <option value="<?php echo htmlspecialchars($optionValue); ?>"><?php echo htmlspecialchars($optionLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="request-action-extra-fields" hidden>
                        <textarea name="action_note" class="request-action-note" placeholder="Observação (obrigatória para esta ação)..." rows="3" maxlength="2000"></textarea>
                        <label class="request-action-upload">
                            <span class="request-action-upload-label">Evidência (opcional)</span>
                            <input type="file" name="action_image" class="js-request-attachment-input" accept="image/*">
                            <small class="request-attachment-feedback"></small>
                        </label>
                    </div>
                    <div class="request-form-buttons" hidden>
                        <button type="button" class="btn-secondary request-action-cancel-btn">Cancelar</button>
                        <a class="btn-secondary dispute-detail-link request-chat-link" href="<?php echo DIRPAGE; ?>dashboard/requestChat/<?php echo $requestId; ?>"><i class="fa fa-comments" aria-hidden="true"></i> Chat</a>
                        <button type="submit" class="btn-primary request-action-apply">Confirmar</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="request-form-buttons">
                    <a class="btn-secondary dispute-detail-link request-chat-link" href="<?php echo DIRPAGE; ?>dashboard/requestChat/<?php echo $requestId; ?>"><i class="fa fa-comments" aria-hidden="true"></i> Chat</a>
                    <span class="request-action-empty">Sem ações disponíveis</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</article>
