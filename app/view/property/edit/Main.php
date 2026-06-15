<?php
/* $property, $boostRequests, $hasPendingBoost are injected by extract($data) in ClassRender */
$propId          = (int) ($property['id'] ?? 0);
$propStatus      = (string) ($property['status'] ?? 'pendente');
$isLocked        = in_array($propStatus, ['vendido', 'alugado'], true);
$isFeatured      = !empty($property['featured']);
$existingImages  = json_decode((string) ($property['images'] ?? '[]'), true);
$existingImages  = is_array($existingImages) ? $existingImages : [];
$selectedRentTerms = json_decode((string) ($property['rent_payment_terms'] ?? '[]'), true);
$selectedRentTerms = is_array($selectedRentTerms) ? $selectedRentTerms : [];
$countries = $countries ?? [];
$regions = $regions ?? [];
$affiliateApprovalMode = (string) ($property['affiliate_approval_mode'] ?? 'auto');
if (!in_array($affiliateApprovalMode, ['manual', 'auto', 'disabled'], true)) {
    $affiliateApprovalMode = 'auto';
}

$statusLabels = [
    'pendente'   => 'Pendente de moderação',
    'disponivel' => 'Disponível',
    'vendido'    => 'Vendido',
    'alugado'    => 'Alugado',
    'rejeitado'  => 'Rejeitado',
];
$statusLabel = $statusLabels[$propStatus] ?? ucfirst($propStatus);
$editError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
$rentalDaysValue = (int) ($property['rental_days'] ?? 0);
$rentalMonthsValue = (int) ($property['rental_months'] ?? 0);

$existingImagesGallery = [];
foreach ($existingImages as $imgPath) {
    $path = trim((string) $imgPath);
    if ($path === '') {
        continue;
    }
    $url = $path;
    if (!preg_match('#^https?://#i', $url)) {
        $url = DIRPAGE . ltrim($url, '/');
    }
    $existingImagesGallery[] = ['path' => $path, 'url' => $url];
}
$existingImagesJson = json_encode(
    $existingImagesGallery,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

$initialImagesManifest = [];
foreach ($existingImages as $imgPath) {
    $path = trim((string) $imgPath);
    if ($path !== '') {
        $initialImagesManifest[] = ['kind' => 'existing', 'path' => $path];
    }
}
$initialImagesManifestJson = json_encode(
    $initialImagesManifest,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

$boostStatusMap = [
    'pendente'  => ['Pendente', 'dashboard-chip-warning'],
    'aprovado'  => ['Aprovado', 'dashboard-chip-success'],
    'rejeitado' => ['Rejeitado', 'dashboard-chip-danger'],
];
?>

<div class="container property-create-shell property-edit-shell">

    <div class="page-header">
        <h1>Editar Imóvel</h1>
        <p>
            Estado actual: <strong><?php echo htmlspecialchars($statusLabel); ?></strong>
            <?php if ($isFeatured): ?>
                &nbsp;<span class="dashboard-chip dashboard-chip-success" style="vertical-align:middle;">★ Em destaque</span>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($editError !== ''): ?>
        <div class="auth-message auth-message-error property-create-alert">
            <?php echo htmlspecialchars($editError); ?>
        </div>
    <?php endif; ?>

    <?php if ($isLocked): ?>
        <div class="auth-message auth-message-error">
            Este imóvel está marcado como <strong><?php echo htmlspecialchars($statusLabel); ?></strong> e não pode ser editado.
            Regresse ao <a href="<?php echo DIRPAGE; ?>dashboard/myProperties">portfólio</a>.
        </div>
    <?php else: ?>

    <div class="property-create-layout property-edit-layout">
        <div class="property-create-layout-main">
            <form action="<?php echo DIRPAGE; ?>property/update/<?php echo $propId; ?>" method="POST" enctype="multipart/form-data" class="auth-container property-create-form property-edit-form">
                <?php echo Src\classes\ClassCsrf::field(); ?>
                <input type="hidden" name="images_manifest" id="images_manifest" value="<?php echo htmlspecialchars((string) $initialImagesManifestJson, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="images_gallery_touched" id="images_gallery_touched" value="0">

                <div class="property-create-block">
                    <h3>Identificação</h3>
                    <div class="form-group">
                        <label for="title">Título</label>
                        <input type="text" id="title" name="title" maxlength="255" required value="<?php echo htmlspecialchars((string) ($property['title'] ?? '')); ?>">
                    </div>

                    <div class="form-group">
                        <label for="description">Descrição</label>
                        <textarea id="description" name="description" required><?php echo htmlspecialchars((string) ($property['description'] ?? '')); ?></textarea>
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
                                            <option value="<?php echo $val; ?>"<?php echo ($property['type'] ?? '') === $val ? ' selected' : ''; ?>><?php echo htmlspecialchars($lbl); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="purpose">Finalidade</label>
                            <select id="purpose" name="purpose" required>
                                <option value="">Selecione</option>
                                <?php foreach (['venda'=>'Venda','aluguer_curto'=>'Aluguer Curto','aluguer_longo'=>'Aluguer Longo'] as $val => $lbl): ?>
                                    <option value="<?php echo $val; ?>"<?php echo ($property['purpose'] ?? '') === $val ? ' selected' : ''; ?>><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="price">Preço (Kz)</label>
                            <input type="number" id="price" name="price" min="0.01" step="0.01" required value="<?php echo htmlspecialchars((string) ($property['price'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="form-group" id="rent-terms-block" style="<?php echo ($property['purpose'] ?? '') === 'aluguer_longo' ? '' : 'display:none;'; ?> margin-top:12px;">
                        <label>Modalidades de pagamento (aluguer longo)</label>
                        <div class="rent-terms-options">
                            <label class="rent-term-option"><input type="checkbox" name="rent_payment_terms[]" value="mensal"<?php echo in_array('mensal', $selectedRentTerms, true) ? ' checked' : ''; ?>> Mensal</label>
                            <label class="rent-term-option"><input type="checkbox" name="rent_payment_terms[]" value="trimestral"<?php echo in_array('trimestral', $selectedRentTerms, true) ? ' checked' : ''; ?>> Trimestral</label>
                            <label class="rent-term-option"><input type="checkbox" name="rent_payment_terms[]" value="semestral"<?php echo in_array('semestral', $selectedRentTerms, true) ? ' checked' : ''; ?>> Semestral</label>
                            <label class="rent-term-option"><input type="checkbox" name="rent_payment_terms[]" value="anual"<?php echo in_array('anual', $selectedRentTerms, true) ? ' checked' : ''; ?>> Anual</label>
                        </div>
                        <small class="property-create-note">Defina quais modalidades estarão disponíveis para solicitação. O preço continua como referência mensal.</small>
                    </div>

                    <div class="property-create-grid-two" id="rental-periods-block" style="<?php echo in_array($property['purpose'] ?? '', ['aluguer_curto', 'aluguer_longo'], true) ? '' : 'display:none;'; ?> margin-top:12px;">
                        <div class="form-group" id="rental-days-field">
                            <label for="rental_days">Dias (Aluguer Curto)</label>
                            <input type="number" id="rental_days" name="rental_days" min="1" step="1" placeholder="Ex: 7" value="<?php echo $rentalDaysValue > 0 ? $rentalDaysValue : ''; ?>">
                            <small class="property-create-note">Número de dias disponível para arrendamento curto.</small>
                        </div>
                        <div class="form-group" id="rental-months-field">
                            <label for="rental_months">Meses (Aluguer Longo)</label>
                            <input type="number" id="rental_months" name="rental_months" min="1" step="1" placeholder="Ex: 1" value="<?php echo $rentalMonthsValue > 0 ? $rentalMonthsValue : ''; ?>">
                            <small class="property-create-note">Número de meses para cálculo de arrendamento longo.</small>
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
                                    <option value="<?php echo (int) ($country['id'] ?? 0); ?>"<?php echo (int) ($property['country_id'] ?? 0) === (int) ($country['id'] ?? 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($country['name'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="region_id">Região</label>
                            <select id="region_id" name="region_id"<?php echo (int) ($property['country_id'] ?? 0) > 0 ? '' : ' disabled'; ?>>
                                <option value=""><?php echo (int) ($property['country_id'] ?? 0) > 0 ? 'Selecione uma região' : 'Selecione um país primeiro'; ?></option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo (int) ($region['id'] ?? 0); ?>" data-country-id="<?php echo (int) ($region['country_id'] ?? 0); ?>"<?php echo (int) ($property['region_id'] ?? 0) === (int) ($region['id'] ?? 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($region['name'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group property-create-grid-span-all">
                            <label for="location">Localização</label>
                            <input type="text" id="location" name="location" maxlength="255" required placeholder="Bairro, rua ou referência" value="<?php echo htmlspecialchars((string) ($property['location'] ?? '')); ?>">
                        </div>
                    </div>
                </div>

                <div class="property-create-block">
                    <h3>Detalhes físicos</h3>
                    <div class="property-create-grid-three">
                        <div class="form-group">
                            <label for="bedrooms">Quartos</label>
                            <input type="number" id="bedrooms" name="bedrooms" min="0" step="1" value="<?php echo (int) ($property['bedrooms'] ?? 0); ?>">
                        </div>

                        <div class="form-group">
                            <label for="bathrooms">Casas de Banho</label>
                            <input type="number" id="bathrooms" name="bathrooms" min="0" step="1" value="<?php echo (int) ($property['bathrooms'] ?? 0); ?>">
                        </div>

                        <div class="form-group">
                            <label for="area">Área (m²)</label>
                            <input type="number" id="area" name="area" min="0" step="0.01" value="<?php echo ($property['area'] ?? '') !== '' && $property['area'] !== null ? htmlspecialchars((string) $property['area']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="property-create-blocks-row">
                    <div class="property-create-block">
                        <h3>Imagens</h3>
                        <div class="form-group property-upload-panel">
                            <label for="images">Galeria do imóvel</label>
                            <input type="file" id="images" name="images[]" accept="image/*" multiple>
                            <small class="property-create-note">Até 8 imagens no total, JPG, PNG, WEBP ou GIF. Máx. <?php echo htmlspecialchars(\Src\classes\UploadLimits::formatShort(\Src\classes\UploadLimits::SERVER_MAX_BYTES), ENT_QUOTES, 'UTF-8'); ?> por ficheiro.</small>
                            <small class="property-create-note">A primeira miniatura é a capa do anúncio.</small>
                            <div
                                id="property-image-preview"
                                class="property-image-preview"
                                aria-live="polite"
                                data-existing-images="<?php echo htmlspecialchars((string) $existingImagesJson, ENT_QUOTES, 'UTF-8'); ?>"
                            ></div>
                        </div>
                    </div>

                    <div class="property-create-block property-create-block-compact">
                        <h3>Link do vídeo</h3>
                        <div class="form-group">
                            <label for="video_url">URL do Vídeo (opcional)</label>
                            <input type="url" id="video_url" name="video_url" maxlength="255" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo htmlspecialchars((string) ($property['video_url'] ?? '')); ?>">
                            <small class="property-create-note">Cole um link do YouTube ou um ficheiro de vídeo directo (.mp4, .webm).</small>
                        </div>

                        <h3 class="property-create-subsection-title">Afiliação do imóvel</h3>
                        <div class="form-group">
                            <label for="affiliate_approval_mode">Modo de afiliação</label>
                            <select id="affiliate_approval_mode" name="affiliate_approval_mode" required>
                                <option value="auto"<?php echo $affiliateApprovalMode === 'auto' ? ' selected' : ''; ?>>Aprovação automática (após aceitar termos)</option>
                                <option value="manual"<?php echo $affiliateApprovalMode === 'manual' ? ' selected' : ''; ?>>Sob aprovação do proprietário</option>
                                <option value="disabled"<?php echo $affiliateApprovalMode === 'disabled' ? ' selected' : ''; ?>>Não permitir afiliação neste imóvel</option>
                            </select>
                            <small class="property-create-note">Pode alterar este comportamento a qualquer momento.</small>
                        </div>
                    </div>
                </div>

                <div class="property-create-submit">
                    <div id="property-create-form-feedback" class="auth-message property-create-form-feedback" role="alert" aria-live="polite" hidden></div>
                    <small class="property-create-note">Depois de guardar, o imóvel volta para moderação.</small>
                    <div class="dashboard-inline-actions property-create-submit-actions">
                        <a href="<?php echo DIRPAGE; ?>dashboard/myProperties" class="btn-secondary">Cancelar</a>
                        <button type="submit" class="btn-primary">Guardar alterações</button>
                    </div>
                </div>
            </form>
        </div>

        <aside class="property-create-layout-aside property-edit-layout-aside" aria-label="Informações de edição">
            <div class="property-create-info-card property-edit-info-card">
                <p><strong>Moderação:</strong> Após qualquer actualização, o imóvel volta para <em>pendente</em> e aguarda nova revisão.</p>
                <p><strong>Estado actual:</strong> <?php echo htmlspecialchars($statusLabel); ?><?php echo $isFeatured ? ' · Em destaque' : ''; ?></p>
                <p><strong>Referência:</strong> #<?php echo $propId; ?></p>
                <p class="property-create-note">
                    <a href="<?php echo DIRPAGE; ?>property/<?php echo $propId; ?>">Ver anúncio publicado</a>
                    ·
                    <a href="<?php echo DIRPAGE; ?>dashboard/myProperties">Voltar ao portfólio</a>
                </p>
            </div>
        </aside>
    </div>

    <?php endif; /* !$isLocked */ ?>

    <?php if (!empty($boostRequests)): ?>
    <div class="dashboard-module-card property-edit-boost-section">
        <div class="dashboard-module-head compact">
            <div>
                <span class="dashboard-module-kicker">Destaque</span>
                <h3>Histórico de solicitações de destaque</h3>
            </div>
        </div>
        <div class="dashboard-table-wrap">
            <table class="commissions-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Duração</th>
                        <th>Referência</th>
                        <th>Estado</th>
                        <th>Solicitado em</th>
                        <th>Expira em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($boostRequests as $br): ?>
                        <?php [$bLabel, $bClass] = $boostStatusMap[$br['status'] ?? 'pendente'] ?? ['–', 'dashboard-chip']; ?>
                        <tr>
                            <td data-label="Tipo"><?php echo htmlspecialchars(ucfirst($br['boost_type'] ?? '–')); ?></td>
                            <td data-label="Duração"><?php echo (int) ($br['duration_days'] ?? 30); ?> dias</td>
                            <td data-label="Referência" class="dashboard-inline-note"><?php echo htmlspecialchars($br['payment_reference'] ?? '–'); ?></td>
                            <td data-label="Estado"><span class="dashboard-chip <?php echo $bClass; ?>"><?php echo $bLabel; ?></span></td>
                            <td data-label="Solicitado em" class="dashboard-inline-note"><?php echo !empty($br['requested_at']) ? date('d/m/Y H:i', strtotime($br['requested_at'])) : '–'; ?></td>
                            <td data-label="Expira em" class="dashboard-inline-note"><?php echo !empty($br['expires_at']) ? date('d/m/Y', strtotime($br['expires_at'])) : '–'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
