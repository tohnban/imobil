<?php
/**
 * Formulário de pedido de selo (apenas quando elegível).
 *
 * @var array $trustPricing
 * @var float $tbMonthlyFee
 * @var string $tbDefaultTotal
 * @var int $tbDefaultMonths
 */
?>
<p style="margin-bottom:1rem;">Solicite o selo de confiança escolhendo a duração desejada e enviando o comprovativo de pagamento.</p>

<form action="<?php echo DIRPAGE; ?>dashboard/requestTrustedBadge" method="POST"
      enctype="multipart/form-data" class="dashboard-trust-form" id="trust-badge-request-form"
      data-can-submit="1">
    <?php echo Src\classes\ClassCsrf::field(); ?>

    <div class="form-group">
        <label for="trust_badge_months">Duração do selo</label>
        <select id="trust_badge_months" name="trust_badge_months" required
                data-monthly-fee="<?php echo htmlspecialchars(number_format($tbMonthlyFee, 2, '.', '')); ?>">
            <?php foreach (($trustPricing['options'] ?? []) as $option): ?>
                <?php $mo = (int) ($option['months'] ?? 0); ?>
                <option value="<?php echo $mo; ?>"<?php echo ($mo === $tbDefaultMonths) ? ' selected' : ''; ?>>
                    <?php echo $mo; ?> mês<?php echo $mo > 1 ? 'es' : ''; ?>
                    — <?php echo number_format((float) ($option['fee'] ?? 0), 0, ',', '.'); ?> Kz
                </option>
            <?php endforeach; ?>
        </select>
        <small class="dashboard-inline-note">
            <?php echo number_format($tbMonthlyFee, 0, ',', '.'); ?> Kz por mês.
        </small>
    </div>

    <div class="form-group">
        <p class="dashboard-inline-note" style="margin:0;">
            Valor total a pagar: <strong id="trustBadgeTotalValue"><?php echo $tbDefaultTotal; ?> Kz</strong>
        </p>
    </div>

    <div class="form-group">
        <label for="payment_proof">Comprovativo de pagamento <span style="color:#c0392b;">*</span></label>
        <input type="file" id="payment_proof" name="payment_proof" accept="image/*" required>
        <small class="dashboard-inline-note" id="proofFeedback">JPG, PNG ou WebP, até <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?>.</small>
        <div id="proofPreviewWrap" style="display:none;margin-top:10px;">
            <img id="proofPreview" src="" alt="Pré-visualização"
                 style="max-width:240px;max-height:180px;border-radius:6px;border:1px solid var(--border);display:block;">
            <small id="proofPreviewMeta" style="display:block;margin-top:4px;color:var(--color-muted,#666);font-size:.8rem;"></small>
        </div>
    </div>

    <button type="submit" class="btn-primary" id="trust-badge-submit-btn">Solicitar Selo de Confiança</button>
</form>
