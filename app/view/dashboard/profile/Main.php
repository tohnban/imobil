<div class="container dashboard-view dashboard-profile-view">
    <?php $isAdminProfile = !empty($isAdminProfile); ?>
    <section class="dashboard-view-hero compact">
        <div>
            <span class="dashboard-hero-kicker">Conta</span>
            <h1><?php echo $isAdminProfile ? 'Segurança da conta' : 'Meu Perfil'; ?></h1>
            <p><?php echo $isAdminProfile
                ? 'Actualize a sua foto e palavra-passe quando precisar.'
                : 'Atualize dados, segurança e estado de confiança da sua conta.'; ?></p>
        </div>
    </section>

    <?php if ($isAdminProfile): ?>
        <?php $accountRoleLabel = Src\classes\ClassAccess::roleLabel($user ?? null); ?>
        <div class="dashboard-profile-layout dashboard-profile-layout-admin">
            <aside class="dashboard-home-side dashboard-profile-avatar-panel">
                <div class="dashboard-module-card">
                    <div class="dashboard-module-head compact">
                        <div>
                            <span class="dashboard-module-kicker">Equipa</span>
                            <h3>A sua conta</h3>
                        </div>
                    </div>
                    <div class="profile-photo-section dashboard-margin-bottom-reset">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img src="<?php echo htmlspecialchars(\Src\classes\ClassMediaUrl::profilePhoto($user['profile_photo'])); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" class="profile-photo-display">
                        <?php else: ?>
                            <div class="profile-photo-placeholder">
                                <i class="fa fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <h4 class="profile-side-name"><?php echo htmlspecialchars(Src\classes\UserDisplay::publicLabel($user)); ?></h4>
                        <?php if (!empty($user['username'])): ?>
                            <p class="profile-side-handle">@<?php echo htmlspecialchars((string) $user['username']); ?></p>
                        <?php endif; ?>
                        <p class="profile-side-role"><?php echo htmlspecialchars($accountRoleLabel); ?></p>
                    </div>
                </div>
            </aside>

            <div class="dashboard-profile-stack">
                <div class="dashboard-module-card dashboard-form-shell">
                    <div class="dashboard-module-head compact">
                        <div>
                            <span class="dashboard-module-kicker">Conta</span>
                            <h3>Foto e palavra-passe</h3>
                        </div>
                    </div>
                    <form action="<?php echo DIRPAGE; ?>profile/update" method="POST" enctype="multipart/form-data" class="profile-update-form profile-update-form-admin">
                        <?php echo Src\classes\ClassCsrf::field(); ?>
                        <fieldset class="profile-form-section">
                            <legend class="profile-form-section-title">Foto de perfil</legend>
                            <div class="form-group">
                                <label for="profile_photo">Nova foto (opcional)</label>
                                <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="dashboard-inline-note">JPG, PNG, GIF ou WebP, até <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?>.</small>
                            </div>
                        </fieldset>
                        <fieldset class="profile-form-section">
                            <legend class="profile-form-section-title">Palavra-passe</legend>
                            <div class="form-group">
                                <label for="current_password">Palavra-passe actual *</label>
                                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                            </div>
                            <div class="form-group">
                                <label for="new_password">Nova palavra-passe (opcional)</label>
                                <input type="password" id="new_password" name="new_password" minlength="6" autocomplete="new-password" placeholder="Deixe em branco para manter a actual">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirmar nova palavra-passe</label>
                                <input type="password" id="confirm_password" name="confirm_password" minlength="6" autocomplete="new-password">
                            </div>
                        </fieldset>
                        <button type="submit" class="btn-primary">Guardar alterações</button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>

    <?php
        $trustUserId = (int) ($user['id'] ?? 0);
        $trust = (isset($trust) && is_array($trust)) ? $trust : \App\model\User::getTrustMetrics($trustUserId);
        $trustGate = (isset($trustGate) && is_array($trustGate)) ? $trustGate : \Src\classes\ClassTrustBadgeEligibility::assertCanRequest($trustUserId);
        $trustBlockers = is_array($trustGate['blockers'] ?? null) ? $trustGate['blockers'] : [];
        $trustPricing = $trustPricing ?? \App\model\User::getTrustedBadgePricingConfig();
    ?>
    <?php
        $officialPlan = (isset($officialPlan) && is_array($officialPlan)) ? $officialPlan : null;
        $planNameLabel = 'Sem subscrição ativa';
        $planLimitLabel = 'Sem limite definido';
        if ($officialPlan) {
            $planNameLabel = (string) ($officialPlan['name'] ?? 'Plano Essencial');
            $maxProperties = $officialPlan['max_active_properties'] ?? null;
            $planLimitLabel = ($maxProperties === null)
                ? 'Sem limite de imóveis ativos'
                : 'Até ' . (int) $maxProperties . ' imóveis ativos';
        }

        $userTypeLabel = (($user['user_type'] ?? 'pessoa_fisica') === 'pessoa_juridica') ? 'Pessoa jurídica' : 'Pessoa física';
        $accountRoleLabel = Src\classes\ClassAccess::roleLabel($user ?? null);
        $statusValue = (string) ($user['status'] ?? 'pendente');
        $statusLabels = [
            'ativo' => 'Ativa',
            'pendente' => 'Pendente',
            'rejeitado' => 'Rejeitada',
        ];
        $accountStatusLabel = $statusLabels[$statusValue] ?? ucfirst($statusValue);

        $usernameCanChange = $usernameCanChange ?? Src\classes\UsernameHelper::canChangeUsername($user);
        $usernameNextChangeAt = $usernameNextChangeAt ?? Src\classes\UsernameHelper::nextChangeEligibleAt($user);
        $hasChangedUsername = !empty($user['username_changed_at']);
        $pendingEmailChange = $pendingEmailChange ?? Src\classes\EmailVerificationService::getPendingEmailChange((int) ($user['id'] ?? 0));
        $pendingEmailAddress = is_array($pendingEmailChange)
            ? trim((string) ($pendingEmailChange['pending_email'] ?? ''))
            : '';
    ?>

    <div class="dashboard-profile-layout">
        <aside class="dashboard-home-side dashboard-profile-avatar-panel">
            <div class="dashboard-module-card">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Avatar</span>
                        <h3>Apresentação</h3>
                    </div>
                </div>

                <div class="profile-photo-section dashboard-margin-bottom-reset">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?php echo htmlspecialchars(\Src\classes\ClassMediaUrl::profilePhoto($user['profile_photo'])); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" class="profile-photo-display">
                    <?php else: ?>
                        <div class="profile-photo-placeholder">
                            <i class="fa fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <h4 class="profile-side-name"><?php echo htmlspecialchars(Src\classes\UserDisplay::publicLabel($user)); ?></h4>
                    <?php if (!empty($user['username'])): ?>
                        <p class="profile-side-handle">@<?php echo htmlspecialchars((string) $user['username']); ?></p>
                    <?php endif; ?>
                    <p class="profile-side-role"><?php echo htmlspecialchars($accountRoleLabel); ?></p>
                    <p class="dashboard-inline-note">Uma boa foto melhora a confiança na sua conta e dá mais contexto aos atendimentos.</p>
                </div>
            </div>
        </aside>

        <div class="dashboard-profile-stack">
    <section class="dashboard-overview-grid dashboard-overview-grid-tight">
        <article class="dashboard-overview-card tone-blue">
            <div class="dashboard-overview-icon"><i class="fa fa-id-card-o"></i></div>
            <div class="dashboard-overview-body">
                <span>Tipo de entidade</span>
                <strong><?php echo $userTypeLabel; ?></strong>
                <small><?php echo !empty($user['document_number']) ? 'Documento: ' . htmlspecialchars((string) $user['document_number']) : 'Documento não informado'; ?></small>
            </div>
        </article>
        <article class="dashboard-overview-card tone-green">
            <div class="dashboard-overview-icon"><i class="fa fa-star-o"></i></div>
            <div class="dashboard-overview-body">
                <span>Plano</span>
                <strong><?php echo htmlspecialchars($planNameLabel); ?></strong>
                <small><?php echo htmlspecialchars($planLimitLabel); ?></small>
            </div>
        </article>
        <?php if (!empty($officialPlan['has_institutional_page'])): ?>
        <article class="dashboard-overview-card tone-yellow agency-profile-card">
            <div class="dashboard-overview-icon"><i class="fa fa-building"></i></div>
            <div class="dashboard-overview-body">
                <span>Página institucional</span>
                <strong>Activa no plano Empresarial</strong>
                <small><a href="<?php echo htmlspecialchars(Src\classes\ClassPlan::getPublicProfileUrl((int) ($user['id'] ?? 0))); ?>" target="_blank" rel="noopener">Ver página pública</a></small>
            </div>
        </article>
        <?php endif; ?>
        <article class="dashboard-overview-card tone-yellow">
            <div class="dashboard-overview-icon"><i class="fa fa-user-circle-o"></i></div>
            <div class="dashboard-overview-body">
                <span>Perfil de acesso</span>
                <strong><?php echo $accountRoleLabel; ?></strong>
                <small>Estado da conta: <?php echo $accountStatusLabel; ?></small>
            </div>
        </article>
        <?php
            $tbStatus = $trust['badge_status'] ?? 'nenhum';
            if (!empty($trust['trusted'])) {
                $tbCardTone  = 'tone-green';
                $tbCardIcon  = 'fa-shield';
                $tbCardLabel = 'Confiança ativa';
                $tbCardNote  = 'Selo de utilizador de confiança aprovado';
            } elseif ($tbStatus === 'aprovado' && empty($trust['fee_paid'])) {
                $tbCardTone  = 'tone-yellow';
                $tbCardIcon  = 'fa-clock-o';
                $tbCardLabel = 'Pagamento pendente';
                $tbCardNote  = 'Pedido aprovado — aguarda confirmação de pagamento';
            } elseif ($tbStatus === 'pendente') {
                $tbCardTone  = 'tone-yellow';
                $tbCardIcon  = 'fa-hourglass-half';
                $tbCardLabel = 'Em análise';
                $tbCardNote  = 'O pedido de selo está a ser revisto pela equipa';
            } elseif ($tbStatus === 'rejeitado') {
                $tbCardTone  = 'tone-red';
                $tbCardIcon  = 'fa-times-circle';
                $tbCardLabel = 'Rejeitado';
                $tbCardNote  = 'Pode submeter um novo pedido de selo';
            } elseif (!empty($trust['blockers'])) {
                $tbCardTone  = 'tone-yellow';
                $tbCardIcon  = 'fa-list-alt';
                $tbCardLabel = 'Requisitos em falta';
                $tbCardNote  = implode('; ', $trust['blockers']);
            } else {
                $tbCardTone  = 'tone-yellow';
                $tbCardIcon  = 'fa-shield';
                $tbCardLabel = 'Sem selo';
                $tbCardNote  = 'Ainda não possui selo de confiança';
            }
        ?>
        <article class="dashboard-overview-card <?php echo $tbCardTone; ?>">
            <div class="dashboard-overview-icon"><i class="fa <?php echo $tbCardIcon; ?>"></i></div>
            <div class="dashboard-overview-body">
                <span>Confiança</span>
                <strong><?php echo $tbCardLabel; ?></strong>
                <small><?php echo $tbCardNote; ?></small>
            </div>
        </article>
    </section>

        <div class="dashboard-home-main dashboard-profile-main">
            <div class="dashboard-module-card">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Identidade</span>
                        <h3>Resumo da Conta</h3>
                    </div>
                </div>

                <div class="dashboard-profile-summary">
                    <p><strong>Perfil de acesso:</strong> <?php echo $accountRoleLabel; ?></p>
                    <p><strong>Estado da conta:</strong> <?php echo $accountStatusLabel; ?></p>

                    <?php if (!empty($user['is_admin'])): ?>
                        <p><strong>Escopo:</strong> Gestão e moderação de utilizadores e conteúdos.</p>
                    <?php else: ?>
                        <p><strong>Limite de anúncios:</strong> <?php echo htmlspecialchars($planLimitLabel); ?></p>
                    <?php endif; ?>

                    <div class="trust-badges">
                        <?php if (!empty($trust['verified'])): ?>
                            <span class="owner-verified-badge"><i class="fa fa-check-circle"></i> Perfil verificado</span>
                        <?php endif; ?>
                        <?php if (!empty($trust['trusted'])): ?>
                            <span class="owner-trust-badge"><i class="fa fa-shield"></i> Utilizador de confiança</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (empty($user['is_admin'])): ?>
                <?php include DIRREQ . 'app/view/partials/trust_badge_section.php'; ?>
            <?php endif; ?>

            <?php
            $promoterUserType = (string) ($user['user_type'] ?? 'pessoa_fisica');
            $promoterUserTypeLabel = $promoterUserType === 'pessoa_juridica' ? 'pessoa jurídica' : 'pessoa singular';
            ?>
            <?php if (empty($user['is_admin']) && empty($user['is_affiliate'])): ?>
            <div class="dashboard-module-card">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Promotor</span>
                        <h3>Ganhe comissões indicando imóveis</h3>
                    </div>
                </div>
                <div class="dashboard-profile-summary">
                    <p>Afilie-se a imóveis de outros proprietários e <strong>ganhe comissões de 3%</strong> por cada negócio concluído através das suas indicações. Sem custos, sem risco.</p>
                    <ul style="margin: 12px 0 16px 18px; line-height:1.8;">
                        <li>Gere links de referência personalizados</li>
                        <li>Acompanhe as suas indicações no painel</li>
                        <li>Receba comissões quando os negócios fecharem</li>
                    </ul>
                    <p class="dashboard-inline-note">Termos aplicáveis à sua conta como <strong><?php echo htmlspecialchars($promoterUserTypeLabel); ?></strong>. Leia e aceite para activar o perfil de promotor.</p>
                    <button type="button" class="btn-primary promoter-activate-btn" id="promoter-activate-btn">Ver termos e activar perfil</button>
                </div>
            </div>

            <div id="promoter-terms-modal" class="modal-overlay" hidden
                 data-user-type="<?php echo htmlspecialchars($promoterUserType); ?>"
                 data-terms-url="<?php echo htmlspecialchars(DIRPAGE . 'dashboard/getPromoterTerms', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="modal-content affiliate-modal-content">
                    <div class="affiliate-modal-header">
                        <h2 class="affiliate-modal-title" id="promoter-terms-modal-title">Termos e Condições — Perfil de Promotor</h2>
                        <button type="button" class="btn-icon promoter-terms-modal-close affiliate-modal-close-btn" aria-label="Fechar">×</button>
                    </div>
                    <div id="promoter-terms-body" class="affiliate-terms-display"></div>
                    <div class="affiliate-modal-actions">
                        <label class="promoter-terms-accept-label">
                            <input type="checkbox" id="promoter-terms-checkbox">
                            Li e aceito os Termos e Condições para <?php echo htmlspecialchars($promoterUserTypeLabel); ?>
                        </label>
                        <div class="promoter-terms-modal-buttons">
                            <button type="button" class="btn-secondary promoter-terms-modal-cancel">Cancelar</button>
                            <button type="button" class="btn-primary promoter-terms-modal-submit" id="promoter-terms-submit" disabled>Aceito e activo o perfil</button>
                        </div>
                    </div>
                </div>
            </div>

            <form id="promoter-activate-form" action="<?php echo DIRPAGE; ?>dashboard/becomeAffiliate" method="POST" hidden>
                <?php echo Src\classes\ClassCsrf::field(); ?>
                <input type="hidden" name="accept_promoter_terms" value="1">
            </form>
            <?php elseif (!empty($user['is_affiliate'])): ?>
            <div class="dashboard-module-card">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Promotor</span>
                        <h3>Perfil de Promotor</h3>
                    </div>
                </div>
                <div class="dashboard-profile-summary">
                    <span class="owner-verified-badge">Promotor de imóveis activo</span>
                    <p style="margin-top:12px;">Aceda ao seu painel de referências e comissões através do menu lateral.</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="dashboard-module-card dashboard-form-shell">
                <div class="dashboard-module-head compact">
                    <div>
                        <span class="dashboard-module-kicker">Atualização</span>
                        <h3>Dados da Conta</h3>
                    </div>
                </div>

                <p class="dashboard-inline-note profile-update-intro">A identificação legal (nome e documento) não se altera aqui. Para correcções solicitadas pela plataforma, use <a href="<?php echo DIRPAGE; ?>dashboard/accountStatus">Estado da conta</a>.</p>

                <div class="profile-readonly-identification" aria-labelledby="profile-identification-title">
                    <h4 class="profile-form-section-title" id="profile-identification-title">Identificação</h4>
                    <dl class="profile-identification-dl">
                        <div>
                            <dt>Nome completo ou razão social</dt>
                            <dd><?php echo htmlspecialchars((string) ($user['name'] ?? '')); ?></dd>
                        </div>
                        <div>
                            <dt>Documento</dt>
                            <dd><?php echo htmlspecialchars((string) ($user['document_number'] ?? '—')); ?></dd>
                        </div>
                        <div>
                            <dt>Tipo de entidade</dt>
                            <dd><?php echo htmlspecialchars($userTypeLabel); ?></dd>
                        </div>
                        <div>
                            <dt>Estado da conta</dt>
                            <dd><?php echo htmlspecialchars($accountStatusLabel); ?></dd>
                        </div>
                    </dl>
                </div>

                <form action="<?php echo DIRPAGE; ?>profile/update" method="POST" enctype="multipart/form-data" class="profile-update-form">
                    <?php echo Src\classes\ClassCsrf::field(); ?>

                    <?php if ($usernameCanChange): ?>
                    <fieldset class="profile-form-section">
                        <legend class="profile-form-section-title">Nome de utilizador</legend>
                        <div class="form-group">
                            <label for="username">@username</label>
                            <div class="profile-username-input-wrap">
                                <span class="profile-username-prefix" aria-hidden="true">@</span>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    value="<?php echo htmlspecialchars((string) ($user['username'] ?? '')); ?>"
                                    minlength="<?php echo (int) Src\classes\UsernameHelper::MIN_LENGTH; ?>"
                                    maxlength="<?php echo (int) Src\classes\UsernameHelper::MAX_LENGTH; ?>"
                                    pattern="[a-z0-9][a-z0-9._-]*[a-z0-9]|[a-z0-9]{3}"
                                    autocomplete="username"
                                    autocapitalize="off"
                                    spellcheck="false"
                                >
                            </div>
                            <?php if (!$hasChangedUsername): ?>
                                <small class="dashboard-inline-note">Gerado no registo. Pode personalizá-lo agora; depois da primeira alteração, só volta a poder mudar passados <?php echo (int) Src\classes\UsernameHelper::CHANGE_COOLDOWN_DAYS; ?> dias.</small>
                            <?php else: ?>
                                <small class="dashboard-inline-note">Após a primeira alteração, só pode mudar novamente <?php echo (int) Src\classes\UsernameHelper::CHANGE_COOLDOWN_DAYS; ?> dias depois.</small>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                    <?php else: ?>
                    <div class="profile-form-section profile-username-locked">
                        <h4 class="profile-form-section-title">Nome de utilizador</h4>
                        <p class="profile-username-locked-value">@<?php echo htmlspecialchars((string) ($user['username'] ?? '')); ?></p>
                        <?php if ($usernameNextChangeAt instanceof DateTimeImmutable): ?>
                            <small class="dashboard-inline-note">Pode alterar novamente a partir de <?php echo htmlspecialchars($usernameNextChangeAt->format('d/m/Y')); ?>.</small>
                        <?php else: ?>
                            <small class="dashboard-inline-note">O seu nome de utilizador está fixo por agora.</small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <fieldset class="profile-form-section">
                        <legend class="profile-form-section-title">Contacto</legend>
                        <div class="form-group">
                            <label for="phone">Telefone *</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required autocomplete="tel">
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required autocomplete="email">
                            <small class="dashboard-inline-note">Para mudar o email, indique o novo endereço e confirme o link que enviarmos. Sem confirmação, mantém-se o email actual.</small>
                            <?php if ($pendingEmailAddress !== ''): ?>
                                <p class="profile-email-pending" role="status">
                                    Confirmação pendente para <strong><?php echo htmlspecialchars($pendingEmailAddress); ?></strong>.
                                    Verifique a caixa de entrada (e spam). O login e as notificações continuam a usar <strong><?php echo htmlspecialchars((string) ($user['email'] ?? '')); ?></strong> até confirmar.
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="profile_photo">Foto de perfil (opcional)</label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp">
                            <small class="dashboard-inline-note">JPG, PNG, GIF ou WebP, até <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?>.</small>
                        </div>
                    </fieldset>

                    <fieldset class="profile-form-section">
                        <legend class="profile-form-section-title">Acesso</legend>
                        <div class="form-group">
                            <label for="new_password">Nova senha (opcional)</label>
                            <input type="password" id="new_password" name="new_password" minlength="6" autocomplete="new-password" placeholder="Deixe em branco para manter a actual">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmar nova senha</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="6" autocomplete="new-password">
                        </div>
                    </fieldset>

                    <fieldset class="profile-form-section profile-form-section-security">
                        <legend class="profile-form-section-title">Confirmação</legend>
                        <p class="dashboard-inline-note">Para guardar qualquer alteração, indique a senha actual da conta.</p>
                        <div class="form-group">
                            <label for="current_password">Senha actual *</label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>
                    </fieldset>

                    <button type="submit" class="btn-primary">Guardar alterações</button>
                </form>
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>
</div>
