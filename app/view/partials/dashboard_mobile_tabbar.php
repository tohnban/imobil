<?php
/**
 * Sticky bottom navigation for dashboard on mobile.
 *
 * @var bool $isLimitedPlatformUser
 * @var array<string, mixed>|null $dashboardShellUser
 * @var string $dashboardSection
 * @var string $dashboardView
 * @var bool $dashboardMobileMenuHub
 * @var array<string, mixed> $headerShell
 * @var int $unreadNotifications
 * @var int $unreadChatMessages
 */

use Src\classes\ClassAccess;

$isLimitedPlatformUser = !empty($isLimitedPlatformUser);
$headerShell = is_array($headerShell ?? null) ? $headerShell : [];
$dashboardShellUser = is_array($dashboardShellUser ?? null) ? $dashboardShellUser : null;
$showNegotiationTabs = $dashboardShellUser
    && (!ClassAccess::isAdmin($dashboardShellUser) || ClassAccess::can('requests.manage', $dashboardShellUser));
$showMyPropertiesTab = $dashboardShellUser && empty($dashboardShellUser['is_admin']);
$dashboardSection = (string) ($dashboardSection ?? '');
$dashboardView = (string) ($dashboardView ?? '');
$dashboardMobileMenuHub = !empty($dashboardMobileMenuHub);
$unreadNotifications = (int) ($unreadNotifications ?? ($headerShell['unread_notifications'] ?? 0));
$unreadChatMessages = (int) ($unreadChatMessages ?? ($headerShell['unread_chat_messages'] ?? 0));

$isHomeActive = false;
$isMenuActive = $dashboardMobileMenuHub;
$isRequestsActive = in_array($dashboardSection, ['requests', 'conversations'], true);
$isMyPropertiesActive = $dashboardSection === 'my_properties';
$isProfileActive = in_array($dashboardSection, ['profile', 'account_status'], true);

$tabClass = static function (bool $active): string {
    return 'dashboard-mobile-tabbar-link' . ($active ? ' is-active' : '');
};
?>

<nav class="dashboard-mobile-tabbar" aria-label="Navegação rápida do painel">
    <?php if ($isLimitedPlatformUser): ?>
        <a href="<?php echo DIRPAGE; ?>dashboard/accountStatus" class="<?php echo $tabClass($dashboardSection === 'account_status'); ?>">
            <i class="fa fa-hourglass-half" aria-hidden="true"></i>
            <span>Conta</span>
        </a>
        <a href="<?php echo DIRPAGE; ?>properties" class="<?php echo $tabClass(false); ?>">
            <i class="fa fa-search" aria-hidden="true"></i>
            <span>Imóveis</span>
        </a>
    <?php else: ?>
        <a href="<?php echo DIRPAGE; ?>properties" class="<?php echo $tabClass($isHomeActive); ?>"<?php echo $isHomeActive ? ' aria-current="page"' : ''; ?>>
            <i class="fa fa-home" aria-hidden="true"></i>
            <span>Início</span>
        </a>
        <a href="<?php echo DIRPAGE; ?>dashboard" class="<?php echo $tabClass($isMenuActive); ?> dashboard-mobile-tabbar-link--badged"<?php echo $isMenuActive ? ' aria-current="page"' : ''; ?>>
            <span class="dashboard-mobile-tabbar-icon-wrap">
                <i class="fa fa-th-large" aria-hidden="true"></i>
                <?php if ($unreadNotifications > 0): ?>
                    <span class="dashboard-mobile-tabbar-badge" aria-label="<?php echo (int) $unreadNotifications; ?> notificação(ões) não lida(s)"><?php echo (int) min($unreadNotifications, 99); ?><?php echo $unreadNotifications > 99 ? '+' : ''; ?></span>
                <?php endif; ?>
            </span>
            <span>Painel</span>
        </a>
        <?php if ($showNegotiationTabs): ?>
            <a href="<?php echo DIRPAGE; ?>requests" class="<?php echo $tabClass($isRequestsActive); ?> dashboard-mobile-tabbar-link--badged"<?php echo $isRequestsActive ? ' aria-current="page"' : ''; ?>>
                <span class="dashboard-mobile-tabbar-icon-wrap">
                    <i class="fa fa-inbox" aria-hidden="true"></i>
                    <?php if ($unreadChatMessages > 0): ?>
                        <span class="dashboard-mobile-tabbar-badge" aria-label="<?php echo (int) $unreadChatMessages; ?> mensagem(ns) não lida(s)"><?php echo (int) min($unreadChatMessages, 99); ?><?php echo $unreadChatMessages > 99 ? '+' : ''; ?></span>
                    <?php endif; ?>
                </span>
                <span>Pedidos</span>
            </a>
        <?php endif; ?>
        <?php if ($showMyPropertiesTab): ?>
            <a href="<?php echo DIRPAGE; ?>dashboard/myProperties" class="<?php echo $tabClass($isMyPropertiesActive); ?>"<?php echo $isMyPropertiesActive ? ' aria-current="page"' : ''; ?>>
                <i class="fa fa-building" aria-hidden="true"></i>
                <span>Meus Imóveis</span>
            </a>
        <?php endif; ?>
        <a href="<?php echo DIRPAGE; ?>profile" class="<?php echo $tabClass($isProfileActive); ?>"<?php echo $isProfileActive ? ' aria-current="page"' : ''; ?>>
            <i class="fa fa-user-circle" aria-hidden="true"></i>
            <span>Perfil</span>
        </a>
    <?php endif; ?>
</nav>
