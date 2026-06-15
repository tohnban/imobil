<?php
/** @var array $user */
/** @var array $commission */
/** @var string $dueAtFormatted */
/** @var bool $isOverdue */
/** @var array $paymentMethods */
/** @var array $channelsByMethod */
/** @var string $csrfField */

$commissionId = (int) ($commission['id'] ?? 0);
$amount = (float) ($commission['amount'] ?? 0);
$selectedMethodId = !empty($paymentMethods) ? (int) ($paymentMethods[0]['id'] ?? 0) : 0;
?>

<div class="container dashboard-view">
    <div class="sub-shell sub-checkout-shell">
        <section class="dashboard-view-hero compact">
            <div>
                <span class="dashboard-hero-kicker">Comissão #<?php echo $commissionId; ?></span>
                <h1>Pagar comissão</h1>
                <p>Transfira o valor indicado e envie o comprovativo para validação financeira.</p>
            </div>
        </section>

        <?php if (!empty($_GET['error'])): ?>
            <div class="sub-feedback error"><?php echo htmlspecialchars((string) $_GET['error']); ?></div>
        <?php endif; ?>

        <section class="sub-section">
            <div class="sub-section-header">
                <h2 class="sub-section-title">Valor e prazo</h2>
            </div>

            <div class="request-payment-amount-block commission-payment-highlight">
                <span class="request-payment-amount-kicker">Valor total a pagar</span>
                <strong class="request-payment-amount-total"><?php echo number_format($amount, 0, ',', '.'); ?> Kz</strong>
                <small class="request-payment-amount-note">
                    Prazo: <strong><?php echo htmlspecialchars($dueAtFormatted); ?></strong>
                    <?php if (!empty($isOverdue)): ?>
                        <span class="commission-payment-overdue-tag">(vencido)</span>
                    <?php endif; ?>
                </small>
                <p class="dashboard-inline-note" style="margin-top:8px;">
                    Imóvel: <a href="<?php echo DIRPAGE; ?>property/<?php echo (int) ($commission['property_id'] ?? 0); ?>" class="table-name-link"><?php echo htmlspecialchars((string) ($commission['property_title'] ?? '')); ?></a>
                </p>
            </div>
        </section>

        <section class="sub-section">
            <div class="sub-section-header">
                <h2 class="sub-section-title">Dados de pagamento</h2>
                <span class="sub-section-note">Método, referência e comprovativo</span>
            </div>

            <form method="POST"
                  action="<?php echo DIRPAGE; ?>dashboard/submitCommissionPayment/<?php echo $commissionId; ?>"
                  enctype="multipart/form-data"
                  class="dashboard-form sub-checkout-form commission-payment-form">
                <?php echo $csrfField; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_method_id">Tipo de pagamento</label>
                        <select id="payment_method_id" name="payment_method_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?php echo (int) ($method['id'] ?? 0); ?>" <?php echo (int) ($method['id'] ?? 0) === $selectedMethodId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($method['name'] ?? 'Método')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="system_channel_id">Canal de recebimento (opcional)</label>
                        <select id="system_channel_id" name="system_channel_id">
                            <option value="">Selecione</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <?php
                                    $methodId = (int) ($method['id'] ?? 0);
                                    $methodName = (string) ($method['name'] ?? 'Método');
                                    $channels = $channelsByMethod[$methodId] ?? [];
                                ?>
                                <?php foreach ($channels as $channel): ?>
                                    <option value="<?php echo (int) ($channel['id'] ?? 0); ?>">
                                        <?php echo htmlspecialchars($methodName . ' - ' . (string) ($channel['channel_name'] ?? 'Canal')); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="payment_reference">Referência do pagamento (opcional)</label>
                    <input type="text" id="payment_reference" name="payment_reference" maxlength="120" placeholder="Ex: TRX-2026-00091">
                </div>

                <div class="form-group">
                    <label for="commission_payment_proof">Comprovativo de pagamento <span class="required-mark">*</span></label>
                    <input type="file" id="commission_payment_proof" name="payment_proof" class="js-request-attachment-input" accept="image/*" required>
                    <small class="request-attachment-feedback" id="commission-payment-proof-feedback">JPG, PNG ou WebP, até <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?>.</small>
                </div>

                <div class="sub-checkout-actions">
                    <button class="sub-submit primary" type="submit">Enviar comprovativo</button>
                    <a href="<?php echo DIRPAGE; ?>dashboard/commissionPayments" class="sub-submit current">Voltar</a>
                </div>
            </form>
        </section>
    </div>
</div>
