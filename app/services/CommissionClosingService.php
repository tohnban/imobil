<?php

namespace App\services;

use App\model\Commission;
use App\model\Notification;
use App\model\Property;
use App\model\PropertyAffiliate;
use App\model\Request;
use Src\classes\ClassSettings;

class CommissionClosingService
{
    /**
     * @return array{ok: bool, error?: string, commission_id?: int, commission_created?: bool, request?: array, property?: array, affiliate_id?: int, has_valid_affiliate?: bool}
     */
    public static function finalizeOwnerPaymentReceipt(int $requestId, int $ownerId): array
    {
        if ($requestId <= 0 || $ownerId <= 0) {
            return ['ok' => false, 'error' => 'Dados inválidos para confirmar o recebimento.'];
        }

        $request = Request::findById($requestId);
        if (!$request) {
            return ['ok' => false, 'error' => 'Solicitação não encontrada.'];
        }

        $property = Property::find((int) ($request['property_id'] ?? 0));
        if (!$property || (int) ($property['affiliate_id'] ?? 0) !== $ownerId) {
            return ['ok' => false, 'error' => 'Sem permissão para confirmar recebimento.'];
        }

        if (($request['status'] ?? '') !== 'fechado_ganho'
            || ($request['closing_confirmation_status'] ?? '') !== Request::CLOSING_CONFIRMATION_PENDING) {
            return ['ok' => false, 'error' => 'Esta solicitação não está em fase de confirmação de recebimento.'];
        }

        if (($request['payment_confirmation_status'] ?? '') !== Request::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER) {
            return ['ok' => false, 'error' => 'Ainda não há declaração de pagamento do interessado.'];
        }

        if (self::isPropertyCommerciallyClosed($property)) {
            return ['ok' => false, 'error' => 'Esta solicitação não pode ser consolidada porque o imóvel já está vendido ou alugado.'];
        }

        $finalStatus = Property::resolveCommercialClosureStatus($property);
        if ($finalStatus === null) {
            return ['ok' => false, 'error' => 'O imóvel não tem finalidade comercial válida para fecho (venda ou aluguer).'];
        }

        $conn = (new Commission())->ConexaoDB();

        try {
            $conn->beginTransaction();

            if (!Request::confirmPaymentReceiptByOwner($requestId, $ownerId)) {
                $conn->rollBack();

                return ['ok' => false, 'error' => 'Não foi possível confirmar o recebimento.'];
            }

            $closing = self::issueCommissionAndCloseProperty($requestId, $request, $property, $ownerId, $finalStatus);
            if (!$closing['ok']) {
                $conn->rollBack();

                return $closing;
            }

            $conn->commit();

            return array_merge($closing, [
                'request' => Request::findById($requestId) ?: $request,
                'property' => Property::find((int) ($property['id'] ?? 0)) ?: $property,
            ]);
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            return ['ok' => false, 'error' => 'Não foi possível consolidar o fecho. Tente novamente.'];
        }
    }

    /**
     * @return array{ok: bool, error?: string, commission_id?: int, commission_created?: bool, request?: array, property?: array, affiliate_id?: int, has_valid_affiliate?: bool}
     */
    public static function finalizeModeratorDisputeWin(int $requestId, int $moderatorId, array $property): array
    {
        if ($requestId <= 0 || $moderatorId <= 0 || empty($property)) {
            return ['ok' => false, 'error' => 'Dados inválidos para consolidar o fecho.'];
        }

        $request = Request::findById($requestId);
        if (!$request || ($request['status'] ?? '') !== 'fechado_ganho') {
            return ['ok' => false, 'error' => 'Solicitação inválida para consolidação financeira.'];
        }

        if (self::isPropertyCommerciallyClosed($property)) {
            return ['ok' => false, 'error' => 'O imóvel já está vendido ou alugado.'];
        }

        $finalStatus = Property::resolveCommercialClosureStatus($property);
        if ($finalStatus === null) {
            return ['ok' => false, 'error' => 'O imóvel não tem finalidade comercial válida para fecho (venda ou aluguer).'];
        }

        $conn = (new Commission())->ConexaoDB();

        try {
            $conn->beginTransaction();

            if (!Request::consolidateFinancialClosingByModerator($requestId, $moderatorId)) {
                $conn->rollBack();

                return ['ok' => false, 'error' => 'Não foi possível consolidar o fecho financeiro.'];
            }

            $refreshedRequest = Request::findById($requestId);
            if (!$refreshedRequest) {
                $conn->rollBack();

                return ['ok' => false, 'error' => 'Solicitação não encontrada após consolidação.'];
            }

            $closing = self::issueCommissionAndCloseProperty(
                $requestId,
                $refreshedRequest,
                $property,
                $moderatorId,
                $finalStatus
            );
            if (!$closing['ok']) {
                $conn->rollBack();

                return $closing;
            }

            $conn->commit();

            return array_merge($closing, [
                'request' => $refreshedRequest,
                'property' => Property::find((int) ($property['id'] ?? 0)) ?: $property,
            ]);
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            return ['ok' => false, 'error' => 'Não foi possível consolidar o fecho. Tente novamente.'];
        }
    }

    /**
     * @return array{ok: bool, error?: string, commission_id?: int, commission_created?: bool, affiliate_id?: int, has_valid_affiliate?: bool}
     */
    private static function issueCommissionAndCloseProperty(
        int $requestId,
        array $request,
        array $property,
        int $actorId,
        string $finalStatus
    ): array {
        $propertyId = (int) ($property['id'] ?? 0);
        if ($propertyId <= 0) {
            return ['ok' => false, 'error' => 'Imóvel inválido para fecho.'];
        }

        $commissionResult = self::ensureCommissionFromRequest($requestId, $request, $property);
        if ($commissionResult['id'] <= 0) {
            return ['ok' => false, 'error' => 'Não foi possível registar a comissão do fecho.'];
        }

        if (!Property::setCommercialClosureStatus($propertyId, $finalStatus)) {
            return ['ok' => false, 'error' => 'Não foi possível actualizar o estado do imóvel para ' . $finalStatus . '.'];
        }

        Request::closeActiveByPropertyClosure($propertyId, $requestId);

        return [
            'ok' => true,
            'commission_id' => $commissionResult['id'],
            'commission_created' => $commissionResult['created'],
            'affiliate_id' => $commissionResult['affiliate_id'],
            'has_valid_affiliate' => $commissionResult['has_valid_affiliate'],
        ];
    }

    /**
     * @return array{id: int, created: bool, affiliate_id: int, has_valid_affiliate: bool}
     */
    public static function ensureCommissionFromRequest(int $requestId, array $request, array $property): array
    {
        $empty = ['id' => 0, 'created' => false, 'affiliate_id' => 0, 'has_valid_affiliate' => false];

        if ($requestId <= 0 || empty($property)) {
            return $empty;
        }

        $existingId = Commission::findIdByRequest($requestId);
        if ($existingId > 0) {
            $affiliateContext = self::resolveAffiliateContext($request, $property);

            return [
                'id' => $existingId,
                'created' => false,
                'affiliate_id' => $affiliateContext['affiliate_id'],
                'has_valid_affiliate' => $affiliateContext['has_valid_affiliate'],
            ];
        }

        $affiliateContext = self::resolveAffiliateContext($request, $property);
        $commissionBase = (float) ($request['modality_total_amount'] ?? 0);
        if ($commissionBase <= 0) {
            $commissionBase = (float) ($property['price'] ?? 0);
        }

        $ownerId = (int) ($property['affiliate_id'] ?? 0);
        $created = Commission::createFromRequest(
            $requestId,
            $affiliateContext['has_valid_affiliate'] ? $affiliateContext['affiliate_id'] : 0,
            (int) ($request['property_id'] ?? 0),
            $commissionBase,
            $ownerId
        );

        $commissionId = is_numeric($created) ? (int) $created : Commission::findIdByRequest($requestId);

        return [
            'id' => $commissionId,
            'created' => $commissionId > 0,
            'affiliate_id' => $affiliateContext['affiliate_id'],
            'has_valid_affiliate' => $affiliateContext['has_valid_affiliate'],
        ];
    }

    /**
     * @return array{affiliate_id: int, has_valid_affiliate: bool}
     */
    private static function resolveAffiliateContext(array $request, array $property): array
    {
        $requestAffiliateId = (int) ($request['affiliate_id'] ?? 0);
        $ownerId = (int) ($property['affiliate_id'] ?? 0);
        $propertyId = (int) ($request['property_id'] ?? 0);
        $hasValidAffiliate = $requestAffiliateId > 0
            && $requestAffiliateId !== $ownerId
            && PropertyAffiliate::isActiveAffiliate($requestAffiliateId, $propertyId);

        if ($hasValidAffiliate && Commission::hasActiveAffiliateCommissionForProperty($propertyId, $requestAffiliateId)) {
            $hasValidAffiliate = false;
        }

        return [
            'affiliate_id' => $requestAffiliateId,
            'has_valid_affiliate' => $hasValidAffiliate,
        ];
    }

    public static function notifyCommissionCreated(
        int $requestId,
        array $request,
        array $property,
        int $actorId,
        int $commissionId,
        bool $commissionCreated,
        bool $hasValidAffiliate,
        int $affiliateId
    ): void {
        if (!$commissionCreated || $commissionId <= 0) {
            return;
        }

        $ownerId = (int) ($property['affiliate_id'] ?? 0);
        if ($ownerId > 0) {
            $commissionRecord = Commission::findById($commissionId);
            $dueAt = (string) ($commissionRecord['due_at'] ?? '');
            $dueLabel = $dueAt !== ''
                ? date('d/m/Y', strtotime($dueAt))
                : date('d/m/Y', strtotime('+' . max(1, (int) ClassSettings::int('commission_due_days', 7)) . ' days'));
            $amountFormatted = number_format(max(0, (float) ($commissionRecord['amount'] ?? 0)), 0, ',', '.');
            Notification::notifyUser(
                $ownerId,
                'commission_payment_due',
                'Comissão a pagar',
                'Foi registada uma comissão de ' . $amountFormatted . ' Kz pelo fecho do imóvel "' . ((string) ($property['title'] ?? '')) . '". Pague até ' . $dueLabel . '.',
                [
                    'request_id' => $requestId,
                    'property_id' => (int) ($request['property_id'] ?? 0),
                    'commission_id' => $commissionId,
                ],
                $actorId
            );
        }

        if ($hasValidAffiliate && $affiliateId > 0) {
            Notification::notifyUser(
                $affiliateId,
                'commission_created',
                'Nova comissão registada',
                'Uma comissão foi registada para o imóvel "' . ((string) ($property['title'] ?? '')) . '". Verifique os detalhes na área de Afiliados.',
                ['request_id' => $requestId, 'property_id' => (int) ($request['property_id'] ?? 0)],
                $actorId
            );
        }
    }

    /**
     * Corrige imóveis com comissão pendente mas estado ainda não fechado comercialmente.
     */
    public static function repairPropertyStatusForPendingCommissions(): int
    {
        return Commission::repairPropertyStatusForPendingCommissions();
    }

    public static function isPropertyCommerciallyClosed(array $property): bool
    {
        return in_array((string) ($property['status'] ?? ''), ['vendido', 'alugado'], true);
    }
}
