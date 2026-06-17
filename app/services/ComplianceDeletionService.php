<?php

namespace App\services;

use App\model\ApiToken;
use App\model\Document;
use App\model\Log;
use App\model\ManipularBanco;
use App\model\Notification;
use App\model\Property;
use App\model\PropertyBoostRequest;
use App\model\Request;
use App\model\User;
use Src\classes\ClassMailer;
use Src\classes\ClassSettings;

final class ComplianceDeletionService
{
    public static function reminderDaysBefore(): int
    {
        return max(1, (int) ClassSettings::int('deletion_reminder_days_before', 7));
    }

    /**
     * @return array{accounts: int, properties: int, account_reminders: int, property_reminders: int, overdue_accounts: int, overdue_properties: int}
     */
    public static function runScheduledMaintenance(int $limit = 50): array
    {
        $result = [
            'accounts' => User::processDueAccountDeletions($limit),
            'properties' => Property::processDueDeletions($limit),
            'account_reminders' => self::sendAccountDeletionReminders($limit),
            'property_reminders' => self::sendPropertyDeletionReminders($limit),
            'overdue_accounts' => User::countOverdueDeletions(),
            'overdue_properties' => Property::countOverdueDeletions(),
        ];

        if ($result['accounts'] > 0 || $result['properties'] > 0) {
            Log::create([
                'user_id' => null,
                'action' => 'compliance_deletion_scheduler',
                'entity_type' => 'system',
                'entity_id' => null,
                'details' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        if ($result['overdue_accounts'] > 0 || $result['overdue_properties'] > 0) {
            self::alertAdminsOverdueDeletions($result['overdue_accounts'], $result['overdue_properties']);
        }

        return $result;
    }

    public static function afterAccountDeletionRequested(int $userId): int
    {
        return self::propagatePropertyDeletionsForUser($userId);
    }

    public static function afterPropertyDeletionRequested(int $propertyId, string $propertyTitle): void
    {
        self::notifyPropertyDeletionParticipants($propertyId, $propertyTitle);
        self::emailPropertyDeletionRequested($propertyId, $propertyTitle);
    }

    public static function afterAccountDeletionRequestedNotify(int $userId, array $user): void
    {
        self::emailAccountDeletionRequested($user);
        Notification::notifyUsers(
            User::getActiveAdminIds(),
            'account_deletion_requested',
            'Pedido de eliminação de conta',
            'Um utilizador solicitou a eliminação da conta. O conteúdo ficará indisponível durante o período de conformidade.',
            ['user_id' => $userId],
            $userId
        );
    }

    public static function purgeUserResidualData(array $user): void
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        self::purgeUserDocuments($userId, $user);
        self::deleteProfilePhotoFile((string) ($user['profile_photo'] ?? ''));
        self::purgeUserAuthenticationArtifacts($user);
        self::purgeOwnerPropertiesOnAccountPurge($userId);
    }

    public static function purgePropertyMedia(array $property): void
    {
        $images = json_decode((string) ($property['images'] ?? '[]'), true);
        if (!is_array($images)) {
            $images = [];
        }

        foreach ($images as $rawPath) {
            self::deletePublicStorageFile((string) $rawPath);
        }

        $video = trim((string) ($property['video_url'] ?? ''));
        if ($video !== '') {
            self::deletePublicStorageFile($video);
        }
    }

    public static function purgePropertyResidualData(array $property): void
    {
        $propertyId = (int) ($property['id'] ?? 0);
        if ($propertyId <= 0) {
            return;
        }

        self::purgePropertyMedia($property);
        self::purgePropertyDocuments($propertyId);
        self::purgePropertyBoostProofs($propertyId);
    }

    public static function deletionSummaryForUser(int $userId): array
    {
        $properties = Property::getPortfolioByAffiliate($userId);
        $openNegotiations = Request::countOpenNegotiationsForOwner($userId);

        return [
            'properties_total' => count($properties),
            'properties_deletable' => count(array_filter($properties, static fn (array $p): bool => Property::canRequestDeletion($p))),
            'open_negotiations' => $openNegotiations,
        ];
    }

    public static function deletionSummaryForProperty(int $propertyId): array
    {
        return [
            'open_negotiations' => Request::countOpenNegotiationsForProperty($propertyId),
        ];
    }

    public static function afterAccountDeletionCancelled(int $userId): int
    {
        return Property::cancelAllDeletionsForOwner($userId);
    }

    private static function propagatePropertyDeletionsForUser(int $userId): int
    {
        $user = User::findById($userId);
        $accountScheduledAt = is_array($user) ? trim((string) ($user['deletion_scheduled_at'] ?? '')) : '';
        $scheduledAt = $accountScheduledAt !== '' ? $accountScheduledAt : null;

        $propagated = 0;
        foreach (Property::getPortfolioByAffiliate($userId) as $property) {
            $propertyId = (int) ($property['id'] ?? 0);
            if ($propertyId <= 0 || !Property::canRequestDeletion($property)) {
                continue;
            }

            $title = (string) ($property['title'] ?? 'Imóvel');
            if (Property::requestDeletion($propertyId, $userId, $scheduledAt)) {
                $propagated++;
                self::notifyPropertyDeletionParticipants($propertyId, $title);
                self::emailPropertyDeletionRequested($propertyId, $title);
            }
        }

        return $propagated;
    }

    private static function purgeOwnerPropertiesOnAccountPurge(int $userId): void
    {
        foreach (Property::getAllByAffiliateIncludingPurged($userId) as $property) {
            $propertyId = (int) ($property['id'] ?? 0);
            if ($propertyId <= 0 || !empty($property['deletion_purged_at'])) {
                continue;
            }

            Property::purgePermanently($propertyId, true);
        }
    }

    private static function purgeUserDocuments(int $userId, array $user = []): void
    {
        self::deleteDocumentFiles(Document::getAllByUser($userId));

        $legacyDocumentFile = basename(str_replace('\\', '/', trim((string) ($user['document_file'] ?? ''))));
        if ($legacyDocumentFile !== '') {
            $legacyPath = DIRREQ . 'storage/documents/' . $legacyDocumentFile;
            if (is_file($legacyPath)) {
                @unlink($legacyPath);
            }
        }

        Document::deleteAllRecordsForUser($userId);
    }

    private static function purgePropertyDocuments(int $propertyId): void
    {
        if ($propertyId <= 0) {
            return;
        }

        self::deleteDocumentFiles(Document::getAllByProperty($propertyId));
        Document::deleteAllRecordsForProperty($propertyId);
    }

    private static function purgePropertyBoostProofs(int $propertyId): void
    {
        if ($propertyId <= 0) {
            return;
        }

        foreach (PropertyBoostRequest::getByProperty($propertyId) as $boostRequest) {
            $proof = trim((string) ($boostRequest['payment_proof'] ?? ''));
            if ($proof !== '') {
                self::deletePublicStorageFile($proof);
            }
        }

        PropertyBoostRequest::clearPaymentProofsForProperty($propertyId);
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     */
    private static function deleteDocumentFiles(array $documents): void
    {
        $uploadDir = DIRREQ . 'storage/documents/';
        foreach ($documents as $document) {
            $filename = basename(str_replace('\\', '/', (string) ($document['filename'] ?? '')));
            if ($filename === '') {
                continue;
            }

            $path = $uploadDir . $filename;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function purgeUserAuthenticationArtifacts(array $user): void
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        ApiToken::revokeAllForUser($userId);
        Notification::deleteAllForUser($userId);

        $db = new ManipularBanco();
        foreach (['password_resets', 'email_verifications'] as $table) {
            $stmt = $db->prepare("DELETE FROM {$table} WHERE user_id = ?");
            $stmt->execute([$userId]);
        }

        foreach (['email', 'username', 'phone'] as $field) {
            $loginIdentifier = trim((string) ($user[$field] ?? ''));
            if ($loginIdentifier === '') {
                continue;
            }

            $stmt = $db->prepare('DELETE FROM login_attempts WHERE login_identifier = ?');
            $stmt->execute([$loginIdentifier]);
        }
    }

    private static function deleteProfilePhotoFile(string $relativePath): void
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath));
        if ($relativePath === '') {
            return;
        }

        $fullPath = rtrim((string) DIRREQ, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private static function deletePublicStorageFile(string $path): void
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, '..')) {
            return;
        }

        if (str_starts_with($path, 'public/')) {
            $fullPath = rtrim((string) DIRREQ, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        } else {
            $fullPath = rtrim((string) DIRREQ, '/\\') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), '/\\');
        }

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private static function notifyPropertyDeletionParticipants(int $propertyId, string $propertyTitle): void
    {
        $participantIds = Request::getActiveNegotiationParticipantIds($propertyId);
        if ($participantIds === []) {
            return;
        }

        $title = trim($propertyTitle) !== '' ? $propertyTitle : 'Imóvel #' . $propertyId;
        Notification::notifyUsers(
            $participantIds,
            'property_deletion_requested',
            'Anúncio marcado para eliminação',
            'O imóvel «' . $title . '» foi marcado para eliminação. O anúncio deixa de estar público, mas as conversas em curso mantêm-se acessíveis durante o período de conformidade.',
            ['property_id' => $propertyId],
            null
        );
    }

    private static function sendAccountDeletionReminders(int $limit): int
    {
        $days = self::reminderDaysBefore();
        $sent = 0;

        foreach (User::getDeletionReminderCandidates($days, $limit) as $user) {
            if (self::emailAccountDeletionReminder($user, $days)) {
                User::markDeletionReminderSent((int) ($user['id'] ?? 0));
                $sent++;
            }
        }

        return $sent;
    }

    private static function sendPropertyDeletionReminders(int $limit): int
    {
        $days = self::reminderDaysBefore();
        $sent = 0;

        foreach (Property::getDeletionReminderCandidates($days, $limit) as $property) {
            $ownerId = (int) ($property['affiliate_id'] ?? 0);
            $owner = $ownerId > 0 ? User::findById($ownerId) : null;
            if (!is_array($owner)) {
                continue;
            }

            if (self::emailPropertyDeletionReminder($owner, $property, $days)) {
                Property::markDeletionReminderSent((int) ($property['id'] ?? 0));
                $sent++;
            }
        }

        return $sent;
    }

    private static function alertAdminsOverdueDeletions(int $overdueAccounts, int $overdueProperties): void
    {
        if (!self::shouldSendOverdueDeletionAlert()) {
            return;
        }

        $adminIds = User::getActiveAdminIds();
        if ($adminIds === []) {
            return;
        }

        Notification::notifyUsers(
            $adminIds,
            'deletion_scheduler_overdue',
            'Eliminações em atraso',
            'Há ' . $overdueAccounts . ' conta(s) e ' . $overdueProperties . ' imóvel(is) com eliminação agendada em atraso. Verifique o cron de conformidade.',
            [
                'overdue_accounts' => $overdueAccounts,
                'overdue_properties' => $overdueProperties,
            ],
            null
        );

        ClassSettings::set('deletion_overdue_alert_last_sent_at', date('Y-m-d H:i:s'));
    }

    private static function shouldSendOverdueDeletionAlert(): bool
    {
        $lastSent = trim(ClassSettings::get('deletion_overdue_alert_last_sent_at', ''));
        if ($lastSent === '') {
            return true;
        }

        $lastTs = strtotime($lastSent);
        if ($lastTs === false) {
            return true;
        }

        return (time() - $lastTs) >= 86400;
    }

    private static function emailAccountDeletionRequested(array $user): void
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !ClassMailer::isEnabled()) {
            return;
        }

        $days = User::getAccountDeletionGraceDays();
        $scheduled = (string) ($user['deletion_scheduled_at'] ?? '');
        $when = $scheduled !== '' ? date('d/m/Y H:i', strtotime($scheduled)) : 'após o período de conformidade';

        $subject = 'Pedido de eliminação da sua conta — Imobil Fácil';
        $html = '<p>Olá ' . htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Registámos o seu pedido de eliminação de conta. Durante os próximos <strong>' . (int) $days . ' dias</strong> o acesso fica limitado.</p>'
            . '<p>Eliminação prevista: <strong>' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Pode cancelar o pedido na área da conta enquanto o prazo não terminar.</p>';

        ClassMailer::sendQueued($email, (string) ($user['name'] ?? ''), $subject, $html);
    }

    private static function emailAccountDeletionReminder(array $user, int $daysBefore): bool
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !ClassMailer::isEnabled()) {
            return false;
        }

        $scheduled = (string) ($user['deletion_scheduled_at'] ?? '');
        $when = $scheduled !== '' ? date('d/m/Y H:i', strtotime($scheduled)) : 'em breve';
        $subject = 'Lembrete: eliminação da conta em ' . $daysBefore . ' dias — Imobil Fácil';
        $html = '<p>Olá ' . htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>A sua conta está agendada para eliminação em <strong>' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Se mudou de ideias, cancele o pedido na plataforma antes dessa data.</p>';

        return ClassMailer::sendQueued($email, (string) ($user['name'] ?? ''), $subject, $html);
    }

    private static function emailPropertyDeletionRequested(int $propertyId, string $propertyTitle): void
    {
        $property = Property::find($propertyId);
        if (!is_array($property)) {
            return;
        }

        $ownerId = (int) ($property['affiliate_id'] ?? 0);
        $owner = $ownerId > 0 ? User::findById($ownerId) : null;
        if (!is_array($owner)) {
            return;
        }

        $email = trim((string) ($owner['email'] ?? ''));
        if ($email === '' || !ClassMailer::isEnabled()) {
            return;
        }

        $scheduled = (string) ($property['deletion_scheduled_at'] ?? '');
        $when = $scheduled !== '' ? date('d/m/Y H:i', strtotime($scheduled)) : 'após o período de conformidade';
        $graceDays = $scheduled !== ''
            ? max(1, (int) ceil((strtotime($scheduled) - time()) / 86400))
            : Property::getPropertyDeletionGraceDays();
        $title = trim($propertyTitle) !== '' ? $propertyTitle : 'Imóvel #' . $propertyId;

        $subject = 'Imóvel marcado para eliminação — Imobil Fácil';
        $html = '<p>Olá ' . htmlspecialchars((string) ($owner['name'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>O anúncio <strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong> foi marcado para eliminação.</p>'
            . '<p>Ficará indisponível ao público durante <strong>' . (int) $graceDays . ' dias</strong>; conversas em curso mantêm-se visíveis.</p>'
            . '<p>Eliminação prevista: <strong>' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</strong>.</p>';

        ClassMailer::sendQueued($email, (string) ($owner['name'] ?? ''), $subject, $html);
    }

    private static function emailPropertyDeletionReminder(array $owner, array $property, int $daysBefore): bool
    {
        $email = trim((string) ($owner['email'] ?? ''));
        if ($email === '' || !ClassMailer::isEnabled()) {
            return false;
        }

        $scheduled = (string) ($property['deletion_scheduled_at'] ?? '');
        $when = $scheduled !== '' ? date('d/m/Y H:i', strtotime($scheduled)) : 'em breve';
        $title = (string) ($property['title'] ?? 'Imóvel');

        $subject = 'Lembrete: eliminação do imóvel em ' . $daysBefore . ' dias — Imobil Fácil';
        $html = '<p>Olá ' . htmlspecialchars((string) ($owner['name'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>O anúncio <strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong> será removido do portfólio em <strong>' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Pode cancelar a eliminação em Minhas Propriedades antes dessa data.</p>';

        return ClassMailer::sendQueued($email, (string) ($owner['name'] ?? ''), $subject, $html);
    }
}
