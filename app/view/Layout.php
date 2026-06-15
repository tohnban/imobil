<!DOCTYPE html>
<?php
$cookieConsentValue = Src\classes\ClassCookieConsent::behavioralValue();
$hasCookiePreference = Src\classes\ClassCookieConsent::hasBehavioralPreference();
$currentViewDir = (string) $this->getDir();
$isPropertyModerationView = $currentViewDir === 'property/moderate';
$isDashboardLayout = Src\classes\ClassAuth::check() && (strpos($currentViewDir, 'dashboard/') === 0 || $isPropertyModerationView);
$isSubscriptionView = $currentViewDir === 'dashboard/subscription';
$dashboardSection = '';
if ($isDashboardLayout) {
    $dashboardSection = $isPropertyModerationView ? 'property_moderate' : basename($currentViewDir);
    if (in_array($dashboardSection, ['request_chats', 'request_chat'], true)) {
        $dashboardSection = 'conversations';
    }
    if ($dashboardSection === 'dispute_detail') {
        $dashboardSection = 'disputes';
    }
}
$dashboardView = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
$dashboardMobileMenuHub = $isDashboardLayout && $dashboardSection === 'index' && $dashboardView !== 'overview';
$isDashboardOverviewView = $isDashboardLayout && $dashboardSection === 'index' && $dashboardView === 'overview';
$unreadChatMessagesForTabbar = (int) ($headerShell['unread_chat_messages'] ?? 0);
$unreadNotificationsForTabbar = (int) ($headerShell['unread_notifications'] ?? 0);
$dashboardNotificationsUrl = DIRPAGE . 'notification/inbox';
$dashboardShellUser = Src\classes\ClassAuth::check() ? Src\classes\ClassAuth::user() : null;
$headerShell = isset($headerShell) && is_array($headerShell) ? $headerShell : [];
$dashboardMenuItems = isset($dashboardMenu) && is_array($dashboardMenu) ? $dashboardMenu : [];
$shellFlags = is_array($headerShell['flags'] ?? null) ? $headerShell['flags'] : [];
$isLimitedPlatformUser = !empty($shellFlags['is_limited'])
    || ($dashboardShellUser && Src\classes\ClassAccess::hasLimitedPlatformAccess($dashboardShellUser));
$dashboardPanelHref = $isLimitedPlatformUser
    ? DIRPAGE . 'dashboard/accountStatus'
    : DIRPAGE . 'dashboard?view=overview';
$dashboardMenuHubHref = DIRPAGE . 'dashboard';
$isAuthLayout = strpos($currentViewDir, 'auth/') === 0;

$rawSuccess = isset($_GET['success']) ? trim((string) $_GET['success']) : '';
$rawError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';

$flashSuccess = null;
$flashError = null;

if ($rawSuccess !== '') {
    $flashSuccess = ($rawSuccess === '1') ? 'Operação realizada com sucesso.' : $rawSuccess;
}

if ($rawError !== '') {
    $flashError = ($rawError === '1') ? 'Não foi possível concluir a operação.' : $rawError;
}

$overdueCommission = is_array($headerShell['overdue_commission'] ?? null) ? $headerShell['overdue_commission'] : [];
$showOverdueCommissionBanner = !empty($overdueCommission['show_banner']);
$overdueCommissionBannerMessage = (string) ($overdueCommission['message'] ?? '');
$overdueCommissionBannerAction = (string) ($overdueCommission['action_label'] ?? 'Pagar comissões');
$overdueCommissionBannerHref = (string) ($overdueCommission['action_href'] ?? DIRPAGE . 'dashboard/commissionPayments');
?>
<html lang="<?php echo Src\classes\ClassSEO::SITE_LANGUAGE; ?>">
<head>
    <meta charset="utf-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <!-- Basic Meta Tags -->
    <meta name="author" content="Antonio Nzage Banduenga">
    <meta name="description" content="<?php echo Src\classes\ClassSEO::sanitizeDescription($this->getDescription());?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($this->getKeywords(), ENT_QUOTES, 'UTF-8');?>">
    <meta name="language" content="<?php echo Src\classes\ClassSEO::SITE_LANGUAGE; ?>">
    <?php
    $robotsMeta = $this->getRobotsMeta() !== ''
        ? $this->getRobotsMeta()
        : Src\classes\ClassSEO::robotsForViewDir($currentViewDir);
    $ogPageUrl = $this->getCanonical() !== ''
        ? $this->getCanonical()
        : Src\classes\ClassSEO::getCanonicalUrl();
    ?>
    <meta name="robots" content="<?php echo htmlspecialchars($robotsMeta, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (Src\classes\ClassAuth::check()): ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(Src\classes\ClassCsrf::get(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <title><?php echo Src\classes\ClassSEO::sanitizeTitle($this->getTitle()); ?></title>

    <!-- Favicon -->
    <link rel="icon" href="<?php echo htmlspecialchars(Src\classes\ClassSEO::faviconIcoUrl(), ENT_QUOTES, 'UTF-8'); ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(Src\classes\ClassSEO::faviconPngUrl(), ENT_QUOTES, 'UTF-8'); ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(Src\classes\ClassSEO::appleTouchIconUrl(), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Canonical URL -->
    <?php if ($this->getCanonical()): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($this->getCanonical(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    
    <!-- Open Graph Tags -->
    <meta property="og:type" content="<?php echo htmlspecialchars($this->getOgType(), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo Src\classes\ClassSEO::sanitizeTitle($this->getOgTitle()); ?>">
    <meta property="og:description" content="<?php echo Src\classes\ClassSEO::sanitizeDescription($this->getOgDescription()); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($ogPageUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo Src\classes\ClassSEO::SITE_NAME; ?>">
    <meta property="og:locale" content="<?php echo Src\classes\ClassSEO::SITE_LOCALE; ?>">
    <?php if ($this->getOgImage()): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($this->getOgImage(), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    
    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo Src\classes\ClassSEO::sanitizeTitle($this->getOgTitle()); ?>">
    <meta name="twitter:description" content="<?php echo Src\classes\ClassSEO::sanitizeDescription($this->getOgDescription()); ?>">
    <?php if ($this->getOgImage()): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($this->getOgImage(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    
    <!-- Structured Data (JSON-LD) -->
    <?php if ($this->getStructuredData()): ?>
    <script type="application/ld+json">
    <?php echo json_encode($this->getStructuredData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
    <?php endif; ?>

    <link rel="preconnect" href="https://i.ytimg.com" crossorigin>
    <link rel="preconnect" href="https://www.youtube-nocookie.com">
    <link rel="stylesheet" href="<?php echo DIRCSS.'Style.css?v=20260615b'?>">
    <link rel="stylesheet" href="<?php echo DIRPAGE; ?>public/vendor/font-awesome/css/font-awesome.min.css?v=4.7.0">
</head>
<body class="<?php
    $bodyClasses = $isDashboardLayout ? ['dashboard-body', 'dashboard-section-' . htmlspecialchars($dashboardSection)] : ['public-body'];
    if ($isAuthLayout) {
        $bodyClasses[] = 'auth-body';
    }
    if ($dashboardMobileMenuHub) {
        $bodyClasses[] = 'dashboard-mobile-menu-hub';
    }
    if ($isDashboardOverviewView) {
        $bodyClasses[] = 'dashboard-overview-view';
    }
    if ($isDashboardLayout) {
        $bodyClasses[] = 'dashboard-has-mobile-tabbar';
    }
    echo implode(' ', $bodyClasses);
?>" data-cookie-behavioral="<?php echo htmlspecialchars($cookieConsentValue !== '' ? $cookieConsentValue : 'unknown'); ?>">
<?php if ($isAuthLayout): ?>
<header class="auth-minimal-header">
    <div class="auth-minimal-header-inner">
        <a class="logo-name" href="<?php echo DIRPAGE; ?>"><span class="brand-imobil">Imobil</span><span class="brand-facil"> Fácil</span></a>
        <nav class="auth-minimal-nav" aria-label="Atalhos de autenticação">
            <a href="<?php echo DIRPAGE; ?>">Início</a>
            <?php if ($currentViewDir === 'auth/login'): ?>
                <a href="<?php echo DIRPAGE; ?>register">Criar conta</a>
            <?php elseif ($currentViewDir === 'auth/register'): ?>
                <a href="<?php echo DIRPAGE; ?>login">Entrar</a>
            <?php else: ?>
                <a href="<?php echo DIRPAGE; ?>login">Entrar</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php else: ?>
<header class="site-header">
  <div class="header-eyebrow">
      <div class="container header-eyebrow-grid">
          <span>Encontre, anuncie e negocie imóveis de forma simples e segura</span>
          <div class="header-eyebrow-tags">
              <span>Imóveis verificados</span>
              <span>Negociação segura</span>
              <span>Pague Fácil</span>
          </div>
      </div>
  </div>
  <div class="container header-grid">
      <div class="brand">
          <a class="logo-name" href="<?php echo DIRPAGE; ?>"><span class="brand-imobil">Imobil</span><span class="brand-facil"> Fácil</span></a>
          <p class="brand-subtitle">simples para anunciar, seguro para negociar</p>
      </div>

      <button class="menu-button" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="menu">
          <i class="fa fa-bars"></i>
      </button>

      <nav class="nav-links" id="menu">
          <a href="<?php echo DIRPAGE; ?>">Home</a>
          <a href="<?php echo DIRPAGE; ?>properties">Imóveis</a>
          <a href="<?php echo DIRPAGE; ?>featured">Destaques</a>
          <?php if (Src\classes\ClassAuth::check()): ?>
            <a href="<?php echo $dashboardPanelHref; ?>">Painel</a>
          <?php endif; ?>
      </nav>

      <div class="header-actions">
          <?php if (Src\classes\ClassAuth::check()): ?>
              <?php
                  $user = Src\classes\ClassAuth::user();
                  $unreadNotifications = (int) ($headerShell['unread_notifications'] ?? 0);
                  $unreadChatMessages = (int) ($headerShell['unread_chat_messages'] ?? 0);
                  $headerNotifications = is_array($headerShell['notifications_preview'] ?? null) ? $headerShell['notifications_preview'] : [];
                  $favoriteCount = (int) ($headerShell['favorite_count'] ?? 0);
                  $showFavoritesIcon = !empty($shellFlags['show_favorites_icon']);
                  $showChatIcon = !empty($shellFlags['show_chat_icon']);
                  $showNotificationsMenu = !empty($shellFlags['show_notifications_menu']);
              ?>
              <?php if ($showFavoritesIcon): ?>
              <a href="<?php echo DIRPAGE; ?>dashboard/myFavorites"
                 class="header-icon-link header-icon-link--favorites"
                 title="<?php echo $favoriteCount > 0 ? 'Favoritos (' . (int) $favoriteCount . ')' : 'Favoritos'; ?>"
                 aria-label="<?php echo $favoriteCount > 0 ? 'Favoritos, ' . (int) $favoriteCount . ' imóveis guardados' : 'Favoritos'; ?>">
                  <i class="fa fa-heart"></i>
                  <?php if ($favoriteCount > 0): ?>
                      <span class="notification-badge header-favorites-badge"><?php echo (int) min($favoriteCount, 99); ?><?php echo $favoriteCount > 99 ? '+' : ''; ?></span>
                  <?php endif; ?>
              </a>
              <?php endif; ?>
              <?php if ($showChatIcon): ?>
              <a href="<?php echo DIRPAGE; ?>dashboard/requestChats" class="header-icon-link header-icon-link--chat" title="Conversas" aria-label="Conversas">
                  <i class="fa fa-comments"></i>
                  <?php if ($unreadChatMessages > 0): ?>
                      <span class="notification-badge"><?php echo (int) min($unreadChatMessages, 99); ?><?php echo $unreadChatMessages > 99 ? '+' : ''; ?></span>
                  <?php endif; ?>
              </a>
              <?php endif; ?>
              <div id="notificationAriaLive" class="notification-aria-live" aria-live="polite" aria-atomic="true"></div>
              <?php if ($showNotificationsMenu): ?>
              <div class="notification-menu" id="notificationMenu" data-feed-url="<?php echo DIRPAGE; ?>dashboard/notificationsFeed" data-dashboard-url="<?php echo $dashboardNotificationsUrl; ?>" data-archive-url="<?php echo DIRPAGE . 'notification/archiveItem'; ?>" data-unread-initial="<?php echo (int) $unreadNotifications; ?>">
                  <button type="button" class="notification-link notification-trigger" title="Notificações" aria-label="Notificações" aria-expanded="false" aria-controls="notificationDropdown">
                      <i class="fa fa-bell"></i>
                      <?php if ($unreadNotifications > 0): ?>
                          <span class="notification-badge" id="notificationBadge"><?php echo (int) min($unreadNotifications, 99); ?><?php echo $unreadNotifications > 99 ? '+' : ''; ?></span>
                      <?php endif; ?>
                  </button>

                  <div class="notification-dropdown-backdrop" id="notificationDropdownBackdrop" hidden aria-hidden="true"></div>

                  <div class="notification-dropdown" id="notificationDropdown" role="dialog" aria-label="Notificações recentes" aria-modal="true">
                      <div class="notification-sheet-handle" aria-hidden="true"></div>
                      <div class="notification-dropdown-head">
                          <div class="notification-dropdown-title-row">
                              <strong>Notificações</strong>
                              <button type="button" class="notification-dropdown-close" id="notificationDropdownClose" aria-label="Fechar notificações">
                                  <i class="fa fa-times" aria-hidden="true"></i>
                              </button>
                          </div>
                          <div class="notification-dropdown-toolbar">
                              <span class="notification-dropdown-unread" id="notificationUnreadLabel"<?php echo $unreadNotifications > 0 ? '' : ' hidden'; ?>><?php echo (int) $unreadNotifications; ?> não lidas</span>
                              <form action="<?php echo DIRPAGE; ?>dashboard/markNotificationsRead" method="POST" class="notification-read-form notification-read-form--inline" id="notificationReadForm"<?php echo $unreadNotifications > 0 ? '' : ' hidden'; ?>>
                                  <?php echo Src\classes\ClassCsrf::field(); ?>
                                  <button type="submit" class="notification-read-btn notification-read-btn--text">Marcar todas como lidas</button>
                              </form>
                          </div>
                      </div>

                      <?php if (!empty($headerNotifications)): ?>
                          <div class="notification-list notification-feed notification-feed--dropdown" id="notificationList">
                              <?php foreach ($headerNotifications as $notification): ?>
                                  <?php
                                      $compact = true;
                                      $showMenu = false;
                                      require __DIR__ . '/partials/notification_feed_item.php';
                                  ?>
                              <?php endforeach; ?>
                          </div>
                      <?php else: ?>
                          <div class="notification-list" id="notificationList" hidden></div>
                          <p class="notification-empty" id="notificationEmpty">Sem notificações no momento.</p>
                      <?php endif; ?>

                      <?php if (!empty($headerNotifications)): ?>
                          <p class="notification-empty" id="notificationEmpty" hidden>Sem notificações no momento.</p>
                      <?php endif; ?>

                      <a href="<?php echo $dashboardNotificationsUrl; ?>" class="notification-see-all" id="notificationSeeAll">Ver todas</a>
                  </div>
              </div>
              <?php endif; ?>
              <a href="<?php echo $isLimitedPlatformUser ? DIRPAGE . 'dashboard/accountStatus' : DIRPAGE . 'profile'; ?>" class="profile" title="<?php echo $isLimitedPlatformUser ? 'A minha conta' : 'Meu Perfil'; ?>">
                  <div class="profile-img">
                      <?php if (!empty($user['profile_photo'])): ?>
                          <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>">
                      <?php else: ?>
                          <span class="profile-fallback"><?php echo strtoupper(substr(trim((string) ($user['name'] ?? 'U')), 0, 1)); ?></span>
                      <?php endif; ?>
                  </div>
              </a>
          <?php else: ?>
              <a class="btn-secondary" href="<?php echo DIRPAGE; ?>login">Login</a>
              <a class="btn-primary" href="<?php echo DIRPAGE; ?>register">Registrar</a>
          <?php endif; ?>
      </div>
  </div>
</header>
<?php endif; ?>

<?php if (!$isAuthLayout && $showOverdueCommissionBanner): ?>
    <div class="site-global-alert site-global-alert--commission-overdue" role="alert" aria-live="polite">
        <div class="container site-global-alert-inner">
            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
            <p class="site-global-alert-text">
                <?php echo htmlspecialchars($overdueCommissionBannerMessage); ?>
            </p>
            <a href="<?php echo htmlspecialchars($overdueCommissionBannerHref); ?>" class="site-global-alert-action"><?php echo htmlspecialchars($overdueCommissionBannerAction); ?></a>
        </div>
    </div>
<?php endif; ?>

<main class="<?php echo $isDashboardLayout ? 'dashboard-main-shell' : ''; ?>">
<?php if (!$isAuthLayout && !$isSubscriptionView && ($flashSuccess !== null || $flashError !== null)): ?>
    <div class="<?php echo $isDashboardLayout ? 'dashboard-shell-alerts' : 'container'; ?>" style="margin-top:16px;">
        <?php if ($flashSuccess !== null): ?>
            <div class="auth-message auth-message-success"><?php echo htmlspecialchars($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if ($flashError !== null): ?>
            <div class="auth-message auth-message-error"><?php echo htmlspecialchars($flashError); ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($isDashboardLayout && $dashboardShellUser): ?>
    <?php
        $sidebarName = trim((string) ($dashboardShellUser['name'] ?? 'Utilizador'));
        $sidebarNameParts = preg_split('/\s+/', $sidebarName, -1, PREG_SPLIT_NO_EMPTY);
        $sidebarDisplayName = !empty($sidebarNameParts) ? implode(' ', array_slice($sidebarNameParts, 0, 2)) : $sidebarName;
        $sidebarRoleLabel = Src\classes\ClassAccess::roleLabel($dashboardShellUser);
    ?>
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar">
            <div class="dashboard-sidebar-card">
                <div class="dashboard-sidebar-avatar">
                    <?php echo strtoupper(substr(trim((string) ($dashboardShellUser['name'] ?? 'U')), 0, 1)); ?>
                </div>
                <div class="dashboard-sidebar-meta">
                    <strong><?php echo htmlspecialchars($sidebarDisplayName); ?></strong>
                    <span><?php echo htmlspecialchars($sidebarRoleLabel); ?></span>
                </div>
            </div>

            <div class="dashboard-mobile-hub-intro">
                <h2>O seu painel</h2>
                <p>Escolha uma área para continuar</p>
            </div>

            <div class="dashboard-sidebar-panel">
                <div class="dashboard-sidebar-kicker">Painel</div>
                <nav class="dashboard-side-nav">
                    <?php foreach ($dashboardMenuItems as $dashboardItem): ?>
                        <?php
                            $isDashboardItemActive = $dashboardSection === $dashboardItem['key'];
                            if ($dashboardItem['key'] === 'index') {
                                $isDashboardItemActive = $dashboardSection === 'index' && $dashboardView === 'overview';
                            }
                        ?>
                        <a href="<?php echo $dashboardItem['href']; ?>" class="dashboard-side-link <?php echo $isDashboardItemActive ? 'is-active' : ''; ?>">
                            <i class="fa <?php echo htmlspecialchars($dashboardItem['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($dashboardItem['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <?php if (empty($dashboardShellUser['is_admin']) && !$isLimitedPlatformUser): ?>
                <a href="<?php echo DIRPAGE; ?>property/create" class="dashboard-sidebar-cta">
                    <i class="fa fa-plus-circle"></i>
                    <span>Novo imóvel</span>
                </a>
            <?php endif; ?>

            <a href="<?php echo DIRPAGE; ?>logout" class="dashboard-side-logout">
                <i class="fa fa-sign-out"></i>
                <span>Sair</span>
            </a>
        </aside>

        <section class="dashboard-shell-content">
            <?php if (!$dashboardMobileMenuHub): ?>
                <nav class="dashboard-mobile-nav" aria-label="Navegação do painel">
                    <a href="<?php echo htmlspecialchars($dashboardMenuHubHref, ENT_QUOTES, 'UTF-8'); ?>" class="dashboard-mobile-nav-back">
                        <i class="fa fa-arrow-left" aria-hidden="true"></i>
                        <span>Menu do painel</span>
                    </a>
                </nav>
            <?php endif; ?>
            <?php $this->addMain();?>
        </section>
    </div>
    <?php
        $unreadChatMessages = $unreadChatMessagesForTabbar;
        $unreadNotifications = $unreadNotificationsForTabbar;
        require DIRREQ . 'app/view/partials/dashboard_mobile_tabbar.php';
    ?>
<?php else: ?>
<?php $this->addMain();?>
<?php endif; ?>
</main>  


<?php if ($isAuthLayout): ?>
<footer class="auth-minimal-footer">
    <div class="auth-minimal-footer-inner">
        <a href="<?php echo DIRPAGE; ?>">Voltar ao início</a>
        <span>© <?php echo date('Y'); ?> Imobil Fácil</span>
    </div>
</footer>
<?php else: ?>
 <footer class="site-footer">
    <div class="container-footer">
        <div class="footer">
            <div class="footer-brand-block">
                <span class="footer-kicker">Plataforma imobiliária</span>
                <strong><span class="brand-imobil">Imobil</span><span class="brand-facil"> Fácil</span></strong>
                <p>Encontre imóveis reais de forma simples e segura. Com a confiança da Pague Fácil.</p>
            </div>

            <div class="footer-nav-area">
                <div class="footer-column" data-footer-accordion>
                    <button type="button" class="footer-column-toggle" aria-expanded="false" aria-controls="footer-panel-explorar">
                        <span class="footer-column-title">Explorar</span>
                        <i class="fa fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="footer-links footer-column-panel is-collapsed" id="footer-panel-explorar">
                        <a href="<?php echo DIRPAGE; ?>">Home</a>
                        <a href="<?php echo DIRPAGE; ?>properties">Imoveis</a>
                        <a href="<?php echo DIRPAGE; ?>featured">Destaques</a>
                    </div>
                </div>

                <div class="footer-column" data-footer-accordion>
                    <button type="button" class="footer-column-toggle" aria-expanded="false" aria-controls="footer-panel-conta">
                        <span class="footer-column-title">Conta</span>
                        <i class="fa fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="footer-links footer-column-panel is-collapsed" id="footer-panel-conta">
                        <?php if (Src\classes\ClassAuth::check()): ?>
                            <a href="<?php echo $dashboardPanelHref; ?>">Painel</a>
                            <a href="<?php echo $isLimitedPlatformUser ? DIRPAGE . 'dashboard/accountStatus' : DIRPAGE . 'profile'; ?>"><?php echo $isLimitedPlatformUser ? 'A minha conta' : 'Perfil'; ?></a>
                            <a href="<?php echo DIRPAGE; ?>logout">Sair</a>
                        <?php else: ?>
                            <a href="<?php echo DIRPAGE; ?>login">Login</a>
                            <a href="<?php echo DIRPAGE; ?>register">Registrar</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="footer-column" data-footer-accordion>
                    <button type="button" class="footer-column-toggle" aria-expanded="false" aria-controls="footer-panel-legal">
                        <span class="footer-column-title">Base legal</span>
                        <i class="fa fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="footer-links footer-column-panel is-collapsed" id="footer-panel-legal">
                        <a href="<?php echo DIRPAGE; ?>cookies">Política de Cookies</a>
                        <a href="<?php echo DIRPAGE; ?>privacidade">Política de Privacidade</a>
                        <a href="<?php echo DIRPAGE; ?>termos">Termos e Condições</a>
                        <a href="#" data-open-cookie-consent="1">Gerir Cookies</a>
                        <?php if (Src\classes\ClassAuth::check()): ?>
                            <a href="<?php echo DIRPAGE; ?>profile">Configurações</a>
                        <?php else: ?>
                            <a href="<?php echo DIRPAGE; ?>login">Configurações</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom-bar">
            <div class="copyright">© 2026 Imobil Fácil — Plataforma imobiliária operada pela Pague Fácil, Comércio e Serviços, LDA</div>
            <div class="footer-bottom-note">Simplicidade · Confiança · Transparência</div>
        </div>
    </div>
</footer>
<?php endif; ?>

<section id="cookieConsentBanner" class="cookie-consent<?php echo $hasCookiePreference ? ' is-hidden' : ''; ?>" role="dialog" aria-live="polite" aria-label="Consentimento de cookies">
    <div class="cookie-consent-card">
        <div class="cookie-consent-copy">
            <strong>Preferencias de cookies</strong>
            <p>
                Utilizamos cookies essenciais para funcionamento da plataforma e, com o seu consentimento,
                usamos informacoes de navegacao para melhorar a sua experiencia no sistema.
                Saiba mais em <a href="<?php echo DIRPAGE; ?>cookies">Politica de Cookies</a>.
            </p>
        </div>
        <div class="cookie-consent-actions">
            <button type="button" class="btn-secondary" id="cookieRejectBtn">Rejeitar personalizacao</button>
            <button type="button" class="btn-primary" id="cookieAcceptBtn">Aceitar personalizacao</button>
        </div>
    </div>
</section>
<script src="<?php echo DIRJS.'upload-limits.js?v=20260612a'?>"></script>
<script src="<?php echo DIRJS.'script.js?v=20260615b'?>"></script>
</body>
</html>             