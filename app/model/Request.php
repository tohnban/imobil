<?php

namespace App\model;

class Request extends ManipularBanco
{
    protected $table = 'requests';

    public const OPEN_STATUSES = ['em_contacto'];

    public const LEGACY_OPEN_STATUSES = [];

    public const ACTIVE_NEGOTIATION_STATUSES = ['em_contacto'];

    public const CANCELLABLE_STATUSES = ['em_contacto'];

    public const CLOSED_STATUSES = ['fechado_ganho', 'cancelado', 'expirado'];

    public const DISPUTE_ELIGIBLE_STATUSES = ['fechado_ganho', 'cancelado'];

    public const AUTO_EXPIRE_DAYS = 30;

    public const DISPUTE_WINDOW_DAYS = 30;

    public const CLOSING_CONFIRMATION_PENDING = 'pendente';

    public const CLOSING_CONFIRMATION_CONFIRMED = 'confirmado';

    public const CLOSING_CONFIRMATION_CONTESTED = 'contestada';

    public const PAYMENT_CONFIRMATION_PENDING = 'pendente';

    public const PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER = 'declarado_comprador';

    public const PAYMENT_CONFIRMATION_CONFIRMED_BY_OWNER = 'confirmado_proprietario';

    public const PAYMENT_CONFIRMATION_CONTESTED = 'contestado';

    public const DISPUTE_STATUS_NONE      = 'nenhuma';
    public const DISPUTE_STATUS_OPEN      = 'aberta';
    public const DISPUTE_STATUS_REVIEWING = 'em_analise';
    public const DISPUTE_STATUS_WON       = 'julgada_procedente';
    public const DISPUTE_STATUS_LOST      = 'julgada_improcedente';

    private const TRANSITIONS = [
        'em_contacto' => ['fechado_ganho', 'cancelado'],
        'fechado_ganho' => ['em_disputa'],
        'expirado' => [],
        'em_disputa' => ['fechado_ganho', 'cancelado'],
        'cancelado' => [],
    ];

    private const MANAGEMENT_ACTION_LABELS = [
        'em_contacto' => 'Iniciar contacto',
        'fechado_ganho' => 'Fecho ganho',
        'em_disputa' => 'Abrir disputa',
        'cancelado' => 'Cancelar',
    ];

    public static function commercialCycleByStatus(string $status, ?string $closingConfirmationStatus = null): string
    {
        if ($status === 'em_disputa') {
            return 'em_disputa';
        }

        if (
            $status === 'fechado_ganho'
            && ($closingConfirmationStatus === null || $closingConfirmationStatus === self::CLOSING_CONFIRMATION_CONFIRMED)
        ) {
            return 'fecho_ganho';
        }

        if (
            in_array($status, self::ACTIVE_NEGOTIATION_STATUSES, true)
            || ($status === 'fechado_ganho' && $closingConfirmationStatus === self::CLOSING_CONFIRMATION_PENDING)
        ) {
            return 'em_aberto';
        }

        return 'fecho_perdido';
    }

    public static function statusLabel(string $status, ?string $closingConfirmationStatus = null): string
    {
        $map = [
            'pendente' => 'Pendente',
            'analise' => 'Em analise',
            'em_contacto' => 'Em contacto',
            'proposta' => 'Em proposta',
            'fechado_ganho' => $closingConfirmationStatus === self::CLOSING_CONFIRMATION_PENDING
                ? 'Fecho ganho pendente de confirmacao'
                : 'Fecho ganho',
            'cancelado' => 'Cancelado',
            'expirado' => 'Expirado',
            'em_disputa' => 'Em disputa',
        ];

        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function allowedStatuses()
    {
        return ['em_contacto', 'fechado_ganho', 'cancelado', 'expirado', 'em_disputa'];
    }

    public static function canTransition(string $currentStatus, string $nextStatus): bool
    {
        return in_array($nextStatus, self::TRANSITIONS[$currentStatus] ?? [], true);
    }

    public static function isDisputeWindowOpen(array $request, int $windowDays = self::DISPUTE_WINDOW_DAYS): bool
    {
        // If a dispute is already open or was resolved, the window is moot.
        $disputeStatus = (string) ($request['dispute_status'] ?? self::DISPUTE_STATUS_NONE);
        if ($disputeStatus !== self::DISPUTE_STATUS_NONE) {
            return false;
        }

        $status = (string) ($request['status'] ?? '');
        if (!in_array($status, self::DISPUTE_ELIGIBLE_STATUSES, true)) {
            return false;
        }

        // Prefer the explicit dispute_open_until column when available.
        $disputeOpenUntil = $request['dispute_open_until'] ?? null;
        if (is_string($disputeOpenUntil) && trim($disputeOpenUntil) !== '') {
            $until = strtotime($disputeOpenUntil);
            return $until !== false && $until >= time();
        }

        // Fallback: derive window from the closing/update timestamp.
        $referenceDate = null;
        if ($status === 'fechado_ganho') {
            $referenceDate = $request['closing_confirmed_at'] ?? $request['closing_declared_at'] ?? null;
        }
        if (!$referenceDate) {
            $referenceDate = $request['last_interaction_at'] ?? $request['updated_at'] ?? $request['created_at'] ?? null;
        }

        if (!is_string($referenceDate) || trim($referenceDate) === '') {
            return false;
        }

        $referenceTimestamp = strtotime($referenceDate);
        if ($referenceTimestamp === false) {
            return false;
        }

        return $referenceTimestamp >= strtotime('-' . max(1, $windowDays) . ' days');
    }

    public static function nextStatusesForNegotiationActor(string $currentStatus, bool $canManageAllRequests, bool $disputeWindowOpen = false): array
    {
        $targets = self::TRANSITIONS[$currentStatus] ?? [];

        if ($currentStatus === 'em_disputa') {
            return $canManageAllRequests ? $targets : [];
        }

        if (in_array($currentStatus, self::DISPUTE_ELIGIBLE_STATUSES, true)) {
            return [];
        }

        if ($canManageAllRequests) {
            return array_values(array_filter($targets, static function (string $status): bool {
                return in_array($status, ['em_contacto', 'cancelado'], true);
            }));
        }

        return array_values(array_filter($targets, static function (string $status): bool {
            return in_array($status, ['em_contacto', 'fechado_ganho', 'cancelado'], true);
        }));
    }

    public static function managementActionsFor(string $currentStatus, bool $canManageAllRequests, bool $disputeWindowOpen = false): array
    {
        $actions = [];
        $targets = self::nextStatusesForNegotiationActor($currentStatus, $canManageAllRequests, $disputeWindowOpen);

        foreach ($targets as $targetStatus) {
            $actions[$targetStatus] = self::MANAGEMENT_ACTION_LABELS[$targetStatus]
                ?? self::statusLabel($targetStatus);
        }

        return $actions;
    }

    public static function requesterActionsFor(
        string $status,
        ?string $closingConfirmationStatus,
        ?string $paymentConfirmationStatus = null,
        bool $disputeWindowOpen = false
    ): array {
        $actions = [];

        if ($status === 'fechado_ganho' && $closingConfirmationStatus === self::CLOSING_CONFIRMATION_PENDING) {
            $normalizedPaymentStatus = (string) ($paymentConfirmationStatus ?? self::PAYMENT_CONFIRMATION_PENDING);
            if (
                $normalizedPaymentStatus === ''
                || $normalizedPaymentStatus === self::PAYMENT_CONFIRMATION_PENDING
            ) {
                $actions['confirm_closing'] = 'Declarar pagamento';
            }

            if (in_array($normalizedPaymentStatus, [
                self::PAYMENT_CONFIRMATION_PENDING,
                self::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER,
            ], true)) {
                $actions['contest_closing'] = 'Contestar fecho';
            }
        }

        if (in_array($status, self::ACTIVE_NEGOTIATION_STATUSES, true)) {
            $actions['cancel'] = 'Cancelar';
        }

        if (
            $disputeWindowOpen
            && in_array($status, self::DISPUTE_ELIGIBLE_STATUSES, true)
            && !($status === 'fechado_ganho' && $closingConfirmationStatus === self::CLOSING_CONFIRMATION_PENDING)
        ) {
            $actions['open_dispute'] = 'Abrir disputa';
        }

        return $actions;
    }

    public static function ownerPaymentActionsFor(
        string $status,
        ?string $closingConfirmationStatus,
        ?string $paymentConfirmationStatus = null,
        bool $disputeWindowOpen = false
    ): array {
        $actions = [];
        $normalizedPaymentStatus = (string) ($paymentConfirmationStatus ?? self::PAYMENT_CONFIRMATION_PENDING);

        if (
            $status === 'fechado_ganho'
            && $closingConfirmationStatus === self::CLOSING_CONFIRMATION_PENDING
            && $normalizedPaymentStatus === self::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER
        ) {
            $actions['confirm_payment_receipt'] = 'Confirmar recebimento';
            $actions['contest_payment'] = 'Contestar pagamento';
        }

        return $actions;
    }

    public static function closingConfirmationLabel(?string $status): string
    {
        if ($status === self::CLOSING_CONFIRMATION_PENDING) {
            return 'Pendente';
        }
        if ($status === self::CLOSING_CONFIRMATION_CONFIRMED) {
            return 'Confirmado';
        }
        if ($status === self::CLOSING_CONFIRMATION_CONTESTED) {
            return 'Contestada';
        }
        return '—';
    }

    public static function paymentConfirmationLabel(?string $status): string
    {
        if ($status === self::PAYMENT_CONFIRMATION_PENDING || $status === null || $status === '') {
            return 'Pendente';
        }
        if ($status === self::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER) {
            return 'Declarado pelo comprador';
        }
        if ($status === self::PAYMENT_CONFIRMATION_CONFIRMED_BY_OWNER) {
            return 'Confirmado pelo proprietario';
        }
        if ($status === self::PAYMENT_CONFIRMATION_CONTESTED) {
            return 'Contestado';
        }

        return ucfirst(str_replace('_', ' ', (string) $status));
    }

    public static function paymentProofPublicUrl(?string $path): string
    {
        return \Src\classes\ClassMediaUrl::proof($path);
    }

    public static function hasVisiblePaymentProof(array $request): bool
    {
        $proofPath = trim((string) ($request['payment_proof_path'] ?? ''));
        if ($proofPath === '') {
            return false;
        }

        $paymentStatus = (string) ($request['payment_confirmation_status'] ?? self::PAYMENT_CONFIRMATION_PENDING);

        return in_array($paymentStatus, [
            self::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER,
            self::PAYMENT_CONFIRMATION_CONFIRMED_BY_OWNER,
            self::PAYMENT_CONFIRMATION_CONTESTED,
        ], true);
    }

    public static function formatAmountKz(float $amount): string
    {
        return number_format(max(0, $amount), 0, ',', '.') . ' Kz';
    }

    public static function paymentTermLabel(?string $term): string
    {
        $labels = [
            'mensal' => 'Mensal',
            'trimestral' => 'Trimestral',
            'semestral' => 'Semestral',
            'anual' => 'Anual',
        ];

        $term = trim((string) $term);

        return $labels[$term] ?? ($term !== '' ? ucfirst($term) : '');
    }

    public static function requestTypeLabel(?string $type): string
    {
        $labels = [
            'compra' => 'Compra',
            'aluguer_curto' => 'Aluguer curto',
            'aluguer_longo' => 'Aluguer longo',
        ];

        $type = trim((string) $type);

        return $labels[$type] ?? ($type !== '' ? ucfirst(str_replace('_', ' ', $type)) : '—');
    }

    /**
     * @return array{
     *     total_amount: float,
     *     total_formatted: string,
     *     breakdown: ?string,
     *     reference_line: ?string,
     *     is_rental_modality: bool
     * }
     */
    public static function negotiationAmountSummary(array $request): array
    {
        $type = (string) ($request['type'] ?? '');
        $propertyPurpose = (string) ($request['property_purpose'] ?? '');
        $monthlyReference = (float) ($request['monthly_reference_amount'] ?? 0);
        $monthsCount = max(1, (int) ($request['months_count'] ?? 1));
        $paymentTerm = trim((string) ($request['payment_term'] ?? ''));
        $modalityTotal = (float) ($request['modality_total_amount'] ?? 0);
        $propertyPrice = (float) ($request['price'] ?? 0);
        $rentalDays = max(0, (int) ($request['rental_days'] ?? 0));

        if ($modalityTotal <= 0 && $monthlyReference > 0) {
            $modalityTotal = $monthlyReference;
        }
        if ($modalityTotal <= 0 && $propertyPrice > 0) {
            $modalityTotal = $propertyPrice;
        }

        $breakdown = null;
        $referenceLine = null;
        $isLongRent = $type === 'aluguer_longo' || $propertyPurpose === 'aluguer_longo';
        $isShortRent = $type === 'aluguer_curto' || $propertyPurpose === 'aluguer_curto';

        if ($isLongRent) {
            $unitAmount = $monthlyReference > 0 ? $monthlyReference : $propertyPrice;
            $termLabel = self::paymentTermLabel($paymentTerm);

            if ($paymentTerm !== '' && $unitAmount > 0) {
                if ($modalityTotal <= $unitAmount && $monthsCount > 1) {
                    $modalityTotal = $unitAmount * $monthsCount;
                }

                $breakdown = self::formatAmountKz($unitAmount) . '/mês × ' . $monthsCount . ' '
                    . ($monthsCount === 1 ? 'mês' : 'meses')
                    . ($termLabel !== '' ? ' (' . $termLabel . ')' : '');
                $referenceLine = 'Modalidade de pagamento: ' . ($termLabel !== '' ? $termLabel : $paymentTerm);
            } elseif ($unitAmount > 0 && $modalityTotal > $unitAmount) {
                $breakdown = self::formatAmountKz($unitAmount) . '/mês × ' . $monthsCount . ' '
                    . ($monthsCount === 1 ? 'mês' : 'meses');
            } elseif ($unitAmount > 0) {
                $referenceLine = 'Referência mensal: ' . self::formatAmountKz($unitAmount);
            }
        } elseif ($isShortRent) {
            $unitAmount = $monthlyReference > 0 ? $monthlyReference : $propertyPrice;

            if ($rentalDays > 0 && $unitAmount > 0) {
                if ($modalityTotal <= $unitAmount) {
                    $modalityTotal = $unitAmount * $rentalDays;
                }

                $breakdown = self::formatAmountKz($unitAmount) . '/dia × ' . $rentalDays . ' '
                    . ($rentalDays === 1 ? 'dia' : 'dias');
            } elseif ($unitAmount > 0) {
                $referenceLine = 'Tarifa de referência: ' . self::formatAmountKz($unitAmount) . '/dia';
            }
        } elseif ($modalityTotal <= 0 && $propertyPrice > 0) {
            $modalityTotal = $propertyPrice;
            $referenceLine = 'Valor de venda do imóvel';
        } else {
            $referenceLine = 'Valor de venda acordado na solicitação';
        }

        return [
            'total_amount' => $modalityTotal,
            'total_formatted' => self::formatAmountKz($modalityTotal),
            'breakdown' => $breakdown,
            'reference_line' => $referenceLine,
            'is_rental_modality' => $isLongRent || $isShortRent,
        ];
    }

    public static function isChatWritable(array $request): bool
    {
        $status = (string) ($request['status'] ?? '');
        $disputeStatus = (string) ($request['dispute_status'] ?? self::DISPUTE_STATUS_NONE);
        $closingConfirmationStatus = (string) ($request['closing_confirmation_status'] ?? '');
        $propertyStatus = (string) ($request['property_status'] ?? '');

        if (in_array($propertyStatus, ['vendido', 'alugado'], true)) {
            return false;
        }

        if ($disputeStatus !== self::DISPUTE_STATUS_NONE) {
            return false;
        }

        if ($status === 'em_contacto') {
            return true;
        }

        return $status === 'fechado_ganho'
            && $closingConfirmationStatus === self::CLOSING_CONFIRMATION_PENDING;
    }

    public static function getByUser($userId, int $limit = 0, int $offset = 0)
    {
        $db = new self();
        $sql = "SELECT r.*, p.title, p.price, p.status AS property_status,
                   u.id AS owner_id, u.username AS owner_username, u.name AS owner_name, u.email AS owner_email
                FROM {$db->table} r
                JOIN properties p ON r.property_id = p.id
                LEFT JOIN users u ON p.affiliate_id = u.id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countByUser(int $userId): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} WHERE user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function getAllWithUser(int $limit = 0, int $offset = 0)
    {
        $db = new self();
        $sql = "SELECT r.*, p.title, p.price, p.status AS property_status,
                   u.id AS requester_id, u.username AS requester_username, u.name AS requester_name, u.email AS requester_email,
                   owner.id AS owner_id, owner.username AS owner_username, owner.name AS owner_name, owner.email AS owner_email
                FROM {$db->table} r
                JOIN properties p ON r.property_id = p.id
                JOIN users u ON r.user_id = u.id
            LEFT JOIN users owner ON p.affiliate_id = owner.id
                ORDER BY r.created_at DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countAllWithUser(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table}";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function getDisputes(int $limit = 20, int $offset = 0, string $statusFilter = 'all'): array
    {
        $db = new self();
        $allowedFilters = ['all', 'nenhuma', 'aberta', 'em_analise', 'julgada_procedente', 'julgada_improcedente'];
        if (!in_array($statusFilter, $allowedFilters, true)) {
            $statusFilter = 'all';
        }

        $baseWhere = "(r.dispute_status <> 'nenhuma'
                   OR (r.dispute_status = 'nenhuma' AND r.status = 'em_disputa'))";
        $params = [];
        if ($statusFilter !== 'all') {
            $baseWhere .= ' AND r.dispute_status = ?';
            $params[] = $statusFilter;
        }

        // Filter by dispute_status to include both 'aberta' and 'em_analise' (backward compat: also include status = 'em_disputa').
        $sql = "SELECT r.*, p.title, p.price, p.status AS property_status,
                       u.id AS requester_id, u.username AS requester_username, u.name AS requester_name, u.email AS requester_email,
                       owner.id AS owner_id, owner.username AS owner_username, owner.name AS owner_name, owner.email AS owner_email
                FROM {$db->table} r
                JOIN properties p ON r.property_id = p.id
                JOIN users u ON r.user_id = u.id
                LEFT JOIN users owner ON p.affiliate_id = owner.id
                WHERE {$baseWhere}
                ORDER BY r.updated_at DESC, r.created_at DESC
                LIMIT " . max(1, (int) $limit) . ' OFFSET ' . (int) $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countDisputes(string $statusFilter = 'all'): int
    {
        $db = new self();
        $allowedFilters = ['all', 'nenhuma', 'aberta', 'em_analise', 'julgada_procedente', 'julgada_improcedente'];
        if (!in_array($statusFilter, $allowedFilters, true)) {
            $statusFilter = 'all';
        }

        $where = "(r.dispute_status <> 'nenhuma'
                   OR (r.dispute_status = 'nenhuma' AND r.status = 'em_disputa'))";
        $params = [];
        if ($statusFilter !== 'all') {
            $where .= ' AND r.dispute_status = ?';
            $params[] = $statusFilter;
        }

        $sql = "SELECT COUNT(*) FROM {$db->table} r
                WHERE {$where}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function getOpenForAdmin()
    {
        $db = new self();
        // Returns all active-negotiation requests for admin review.
        $sql = "SELECT r.*, p.title, p.price, u.name AS requester_name, u.email AS requester_email
                FROM {$db->table} r
                JOIN properties p ON r.property_id = p.id
                JOIN users u ON r.user_id = u.id
            WHERE r.status = 'em_contacto'
                ORDER BY r.created_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getByPropertyOwner($ownerId, int $limit = 0, int $offset = 0)
    {
        $db = new self();
        $sql = "SELECT r.*, p.title, p.price, p.id as property_id, p.status AS property_status,
                   u.id AS requester_id, u.username AS requester_username, u.name AS requester_name, u.email AS requester_email,
                   p.affiliate_id AS owner_id
                FROM {$db->table} r
                JOIN properties p ON r.property_id = p.id
                JOIN users u ON r.user_id = u.id
                WHERE p.affiliate_id = ?
                ORDER BY r.created_at DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countByPropertyOwner(int $ownerId): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} r
                JOIN properties p ON r.property_id = p.id
                WHERE p.affiliate_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId]);
        return (int) $stmt->fetchColumn();
    }

    public static function findById($id)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([(int) $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function getByIdWithContext(int $id): ?array
    {
        $db = new self();
        $sql = "SELECT r.*, p.title, p.price, p.purpose AS property_purpose,
                       p.rental_days, p.rental_months, p.status AS property_status,
                       u.id AS requester_id, u.username AS requester_username, u.name AS requester_name, u.email AS requester_email,
                       owner.id AS owner_id, owner.username AS owner_username, owner.name AS owner_name, owner.email AS owner_email
                FROM {$db->table} r
                JOIN properties p ON r.property_id = p.id
                JOIN users u ON r.user_id = u.id
                LEFT JOIN users owner ON p.affiliate_id = owner.id
                WHERE r.id = ?
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function updateStatus($id, $status)
    {
        if (!in_array($status, self::allowedStatuses(), true)) {
            return false;
        }

        $db = new self();

        // When landing in a dispute-eligible status, open the 30-day dispute window
        // and mirror the value in commercial_status.
        if (in_array($status, self::DISPUTE_ELIGIBLE_STATUSES, true)) {
            $sql = "UPDATE {$db->table}
                    SET status = ?,
                        commercial_status   = ?,
                        last_interaction_at = NOW(),
                        dispute_open_until  = DATE_ADD(NOW(), INTERVAL ? DAY),
                        updated_at = NOW()
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            if (!$stmt->execute([$status, $status, self::DISPUTE_WINDOW_DAYS, (int) $id])) {
                return false;
            }
        } else {
            $sql = "UPDATE {$db->table}
                    SET status = ?,
                        commercial_status   = ?,
                        last_interaction_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            if (!$stmt->execute([$status, $status, (int) $id])) {
                return false;
            }
        }

        return $stmt->rowCount() > 0;
    }

    public static function closeActiveByPropertyClosure(int $propertyId, ?int $excludeRequestId = null): int
    {
        if ($propertyId <= 0) {
            return 0;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET status = 'cancelado',
                    commercial_status = 'cancelado',
                    dispute_open_until = DATE_ADD(NOW(), INTERVAL ? DAY),
                    last_interaction_at = NOW(),
                    updated_at = NOW()
                WHERE property_id = ?
                  AND status IN ('em_contacto', 'fechado_ganho')";

        $params = [self::DISPUTE_WINDOW_DAYS, $propertyId];
        if ($excludeRequestId !== null && $excludeRequestId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeRequestId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->rowCount();
    }

    /**
     * Open a dispute for a closed/cancelled request.
     * Sets dispute_status = 'aberta' and status = 'em_disputa'.
     * commercial_status is NOT changed — it retains the last commercial outcome.
     */
    public static function openDisputeStatus(int $requestId): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET status              = 'em_disputa',
                    dispute_status      = ?,
                    last_interaction_at = NOW(),
                    updated_at          = NOW()
                WHERE id = ?
                  AND status IN ('fechado_ganho', 'cancelado')
                  AND dispute_status = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([self::DISPUTE_STATUS_OPEN, (int) $requestId, self::DISPUTE_STATUS_NONE])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Resolve a dispute with a final admin judgment.
     * Sets dispute_status to julgada_procedente or julgada_improcedente,
     * updates commercial_status and status to the outcome, clears dispute window.
     */
    public static function resolveDispute(int $requestId, string $outcome, int $actorId): bool
    {
        if (!in_array($outcome, ['fechado_ganho', 'cancelado'], true)) {
            return false;
        }

        $disputeStatus = $outcome === 'fechado_ganho'
            ? self::DISPUTE_STATUS_WON
            : self::DISPUTE_STATUS_LOST;

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET status              = ?,
                    commercial_status   = ?,
                    dispute_status      = ?,
                    dispute_open_until  = NULL,
                    last_interaction_at = NOW(),
                    updated_at          = NOW()
                WHERE id = ?
                  AND dispute_status IN (?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([
            $outcome,
            $outcome,
            $disputeStatus,
            (int) $requestId,
            self::DISPUTE_STATUS_OPEN,
            self::DISPUTE_STATUS_REVIEWING,
        ])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function hasActiveRequest(int $userId, int $propertyId): bool
    {
        $db = new self();
        $sql = "SELECT id FROM {$db->table}
                WHERE user_id = ? AND property_id = ?
                  AND status NOT IN ('cancelado', 'expirado')
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $propertyId]);
        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function create($data)
    {
        $db = new self();

        if (empty($data['status'])) {
            $data['status'] = 'em_contacto';
        }

        $now = date('Y-m-d H:i:s');
        if (empty($data['next_followup_at'])) {
            $data['next_followup_at'] = date('Y-m-d H:i:s', strtotime($now . ' +7 days'));
        }
        if (empty($data['attribution_expires_at'])) {
            $data['attribution_expires_at'] = date('Y-m-d H:i:s', strtotime($now . ' +90 days'));
        }
        if (!array_key_exists('closing_confirmation_status', $data)) {
            $data['closing_confirmation_status'] = null;
        }
        if (!array_key_exists('payment_confirmation_status', $data)) {
            $data['payment_confirmation_status'] = null;
        }
        if (!array_key_exists('contact_started_at', $data)) {
            $data['contact_started_at'] = $now;
        }
        if (!array_key_exists('last_interaction_at', $data)) {
            $data['last_interaction_at'] = $now;
        }
        if (!array_key_exists('commercial_status', $data)) {
            $data['commercial_status'] = $data['status'];
        }
        if (!array_key_exists('dispute_status', $data)) {
            $data['dispute_status'] = self::DISPUTE_STATUS_NONE;
        }

        $insertResult = $db->Salvar($data, $db->table);
        if (!$insertResult) {
            return false;
        }

        $requestId = is_int($insertResult)
            ? $insertResult
            : (int) $db->ConexaoDB()->lastInsertId();
        if ($requestId <= 0) {
            return false;
        }

        MetricEvent::track('request_created', [
            'entity_type' => 'request',
            'entity_id' => $requestId,
            'user_id' => $data['user_id'] ?? null,
            'metadata' => [
                'property_id' => $data['property_id'] ?? null,
                'status' => $data['status'],
            ],
        ]);

        return $requestId;
    }

    public static function touchLastInteraction(int $requestId): bool
    {
        if ($requestId <= 0) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET last_interaction_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$requestId])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Consolida fecho financeiro após julgamento de disputa a favor do fecho ganho.
     */
    public static function consolidateFinancialClosingByModerator(int $requestId, int $actorId): bool
    {
        if ($requestId <= 0 || $actorId <= 0) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET commercial_status = 'fechado_ganho',
                    payment_confirmation_status = ?,
                    payment_received_confirmed_by = COALESCE(payment_received_confirmed_by, ?),
                    payment_received_confirmed_at = COALESCE(payment_received_confirmed_at, NOW()),
                    closing_confirmation_status = ?,
                    closing_confirmed_by = COALESCE(closing_confirmed_by, ?),
                    closing_confirmed_at = COALESCE(closing_confirmed_at, NOW()),
                    last_interaction_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
                  AND status = 'fechado_ganho'";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([
            self::PAYMENT_CONFIRMATION_CONFIRMED_BY_OWNER,
            $actorId,
            self::CLOSING_CONFIRMATION_CONFIRMED,
            $actorId,
            $requestId,
        ])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function declareClosingWon(int $requestId, int $actorId): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET status = 'fechado_ganho',
                    commercial_status           = 'fechado_ganho',
                    closing_confirmation_status = ?,
                    payment_confirmation_status = ?,
                    closing_declared_by         = ?,
                    closing_declared_at         = NOW(),
                    dispute_open_until          = DATE_ADD(NOW(), INTERVAL ? DAY),
                    last_interaction_at         = NOW(),
                    updated_at                  = NOW()
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([
            self::CLOSING_CONFIRMATION_PENDING,
            self::PAYMENT_CONFIRMATION_PENDING,
            $actorId,
            self::DISPUTE_WINDOW_DAYS,
            $requestId,
        ])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function confirmClosingWon(int $requestId, int $actorId): bool
    {
        return self::declarePaymentByRequester($requestId, $actorId);
    }

    public static function declarePaymentByRequester(int $requestId, int $actorId, ?string $proofPath = null): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET payment_confirmation_status = ?,
                    payment_declared_by         = ?,
                    payment_declared_at         = NOW(),
                    payment_proof_path          = ?,
                    last_interaction_at         = NOW(),
                    updated_at                  = NOW()
                WHERE id = ?
                  AND status = 'fechado_ganho'
                  AND closing_confirmation_status = ?
                  AND COALESCE(payment_confirmation_status, ?) IN (?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([
            self::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER,
            $actorId,
            $proofPath,
            $requestId,
            self::CLOSING_CONFIRMATION_PENDING,
            self::PAYMENT_CONFIRMATION_PENDING,
            self::PAYMENT_CONFIRMATION_PENDING,
            self::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER,
        ])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function contestClosingWon(int $requestId, int $actorId): bool
    {
        return self::contestPayment($requestId, $actorId);
    }

    public static function confirmPaymentReceiptByOwner(int $requestId, int $actorId): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET commercial_status           = 'fechado_ganho',
                    payment_confirmation_status = ?,
                    payment_received_confirmed_by = ?,
                    payment_received_confirmed_at = NOW(),
                    closing_confirmation_status = ?,
                    closing_confirmed_by        = ?,
                    closing_confirmed_at        = NOW(),
                    last_interaction_at         = NOW(),
                    updated_at                  = NOW()
                WHERE id = ?
                  AND status = 'fechado_ganho'
                  AND closing_confirmation_status = ?
                  AND payment_confirmation_status = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([
            self::PAYMENT_CONFIRMATION_CONFIRMED_BY_OWNER,
            $actorId,
            self::CLOSING_CONFIRMATION_CONFIRMED,
            $actorId,
            $requestId,
            self::CLOSING_CONFIRMATION_PENDING,
            self::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER,
        ])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function contestPayment(int $requestId, int $actorId): bool
    {
        $db = new self();
        // commercial_status stays 'fechado_ganho' (the last commercial outcome before dispute).
        $sql = "UPDATE {$db->table}
                SET status                      = 'em_disputa',
                    dispute_status              = ?,
                    payment_confirmation_status = ?,
                    closing_confirmation_status = ?,
                    closing_confirmed_by        = ?,
                    closing_confirmed_at        = NOW(),
                    last_interaction_at         = NOW(),
                    updated_at                  = NOW()
                WHERE id = ?
                  AND status = 'fechado_ganho'
                  AND closing_confirmation_status = ?
                  AND COALESCE(payment_confirmation_status, ?) IN (?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([
            self::DISPUTE_STATUS_OPEN,
            self::PAYMENT_CONFIRMATION_CONTESTED,
            self::CLOSING_CONFIRMATION_CONTESTED,
            $actorId,
            $requestId,
            self::CLOSING_CONFIRMATION_PENDING,
            self::PAYMENT_CONFIRMATION_PENDING,
            self::PAYMENT_CONFIRMATION_PENDING,
            self::PAYMENT_CONFIRMATION_DECLARED_BY_REQUESTER,
        ])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function autoExpireOpenRequests(int $expireDays = self::AUTO_EXPIRE_DAYS): int
    {
        $db = new self();
        // Use last_interaction_at when available for accurate inactivity tracking.
        $sql = "UPDATE {$db->table}
                SET status            = 'expirado',
                    commercial_status = 'expirado',
                    updated_at        = NOW()
                WHERE status = 'em_contacto'
                  AND COALESCE(last_interaction_at, updated_at, created_at) <= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $db->prepare($sql);
        $stmt->execute([(int) $expireDays]);
        return $stmt->rowCount();
    }

    public static function getDueSlaAlerts(int $limit = 30): array
    {
        $db = new self();
        // Primary active status is em_contacto.
        $sql = "SELECT
                    r.id,
                    r.status,
                    r.created_at,
                    r.updated_at,
                    r.last_interaction_at,
                    r.user_id AS requester_id,
                    p.affiliate_id AS owner_id,
                    p.title AS property_title,
                    TIMESTAMPDIFF(DAY, COALESCE(r.last_interaction_at, r.updated_at), NOW()) AS days_without_update
                FROM {$db->table} r
                JOIN properties p ON p.id = r.property_id
                                WHERE r.status = 'em_contacto'
                  AND (
                        r.next_followup_at IS NULL
                        OR r.next_followup_at <= NOW()
                  )
                ORDER BY r.next_followup_at ASC, COALESCE(r.last_interaction_at, r.updated_at) ASC
                LIMIT " . (int) $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function markSlaAlertSent(int $requestId, int $nextInDays = 7): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET last_sla_alert_at = NOW(),
                    next_followup_at = DATE_ADD(NOW(), INTERVAL ? DAY),
                    updated_at = updated_at
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        return (bool) $stmt->execute([(int) $nextInDays, (int) $requestId]);
    }

    public static function getStatusStats(): array
    {
        $db = new self();
        $sql = "SELECT
            COUNT(*) AS total,
            0 AS pendente,
            0 AS analise,
            SUM(CASE WHEN status = 'em_contacto' THEN 1 ELSE 0 END) AS em_contacto,
            0 AS proposta,
            SUM(CASE WHEN status = 'fechado_ganho' THEN 1 ELSE 0 END) AS fechado_ganho,
            SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) AS cancelado,
            SUM(CASE WHEN status = 'expirado' THEN 1 ELSE 0 END) AS expirado,
            SUM(CASE WHEN status = 'em_disputa' THEN 1 ELSE 0 END) AS em_disputa,
            SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 1 ELSE 0 END) AS new_this_month,
            ROUND(AVG(CASE WHEN status IN ('fechado_ganho', 'cancelado', 'expirado') THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) END), 1) AS avg_response_hours,
            ROUND(
                SUM(CASE WHEN status = 'fechado_ganho' AND (closing_confirmation_status = 'confirmado' OR closing_confirmation_status IS NULL) THEN 1 ELSE 0 END) /
                NULLIF(SUM(CASE WHEN status IN ('fechado_ganho', 'cancelado', 'expirado') THEN 1 ELSE 0 END), 0) * 100, 1
            ) AS acceptance_rate
        FROM {$db->table}";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }
}
