<div class="container property-create-shell">
    <?php
    $countries = $countries ?? [];
    $regions = $regions ?? [];
    $createError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
    $userPlan = isset($userPlan) && is_array($userPlan) ? $userPlan : [];
    $planCatalog = isset($planCatalog) && is_array($planCatalog) ? $planCatalog : [];
    $commissionSystemOnlyPct = isset($commissionSystemOnlyPct) ? (float) $commissionSystemOnlyPct : 5.0;

    $formatPct = static function (float $value): string {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    };

    $formatPlanLimit = static function ($max): string {
        if ($max === null || $max === '') {
            return 'imóveis ativos ilimitados';
        }
        $count = (int) $max;
        return 'até ' . $count . ' imóvel' . ($count === 1 ? '' : 'es') . ' ativo' . ($count === 1 ? '' : 's');
    };

    $formatVisibilityTier = static function (?string $tier): string {
        return ($tier ?? 'basic') === 'premium' ? 'prioritária' : 'básica';
    };
    ?>

    <div class="page-header">
        <h1>Cadastrar Imóvel</h1>
        <p>Preencha os detalhes do seu imóvel. Ele será revisado antes de ser publicado.</p>
    </div>

    <?php if ($createError !== ''): ?>
        <div class="auth-message auth-message-error property-create-alert">
            <?php echo htmlspecialchars($createError); ?>
        </div>
    <?php endif; ?>

    <div class="property-create-layout">
        <div class="property-create-layout-main">
            <form action="<?php echo DIRPAGE; ?>property/store" method="POST" enctype="multipart/form-data" class="auth-container property-create-form">
                <?php echo Src\classes\ClassCsrf::field(); ?>

                <div class="property-create-block">
                    <h3>Identificação</h3>
                    <div class="form-group">
                        <label for="title">Título</label>
                        <input type="text" id="title" name="title" maxlength="255" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Descrição</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>
                </div>

                <div class="property-create-block">
                    <h3>Classificação e preço</h3>
                    <div class="property-create-grid-three property-create-grid-pricing">
                        <div class="form-group">
                            <label for="type">Tipo</label>
                            <select id="type" name="type" required>
                                <option value="">Selecione</option>
                                <?php foreach (Src\classes\PropertyTypeHelper::getGroupedTypes() as $groupLabel => $groupOptions): ?>
                                    <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                                        <?php foreach ($groupOptions as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>"><?php echo htmlspecialchars($lbl); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="purpose">Finalidade</label>
                            <select id="purpose" name="purpose" required>
                                <option value="">Selecione</option>
                                <option value="venda">Venda</option>
                                <option value="aluguer_curto">Aluguer Curto</option>
                                <option value="aluguer_longo">Aluguer Longo</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="price">Preço (Kz)</label>
                            <input type="number" id="price" name="price" min="0.01" step="0.01" required>
                        </div>
                    </div>
                </div>

                <div class="property-create-block" id="rental-periods-block" style="display:none;">
                    <h3>Período de arrendamento</h3>
                    <div class="property-create-grid-two">
                        <div class="form-group" id="rental-days-field" style="display:none;">
                            <label for="rental_days">Dias (Aluguer Curto)</label>
                            <input type="number" id="rental_days" name="rental_days" min="1" step="1" placeholder="Ex: 7">
                            <small class="property-create-note">Número de dias disponível para arrendamento curto.</small>
                        </div>
                        <div class="form-group" id="rental-months-field" style="display:none;">
                            <label for="rental_months">Meses (Aluguer Longo)</label>
                            <input type="number" id="rental_months" name="rental_months" min="1" step="1" placeholder="Ex: 1">
                            <small class="property-create-note">Número de meses para cálculo de arrendamento longo.</small>
                        </div>
                    </div>
                    <div class="form-group" id="rent-terms-block" style="display:none; margin-top: 12px;">
                        <label>Modalidades de pagamento (aluguer longo)</label>
                        <div class="rent-terms-options">
                            <label class="rent-term-option"><input type="checkbox" name="rent_payment_terms[]" value="mensal"> Mensal</label>
                            <label class="rent-term-option"><input type="checkbox" name="rent_payment_terms[]" value="trimestral"> Trimestral</label>
                            <label class="rent-term-option"><input type="checkbox" name="rent_payment_terms[]" value="semestral"> Semestral</label>
                            <label class="rent-term-option"><input type="checkbox" name="rent_payment_terms[]" value="anual"> Anual</label>
                        </div>
                        <small class="property-create-note">Selecione as opções que quer disponibilizar ao cliente. O valor informado acima é a referência mensal.</small>
                    </div>
                </div>

                <div class="property-create-block">
                    <h3>Detalhes físicos</h3>
                    <div class="property-create-grid-three">
                        <div class="form-group">
                            <label for="bedrooms">Quartos</label>
                            <input type="number" id="bedrooms" name="bedrooms" min="0" step="1">
                        </div>

                        <div class="form-group">
                            <label for="bathrooms">Casas de Banho</label>
                            <input type="number" id="bathrooms" name="bathrooms" min="0" step="1">
                        </div>

                        <div class="form-group">
                            <label for="area">Área (m²)</label>
                            <input type="number" id="area" name="area" min="0" step="0.01">
                        </div>
                    </div>
                </div>

                <div class="property-create-block">
                    <h3>Informações de localização</h3>
                    <div class="property-create-grid-two property-create-grid-location">
                        <div class="form-group">
                            <label for="country_id">País</label>
                            <select id="country_id" name="country_id">
                                <option value="">Selecione um país</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo (int) ($country['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($country['name'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="region_id">Região</label>
                            <select id="region_id" name="region_id" disabled>
                                <option value="">Selecione um país primeiro</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo (int) ($region['id'] ?? 0); ?>" data-country-id="<?php echo (int) ($region['country_id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($region['name'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group property-create-grid-span-all">
                            <label for="location">Localização</label>
                            <input type="text" id="location" name="location" maxlength="255" required placeholder="Bairro, rua ou referência">
                        </div>
                    </div>
                </div>

                <div class="property-create-blocks-row">
                    <div class="property-create-block">
                        <h3>Imagens</h3>
                        <div class="form-group property-upload-panel">
                            <label for="images">Imagens do Imóvel</label>
                            <input type="file" id="images" name="images[]" accept="image/*" multiple>
                            <small class="property-create-note">Até 8 imagens, JPG, PNG, WEBP ou GIF. Máx. <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?> por ficheiro.</small>
                            <small class="property-create-note">A primeira miniatura será usada como capa. Pode remover imagens ou definir outra como capa antes de enviar.</small>
                            <div id="property-image-preview" class="property-image-preview" aria-live="polite"></div>
                        </div>
                    </div>

                    <div class="property-create-block property-create-block-compact">
                        <h3>Link do vídeo</h3>
                        <div class="form-group">
                            <label for="video_url">URL do Vídeo (opcional)</label>
                            <input type="url" id="video_url" name="video_url" maxlength="255" placeholder="https://www.youtube.com/watch?v=...">
                            <small class="property-create-note">Cole um link do YouTube ou um ficheiro de vídeo directo (.mp4, .webm).</small>
                        </div>

                        <h3 class="property-create-subsection-title">Afiliação do imóvel</h3>
                        <div class="form-group">
                            <label for="affiliate_approval_mode">Modo de afiliação</label>
                            <select id="affiliate_approval_mode" name="affiliate_approval_mode" required>
                                <option value="auto" selected>Aprovação automática (após aceitar termos)</option>
                                <option value="manual">Sob aprovação do proprietário</option>
                                <option value="disabled">Não permitir afiliação neste imóvel</option>
                            </select>
                            <small class="property-create-note">Escolha entre aprovar automaticamente, aprovar manualmente ou bloquear afiliações.</small>
                        </div>
                    </div>
                </div>

                <div class="property-create-submit">
                    <div id="property-create-form-feedback" class="auth-message property-create-form-feedback" role="alert" aria-live="polite" hidden></div>
                    <small class="property-create-note">Revise os dados antes de publicar. O imóvel entra como pendente para moderação.</small>
                    <div class="dashboard-inline-actions property-create-submit-actions">
                        <a href="<?php echo DIRPAGE; ?>dashboard/myProperties" class="btn-secondary">Cancelar</a>
                        <button type="submit" class="btn-primary">Cadastrar Imóvel</button>
                    </div>
                </div>
            </form>
        </div>

        <aside class="property-create-layout-aside" aria-label="Informações de comissão e planos">
            <div class="property-create-info-card">
                <p><strong>Comissão:</strong> <?php echo $formatPct($commissionSystemOnlyPct); ?>% sobre o valor do negócio fechado.</p>
                <?php if (!empty($userPlan)): ?>
                    <p>
                        <strong>O seu plano (<?php echo htmlspecialchars((string) ($userPlan['name'] ?? 'Plano Essencial')); ?>):</strong>
                        <?php echo htmlspecialchars($formatPlanLimit($userPlan['max_active_properties'] ?? 3)); ?>,
                        visibilidade <?php echo htmlspecialchars($formatVisibilityTier($userPlan['visibility_tier'] ?? 'basic')); ?>.
                        <?php if (!empty($userPlan['has_featured_in_results'])): ?>
                            Inclui posicionamento prioritário nos resultados.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($planCatalog)): ?>
                    <div class="property-create-plan-list-wrap">
                        <p class="property-create-plan-list-title"><strong>Planos na plataforma</strong></p>
                        <ul class="property-create-plan-list">
                            <?php foreach ($planCatalog as $plan): ?>
                                <?php
                                $planName = (string) ($plan['name'] ?? 'Plano');
                                $planMax = $formatPlanLimit($plan['max_active_properties'] ?? null);
                                $planVisibility = $formatVisibilityTier($plan['visibility_tier'] ?? 'basic');
                                $planPrice = (float) ($plan['monthly_price_aoa'] ?? 0);
                                $isCustom = !empty($plan['is_custom_pricing']);
                                ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($planName); ?></strong>
                                    <span><?php echo htmlspecialchars($planMax); ?>, visibilidade <?php echo htmlspecialchars($planVisibility); ?>.</span>
                                    <?php if ($isCustom): ?>
                                        <span class="property-create-plan-price">Sob proposta</span>
                                    <?php elseif ($planPrice <= 0): ?>
                                        <span class="property-create-plan-price">Grátis</span>
                                    <?php else: ?>
                                        <span class="property-create-plan-price"><?php echo number_format($planPrice, 0, ',', '.'); ?> Kz/mês</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="property-create-note">
                            <a href="<?php echo DIRPAGE; ?>dashboard/subscription">Ver ou alterar o seu plano</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
