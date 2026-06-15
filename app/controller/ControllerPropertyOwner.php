<?php

namespace App\controller;

use App\model\Country;
use App\model\Log;
use App\model\Notification;
use App\model\Property;
use App\model\PropertyBoostRequest;
use App\model\Region;
use App\model\Request;
use App\model\SubscriptionPlan;
use App\model\User;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\UploadLimits;
use Src\classes\ClassCommissionGuard;
use Src\classes\ClassPlan;
use Src\classes\ClassRender;
use Src\classes\ClassSettings;

class ControllerPropertyOwner
{

    private function normalizeUploadError(int $code): string
    {
        $map = [
            UPLOAD_ERR_INI_SIZE => 'Uma imagem excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE => 'Uma imagem excede o limite permitido no formulário.',
            UPLOAD_ERR_PARTIAL => 'Uma imagem foi enviada parcialmente.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária de upload indisponível.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar imagem no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por uma extensão do servidor.',
        ];

        return $map[$code] ?? 'Erro ao enviar imagem.';
    }


    private function normalizePropertyImagePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }

        $path = preg_replace('#\?.*$#', '', $path);
        $path = preg_replace('#^https?://[^/]+/#i', '', $path);
        $path = ltrim($path, '/');

        if (strpos($path, 'storage/uploads/properties/') === 0) {
            $path = 'public/' . $path;
        }

        if (strpos($path, 'public/storage/uploads/properties/') !== 0) {
            $tail = preg_replace('#^.*?(public/storage/uploads/properties/.+)$#i', '$1', $path);
            if ($tail !== $path && strpos($tail, 'public/storage/uploads/properties/') === 0) {
                $path = $tail;
            }
        }

        if (!preg_match('#^public/storage/uploads/properties/[A-Za-z0-9._-]+$#', $path)) {
            return null;
        }

        return $path;
    }


    private function preserveCurrentPropertyImages(array $currentImages, array $allowedCurrent): array
    {
        $final = [];
        foreach ($currentImages as $rawPath) {
            $normalized = $this->normalizePropertyImagePath((string) $rawPath);
            if ($normalized !== null) {
                $final[] = $normalized;
            }
        }

        if ($final === []) {
            $final = array_values($allowedCurrent);
        }

        return array_values(array_unique($final));
    }


    private function deletePropertyImageFile(string $path): void
    {
        $normalized = $this->normalizePropertyImagePath($path);
        if ($normalized === null) {
            return;
        }

        $fullPath = DIRREQ . $normalized;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }


    private function resolvePropertyImagesForUpdate(array $property, array $post, array $files): array
    {
        $errors = [];
        $currentImages = json_decode((string) ($property['images'] ?? '[]'), true);
        if (!is_array($currentImages)) {
            $currentImages = [];
        }

        $allowedCurrent = [];
        foreach ($currentImages as $rawPath) {
            $normalized = $this->normalizePropertyImagePath((string) $rawPath);
            if ($normalized !== null) {
                $allowedCurrent[$normalized] = $normalized;
            }
        }

        $manifest = json_decode((string) ($post['images_manifest'] ?? ''), true);
        $maxNewUploads = 8;

        if (is_array($manifest) && $manifest !== []) {
            $maxNewUploads = 0;
            foreach ($manifest as $slot) {
                if (is_array($slot) && (string) ($slot['kind'] ?? '') === 'new') {
                    $maxNewUploads++;
                }
            }
        } else {
            $keptCount = 0;
            foreach ((array) ($post['existing_images'] ?? []) as $rawPath) {
                $path = $this->normalizePropertyImagePath((string) $rawPath);
                if ($path !== null && isset($allowedCurrent[$path])) {
                    $keptCount++;
                }
            }
            if ($keptCount === 0 && !array_key_exists('existing_images', $post)) {
                $keptCount = count($allowedCurrent);
            }
            $maxNewUploads = max(0, 8 - $keptCount);
        }

        if ($maxNewUploads === 0) {
            $attemptedUploads = $this->countSubmittedPropertyImageUploads($files);
            $uploaded = $attemptedUploads > 0
                ? ['paths' => [], 'errors' => ['Limite de 8 imagens atingido. Remova uma imagem antes de adicionar outra.']]
                : ['paths' => [], 'errors' => []];
        } else {
            $uploaded = $this->processPropertyImages($files, $maxNewUploads);
        }

        $final = [];

        if (is_array($manifest) && $manifest !== []) {
            $newIdx = 0;
            foreach ($manifest as $slot) {
                if (!is_array($slot)) {
                    continue;
                }

                $kind = (string) ($slot['kind'] ?? '');
                if ($kind === 'existing') {
                    $path = $this->normalizePropertyImagePath((string) ($slot['path'] ?? ''));
                    if ($path !== null && isset($allowedCurrent[$path])) {
                        $final[] = $path;
                    }
                } elseif ($kind === 'new' && isset($uploaded['paths'][$newIdx])) {
                    $final[] = $uploaded['paths'][$newIdx];
                    $newIdx++;
                }
            }
        } else {
            $kept = [];
            foreach ((array) ($post['existing_images'] ?? []) as $rawPath) {
                $path = $this->normalizePropertyImagePath((string) $rawPath);
                if ($path !== null && isset($allowedCurrent[$path])) {
                    $kept[] = $path;
                }
            }

            if ($kept === [] && !array_key_exists('existing_images', $post) && $allowedCurrent !== []) {
                $kept = array_values($allowedCurrent);
            }

            $final = array_merge($kept, $uploaded['paths']);
        }

        $final = array_values(array_unique($final));
        $hasNewUploads = $uploaded['paths'] !== [];
        $galleryTouched = ($post['images_gallery_touched'] ?? '') === '1';

        // Sem alterações na galeria nem ficheiros novos: manter imagens actuais.
        if (!$galleryTouched && !$hasNewUploads && $allowedCurrent !== []) {
            $final = $this->preserveCurrentPropertyImages($currentImages, $allowedCurrent);
        } elseif ($final === [] && !$hasNewUploads && $allowedCurrent !== [] && !$galleryTouched) {
            $final = $this->preserveCurrentPropertyImages($currentImages, $allowedCurrent);
        }

        if (count($final) > 8) {
            $errors[] = 'O imóvel pode ter no máximo 8 imagens.';
        }

        if ($final === []) {
            $errors[] = 'Mantenha pelo menos uma imagem no anúncio.';
        }

        foreach ($allowedCurrent as $path) {
            if (!in_array($path, $final, true)) {
                $this->deletePropertyImageFile($path);
            }
        }

        return [
            'paths' => $final,
            'errors' => array_merge($uploaded['errors'], $errors),
        ];
    }


    private function countSubmittedPropertyImageUploads(array $files): int
    {
        if (empty($files['name']) || !is_array($files['name'])) {
            return 0;
        }

        $actualUploadCount = 0;
        foreach ($files['name'] as $index => $_name) {
            $errorCode = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_NO_FILE) {
                $actualUploadCount++;
            }
        }

        return $actualUploadCount;
    }


    private function processPropertyImages(array $files, int $maxFiles = 8): array
    {
        $savedPaths = [];
        $errors = [];

        if (empty($files) || !isset($files['name']) || !is_array($files['name'])) {
            return ['paths' => [], 'errors' => []];
        }

        $allowedMime = ['image/webp'];
        $maxPerFile = UploadLimits::serverMaxBytes();
        $count = count($files['name']);
        $maxFiles = max(0, $maxFiles);
        $actualUploadCount = $this->countSubmittedPropertyImageUploads($files);

        if ($actualUploadCount === 0) {
            return ['paths' => [], 'errors' => []];
        }

        if ($actualUploadCount > $maxFiles) {
            $message = $maxFiles === 8
                ? 'Envie no máximo 8 imagens.'
                : ($maxFiles === 0
                    ? 'Limite de 8 imagens atingido. Remova uma imagem antes de adicionar outra.'
                    : 'Pode adicionar no máximo ' . $maxFiles . ' imagem(ns) nesta actualização.');
            return ['paths' => [], 'errors' => [$message]];
        }

        $uploadDir = DIRREQ . 'public/storage/uploads/properties/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['paths' => [], 'errors' => ['Não foi possível preparar a pasta de imagens.']];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        for ($i = 0; $i < $count; $i++) {
            $errorCode = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($errorCode !== UPLOAD_ERR_OK) {
                $errors[] = $this->normalizeUploadError($errorCode);
                continue;
            }

            $tmpName = (string) ($files['tmp_name'][$i] ?? '');
            $size = (int) ($files['size'][$i] ?? 0);

            if ($size <= 0 || $size > $maxPerFile) {
                $errors[] = 'Cada imagem deve ter até ' . UploadLimits::formatShort(UploadLimits::serverMaxBytes()) . '.';
                continue;
            }

            $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
            if (!in_array($mime, $allowedMime, true)) {
                $errors[] = 'Formato de imagem inválido. Envie imagens em WEBP.';
                continue;
            }

            try {
                $randomSuffix = bin2hex(random_bytes(4));
            } catch (\Exception $e) {
                $randomSuffix = substr(md5(uniqid('', true)), 0, 8);
            }

            $filename = 'property_' . time() . '_' . $randomSuffix . '.webp';
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($tmpName, $destination)) {
                $errors[] = 'Falha ao salvar uma imagem enviada.';
                continue;
            }

            $savedPaths[] = 'public/storage/uploads/properties/' . $filename;
        }

        if ($finfo) {
            finfo_close($finfo);
        }

        return ['paths' => $savedPaths, 'errors' => $errors];
    }


    public function create()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não podem cadastrar imóveis');
        $userId = (int) ($user['id'] ?? 0);

        $render = new ClassRender();
        $render->setTitle('Cadastrar Imóvel');
        $render->setDescription('Adicione um novo imóvel');
        $render->setKeywords('cadastrar, imóvel');
        $render->setData([
            'countries' => Country::getActive(),
            'regions' => Region::getActive(),
            'userPlan' => ClassPlan::getOfficialPlanByUser($userId),
            'planCatalog' => SubscriptionPlan::getActiveCatalog(),
            'commissionSystemOnlyPct' => ClassSettings::float('commission_system_only_pct', 5.0),
        ]);
        $render->setDir('property/create');
        $render->renderLayout();
    }


    public function store()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não podem cadastrar imóveis');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'properties');
            exit;
        }

        if (ClassCommissionGuard::currentUserHasBlockingOverdue()) {
            header('Location: ' . DIRPAGE . 'dashboard/commissionPayments');
            exit;
        }

        $currentCount = Property::countActiveByOwner((int) $user['id']);
        $limitCheck = ClassPlan::canPublishProperty((int) $user['id'], $currentCount);
        if (empty($limitCheck['allowed'])) {
            $maxProperties = isset($limitCheck['max']) ? (int) $limitCheck['max'] : 0;
            $planName = (string) (($limitCheck['plan']['name'] ?? 'Plano Essencial'));
            $errorMessage = 'O ' . $planName . ' permite ate ' . $maxProperties . ' imoveis ativos. Voce ja atingiu o limite.';
            header('Location: ' . DIRPAGE . 'property/create?error=' . urlencode($errorMessage));
            exit;
        }

        $plan = ClassPlan::getOfficialPlanByUser((int) $user['id']);

        $allowedRentTerms = ['mensal', 'trimestral', 'semestral', 'anual'];
        $selectedRentTerms = array_values(array_unique(array_filter(
            (array) ($_POST['rent_payment_terms'] ?? []),
            static function ($term) use ($allowedRentTerms) {
                return in_array((string) $term, $allowedRentTerms, true);
            }
        )));

        $purpose = (string) ($_POST['purpose'] ?? '');
        $rentPaymentTerms = $purpose === 'aluguer_longo'
            ? json_encode($selectedRentTerms, JSON_UNESCAPED_UNICODE)
            : null;
        $affiliateApprovalMode = (string) ($_POST['affiliate_approval_mode'] ?? Property::AFFILIATE_APPROVAL_AUTO);
        if (!in_array($affiliateApprovalMode, [Property::AFFILIATE_APPROVAL_MANUAL, Property::AFFILIATE_APPROVAL_AUTO, Property::AFFILIATE_APPROVAL_DISABLED], true)) {
            $affiliateApprovalMode = Property::AFFILIATE_APPROVAL_AUTO;
        }

        $uploadResult = $this->processPropertyImages($_FILES['images'] ?? []);
        if (!empty($uploadResult['errors'])) {
            header('Location: ' . DIRPAGE . 'property/create?error=' . urlencode(implode(' ', $uploadResult['errors'])));
            exit;
        }
        if (empty($uploadResult['paths'])) {
            header('Location: ' . DIRPAGE . 'property/create?error=' . urlencode('Envie pelo menos 1 imagem do imóvel.'));
            exit;
        }

        $data = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'type' => $_POST['type'] ?? '',
            'purpose' => $purpose,
            'rent_payment_terms' => $rentPaymentTerms,
            'rental_days' => $purpose === 'aluguer_curto' ? (int) ($_POST['rental_days'] ?? 0) : null,
            'rental_months' => $purpose === 'aluguer_longo' ? (int) ($_POST['rental_months'] ?? 0) : null,
            'price' => $_POST['price'] ?? '',
            'country_id' => !empty($_POST['country_id']) ? (int) $_POST['country_id'] : null,
            'location' => $_POST['location'] ?? '',
            'region_id' => !empty($_POST['region_id']) ? (int) $_POST['region_id'] : null,
            'bedrooms' => (int) ($_POST['bedrooms'] ?? 0),
            'bathrooms' => (int) ($_POST['bathrooms'] ?? 0),
            'area' => ($_POST['area'] ?? '') === '' ? null : (float) $_POST['area'],
            'images' => json_encode($uploadResult['paths'], JSON_UNESCAPED_SLASHES),
            'video_url' => $_POST['video_url'] ?? '',
            'affiliate_approval_mode' => $affiliateApprovalMode,
            'visibility' => ClassPlan::mapPlanToPropertyVisibility($plan),
            'affiliate_id' => $user['id'],
            'status' => 'pendente',
        ];

        $errors = Property::validateData($data);
        if (!empty($errors)) {
            // Handle errors, perhaps redirect with errors
            header('Location: ' . DIRPAGE . 'property/create?error=' . urlencode(implode(', ', $errors)));
            exit;
        }

        $propertyId = Property::create($data);
        if ($propertyId) {
            Log::create([
                'user_id' => $user['id'],
                'action' => 'create_property',
                'entity_type' => 'property',
                'entity_id' => $propertyId,
                'details' => 'Imóvel cadastrado aguardando aprovação',
            ]);
            header('Location: ' . DIRPAGE . 'dashboard?success=Imóvel cadastrado com sucesso, aguardando aprovação');
            exit;
        } else {
            header('Location: ' . DIRPAGE . 'property/create?error=Erro ao cadastrar imóvel');
            exit;
        }
    }


    public function edit($id)
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores nao podem editar imoveis');

        $property = Property::find((int) $id);
        if (!$property) {
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        if ((int) ($property['affiliate_id'] ?? 0) !== (int) $user['id']) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Sem+permissao+para+editar+este+imovel');
            exit;
        }

        $boostRequests   = PropertyBoostRequest::getByProperty((int) $id);
        $hasPendingBoost = PropertyBoostRequest::alreadyPending((int) $id);

        $render = new ClassRender();
        $render->setTitle('Editar Imovel');
        $render->setDescription('Actualize os dados do seu imovel');
        $render->setKeywords('editar, imovel');
        $render->setData([
            'property'        => $property,
            'countries'       => Country::getActive(),
            'regions'         => Region::getActive(),
            'boostRequests'   => $boostRequests,
            'hasPendingBoost' => $hasPendingBoost,
        ]);
        $render->setDir('property/edit');
        $render->renderLayout();
    }


    public function update($id)
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores nao podem editar imoveis');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties');
            exit;
        }

        $property = Property::find((int) $id);
        if (!$property) {
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        if ((int) ($property['affiliate_id'] ?? 0) !== (int) $user['id']) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Sem+permissao+para+editar+este+imovel');
            exit;
        }

        $lockedStatuses = ['vendido', 'alugado'];
        if (in_array($property['status'] ?? '', $lockedStatuses, true)) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Imovel+vendido+ou+alugado+nao+pode+ser+editado');
            exit;
        }

        $allowedRentTerms = ['mensal', 'trimestral', 'semestral', 'anual'];
        $selectedRentTerms = array_values(array_unique(array_filter(
            (array) ($_POST['rent_payment_terms'] ?? []),
            static function ($term) use ($allowedRentTerms) {
                return in_array((string) $term, $allowedRentTerms, true);
            }
        )));

        $imageResult = $this->resolvePropertyImagesForUpdate($property, $_POST, $_FILES['images'] ?? []);
        if (!empty($imageResult['errors'])) {
            header('Location: ' . DIRPAGE . 'property/edit/' . (int) $id . '?error=' . urlencode(implode(' ', $imageResult['errors'])));
            exit;
        }

        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $type        = trim($_POST['type']        ?? '');
        $purpose     = trim($_POST['purpose']     ?? '');
        $affiliateApprovalMode = (string) ($_POST['affiliate_approval_mode'] ?? Property::AFFILIATE_APPROVAL_AUTO);
        if (!in_array($affiliateApprovalMode, [Property::AFFILIATE_APPROVAL_MANUAL, Property::AFFILIATE_APPROVAL_AUTO, Property::AFFILIATE_APPROVAL_DISABLED], true)) {
            $affiliateApprovalMode = Property::AFFILIATE_APPROVAL_AUTO;
        }
        $location    = trim($_POST['location']    ?? '');
        $rentPaymentTerms = $purpose === 'aluguer_longo'
            ? json_encode($selectedRentTerms, JSON_UNESCAPED_UNICODE)
            : null;

        $newStatus = 'pendente';

        $updateData = [
            'title'           => $title,
            'description'     => $description,
            'type'            => $type,
            'purpose'         => $purpose,
            'rent_payment_terms' => $rentPaymentTerms,
            'rental_days' => $purpose === 'aluguer_curto' ? (int) ($_POST['rental_days'] ?? 0) : null,
            'rental_months' => $purpose === 'aluguer_longo' ? (int) ($_POST['rental_months'] ?? 0) : null,
            'price'           => (float) ($_POST['price'] ?? 0),
            'country_id'      => !empty($_POST['country_id']) ? (int) $_POST['country_id'] : null,
            'location'        => $location,
            'region_id'       => !empty($_POST['region_id']) ? (int) $_POST['region_id'] : null,
            'bedrooms'        => (int)   ($_POST['bedrooms']  ?? 0),
            'bathrooms'       => (int)   ($_POST['bathrooms'] ?? 0),
            'area'            => ($_POST['area'] ?? '') !== '' ? (float) $_POST['area'] : null,
            'video_url'       => trim($_POST['video_url'] ?? '') ?: null,
            'affiliate_approval_mode' => $affiliateApprovalMode,
            'status'          => $newStatus,
            'images'          => json_encode($imageResult['paths'], JSON_UNESCAPED_SLASHES),
        ];

        $errors = Property::validateData($updateData);
        if (!empty($errors)) {
            header('Location: ' . DIRPAGE . 'property/edit/' . (int) $id . '?error=' . urlencode(implode('. ', $errors)));
            exit;
        }

        Property::update((int) $id, $updateData);

        Log::create([
            'user_id'     => $user['id'],
            'action'      => 'update_property',
            'entity_type' => 'property',
            'entity_id'   => (int) $id,
            'details'     => 'Imovel actualizado - voltou para pendente de moderacao',
        ]);

        $msg = 'Imovel actualizado. Aguarda nova moderacao.';

        header('Location: ' . DIRPAGE . 'dashboard/myProperties?success=' . urlencode($msg));
        exit;
    }


    public function setStatus($id)
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores nao podem alterar estado de imoveis desta forma');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties');
            exit;
        }

        $property = Property::find((int) $id);
        if (!$property) {
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        if ((int) ($property['affiliate_id'] ?? 0) !== (int) $user['id']) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Sem+permissao');
            exit;
        }

        $newStatus = trim($_POST['new_status'] ?? '');
        if (!in_array($newStatus, ['vendido', 'alugado'], true)) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Estado+invalido');
            exit;
        }

        if (($property['status'] ?? '') !== 'disponivel') {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=So+e+possivel+marcar+imoveis+disponiveis+como+vendido+ou+alugado');
            exit;
        }

        Property::setStatus((int) $id, $newStatus);

        Request::closeActiveByPropertyClosure((int) $id, null);

        Log::create([
            'user_id'     => $user['id'],
            'action'      => 'set_property_status',
            'entity_type' => 'property',
            'entity_id'   => (int) $id,
            'details'     => 'Estado alterado para: ' . $newStatus,
        ]);

        header('Location: ' . DIRPAGE . 'dashboard/myProperties?success=' . urlencode('Imovel marcado como ' . $newStatus . '.'));
        exit;
    }


    public function requestBoost($id)
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores nao podem solicitar destaque');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties');
            exit;
        }

        $property = Property::find((int) $id);
        if (!$property) {
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        if ((int) ($property['affiliate_id'] ?? 0) !== (int) $user['id']) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Sem+permissao');
            exit;
        }

        if (($property['status'] ?? '') !== 'disponivel') {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=So+e+possivel+solicitar+destaque+para+imoveis+disponiveis');
            exit;
        }

        if (PropertyBoostRequest::alreadyPending((int) $id)) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Ja+existe+uma+solicitacao+de+destaque+pendente+para+este+imovel');
            exit;
        }

        $config   = PropertyBoostRequest::getBoostPricingConfig();
        $days     = max($config['min_days'], min($config['max_days'], (int) ($_POST['duration_days'] ?? $config['default_days'])));
        $feeRequired = PropertyBoostRequest::calculateBoostFee($days);

        // Handle proof upload
        $proofFile = $_FILES['boost_payment_proof'] ?? null;
        if (empty($proofFile['tmp_name']) || ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Comprovativo+de+pagamento+obrigatorio');
            exit;
        }

        $finfo     = new \finfo(FILEINFO_MIME_TYPE);
        $proofMime = (string) $finfo->file((string) $proofFile['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($proofMime, $allowedMimes, true)) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Formato+invalido+use+JPG+PNG+ou+WebP');
            exit;
        }
        if (UploadLimits::exceedsServerMax((int) ($proofFile['size'] ?? 0))) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=' . rawurlencode(UploadLimits::serverMaxError('O comprovativo')));
            exit;
        }

        $proofUploadDirRelative = 'public/storage/uploads/boost_proofs/';
        $proofUploadDir = DIRREQ . $proofUploadDirRelative;
        if (!is_dir($proofUploadDir)) {
            mkdir($proofUploadDir, 0755, true);
        }
        $extMap   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $ext      = $extMap[$proofMime] ?? 'jpg';
        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            $suffix = substr(md5(uniqid('', true)), 0, 12);
        }
        $filename = 'boost_' . (int) $user['id'] . '_' . (int) $id . '_' . time() . '_' . $suffix . '.' . $ext;
        if (!move_uploaded_file((string) $proofFile['tmp_name'], $proofUploadDir . $filename)) {
            header('Location: ' . DIRPAGE . 'dashboard/myProperties?error=Erro+ao+guardar+comprovativo');
            exit;
        }
        $proofPath = $proofUploadDirRelative . $filename;

        PropertyBoostRequest::create((int) $id, (int) $user['id'], 'destaque', $days, $feeRequired, $proofPath);

        Log::create([
            'user_id'     => $user['id'],
            'action'      => 'request_boost',
            'entity_type' => 'property',
            'entity_id'   => (int) $id,
            'details'     => 'Destaque ' . $days . ' dias, ' . $feeRequired . ' Kz. Comprovativo: ' . $filename,
        ]);

        $financeiroUsers = User::getByRole('financeiro');
        foreach ($financeiroUsers as $fin) {
            Notification::notifyUser(
                (int) $fin['id'],
                'boost_request',
                'Nova solicitação de destaque',
                $user['name'] . ' solicitou destaque para "' . $property['title'] . '" (' . $days . ' dias, ' . number_format($feeRequired, 0, ',', '.') . ' Kz).',
                ['property_id' => (int) $id],
                (int) $user['id']
            );
        }

        header('Location: ' . DIRPAGE . 'dashboard/myProperties?success=' . urlencode('Solicitação de destaque enviada. A equipa financeira irá validar o pagamento.'));
        exit;
    }

}
