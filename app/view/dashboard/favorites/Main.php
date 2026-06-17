<?php
$properties = is_array($properties ?? null) ? $properties : [];
$propertyCount = count($properties);
$feedGroups = $propertyCount > 0
    ? [['key' => 'saved', 'label' => 'Guardados', 'items' => $properties]]
    : [];
?>

<?php
$dashboardPageClass = 'notification-inbox-view favorites-inbox-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$inboxHeroTitle = 'Meus Favoritos';
$inboxHeroMeta = (int) $propertyCount . ' guardado' . ($propertyCount === 1 ? '' : 's');
ob_start();
?>
<a href="<?php echo DIRPAGE; ?>properties" class="notification-inbox-text-btn">Explorar imóveis</a>
<?php
$inboxHeroActionsHtml = ob_get_clean();
include DIRREQ . 'app/view/partials/dashboard_inbox_hero.php';
unset($inboxHeroActionsHtml);
?>

    <div class="notification-inbox-panel favorites-inbox-panel">
        <?php
            $shellHasItems = $propertyCount > 0;
            $shellEmptyIcon = 'fa-heart-o';
            $shellEmptyTitle = 'Nenhum imóvel favorito ainda';
            $shellEmptyMessage = 'Navegue pelos imóveis disponíveis e toque no coração para guardar os que lhe interessam.';
            $feedGroups = $feedGroups;
            $shellFeedItemPartial = 'favorite_property_feed_item.php';
            $shellFeedItemVarName = 'property';
            $shellFeedExtraClass = 'favorite-property-feed-list';
            $shellFeedItemContext = [];
            require DIRREQ . 'app/view/partials/user_feed_shell.php';
        ?>

        <?php if ($propertyCount === 0): ?>
            <div class="notification-inbox-panel-foot">
                <a href="<?php echo DIRPAGE; ?>properties" class="btn-primary">Ver imóveis</a>
            </div>
        <?php endif; ?>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
