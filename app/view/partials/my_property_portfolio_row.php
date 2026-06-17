<?php
/**
 * Portfolio row for /dashboard/myProperties.
 *
 * @var array<string, mixed> $property
 * @var array<string, bool> $pendingBoostIds
 * @var array<string, string> $statusClassMap
 * @var array<string, string> $propertyStatusLabels
 * @var int $propertyDeletionGraceDays
 */

$property = is_array($property ?? null) ? $property : [];
$pendingBoostIds = is_array($pendingBoostIds ?? null) ? $pendingBoostIds : [];
$statusClassMap = is_array($statusClassMap ?? null) ? $statusClassMap : [];
$propertyStatusLabels = is_array($propertyStatusLabels ?? null) ? $propertyStatusLabels : [];

$propertyId = (int) ($property['id'] ?? 0);
$propertyStatus = (string) ($property['status'] ?? 'pendente');
$statusClass = $statusClassMap[$propertyStatus] ?? 'dashboard-chip';
$propertyStatusLabel = $propertyStatusLabels[$propertyStatus] ?? ucfirst(str_replace('_', ' ', $propertyStatus));

$propertyImages = json_decode((string) ($property['images'] ?? '[]'), true);
$propertyFirstImage = (is_array($propertyImages) && !empty($propertyImages[0])) ? (string) $propertyImages[0] : '';
if ($propertyFirstImage !== '') {
    $propertyFirstImage = \Src\classes\ClassMediaUrl::propertyImage($propertyFirstImage);
}
$propertyCover = $propertyFirstImage !== '' ? $propertyFirstImage : (DIRIMG . 'apt20.avif');

$title = (string) ($property['title'] ?? 'Sem título');
$location = (string) ($property['location'] ?? 'Localização não informada');
$priceLabel = number_format((float) ($property['price'] ?? 0), 0, ',', '.') . ' Kz';
$purposeLabel = ucfirst(str_replace('_', ' ', (string) ($property['purpose'] ?? 'nao informado')));
$propertyPurpose = (string) ($property['purpose'] ?? '');
$isForSale = $propertyPurpose === 'venda';
$isForRent = str_starts_with($propertyPurpose, 'aluguer');
$typeLabel = Src\classes\PropertyTypeHelper::getLabel($property['type'] ?? null);
$bedrooms = (int) ($property['bedrooms'] ?? 0);
$bathrooms = (int) ($property['bathrooms'] ?? 0);

$hasPendingBoost = !empty($pendingBoostIds[$propertyId]);
$isAvailable = $propertyStatus === 'disponivel';
$isFeatured = !empty($property['featured']);
$isPendingDeletion = $propertyStatus === 'eliminado';
$canDeleteProperty = \App\model\Property::canRequestDeletion($property);
$deletionScheduledAt = strtotime((string) ($property['deletion_scheduled_at'] ?? ''));
$deletionDaysRemaining = $isPendingDeletion && $deletionScheduledAt > time()
    ? (int) ceil(($deletionScheduledAt - time()) / 86400)
    : 0;
$openNegotiations = ($canDeleteProperty || $isPendingDeletion)
    ? App\model\Request::countOpenNegotiationsForProperty($propertyId)
    : 0;

$searchBlob = strtolower($title . ' ' . $location . ' ' . $typeLabel . ' ' . $purposeLabel);
$propertyUrl = DIRPAGE . 'property/' . $propertyId;
$editUrl = DIRPAGE . 'property/edit/' . $propertyId;
$hasExtraMenu = $isAvailable && ($isForSale || $isForRent || (!$hasPendingBoost && !$isFeatured)) || $canDeleteProperty;
?>

<article class="notification-feed-item my-properties-row my-properties-card<?php echo $isPendingDeletion ? ' is-pending-deletion' : ''; ?>"
         data-status="<?php echo htmlspecialchars($propertyStatus, ENT_QUOTES, 'UTF-8'); ?>"
         data-property-id="<?php echo $propertyId; ?>"
         data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="my-properties-row-main">
        <a href="<?php echo htmlspecialchars($propertyUrl, ENT_QUOTES, 'UTF-8'); ?>"
           class="notification-feed-link my-properties-row-link">
            <span class="notification-feed-thumb my-properties-row-thumb" aria-hidden="true">
                <img src="<?php echo htmlspecialchars($propertyCover, ENT_QUOTES, 'UTF-8'); ?>"
                     alt=""
                     loading="lazy">
            </span>
            <span class="notification-feed-body my-properties-row-body">
                <span class="notification-feed-text">
                    <strong><?php echo htmlspecialchars($title); ?></strong>
                    <span class="notification-feed-message">
                        <?php echo htmlspecialchars($location); ?>
                        <span class="my-properties-price-mobile"> · <?php echo htmlspecialchars($priceLabel); ?></span>
                    </span>
                </span>
                <span class="notification-feed-meta my-properties-row-meta">
                    <span class="dashboard-chip my-properties-row-status <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($propertyStatusLabel); ?>
                    </span>
                    <?php if ($isFeatured): ?>
                        <span class="dashboard-chip dashboard-chip-warning">★ Destaque</span>
                    <?php elseif ($hasPendingBoost): ?>
                        <span class="dashboard-chip dashboard-chip-warning">Destaque pendente</span>
                    <?php endif; ?>
                    <span class="notification-feed-dot" aria-hidden="true">·</span>
                    <span><?php echo htmlspecialchars($typeLabel); ?></span>
                    <span class="notification-feed-dot" aria-hidden="true">·</span>
                    <span><?php echo htmlspecialchars($purposeLabel); ?></span>
                    <?php if ($bedrooms > 0 || $bathrooms > 0): ?>
                        <span class="notification-feed-dot my-properties-row-meta-extra" aria-hidden="true">·</span>
                        <span class="my-properties-row-meta-extra">
                            <?php if ($bedrooms > 0): ?><?php echo $bedrooms; ?> q<?php echo $bedrooms === 1 ? '' : 's'; ?><?php endif; ?>
                            <?php if ($bedrooms > 0 && $bathrooms > 0): ?> · <?php endif; ?>
                            <?php if ($bathrooms > 0): ?><?php echo $bathrooms; ?> wc<?php endif; ?>
                        </span>
                    <?php endif; ?>
                </span>
            </span>
            <span class="my-properties-row-price" aria-label="Preço"><?php echo htmlspecialchars($priceLabel); ?></span>
        </a>

        <div class="my-properties-row-actions" role="group" aria-label="Acções do imóvel">
            <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn-secondary my-properties-row-btn"
               title="Editar anúncio">Editar</a>

            <?php if ($hasExtraMenu): ?>
            <div class="notification-feed-menu-wrap">
                <button type="button"
                        class="notification-feed-menu-btn"
                        aria-label="Mais opções para <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-expanded="false"
                        aria-haspopup="true">
                    <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                </button>
                <div class="notification-feed-menu" hidden>
                    <a href="<?php echo htmlspecialchars($propertyUrl, ENT_QUOTES, 'UTF-8'); ?>">Ver anúncio público</a>
                    <?php if ($isAvailable && $isForSale): ?>
                    <form method="POST" action="<?php echo DIRPAGE; ?>property/setStatus/<?php echo $propertyId; ?>" class="request-actions ajax-form-inline" data-ajax-action="property-set-status">
                        <?php echo Src\classes\ClassCsrf::field(); ?>
                        <input type="hidden" name="new_status" value="vendido">
                        <button type="submit" data-confirm="Marcar este imóvel como vendido?">Marcar como vendido</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($isAvailable && $isForRent): ?>
                    <form method="POST" action="<?php echo DIRPAGE; ?>property/setStatus/<?php echo $propertyId; ?>" class="request-actions ajax-form-inline" data-ajax-action="property-set-status">
                        <?php echo Src\classes\ClassCsrf::field(); ?>
                        <input type="hidden" name="new_status" value="alugado">
                        <button type="submit" data-confirm="Marcar este imóvel como alugado?">Marcar como alugado</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($isAvailable && !$hasPendingBoost && !$isFeatured): ?>
                        <a href="#boost-section">Solicitar destaque</a>
                    <?php endif; ?>
                    <?php if ($canDeleteProperty): ?>
                        <button type="button"
                                class="my-properties-open-delete-drawer"
                                data-drawer-target="my-properties-delete-<?php echo $propertyId; ?>">
                            Eliminar anúncio…
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars($propertyUrl, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn-primary my-properties-row-btn"
               title="Ver anúncio">Ver</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isPendingDeletion): ?>
        <div class="my-properties-row-drawer is-visible">
            <div class="my-properties-status-banner" role="status">
                <i class="fa fa-clock-o" aria-hidden="true"></i>
                <div>
                    <strong>Em período de conformidade</strong>
                    <p>Indisponível ao público; visível em conversas como eliminado.
                    <?php if ($deletionDaysRemaining > 0): ?>
                        Remoção definitiva em <strong><?php echo (int) $deletionDaysRemaining; ?></strong> dia<?php echo $deletionDaysRemaining === 1 ? '' : 's'; ?>.
                    <?php endif; ?></p>
                </div>
            </div>
            <form method="POST" action="<?php echo DIRPAGE; ?>property/cancelDeletion/<?php echo $propertyId; ?>" class="request-actions ajax-form-inline">
                <?php echo Src\classes\ClassCsrf::field(); ?>
                <button type="submit" class="btn-primary" data-confirm="Cancelar a eliminação e restaurar o anúncio?">Cancelar eliminação</button>
            </form>
        </div>
    <?php elseif ($canDeleteProperty): ?>
        <div class="my-properties-row-drawer" id="my-properties-delete-<?php echo $propertyId; ?>" hidden>
            <?php if ($openNegotiations > 0): ?>
                <p class="dashboard-inline-note my-properties-deletion-note">
                    <strong><?php echo (int) $openNegotiations; ?></strong> negociação<?php echo $openNegotiations === 1 ? '' : 'ões'; ?> em curso — os chats mantêm-se acessíveis durante <strong><?php echo (int) $propertyDeletionGraceDays; ?> dias</strong>.
                </p>
            <?php endif; ?>
            <form method="POST" action="<?php echo DIRPAGE; ?>property/requestDeletion/<?php echo $propertyId; ?>" class="request-actions my-properties-delete-form ajax-form-inline">
                <?php echo Src\classes\ClassCsrf::field(); ?>
                <div class="form-group">
                    <label class="profile-deletion-confirm">
                        <input type="checkbox" name="confirm_property_deletion" value="1" required>
                        <span>Compreendo que o anúncio deixa de estar público e que conversas em curso permanecem visíveis como eliminado.</span>
                    </label>
                </div>
                <div class="form-group">
                    <label for="delete_password_<?php echo $propertyId; ?>">Palavra-passe actual</label>
                    <input type="password" id="delete_password_<?php echo $propertyId; ?>" name="current_password" required autocomplete="current-password" class="my-properties-danger-input">
                </div>
                <div class="my-properties-row-drawer-actions">
                    <button type="button" class="btn-secondary my-properties-close-delete-drawer" data-drawer-target="my-properties-delete-<?php echo $propertyId; ?>">Cancelar</button>
                    <button type="submit" class="btn-secondary my-properties-delete-btn" data-confirm="Eliminar este imóvel? Fica indisponível ao público durante <?php echo (int) $propertyDeletionGraceDays; ?> dias.">Confirmar eliminação</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</article>
