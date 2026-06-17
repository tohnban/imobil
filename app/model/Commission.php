<?php

namespace App\model;

use Src\classes\ClassSettings;

class Commission extends ManipularBanco
{
    protected $table = 'commissions';

    public const OWNER_PAYMENT_NENHUM = 'nenhum';
    public const OWNER_PAYMENT_ENVIADO = 'enviado';
    public const OWNER_PAYMENT_APROVADO = 'aprovado';
    public const OWNER_PAYMENT_REJEITADO = 'rejeitado';

    public const AFFILIATE_PAYOUT_NENHUM = 'nenhum';
    public const AFFILIATE_PAYOUT_PENDENTE = 'pendente';
    public const AFFILIATE_PAYOUT_PAGO = 'pago';

    /** Bloqueio por comissão vencida sem comprovativo ou com rejeição. */
    public const OVERDUE_BLOCK_PAGAMENTO_PENDENTE = 'pagamento_pendente';

    /** Bloqueio mantido: comprovativo enviado, aguarda aprovação do admin. */
    public const OVERDUE_BLOCK_AGUARDANDO_VALIDACAO = 'aguardando_validacao';

    /**
     * Motivo de bloqueio por comissões vencidas, ou null se não houver bloqueio.
     * Comprovativo enviado (enviado) mantém bloqueio até o admin aprovar (pago/aprovado).
     */
    public static function getOverdueBlockReason(int $ownerId): ?string
    {
        if ($ownerId <= 0) {
            return null;
        }

        $db = new self();
        $sql = "SELECT c.owner_payment_status, c.owner_payment_submitted_at, c.owner_payment_proof_path
                FROM {$db->table} c
                JOIN properties p ON p.id = c.property_id
                WHERE p.affiliate_id = ?
                  AND c.status = 'pendente'
                  AND c.due_at IS NOT NULL
                  AND c.due_at < NOW()";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            return null;
        }

        $awaitingValidation = false;
        $paymentPending = false;

        foreach ($rows as $row) {
            $paymentStatus = self::resolveOwnerPaymentStatus($row);
            if ($paymentStatus === self::OWNER_PAYMENT_ENVIADO) {
                $awaitingValidation = true;
            } elseif (in_array($paymentStatus, [self::OWNER_PAYMENT_NENHUM, self::OWNER_PAYMENT_REJEITADO], true)) {
                $paymentPending = true;
            }
        }

        if ($awaitingValidation) {
            return self::OVERDUE_BLOCK_AGUARDANDO_VALIDACAO;
        }

        if ($paymentPending) {
            return self::OVERDUE_BLOCK_PAGAMENTO_PENDENTE;
        }

        return null;
    }

    public static function overdueBlockMessage(?string $reason): string
    {
        if ($reason === self::OVERDUE_BLOCK_AGUARDANDO_VALIDACAO) {
            return 'O comprovativo foi enviado e está aguardando validação da equipa financeira. As funcionalidades voltam a ficar disponíveis após aprovação do pagamento.';
        }

        return 'Tem comissões vencidas. Envie o comprovativo de pagamento e aguarde a validação da equipa para desbloquear a plataforma.';
    }

    public static function resolveOwnerPaymentStatus(array $commission): string
    {
        $status = trim((string) ($commission['owner_payment_status'] ?? ''));
        if (in_array($status, [
            self::OWNER_PAYMENT_NENHUM,
            self::OWNER_PAYMENT_ENVIADO,
            self::OWNER_PAYMENT_APROVADO,
            self::OWNER_PAYMENT_REJEITADO,
        ], true)) {
            return $status;
        }

        if (trim((string) ($commission['owner_payment_proof_path'] ?? '')) !== ''
            && !empty($commission['owner_payment_submitted_at'])) {
            return self::OWNER_PAYMENT_ENVIADO;
        }

        return self::OWNER_PAYMENT_NENHUM;
    }

    public static function hasValidAffiliate(array $commission): bool
    {
        $ownerId = (int) ($commission['owner_id'] ?? 0);
        $affiliateId = (int) ($commission['affiliate_id'] ?? 0);

        return (float) ($commission['affiliate_amount'] ?? 0) > 0
            && $affiliateId > 0
            && $affiliateId !== $ownerId;
    }

    /**
     * Impede segunda comissão de afiliado no mesmo imóvel (negócio já liquidado ou em curso).
     */
    public static function hasActiveAffiliateCommissionForProperty(int $propertyId, int $affiliateUserId): bool
    {
        if ($propertyId <= 0 || $affiliateUserId <= 0) {
            return false;
        }

        $db = new self();
        $sql = "SELECT c.id
                FROM {$db->table} c
                INNER JOIN properties p ON p.id = c.property_id
                WHERE c.property_id = ?
                  AND c.affiliate_id = ?
                  AND c.affiliate_id <> p.affiliate_id
                  AND c.affiliate_amount > 0
                  AND c.status IN ('pendente', 'pago')
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$propertyId, $affiliateUserId]);

        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function hasOwnerPaymentSubmitted(array $commission): bool
    {
        return self::resolveOwnerPaymentStatus($commission) === self::OWNER_PAYMENT_ENVIADO;
    }

    public static function canOwnerSubmitPayment(array $commission): bool
    {
        if ((string) ($commission['status'] ?? '') !== 'pendente') {
            return false;
        }

        return in_array(self::resolveOwnerPaymentStatus($commission), [
            self::OWNER_PAYMENT_NENHUM,
            self::OWNER_PAYMENT_REJEITADO,
        ], true);
    }

    public static function canValidateOwnerPayment(array $commission): bool
    {
        return (string) ($commission['status'] ?? '') === 'pendente'
            && self::resolveOwnerPaymentStatus($commission) === self::OWNER_PAYMENT_ENVIADO
            && trim((string) ($commission['owner_payment_proof_path'] ?? '')) !== '';
    }

    public static function resolveAffiliatePayoutStatus(array $commission): string
    {
        $status = trim((string) ($commission['affiliate_payout_status'] ?? ''));
        if (in_array($status, [self::AFFILIATE_PAYOUT_NENHUM, self::AFFILIATE_PAYOUT_PENDENTE, self::AFFILIATE_PAYOUT_PAGO], true)) {
            return $status;
        }

        if (!empty($commission['affiliate_payout_completed_at'])) {
            return self::AFFILIATE_PAYOUT_PAGO;
        }

        if (self::hasValidAffiliate($commission) && (string) ($commission['status'] ?? '') === 'pago') {
            return self::AFFILIATE_PAYOUT_PENDENTE;
        }

        return self::AFFILIATE_PAYOUT_NENHUM;
    }

    public static function isAffiliateCommissionPaid(array $commission): bool
    {
        return self::resolveAffiliatePayoutStatus($commission) === self::AFFILIATE_PAYOUT_PAGO;
    }

    /**
     * Estado apresentado ao afiliado (independente do status global da comissão ao proprietário).
     */
    public static function affiliateDisplayStatus(array $commission): string
    {
        if (!self::hasValidAffiliate($commission)) {
            return 'nenhum';
        }

        if (self::isAffiliateCommissionPaid($commission)) {
            return 'pago';
        }

        if ((string) ($commission['status'] ?? '') === 'cancelado') {
            return 'cancelado';
        }

        if ((string) ($commission['status'] ?? '') === 'pago'
            || self::resolveAffiliatePayoutStatus($commission) === self::AFFILIATE_PAYOUT_PENDENTE) {
            return 'aguardando_pagamento';
        }

        return 'pendente';
    }

    public static function affiliateDisplayStatusLabel(string $status): string
    {
        $labels = [
            'pendente' => 'Pendente',
            'aguardando_pagamento' => 'Aguardando pagamento',
            'pago' => 'Pago',
            'cancelado' => 'Cancelado',
            'nenhum' => '—',
        ];

        return $labels[$status] ?? '—';
    }

    public static function needsAffiliatePayout(array $commission): bool
    {
        $commissionId = (int) ($commission['id'] ?? 0);

        return $commissionId > 0
            && (string) ($commission['status'] ?? '') === 'pago'
            && self::hasValidAffiliate($commission)
            && self::resolveAffiliatePayoutStatus($commission) === self::AFFILIATE_PAYOUT_PENDENTE
            && empty($commission['affiliate_payout_completed_at'])
            && !PaymentTransaction::hasConfirmedAffiliatePayout($commissionId);
    }

    public static function findByIdForUpdate(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $db = new self();
        $conn = $db->ConexaoDB();
        $sql = "SELECT c.*, p.title AS property_title, p.affiliate_id AS owner_id
                FROM {$db->table} c
                INNER JOIN properties p ON c.property_id = p.id
                WHERE c.id = ?
                FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function existsByRequest($requestId)
    {
        return self::findIdByRequest((int) $requestId) > 0;
    }

    public static function findIdByRequest(int $requestId): int
    {
        if ($requestId <= 0) {
            return 0;
        }

        $db = new self();
        $sql = "SELECT id FROM {$db->table} WHERE request_id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$requestId]);
        $id = $stmt->fetchColumn();

        return is_numeric($id) ? (int) $id : 0;
    }

    public static function getByAffiliate($affiliateId, int $limit = 0, int $offset = 0)
    {
        $db = new self();
        $sql = "SELECT c.*, p.title, p.affiliate_id AS owner_id
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
                WHERE c.affiliate_id = ?
                  AND c.affiliate_amount > 0
                ORDER BY c.created_at DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$affiliateId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countByAffiliate(int $affiliateId): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} WHERE affiliate_id = ? AND affiliate_amount > 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([$affiliateId]);
        return (int) $stmt->fetchColumn();
    }

    public static function getAffiliateSummary(int $affiliateId): array
    {
        $db = new self();
        $sql = "SELECT
            SUM(CASE WHEN affiliate_amount > 0 THEN 1 ELSE 0 END) AS total,
            SUM(affiliate_amount) AS earned_total,
            SUM(CASE WHEN affiliate_payout_status = 'pago' OR affiliate_payout_completed_at IS NOT NULL THEN affiliate_amount ELSE 0 END) AS earned_paid,
            SUM(CASE WHEN affiliate_payout_status <> 'pago' AND affiliate_payout_completed_at IS NULL THEN affiliate_amount ELSE 0 END) AS earned_pending,
            SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN affiliate_amount ELSE 0 END) AS earned_this_month
        FROM {$db->table} WHERE affiliate_id = ? AND affiliate_amount > 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([$affiliateId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function getAllPending(int $limit = 0, int $offset = 0): array
    {
        $db = new self();
        $sql = "SELECT c.*, p.title AS property_title, p.affiliate_id AS owner_id,
                       ou.name AS owner_name, ou.phone AS owner_phone, ou.email AS owner_email,
                       u.name AS affiliate_name, u.phone AS affiliate_phone
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
                LEFT JOIN users ou ON p.affiliate_id = ou.id
                LEFT JOIN users u ON c.affiliate_id = u.id
                WHERE c.status = 'pendente'
                  AND c.owner_payment_status = ?
                ORDER BY c.owner_payment_submitted_at ASC, c.id ASC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([self::OWNER_PAYMENT_ENVIADO]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countAllPending(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table}
                WHERE status = 'pendente' AND owner_payment_status = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([self::OWNER_PAYMENT_ENVIADO]);
        return (int) $stmt->fetchColumn();
    }

    public static function sumAllPendingAmount(): float
    {
        $db = new self();
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM {$db->table}
                WHERE status = 'pendente' AND owner_payment_status = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([self::OWNER_PAYMENT_ENVIADO]);
        return (float) $stmt->fetchColumn();
    }

    public static function getAwaitingAffiliatePayout(int $limit = 50): array
    {
        $db = new self();
        $limit = max(1, min(200, $limit));
        $sql = "SELECT c.*, p.title AS property_title, p.affiliate_id AS owner_id,
                       u.name AS affiliate_name, u.phone AS affiliate_phone
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
                LEFT JOIN users u ON c.affiliate_id = u.id
                WHERE c.status = 'pago'
                  AND c.affiliate_amount > 0
                  AND c.affiliate_id <> p.affiliate_id
                  AND c.affiliate_payout_status = ?
                  AND c.affiliate_payout_completed_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM payment_transactions pt
                      WHERE pt.related_entity_type = 'commission'
                        AND pt.related_entity_id = c.id
                        AND pt.transaction_type = 'commission_payout'
                        AND pt.status = 'confirmado'
                  )
                ORDER BY c.paid_at ASC
                LIMIT {$limit}";
        $stmt = $db->prepare($sql);
        $stmt->execute([self::AFFILIATE_PAYOUT_PENDENTE]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function countAwaitingAffiliatePayout(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} c
                JOIN properties p ON p.id = c.property_id
                WHERE c.status = 'pago'
                  AND c.affiliate_amount > 0
                  AND c.affiliate_id <> p.affiliate_id
                  AND c.affiliate_payout_status = ?
                  AND c.affiliate_payout_completed_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM payment_transactions pt
                      WHERE pt.related_entity_type = 'commission'
                        AND pt.related_entity_id = c.id
                        AND pt.transaction_type = 'commission_payout'
                        AND pt.status = 'confirmado'
                  )";
        $stmt = $db->prepare($sql);
        $stmt->execute([self::AFFILIATE_PAYOUT_PENDENTE]);
        return (int) $stmt->fetchColumn();
    }

    public static function sumAwaitingAffiliatePayoutAmount(): float
    {
        $db = new self();
        $sql = "SELECT COALESCE(SUM(c.affiliate_amount), 0) FROM {$db->table} c
                JOIN properties p ON p.id = c.property_id
                WHERE c.status = 'pago'
                  AND c.affiliate_amount > 0
                  AND c.affiliate_id <> p.affiliate_id
                  AND c.affiliate_payout_status = ?
                  AND c.affiliate_payout_completed_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM payment_transactions pt
                      WHERE pt.related_entity_type = 'commission'
                        AND pt.related_entity_id = c.id
                        AND pt.transaction_type = 'commission_payout'
                        AND pt.status = 'confirmado'
                  )";
        $stmt = $db->prepare($sql);
        $stmt->execute([self::AFFILIATE_PAYOUT_PENDENTE]);

        return (float) $stmt->fetchColumn();
    }

    /** Soma comprovativos a validar (valor total) + pagamentos ao afiliado pendentes. */
    public static function sumCommissionsTabPendingAmount(): float
    {
        return self::sumAllPendingAmount() + self::sumAwaitingAffiliatePayoutAmount();
    }

    public static function getAll(int $limit = 200, int $offset = 0): array
    {
        $db = new self();
        $sql = "SELECT c.*, p.title AS property_title, u.name AS affiliate_name
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
            LEFT JOIN users u ON c.affiliate_id = u.id
                ORDER BY c.created_at DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countAll(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table}";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function markAsPaid(int $id, string $reference = '', ?int $confirmedBy = null): bool
    {
        return \App\services\CommissionSettlementService::approveOwnerPayment(
            $id,
            (int) ($confirmedBy ?? 0),
            $reference
        );
    }

    public static function markAsCancelled(int $id): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET status = 'cancelado' WHERE id = ? AND status = 'pendente'";
        $stmt = $db->prepare($sql);
        $ok = $stmt->execute([$id]);
        return $ok && $stmt->rowCount() > 0;
    }

    public static function hasOverdueByOwner(int $ownerId): bool
    {
        return self::getOverdueBlockReason($ownerId) !== null;
    }

    public static function countPendingByOwner(int $ownerId): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} c
                JOIN properties p ON p.id = c.property_id
                WHERE p.affiliate_id = ? AND c.status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId]);
        return (int) $stmt->fetchColumn();
    }

    public static function getPayableByOwner(int $ownerId): array
    {
        $db = new self();
        $sql = "SELECT c.*, p.title AS property_title, p.affiliate_id AS owner_id
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
                WHERE p.affiliate_id = ?
                  AND c.status = 'pendente'
                ORDER BY COALESCE(c.due_at, c.created_at) ASC, c.id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Estados de comissão visíveis no histórico do proprietário. */
    public static function ownerHistoryStatuses(): array
    {
        return ['pago', 'cancelado'];
    }

    public static function isOwnerHistoryStatus(string $status): bool
    {
        return in_array($status, self::ownerHistoryStatuses(), true);
    }

    public static function statusLabel(string $status): string
    {
        $labels = [
            'pendente' => 'Pendente',
            'pago' => 'Paga',
            'cancelado' => 'Cancelada',
        ];

        return $labels[$status] ?? '—';
    }

    public static function ownerPaymentStatusLabel(string $status): string
    {
        $labels = [
            self::OWNER_PAYMENT_NENHUM => 'Sem comprovativo',
            self::OWNER_PAYMENT_ENVIADO => 'Aguardando validação',
            self::OWNER_PAYMENT_APROVADO => 'Comprovativo aprovado',
            self::OWNER_PAYMENT_REJEITADO => 'Comprovativo rejeitado',
        ];

        return $labels[$status] ?? '—';
    }

    public static function getHistoryByOwner(int $ownerId, string $status, int $limit = 200): array
    {
        if ($ownerId <= 0 || !self::isOwnerHistoryStatus($status)) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $db = new self();
        $sql = "SELECT c.*, p.title AS property_title, p.affiliate_id AS owner_id
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
                WHERE p.affiliate_id = ?
                  AND c.status = ?
                ORDER BY COALESCE(c.paid_at, c.owner_payment_validated_at, c.created_at) DESC, c.id DESC
                LIMIT {$limit}";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId, $status]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function countHistoryByOwner(int $ownerId, string $status): int
    {
        if ($ownerId <= 0 || !self::isOwnerHistoryStatus($status)) {
            return 0;
        }

        $db = new self();
        $sql = "SELECT COUNT(*)
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
                WHERE p.affiliate_id = ?
                  AND c.status = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId, $status]);

        return (int) $stmt->fetchColumn();
    }

    public static function findPayableForOwner(int $commissionId, int $ownerId): ?array
    {
        if ($commissionId <= 0 || $ownerId <= 0) {
            return null;
        }

        $db = new self();
        $sql = "SELECT c.*, p.title AS property_title, p.affiliate_id AS owner_id
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
                WHERE c.id = ?
                  AND p.affiliate_id = ?
                  AND c.status = 'pendente'
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$commissionId, $ownerId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function ownerPaymentProofPublicUrl(?string $path): string
    {
        return \App\model\Request::paymentProofPublicUrl($path);
    }

    public static function submitOwnerPayment(
        int $commissionId,
        int $ownerId,
        string $proofPath,
        string $reference = '',
        ?int $methodId = null,
        ?int $channelId = null
    ): bool {
        $commission = self::findPayableForOwner($commissionId, $ownerId);
        if (!$commission || !self::canOwnerSubmitPayment($commission)) {
            return false;
        }

        $proofPath = trim($proofPath);
        if ($proofPath === '') {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET owner_payment_proof_path = ?,
                    owner_payment_reference = ?,
                    owner_payment_submitted_at = NOW(),
                    owner_payment_method_id = ?,
                    owner_payment_channel_id = ?,
                    owner_payment_status = ?,
                    owner_payment_validated_by = NULL,
                    owner_payment_validated_at = NULL,
                    owner_payment_rejection_reason = NULL
                WHERE id = ?
                  AND status = 'pendente'
                  AND owner_payment_status IN (?, ?)";
        $stmt = $db->prepare($sql);

        $ok = $stmt->execute([
            $proofPath,
            trim($reference) !== '' ? trim($reference) : null,
            $methodId > 0 ? $methodId : null,
            $channelId > 0 ? $channelId : null,
            self::OWNER_PAYMENT_ENVIADO,
            $commissionId,
            self::OWNER_PAYMENT_NENHUM,
            self::OWNER_PAYMENT_REJEITADO,
        ]) && $stmt->rowCount() > 0;

        if ($ok) {
            \App\services\HeaderShellService::invalidateCommissionBlock($ownerId);
        }

        return $ok;
    }

    public static function findById(int $id): ?array
    {
        $db = new self();
        $sql = "SELECT c.*, p.title AS property_title, p.affiliate_id AS owner_id,
                       u.name AS affiliate_name, u.id AS affiliate_user_id
                FROM {$db->table} c
                JOIN properties p ON c.property_id = p.id
            LEFT JOIN users u ON c.affiliate_id = u.id
                WHERE c.id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getSummaryStats(): array
    {
        $db = new self();
        $sql = "SELECT
            COUNT(*) AS total,
            SUM(affiliate_amount) AS total_affiliate,
            SUM(system_amount) AS total_system,
            SUM(amount) AS total_amount,
            SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN affiliate_amount ELSE 0 END) AS affiliate_this_month
        FROM {$db->table}";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function getTopAffiliates(int $limit = 5): array
    {
        $db = new self();
        $sql = "SELECT u.name, u.id AS user_id, SUM(c.affiliate_amount) AS total, COUNT(*) AS count
                FROM {$db->table} c
                JOIN users u ON c.affiliate_id = u.id
                WHERE c.affiliate_amount > 0
                GROUP BY c.affiliate_id, u.name, u.id
                ORDER BY total DESC
                LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function createFromRequest($requestId, $affiliateId, $propertyId, $modalityTotalAmount, ?int $ownerId = null)
    {
        $db = new self();

        $normalizedAffiliateId = (int) $affiliateId;
        $normalizedOwnerId     = (int) ($ownerId ?? 0);
        $hasAffiliate = $normalizedAffiliateId > 0
            && ($normalizedOwnerId <= 0 || $normalizedAffiliateId !== $normalizedOwnerId);

        if ($hasAffiliate && self::hasActiveAffiliateCommissionForProperty((int) $propertyId, $normalizedAffiliateId)) {
            $hasAffiliate = false;
        }

        // Percentuais lidos da tabela settings (configuráveis pelo admin).
        if ($hasAffiliate) {
            $systemPct    = ClassSettings::float('commission_system_pct', 2.0);
            $affiliatePct = ClassSettings::float('commission_affiliate_pct', 3.0);
        } else {
            $systemPct    = ClassSettings::float('commission_system_only_pct', 5.0);
            $affiliatePct = 0.0;
        }
        $totalPct = $systemPct + $affiliatePct;
        $dueDays  = ClassSettings::int('commission_due_days', 7);

        $base            = max(0.0, (float) $modalityTotalAmount);
        $systemAmount    = ($base * $systemPct)    / 100;
        $affiliateAmount = ($base * $affiliatePct) / 100;
        $totalAmount     = $systemAmount + $affiliateAmount;

        $data = [
            // affiliate_id precisa existir por constraint atual; em casos sem afiliado válido usa-se ownerId sem payout.
            'affiliate_id'    => $hasAffiliate ? $normalizedAffiliateId : $normalizedOwnerId,
            'property_id'     => $propertyId,
            'request_id'      => $requestId,
            'amount'          => $totalAmount,
            'total_pct'       => $totalPct,
            'system_pct'      => $systemPct,
            'affiliate_pct'   => $affiliatePct,
            'system_amount'   => $systemAmount,
            'affiliate_amount' => $affiliateAmount,
            'affiliate_payout_status' => self::AFFILIATE_PAYOUT_NENHUM,
            'due_at'          => date('Y-m-d H:i:s', strtotime("+{$dueDays} days")),
        ];

        try {
            return $db->Salvar($data, $db->table);
        } catch (\PDOException $e) {
            // 23000: integrity constraint violation (e.g. duplicate request_id).
            if ((string) $e->getCode() === '23000') {
                return self::existsByRequest((int) $requestId);
            }
            throw $e;
        }
    }

    /**
     * Corrige imóveis com comissão pendente mas estado ainda não fechado comercialmente.
     */
    public static function repairPropertyStatusForPendingCommissions(): int
    {
        $db = new self();
        $sql = "SELECT c.id AS commission_id, c.property_id, c.request_id, p.status, p.purpose
                FROM {$db->table} c
                INNER JOIN properties p ON p.id = c.property_id
                INNER JOIN requests r ON r.id = c.request_id
                WHERE c.status = 'pendente'
                  AND p.status NOT IN ('vendido', 'alugado', 'eliminado')
                  AND r.status = 'fechado_ganho'
                  AND r.closing_confirmation_status = ?
                ORDER BY c.id ASC
                LIMIT 200";
        $stmt = $db->prepare($sql);
        $stmt->execute([Request::CLOSING_CONFIRMATION_CONFIRMED]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $repaired = 0;
        foreach ($rows as $row) {
            $propertyId = (int) ($row['property_id'] ?? 0);
            if ($propertyId <= 0) {
                continue;
            }

            $property = Property::find($propertyId);
            if (!$property) {
                continue;
            }

            $finalStatus = Property::resolveCommercialClosureStatus($property);
            if ($finalStatus === null) {
                continue;
            }

            if (Property::setCommercialClosureStatus($propertyId, $finalStatus)) {
                $repaired++;
            }
        }

        return $repaired;
    }
}
