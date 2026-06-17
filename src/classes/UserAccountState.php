<?php

namespace Src\classes;

/**
 * Estados reais da conta — fonte única de verdade.
 *
 * Camada 1 — users.status (moderação): pendente | ativo | rejeitado
 * Camada 2 — Acesso: derivado do status + suspended_until (não altera users.status)
 * Camada 3 — Documento de registo: tabela documents (independente; ver resolveWithDocument)
 */
class UserAccountState
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_REJEITADO = 'rejeitado';

    public const ACCESS_SUSPENDED = 'suspended';
    public const ACCESS_FULL = 'full';
    public const ACCESS_ONBOARDING = 'onboarding';
    public const ACCESS_CORRECTION = 'correction';
    public const ACCESS_PENDING_DELETION = 'pending_deletion';
    public const ACCESS_UNKNOWN = 'unknown';

    private const STATUS_LABELS = [
        self::STATUS_PENDENTE => 'Em análise',
        self::STATUS_ATIVO => 'Aprovada',
        self::STATUS_REJEITADO => 'A corrigir',
    ];

    private const STATUS_DESCRIPTIONS = [
        self::STATUS_PENDENTE => 'Já temos o seu registo. A nossa equipa está a rever os dados — avisamos quando houver novidades.',
        self::STATUS_ATIVO => 'Tudo certo: pode usar a plataforma com acesso completo.',
        self::STATUS_REJEITADO => 'Falta alinhar alguns dados ou o documento de identificação. Corrija abaixo e voltamos a analisar.',
    ];

    private const ACCESS_LABELS = [
        self::ACCESS_SUSPENDED => 'Acesso em pausa',
        self::ACCESS_FULL => 'Tudo disponível',
        self::ACCESS_ONBOARDING => 'Enquanto aguarda aprovação',
        self::ACCESS_CORRECTION => 'Pode corrigir aqui',
        self::ACCESS_PENDING_DELETION => 'Eliminação agendada',
        self::ACCESS_UNKNOWN => 'Indisponível',
    ];

    public static function normalizeStatus(?array $user): string
    {
        if (!is_array($user)) {
            return '';
        }

        $status = strtolower(trim((string) ($user['status'] ?? '')));

        return in_array($status, [self::STATUS_PENDENTE, self::STATUS_ATIVO, self::STATUS_REJEITADO], true)
            ? $status
            : '';
    }

    public static function isSuspended(?array $user): bool
    {
        if (!is_array($user)) {
            return true;
        }

        return !empty($user['suspended_until']) && strtotime((string) $user['suspended_until']) > time();
    }

    public static function isPendingDeletion(?array $user): bool
    {
        return \App\model\User::isPendingDeletion($user);
    }

    public static function resolveAccessTier(?array $user): string
    {
        if (!is_array($user)) {
            return self::ACCESS_UNKNOWN;
        }

        if (self::isPendingDeletion($user)) {
            return self::ACCESS_PENDING_DELETION;
        }

        if (self::isSuspended($user)) {
            return self::ACCESS_SUSPENDED;
        }

        return match (self::normalizeStatus($user)) {
            self::STATUS_ATIVO => self::ACCESS_FULL,
            self::STATUS_PENDENTE => self::ACCESS_ONBOARDING,
            self::STATUS_REJEITADO => self::ACCESS_CORRECTION,
            default => self::ACCESS_UNKNOWN,
        };
    }

    public static function resolve(?array $user): array
    {
        $status = self::normalizeStatus($user);
        $access = self::resolveAccessTier($user);
        $suspended = self::isSuspended($user);

        $canLogin = $status !== '' && !$suspended;
        $canFullPlatform = $access === self::ACCESS_FULL;
        $canAccountPage = in_array($access, [self::ACCESS_ONBOARDING, self::ACCESS_CORRECTION], true);
        $canDeletionStatusPage = $access === self::ACCESS_PENDING_DELETION;
        $showLimitedMenu = $canAccountPage || $canDeletionStatusPage;

        return [
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? '—',
            'status_description' => self::STATUS_DESCRIPTIONS[$status] ?? '',
            'access' => $access,
            'access_label' => self::ACCESS_LABELS[$access] ?? '—',
            'is_suspended' => $suspended,
            'is_pending_deletion' => $canDeletionStatusPage,
            'deletion_requested_at' => $canDeletionStatusPage ? (string) ($user['deletion_requested_at'] ?? '') : null,
            'deletion_scheduled_at' => $canDeletionStatusPage ? (string) ($user['deletion_scheduled_at'] ?? '') : null,
            'suspended_until' => $suspended ? (string) ($user['suspended_until'] ?? '') : null,
            'can_login' => $canLogin,
            'can_full_platform' => $canFullPlatform,
            'can_account_status_page' => $canAccountPage,
            'can_deletion_status_page' => $canDeletionStatusPage,
            'can_edit_contact_on_account_page' => false,
            'can_edit_identification_on_account_page' => false,
            'can_submit_documents_on_account_page' => false,
            'can_submit_property_requests' => $canFullPlatform && !ClassAccess::isAdmin($user),
            'show_limited_menu' => $showLimitedMenu,
            'hero' => self::heroCopy($access, false, false),
            'capabilities' => self::capabilitiesCopy($access, false, false),
        ];
    }

    /** Nome e número de BI só editáveis com users.status = rejeitado. */
    public static function canEditIdentificationOnAccountPage(?array $user): bool
    {
        if (!is_array($user)) {
            return false;
        }

        return self::resolve($user)['can_account_status_page']
            && self::normalizeStatus($user) === self::STATUS_REJEITADO;
    }

    public static function resolveWithDocument(
        ?array $user,
        string $documentCompliance = 'missing',
        int $rejectedDocumentCount = 0
    ): array {
        $state = self::resolve($user);
        $state['document'] = self::resolveDocumentPhase($documentCompliance);

        $needsDocumentCorrection = in_array($documentCompliance, ['rejected', 'missing'], true)
            || $rejectedDocumentCount > 0;

        $canEditIdentification = self::canEditIdentificationOnAccountPage($user);

        $canSubmitDocuments = $state['can_account_status_page']
            && ($state['status'] === self::STATUS_REJEITADO || $needsDocumentCorrection);

        $state['can_edit_identification_on_account_page'] = $canEditIdentification;
        $state['can_submit_documents_on_account_page'] = $canSubmitDocuments;
        $state['capabilities'] = self::capabilitiesCopy($state['access'], $canEditIdentification, $canSubmitDocuments);
        $state['hero'] = self::heroCopy($state['access'], $canEditIdentification, $canSubmitDocuments);

        return $state;
    }

    public static function documentsNeedCorrection(string $documentCompliance, int $rejectedDocumentCount = 0): bool
    {
        return in_array($documentCompliance, ['rejected', 'missing'], true) || $rejectedDocumentCount > 0;
    }

    /** Badge único para listagens admin (tab Acessos, etc.). */
    public static function adminRowBadge(?array $user): array
    {
        $state = self::resolve($user);
        $badges = [];

        $badges[] = [
            'label' => $state['status_label'],
            'tone' => match ($state['status']) {
                self::STATUS_ATIVO => 'success',
                self::STATUS_REJEITADO => 'danger',
                self::STATUS_PENDENTE => 'warning',
                default => 'muted',
            },
            'title' => 'users.status = ' . ($state['status'] !== '' ? $state['status'] : '—'),
        ];

        if ($state['is_suspended']) {
            $until = $state['suspended_until'];
            $badges[] = [
                'label' => 'Suspenso' . ($until ? ' até ' . date('d/m/Y', strtotime($until)) : ''),
                'tone' => 'danger',
                'title' => 'Acesso suspenso (suspended_until)',
            ];
        }

        if ($state['is_pending_deletion']) {
            $until = (string) ($state['deletion_scheduled_at'] ?? '');
            $badges[] = [
                'label' => 'A eliminar' . ($until !== '' ? ' até ' . date('d/m/Y H:i', strtotime($until)) : ''),
                'tone' => 'danger',
                'title' => 'Pedido de eliminação de conta',
            ];
        }

        return $badges;
    }

    public static function allowedLoginStatuses(): array
    {
        return [self::STATUS_PENDENTE, self::STATUS_ATIVO, self::STATUS_REJEITADO];
    }

    private static function resolveDocumentPhase(string $compliance): array
    {
        $map = [
            'compliant' => ['label' => 'Validado', 'tone' => 'green', 'note' => 'O documento está em ordem. Falta apenas a decisão final sobre a conta.'],
            'pending' => ['label' => 'Em análise', 'tone' => 'yellow', 'note' => 'O ficheiro que enviou está connosco. Assim que a revisão terminar, avisamos.'],
            'rejected' => ['label' => 'Precisa de novo envio', 'tone' => 'red', 'note' => 'Leia o motivo indicado abaixo e envie uma versão mais legível ou correcta.'],
            'missing' => ['label' => 'Ainda por enviar', 'tone' => 'yellow', 'note' => 'Falta o BI ou o documento de identificação da empresa.'],
        ];

        $phase = $map[$compliance] ?? $map['missing'];
        $phase['compliance'] = $compliance;

        return $phase;
    }

    private static function heroCopy(string $access, bool $canEditIdentification, bool $canSubmitDocuments): array
    {
        if ($access === self::ACCESS_CORRECTION) {
            return [
                'kicker' => 'Conta a corrigir',
                'title' => 'Actualize os dados da conta',
                'text' => 'A sua conta foi rejeitada para correcção (estado «A corrigir»). Pode editar o nome, o número de BI e enviar o documento outra vez. Telefone e email só mudam no perfil, depois da aprovação.',
            ];
        }

        if ($access === self::ACCESS_ONBOARDING) {
            $text = 'Enquanto a conta está em análise, pode ver imóveis à vontade.';
            if ($canSubmitDocuments && !$canEditIdentification) {
                $text .= ' Pode enviar o documento de identificação; o nome e o número de BI ficam bloqueados até a conta ser rejeitada para correcção.';
            } elseif ($canSubmitDocuments) {
                $text .= ' Envie o que faltar na secção de identificação.';
            } else {
                $text .= ' Assim que tivermos uma resposta, avisamos aqui e por email.';
            }

            return [
                'kicker' => 'Bem-vindo',
                'title' => 'Estamos a rever o seu registo',
                'text' => $text,
            ];
        }

        if ($access === self::ACCESS_PENDING_DELETION) {
            return [
                'kicker' => 'Eliminação de conta',
                'title' => 'Conta marcada para eliminação',
                'text' => 'O seu pedido está em análise de conformidade. Durante este período, o acesso à plataforma fica limitado e os seus conteúdos ficam indisponíveis para outros utilizadores.',
            ];
        }

        return [
            'kicker' => 'A sua conta',
            'title' => 'Como está o seu registo',
            'text' => '',
        ];
    }

    private static function capabilitiesCopy(string $access, bool $canEditIdentification, bool $canSubmitDocuments): array
    {
        if ($access === self::ACCESS_PENDING_DELETION) {
            return [
                ['allowed' => true, 'text' => 'Consultar o estado do pedido de eliminação'],
                ['allowed' => true, 'text' => 'Cancelar o pedido antes do prazo (com palavra-passe)'],
                ['allowed' => false, 'text' => 'Ver ou gerir imóveis, solicitações e perfil'],
                ['allowed' => false, 'text' => 'Marcar visitas ou avançar com negócios'],
            ];
        }

        $identificationCapability = ['allowed' => false, 'text' => 'Corrigir dados de identificação (só se pedirmos)'];
        if ($canEditIdentification) {
            $identificationCapability = [
                'allowed' => true,
                'text' => 'Actualizar nome, número de BI e documento',
            ];
        } elseif ($canSubmitDocuments) {
            $identificationCapability = [
                'allowed' => true,
                'text' => 'Enviar ou reenviar documento de identificação',
            ];
        } elseif ($access === self::ACCESS_CORRECTION) {
            $identificationCapability['text'] = 'Actualizar nome, número de BI e documento (conta a corrigir)';
        }

        return [
            ['allowed' => true, 'text' => 'Ver imóveis e abrir os detalhes dos anúncios'],
            ['allowed' => false, 'text' => 'Mudar telefone ou email aqui (só no perfil, depois da aprovação)'],
            $identificationCapability,
            ['allowed' => false, 'text' => 'Marcar visitas ou avançar com compra ou arrendamento'],
            ['allowed' => false, 'text' => 'Publicar ou gerir os seus imóveis'],
        ];
    }
}
