<?php
$property = $property ?? null;
$status = (string) ($property['status'] ?? 'disponivel');
$statusLabel = [
    'pendente' => 'pendente de aprovacao',
    'rejeitado' => 'rejeitado',
    'suspenso' => 'suspenso',
    'disponivel' => 'disponivel',
    'vendido' => 'vendido',
    'alugado' => 'alugado',
][$status] ?? 'em revisao';
$typeLabel = Src\classes\PropertyTypeHelper::getLabel($property['type'] ?? null);
$purposeLabel = !empty($property['purpose']) ? ucfirst(str_replace('_', ' ', (string) $property['purpose'])) : 'Nao informado';
$ownerDisplay = 'Nao informado';
if (!empty($property['owner_username'])) {
    $ownerDisplay = '@' . htmlspecialchars((string) $property['owner_username']);
} elseif (!empty($property['owner_name'])) {
    $ownerDisplay = htmlspecialchars(Src\classes\UserDisplay::publicLabel(['name' => $property['owner_name']]));
}
$hasProperty = !empty($property);
$rawImages = json_decode((string) ($property['images'] ?? '[]'), true);
$galleryImages = [];

if (is_array($rawImages)) {
    foreach ($rawImages as $rawImage) {
        $imagePath = trim((string) $rawImage);
        if ($imagePath === '') {
            continue;
        }

        if (!preg_match('#^https?://#i', $imagePath)) {
            $imagePath = \Src\classes\ClassMediaUrl::propertyImage($imagePath);
        }

        $galleryImages[] = $imagePath;
    }
}

if (empty($galleryImages)) {
    $galleryImages[] = DIRIMG . 'apt20.avif';
}
$coverImage = $galleryImages[0];
$purposeToRequestOption = [
    'venda' => ['value' => 'compra', 'label' => 'Comprar'],
    'aluguer_curto' => ['value' => 'aluguer_curto', 'label' => 'Aluguer Curto'],
    'aluguer_longo' => ['value' => 'aluguer_longo', 'label' => 'Aluguer Longo'],
];
$requestOption = $purposeToRequestOption[(string) ($property['purpose'] ?? '')] ?? null;
$isOwnerViewing = Src\classes\ClassAuth::check() && (int) (Src\classes\ClassAuth::user()['id'] ?? 0) === (int) ($property['affiliate_id'] ?? 0);
$canSubmitPropertyRequest = !empty($canSubmitPropertyRequest);
$hasLimitedAccountAccess = !empty($hasLimitedAccountAccess);
$isStaffViewer = Src\classes\ClassAuth::check() && Src\classes\ClassAccess::isAdmin();
$viewerCanRequestAffiliate = Src\classes\ClassAuth::check()
    && $canSubmitPropertyRequest
    && !$isOwnerViewing
    && $status === 'disponivel';
$rentPaymentTerms = json_decode((string) ($property['rent_payment_terms'] ?? '[]'), true);
$rentPaymentTerms = is_array($rentPaymentTerms) ? $rentPaymentTerms : [];
$rentTermLabels = [
    'mensal' => 'Mensal',
    'trimestral' => 'Trimestral',
    'semestral' => 'Semestral',
    'anual' => 'Anual',
];
$isLongTermRent = (string) ($property['purpose'] ?? '') === 'aluguer_longo';
$affiliateApprovalMode = (string) ($property['affiliate_approval_mode'] ?? 'auto');
if (!in_array($affiliateApprovalMode, ['manual', 'auto', 'disabled'], true)) {
    $affiliateApprovalMode = 'auto';
}

$purpose = (string) ($property['purpose'] ?? '');
$rentalDays = (int) ($property['rental_days'] ?? 0);
$rentalMonths = (int) ($property['rental_months'] ?? 0);
$basePrice = (float) ($property['price'] ?? 0);
$isShortRent = $purpose === 'aluguer_curto';
$isLongRent = $purpose === 'aluguer_longo';
$totalPrice = $basePrice;
$periodLabel = '';
$periodValue = 0;

if ($isShortRent && $rentalDays > 0) {
    $totalPrice = $basePrice * $rentalDays;
    $periodLabel = 'dia(s)';
    $periodValue = $rentalDays;
} elseif ($isLongRent && $rentalMonths > 0) {
    $totalPrice = $basePrice * $rentalMonths;
    $periodLabel = 'mês/meses';
    $periodValue = $rentalMonths;
}
?>

<div class="container property-page-shell property-detail-page-view">
    <?php if (!$hasProperty): ?>
        <div class="property-state-card property-state-card-error">
            <strong>Imovel nao encontrado</strong>
            <p>Nenhum dado valido foi recebido para montar esta pagina.</p>
            <a href="<?php echo DIRPAGE; ?>properties" class="btn-primary">Voltar para a listagem</a>
        </div>
    <?php else: ?>
        <section class="property-show-hero property-show-hero-section">
            <div class="property-show-copy property-mobile-block-intro">
                <div class="property-show-topline">
                    <span class="sales-kicker">Imóvel verificado</span>
                </div>

                <h1><?php echo htmlspecialchars((string) ($property['title'] ?? 'Sem titulo')); ?></h1>

                <div class="property-mobile-price-head property-mobile-only">
                    <strong><?php echo number_format($basePrice, 0, ',', '.'); ?> Kz</strong>
                    <?php if ($isLongRent): ?>
                        <span>/ mês</span>
                    <?php elseif ($isShortRent): ?>
                        <span>/ dia</span>
                    <?php endif; ?>
                    <?php if (($isShortRent && $rentalDays > 0) || ($isLongRent && $rentalMonths > 0)): ?>
                        <small>Total <?php echo number_format($totalPrice, 0, ',', '.'); ?> Kz · <?php echo (int) $periodValue; ?> <?php echo htmlspecialchars($periodLabel); ?></small>
                    <?php endif; ?>
                </div>

                <p class="property-show-lead">Veja os detalhes, fale com o proprietário e tome a sua decisão com confiança.</p>

                <div class="property-show-tags property-mobile-meta">
                    <span><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars((string) ($property['location'] ?? 'Localizacao nao informada')); ?></span>
                    <span><i class="fa fa-tag"></i> <?php echo htmlspecialchars($typeLabel); ?></span>
                    <span><i class="fa fa-exchange"></i> <?php echo htmlspecialchars($purposeLabel); ?></span>
                    <span><i class="fa fa-circle"></i> <?php echo htmlspecialchars($statusLabel); ?></span>
                </div>

                <div class="property-show-proof">
                    <div class="property-proof-price">
                        <strong><?php echo number_format($basePrice, 0, ',', '.'); ?> Kz</strong>
                        <span><?php echo $isLongRent ? 'por mês' : ($isShortRent ? 'por dia' : 'preço'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo (int) ($property['area'] ?? 0); ?> m²</strong>
                        <span>área</span>
                    </div>
                    <div>
                        <strong><?php echo (int) ($property['bedrooms'] ?? 0); ?> / <?php echo (int) ($property['bathrooms'] ?? 0); ?></strong>
                        <span>quartos / banhos</span>
                    </div>
                </div>

                <div class="property-mobile-owner-line property-mobile-only">
                    <span class="property-mobile-owner-label">Anunciante</span>
                    <div class="property-mobile-owner-main">
                        <?php if (!empty($property['affiliate_id'])): ?>
                            <a href="<?php echo htmlspecialchars(Src\classes\ClassPlan::getPublicProfileUrl((int) $property['affiliate_id'])); ?>" class="owner-name-link"><?php echo $ownerDisplay; ?></a>
                        <?php else: ?>
                            <span><?php echo $ownerDisplay; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="property-mobile-owner-badges">
                        <?php if (!empty($property['owner_verified'])): ?>
                            <span class="owner-verified-badge"><i class="fa fa-check-circle"></i> Verificado</span>
                        <?php endif; ?>
                        <?php if (!empty($property['owner_trusted'])): ?>
                            <span class="owner-trust-badge"><i class="fa fa-shield"></i> Confiança</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <aside class="property-show-summary-card">
                <span class="property-summary-kicker">Leitura rapida</span>
                <div class="property-summary-price"><?php echo number_format((float) ($property['price'] ?? 0), 0, ',', '.'); ?> Kz</div>
                <?php if (($isShortRent && $rentalDays > 0) || ($isLongRent && $rentalMonths > 0)): ?>
                <div class="property-summary-total">Total: <strong><?php echo number_format($totalPrice, 0, ',', '.'); ?> Kz</strong> por <?php echo $periodValue; ?> <?php echo $periodLabel; ?></div>
                <?php endif; ?>
                <div class="property-summary-owner">
                    <?php if (!empty($property['affiliate_id'])): ?>
                        <a href="<?php echo htmlspecialchars(Src\classes\ClassPlan::getPublicProfileUrl((int) $property['affiliate_id'])); ?>" class="owner-name-link"><?php echo $ownerDisplay; ?></a>
                    <?php else: ?>
                        <?php echo $ownerDisplay; ?>
                    <?php endif; ?>
                </div>
                <div class="property-summary-badges">
                    <?php if (!empty($property['owner_verified'])): ?>
                        <span class="owner-verified-badge"><i class="fa fa-check-circle"></i> Perfil verificado</span>
                    <?php endif; ?>
                    <?php if (!empty($property['owner_trusted'])): ?>
                        <span class="owner-trust-badge"><i class="fa fa-shield"></i> Utilizador de confianca</span>
                    <?php endif; ?>
                    <?php if (empty($property['owner_verified']) && empty($property['owner_trusted'])): ?>
                        <span class="property-summary-muted">Conta sem selos adicionais no momento.</span>
                    <?php endif; ?>
                </div>
                <p>Tome a sua decisão com informação completa. Imóveis disponíveis saem do mercado rapidamente.</p>
            </aside>
        </section>

        <?php if ($status !== 'disponivel'): ?>
            <div class="property-state-card property-state-card-warning property-mobile-block-warning">
                <strong>Aviso operacional</strong>
                <?php if (in_array($status, ['vendido', 'alugado'], true)): ?>
                    <p>Este imóvel está <?php echo htmlspecialchars($statusLabel); ?>. O anúncio permanece acessível para consulta, mas não aceita novas negociações.</p>
                <?php else: ?>
                    <p>Este imovel esta <?php echo htmlspecialchars($statusLabel); ?> e ainda nao esta visivel para publicacao geral.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="property-detail">
            <div class="property-main-column">
                <div class="property-images property-show-gallery-card">
                    <img id="property-main-image" src="<?php echo htmlspecialchars($coverImage); ?>" alt="<?php echo htmlspecialchars((string) ($property['title'] ?? 'Imovel')); ?>">
                    <?php if (Src\classes\ClassAuth::check() && !(Src\classes\ClassAuth::user()['is_admin'] ?? false)): ?>
                        <form method="POST" action="<?php echo DIRPAGE; ?>property/<?php echo !empty($isFavorite) ? 'unfavorite' : 'favorite'; ?>/<?php echo (int) ($property['id'] ?? 0); ?>" class="favorite-form-inline favorite-overlay">
                            <?php echo Src\classes\ClassCsrf::field(); ?>
                            <button type="submit" class="btn-favorite<?php echo !empty($isFavorite) ? ' is-active' : ''; ?>" title="<?php echo !empty($isFavorite) ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>" aria-label="<?php echo !empty($isFavorite) ? 'Remover dos favoritos' : 'Guardar nos favoritos'; ?>">
                                <i class="fa <?php echo !empty($isFavorite) ? 'fa-heart' : 'fa-heart-o'; ?>"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                    <div class="property-gallery-overlay">
                        <span class="sales-card-badge"><i class="fa fa-home"></i> Imóvel verificado</span>
                        <?php if (count($galleryImages) > 1): ?>
                            <span class="sales-card-badge"><i class="fa fa-picture-o"></i> <?php echo count($galleryImages); ?> fotos</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($galleryImages) > 1): ?>
                    <div class="property-gallery-thumbs" id="property-gallery-thumbs">
                        <?php foreach ($galleryImages as $index => $imageUrl): ?>
                            <button
                                type="button"
                                class="property-gallery-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                data-gallery-image="<?php echo htmlspecialchars($imageUrl); ?>"
                                aria-label="Ver imagem <?php echo (int) ($index + 1); ?>"
                            >
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Miniatura <?php echo (int) ($index + 1); ?> de <?php echo htmlspecialchars((string) ($property['title'] ?? 'Imovel')); ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($property['video_url'])): ?>
                    <div class="property-video property-show-video-card" id="property-video-section">
                        <?php
                            $videoUrl = $property['video_url'];
                            if (preg_match('/(youtube\.com|youtu\.be)/i', $videoUrl)) {
                                $youtubeId = '';
                                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/', $videoUrl, $match)) {
                                    $youtubeId = $match[1];
                                }
                                if (!$youtubeId && preg_match('/embed\/([A-Za-z0-9_-]+)/', $videoUrl, $match)) {
                                    $youtubeId = $match[1];
                                }
                                if ($youtubeId) {
                                    $embedUrl = 'https://www.youtube-nocookie.com/embed/' . $youtubeId . '?autoplay=1&playsinline=1&rel=0';
                                    $posterUrl = 'https://i.ytimg.com/vi/' . $youtubeId . '/hqdefault.jpg';
                                    echo '<div class="property-video-embed property-video-embed-youtube" data-youtube-embed="' . htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') . '">';
                                    echo '<button type="button" class="property-video-play" aria-label="Reproduzir vídeo do imóvel">';
                                    echo '<img class="property-video-poster" src="' . htmlspecialchars($posterUrl, ENT_QUOTES, 'UTF-8') . '" alt="" width="480" height="360" loading="eager" decoding="async">';
                                    echo '<span class="property-video-play-shell" aria-hidden="true"><i class="fa fa-play"></i></span>';
                                    echo '<span class="property-video-play-text">Toque para reproduzir</span>';
                                    echo '</button>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="property-video-embed">';
                                    echo '<iframe class="property-video-iframe" src="' . htmlspecialchars((string) $videoUrl, ENT_QUOTES, 'UTF-8') . '" title="Vídeo do imóvel" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';
                                    echo '</div>';
                                }
                            } elseif (preg_match('/\.(mp4|webm|ogg)(\?|$)/i', $videoUrl)) {
                                echo '<div class="property-video-embed property-video-embed-native">'
                                    . '<video class="property-video-native" controls playsinline preload="metadata">'
                                    . '<source src="' . htmlspecialchars((string) $videoUrl, ENT_QUOTES, 'UTF-8') . '">'
                                    . 'Seu browser nao suporta video.'
                                    . '</video>'
                                    . '</div>';
                            } else {
                                echo '<p class="property-video-fallback"><a href="' . htmlspecialchars((string) $videoUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">Abrir vídeo</a></p>';
                            }
                        ?>
                    </div>
                <?php endif; ?>

                <div class="property-description property-story-card">
                    <div class="property-section-head">
                        <span class="sales-kicker">Sobre este imóvel</span>
                        <h3>Descricao</h3>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars((string) ($property['description'] ?? 'Sem descricao'))); ?></p>
                </div>

                <div class="property-description property-highlights-card property-mobile-hide-section">
                    <div class="property-section-head">
                        <span class="sales-kicker">Características</span>
                        <h3>Pontos-chave</h3>
                    </div>
                    <div class="property-highlight-grid">
                        <article>
                            <strong><?php echo htmlspecialchars($typeLabel); ?></strong>
                            <span>Tipo de imóvel</span>
                        </article>
                        <article>
                            <strong><?php echo htmlspecialchars($purposeLabel); ?></strong>
                            <span>Forma de negociação</span>
                        </article>
                        <article>
                            <strong><?php echo (int) ($property['bedrooms'] ?? 0); ?> quartos</strong>
                            <span>Número de quartos</span>
                        </article>
                        <article>
                            <strong><?php echo (int) ($property['bathrooms'] ?? 0); ?> banhos</strong>
                            <span>Número de casas de banho</span>
                        </article>
                    </div>
                </div>
            </div>

            <aside class="property-sidebar">
                <div class="property-info property-info-card property-mobile-info-card">
                    <div class="property-info-price-block">
                        <span><?php echo $isLongTermRent ? 'Referencia mensal' : 'Preco anunciado'; ?></span>
                        <strong><?php echo number_format((float) ($property['price'] ?? 0), 0, ',', '.'); ?> Kz</strong>
                        <?php if (($isShortRent && $rentalDays > 0) || ($isLongRent && $rentalMonths > 0)): ?>
                        <span class="property-info-total">Total: <strong><?php echo number_format($totalPrice, 0, ',', '.'); ?> Kz</strong> por <?php echo $periodValue; ?> <?php echo $periodLabel; ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($isLongTermRent): ?>
                        <div class="property-state-card property-state-card-muted property-rent-terms-card">
                            <strong>Modalidades de pagamento</strong>
                            <?php if (!empty($rentPaymentTerms)): ?>
                                <p class="property-rent-terms-copy">
                                    <?php
                                        $labels = [];
                                        foreach ($rentPaymentTerms as $term) {
                                            if (isset($rentTermLabels[$term])) {
                                                $labels[] = $rentTermLabels[$term];
                                            }
                                        }
                                        echo htmlspecialchars(implode(' • ', $labels));
                                    ?>
                                </p>
                            <?php else: ?>
                                <p class="property-rent-terms-copy">Sem modalidades definidas pelo proprietário.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="property-info-list">
                        <div><span>Proprietario</span><strong>
                            <?php if (!empty($property['affiliate_id'])): ?>
                                <a href="<?php echo htmlspecialchars(Src\classes\ClassPlan::getPublicProfileUrl((int) $property['affiliate_id'])); ?>" class="owner-name-link"><?php echo $ownerDisplay; ?></a>
                            <?php else: ?>
                                <?php echo $ownerDisplay; ?>
                            <?php endif; ?>
                        </strong></div>
                        <div><span>Localizacao</span><strong><?php echo htmlspecialchars((string) ($property['location'] ?? 'Nao informada')); ?></strong></div>
                        <div><span>Tipo</span><strong><?php echo htmlspecialchars($typeLabel); ?></strong></div>
                        <div><span>Finalidade</span><strong><?php echo htmlspecialchars($purposeLabel); ?></strong></div>
                        <div><span>Area</span><strong><?php echo htmlspecialchars((string) ($property['area'] ?? '0')); ?> m2</strong></div>
                    </div>
                </div>

                <?php if (Src\classes\ClassAuth::check()): ?>
                    <div class="property-actions property-actions-card" id="property-request-panel">
                        <div class="property-section-head compact">
                            <span class="sales-kicker">Próximo passo</span>
                            <h3>Marque a sua visita</h3>
                        </div>

                        <?php if (!empty($_GET['error'])): ?>
                            <div class="sub-feedback error"><?php echo htmlspecialchars((string) $_GET['error']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($_GET['success'])): ?>
                            <div class="sub-feedback success"><?php echo htmlspecialchars((string) $_GET['success']); ?></div>
                        <?php endif; ?>

                        <?php if ($isStaffViewer): ?>
                            <div class="property-state-card property-state-card-muted">
                                <strong>Conta da equipa</strong>
                                <p>Administradores e perfis de moderação não enviam pedidos de compra ou aluguer nesta página. Acompanhe e gira solicitações em <a href="<?php echo DIRPAGE; ?>requests">Solicitações</a>.</p>
                            </div>
                        <?php elseif ($hasLimitedAccountAccess): ?>
                            <div class="property-state-card property-state-card-warning">
                                <strong>Ainda estamos a validar a sua conta</strong>
                                <p>Por agora pode ver este imóvel à vontade. Quando a conta estiver activa, pode pedir visita ou avançar com a compra ou aluguer. <a href="<?php echo DIRPAGE; ?>dashboard/accountStatus">Ver o que falta</a></p>
                            </div>
                        <?php elseif (!empty($hasBlockingOverdueCommissions)): ?>
                            <div class="property-state-card property-state-card-warning">
                                <strong>Pedidos temporariamente bloqueados</strong>
                                <p>Regularize as comissões conforme o aviso no topo da página.</p>
                            </div>
                        <?php elseif (!empty($hasActiveRequest)): ?>
                            <div class="property-state-card property-state-card-warning">
                                <strong>Solicitação já submetida</strong>
                                <p>Já tem uma solicitação ativa para este imóvel. Acompanhe o progresso no seu <a href="<?php echo DIRPAGE; ?>requests">painel de solicitações</a>.</p>
                            </div>
                        <?php elseif ($canSubmitPropertyRequest && $status === 'disponivel' && !$isOwnerViewing && (!$isLongTermRent || !empty($rentPaymentTerms))): ?>
                            <form action="<?php echo DIRPAGE; ?>request" method="POST">
                                <?php echo Src\classes\ClassCsrf::field(); ?>
                                <input type="hidden" name="property_id" value="<?php echo (int) ($property['id'] ?? 0); ?>">
                                <div class="form-group">
                                    <label for="request-type">Tipo de solicitacao</label>
                                    <select name="type" id="request-type" required>
                                        <?php if ($requestOption): ?>
                                            <option value="<?php echo htmlspecialchars($requestOption['value']); ?>"><?php echo htmlspecialchars($requestOption['label']); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <?php if ($isLongTermRent): ?>
                                    <div class="form-group">
                                        <label for="payment-term">Modalidade de pagamento</label>
                                        <select name="payment_term" id="payment-term" required>
                                            <option value="">Selecione</option>
                                            <?php foreach ($rentPaymentTerms as $term): ?>
                                                <?php if (isset($rentTermLabels[$term])): ?>
                                                    <option value="<?php echo htmlspecialchars($term); ?>"><?php echo htmlspecialchars($rentTermLabels[$term]); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label for="message">Mensagem</label>
                                    <textarea id="message" name="message" placeholder="Descreva interesse, prazo e objectivo da solicitacao"></textarea>
                                </div>
                                <button type="submit" class="btn-primary">Enviar pedido agora</button>
                            </form>
                        <?php elseif ($status === 'disponivel' && !$isOwnerViewing && $isLongTermRent && empty($rentPaymentTerms)): ?>
                            <div class="property-state-card property-state-card-muted">
                                <strong>Solicitacao indisponivel</strong>
                                <p>O proprietario ainda nao definiu modalidades de pagamento para este aluguer longo.</p>
                            </div>
                        <?php elseif ($isOwnerViewing): ?>
                            <div class="property-state-card property-state-card-muted">
                                <strong>Imóvel do seu portfólio</strong>
                                <p>Este anúncio pertence à sua conta. Utilize o painel para gerir leads e afiliações.</p>
                            </div>
                        <?php else: ?>
                            <div class="property-state-card property-state-card-muted">
                                <strong>Nao e possivel solicitar</strong>
                                <p>Este imóvel está em revisão e não aceita pedidos neste momento.</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($viewerCanRequestAffiliate): ?>
                            <div class="property-state-card property-state-card-muted property-affiliate-card">
                                <strong>Programa de afiliação</strong>
                                <?php if (!empty($isAffiliate)): ?>
                                    <p>Você já está aprovado como afiliado deste imóvel e pode usar o seu link de indicação.</p>
                                    <details class="affiliate-terms-details">
                                        <summary class="affiliate-terms-summary">Ver termos de afiliação</summary>
                                        <div id="affiliate-terms-display" class="affiliate-terms-display">
                                            <div class="affiliate-terms-content"></div>
                                        </div>
                                    </details>
                                <?php elseif ($affiliateStatus === 'pendente'): ?>
                                    <p>Sua solicitação de afiliação para este imóvel está pendente de aprovação do proprietário.</p>
                                    <p class="affiliate-pending-note">Será notificado por email quando houver uma decisão.</p>
                                <?php elseif ($affiliateStatus === 'rejeitado'): ?>
                                    <p>Sua solicitação de afiliação para este imóvel foi rejeitada. Contacte o suporte para mais informações.</p>
                                <?php else: ?>
                                    <?php if ($affiliateApprovalMode === 'disabled'): ?>
                                        <p>O proprietário desativou a afiliação para este imóvel. Não é possível solicitar participação no programa.</p>
                                    <?php elseif ($affiliateApprovalMode === 'auto'): ?>
                                        <p>Este imóvel usa aprovação automática de afiliação. Ao solicitar, você será afiliado de imediato.</p>
                                    <?php else: ?>
                                        <p>Solicite afiliação para poder divulgar este imóvel com o seu código e ganhar comissão quando houver fecho do negócio.</p>
                                    <?php endif; ?>
                                    <?php if ($affiliateApprovalMode !== 'disabled'): ?>
                                        <button type="button" class="btn-secondary affiliation-request-btn" data-affiliate-property-id="<?php echo (int) ($property['id'] ?? 0); ?>">Solicitar afiliação</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Modal de Termos de Afiliação -->
                        <div id="affiliation-terms-modal" class="modal-overlay" hidden>
                            <div class="modal-content affiliate-modal-content">
                                <div class="affiliate-modal-header">
                                    <h2 class="affiliate-modal-title">Programa de Afiliação - Termos e Condições</h2>
                                    <button type="button" class="btn-icon affiliation-modal-close affiliate-modal-close-btn">×</button>
                                </div>
                                <div id="affiliation-terms-body"></div>
                                <div class="affiliate-modal-actions">
                                    <button type="button" class="btn-secondary affiliation-modal-cancel">Cancelar</button>
                                    <button type="button" class="btn-primary affiliation-submit-btn" data-property-id="<?php echo (int) ($property['id'] ?? 0); ?>">Aceito os termos e solicito afiliação</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($status === 'disponivel'): ?>
                    <div class="property-actions property-actions-card property-login-card" id="property-request-panel">
                        <div class="property-section-head compact">
                            <span class="sales-kicker">Acesso</span>
                            <h3>Entre para negociar</h3>
                        </div>
                        <p>Faca login para enviar solicitacoes, acompanhar respostas e gerir o fluxo comercial deste imovel.</p>
                        <a href="<?php echo DIRPAGE; ?>login" class="btn-primary">Entrar na conta</a>
                    </div>
                    <?php else: ?>
                    <div class="property-actions property-actions-card property-login-card">
                        <div class="property-section-head compact">
                            <span class="sales-kicker">Estado comercial</span>
                            <h3>Negociação encerrada</h3>
                        </div>
                        <p>Este imóvel está <?php echo htmlspecialchars($statusLabel); ?> e não aceita novos pedidos.</p>
                        <a href="<?php echo DIRPAGE; ?>properties" class="btn-secondary">Ver imóveis disponíveis</a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </aside>
        </section>

        <?php
            $mobileCtaLabel = 'Ver imóveis';
            $mobileCtaHref = DIRPAGE . 'properties';
            $mobileCtaClass = 'btn-secondary';
            if ($status === 'disponivel') {
                if (!Src\classes\ClassAuth::check()) {
                    $mobileCtaLabel = 'Entrar para negociar';
                    $mobileCtaHref = DIRPAGE . 'login';
                    $mobileCtaClass = 'btn-primary';
                } elseif ($isOwnerViewing) {
                    $mobileCtaLabel = 'Meus imóveis';
                    $mobileCtaHref = DIRPAGE . 'dashboard/myProperties';
                    $mobileCtaClass = 'btn-secondary';
                } elseif ($canSubmitPropertyRequest && !$hasActiveRequest && empty($hasBlockingOverdueCommissions) && !$isStaffViewer && (!$isLongTermRent || !empty($rentPaymentTerms))) {
                    $mobileCtaLabel = 'Enviar pedido';
                    $mobileCtaHref = '#property-request-panel';
                    $mobileCtaClass = 'btn-primary';
                } elseif (!empty($hasActiveRequest)) {
                    $mobileCtaLabel = 'Ver solicitações';
                    $mobileCtaHref = DIRPAGE . 'requests';
                    $mobileCtaClass = 'btn-primary';
                }
            }
        ?>
        <aside class="property-mobile-cta-bar" aria-label="Resumo e ação principal">
            <div class="property-mobile-cta-copy">
                <span class="property-mobile-cta-kicker"><?php echo $isLongRent ? 'Referência mensal' : ($isShortRent ? 'Por dia' : 'Preço'); ?></span>
                <strong><?php echo number_format($basePrice, 0, ',', '.'); ?> Kz</strong>
                <?php if (($isShortRent && $rentalDays > 0) || ($isLongRent && $rentalMonths > 0)): ?>
                    <small>Total <?php echo number_format($totalPrice, 0, ',', '.'); ?> Kz · <?php echo $periodValue; ?> <?php echo $periodLabel; ?></small>
                <?php endif; ?>
            </div>
            <a href="<?php echo htmlspecialchars($mobileCtaHref); ?>" class="<?php echo htmlspecialchars($mobileCtaClass); ?> property-mobile-cta-btn"><?php echo htmlspecialchars($mobileCtaLabel); ?></a>
        </aside>
    <?php endif; ?>
</div>