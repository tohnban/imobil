<?php

namespace App\controller;

use App\model\Favorite;
use App\model\Log;
use App\model\Notification;
use App\model\Property;
use App\model\PropertyAffiliate;
use App\model\PropertyBehaviorEvent;
use Src\classes\ClassAjaxResponse;
use Src\classes\ClassAuth;
use Src\classes\ClassCookieConsent;
use Src\classes\ClassCsrf;
use Src\classes\ClassSession;

class ControllerPropertyEngagement
{
    private function respondFavoriteToggle(int $propertyId, bool $favorited): void
    {
        $userId = (int) (ClassAuth::user()['id'] ?? 0);
        $favoriteCount = Favorite::countByUser($userId);

        if (ClassCsrf::isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'favorited' => $favorited,
                'property_id' => $propertyId,
                'favorite_count' => $favoriteCount,
                'csrf_token' => ClassCsrf::get(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $redirect = ClassCsrf::resolveReturnUrl('property/' . $propertyId);
        header('Location: ' . $redirect);
        exit;
    }

    public function favorite($id)
    {
        ClassAuth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'properties');
            exit;
        }

        Favorite::add(ClassAuth::user()['id'], (int) $id);
        if (ClassCookieConsent::hasBehavioralConsent()) {
            PropertyBehaviorEvent::track(
                (int) (ClassAuth::user()['id'] ?? 0),
                (int) $id,
                'favorite',
                ClassSession::getOrCreateVisitorKey()
            );
        }
        $this->respondFavoriteToggle((int) $id, true);
    }


    public function unfavorite($id)
    {
        ClassAuth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . DIRPAGE . 'properties');
            exit;
        }

        Favorite::remove(ClassAuth::user()['id'], (int) $id);
        $this->respondFavoriteToggle((int) $id, false);
    }


    public function affiliateRequest($id)
    {
        ClassAuth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ClassCsrf::failRedirect('property/' . (int) $id, 'Token inválido');
        }

        $user = ClassAuth::user();
        $propertyId = (int) $id;

        // Check if property exists
        $property = Property::find($propertyId);
        if (!$property) {
            if (ClassCsrf::isAjaxRequest()) {
                ClassAjaxResponse::json(false, ['error' => 'Imóvel não encontrado.'], 404);
            }
            header('Location: ' . DIRPAGE . '404');
            exit;
        }

        // Can't request affiliation to own property
        if ($property['affiliate_id'] == $user['id']) {
            ClassAjaxResponse::redirectOrJson(false, 'Você é o proprietário deste imóvel.', 'property/' . $propertyId);
        }

        if (!Property::isPubliclyListed($property)) {
            ClassAjaxResponse::redirectOrJson(false, 'Este imóvel não está disponível para afiliação.', 'property/' . $propertyId);
        }

        // Must have the affiliate profile enabled first
        if (empty($user['is_affiliate'])) {
            ClassAjaxResponse::redirectOrJson(false, 'Active o perfil de promotor no seu dashboard antes de solicitar afiliação.', 'property/' . $propertyId);
        }

        // Check if already affiliate (pendente or ativo)
        if (PropertyAffiliate::exists($user['id'], $propertyId)) {
            ClassAjaxResponse::redirectOrJson(false, 'Você já tem uma solicitação de afiliação para este imóvel.', 'property/' . $propertyId);
        }

        $approvalMode = (string) ($property['affiliate_approval_mode'] ?? Property::AFFILIATE_APPROVAL_AUTO);
        if (!in_array($approvalMode, [Property::AFFILIATE_APPROVAL_MANUAL, Property::AFFILIATE_APPROVAL_AUTO, Property::AFFILIATE_APPROVAL_DISABLED], true)) {
            $approvalMode = Property::AFFILIATE_APPROVAL_AUTO;
        }
        if ($approvalMode === Property::AFFILIATE_APPROVAL_DISABLED) {
            ClassAjaxResponse::redirectOrJson(false, 'Este imóvel não aceita afiliações.', 'property/' . $propertyId);
        }
        $initialStatus = $approvalMode === Property::AFFILIATE_APPROVAL_AUTO ? 'ativo' : 'pendente';

        // Create affiliate request
        PropertyAffiliate::create([
            'user_id' => $user['id'],
            'property_id' => $propertyId,
            'status' => $initialStatus,
        ]);

        // Log action
        Log::create([
            'user_id' => $user['id'],
            'action' => 'Solicitou afiliação em um imóvel',
            'entity_type' => 'property_affiliate',
            'entity_id' => $propertyId,
            'details' => 'Solicitação de afiliação para a propriedade: ' . $property['title'] . ' | Modo: ' . $approvalMode,
        ]);

        if ($initialStatus === 'ativo') {
            Notification::notifyUser(
                (int) ($user['id'] ?? 0),
                'affiliate_approved',
                'Afiliação aprovada automaticamente',
                'A sua afiliação ao imóvel "' . ($property['title'] ?? '') . '" foi aprovada automaticamente pelo proprietário.',
                ['property_id' => (int) $propertyId],
                (int) ($property['affiliate_id'] ?? 0)
            );

            ClassAjaxResponse::redirectOrJson(
                true,
                'Afiliação aprovada automaticamente. Pode começar a indicar este imóvel.',
                'property/' . $propertyId,
                ['property_id' => $propertyId, 'affiliate_status' => 'ativo']
            );
        }

        ClassAjaxResponse::redirectOrJson(
            true,
            'Solicitação de afiliação enviada com sucesso.',
            'property/' . $propertyId,
            ['property_id' => $propertyId, 'affiliate_status' => 'pendente']
        );
    }


    public function getAffiliationTerms()
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(PropertyAffiliate::getAffiliationTerms());
        exit;
    }

}
