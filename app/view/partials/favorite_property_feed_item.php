<?php
/**
 * Favorites feed row for /dashboard/myFavorites.
 *
 * @var array<string, mixed> $property
 */

use App\model\Notification;

$property = is_array($property ?? null) ? $property : [];

$propertyId = (int) ($property['id'] ?? 0);
$propertyUrl = DIRPAGE . 'property/' . $propertyId;
$title = (string) ($property['title'] ?? 'Sem título');
$location = (string) ($property['location'] ?? 'Localização não informada');
$price = number_format((float) ($property['price'] ?? 0), 0, ',', '.') . ' Kz';
$propertyStatus = (string) ($property['status'] ?? 'pendente');
$typeLabel = Src\classes\PropertyTypeHelper::getLabel($property['type'] ?? null);
$purposeLabel = ucfirst(str_replace('_', ' ', (string) ($property['purpose'] ?? 'não informado')));

$statusLabels = [
    'disponivel' => 'Disponível',
    'pendente' => 'Pendente',
    'rejeitado' => 'Rejeitado',
    'suspenso' => 'Suspenso',
    'vendido' => 'Vendido',
    'alugado' => 'Alugado',
];
$statusLabel = $statusLabels[$propertyStatus] ?? ucfirst($propertyStatus);

$propertyImages = json_decode((string) ($property['images'] ?? '[]'), true);
$firstImage = (is_array($propertyImages) && !empty($propertyImages[0])) ? (string) $propertyImages[0] : '';
if ($firstImage !== '') {
    $firstImage = \Src\classes\ClassMediaUrl::propertyImage($firstImage);
}
$coverImage = $firstImage !== '' ? $firstImage : (DIRIMG . 'apt20.avif');

$createdAt = (string) ($property['created_at'] ?? '');
$relativeTime = Notification::relativeTime($createdAt);
$absoluteTime = $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '';

$isUnavailable = in_array($propertyStatus, ['vendido', 'alugado', 'rejeitado', 'suspenso'], true);
$feedTone = $isUnavailable ? 'tone-document' : 'tone-affiliate';
?>

<article class="notification-feed-item favorite-property-feed-item<?php echo $isUnavailable ? ' is-muted' : ''; ?>">
    <a href="<?php echo htmlspecialchars($propertyUrl, ENT_QUOTES, 'UTF-8'); ?>"
       class="notification-feed-link favorite-property-feed-link">
        <span class="notification-feed-icon <?php echo htmlspecialchars($feedTone, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
            <i class="fa fa-heart"></i>
        </span>
        <span class="notification-feed-thumb favorite-property-feed-thumb" aria-hidden="true">
            <img src="<?php echo htmlspecialchars($coverImage, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
        </span>
        <span class="notification-feed-body">
            <span class="notification-feed-text">
                <strong><?php echo htmlspecialchars($title); ?></strong>
                <span class="notification-feed-message"><?php echo htmlspecialchars($location); ?> · <?php echo htmlspecialchars($price); ?></span>
            </span>
            <span class="notification-feed-meta">
                <span class="request-status-badge request-status-<?php echo htmlspecialchars($propertyStatus, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                <span class="notification-feed-dot" aria-hidden="true">·</span>
                <span><?php echo htmlspecialchars($typeLabel); ?></span>
                <span class="notification-feed-dot request-feed-meta-extra" aria-hidden="true">·</span>
                <span class="request-feed-meta-extra"><?php echo htmlspecialchars($purposeLabel); ?></span>
                <?php if ($relativeTime !== '' || $absoluteTime !== ''): ?>
                    <span class="notification-feed-dot request-feed-meta-extra" aria-hidden="true">·</span>
                    <time class="notification-feed-time request-feed-meta-extra"
                          <?php if ($createdAt !== ''): ?>datetime="<?php echo htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
                          <?php if ($absoluteTime !== ''): ?>title="<?php echo htmlspecialchars($absoluteTime, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                        <?php echo htmlspecialchars($relativeTime !== '' ? $relativeTime : $absoluteTime); ?>
                    </time>
                <?php endif; ?>
            </span>
        </span>
    </a>

    <div class="favorite-property-feed-actions">
        <a href="<?php echo htmlspecialchars($propertyUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-secondary favorite-property-feed-view-btn">Ver imóvel</a>
        <form method="POST" action="<?php echo DIRPAGE; ?>property/unfavorite/<?php echo $propertyId; ?>" class="favorite-property-feed-remove-form">
            <?php echo Src\classes\ClassCsrf::field(); ?>
            <button type="submit" class="notification-inbox-text-btn favorite-property-feed-remove-btn" data-confirm="Remover este imóvel dos favoritos?">
                Remover
            </button>
        </form>
    </div>
</article>
