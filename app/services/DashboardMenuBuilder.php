<?php

namespace App\services;

use Src\classes\ClassAccess;
use Src\classes\ClassAuth;

final class DashboardMenuBuilder
{
    /**
     * @return array<int, array{key:string,label:string,icon:string,href:string}>
     */
    public static function build(array $user): array
    {
        if (ClassAccess::isPendingAccountDeletion($user)) {
            return [
                ['key' => 'account_deletion', 'label' => 'Eliminação de conta', 'icon' => 'fa-hourglass-half', 'href' => DIRPAGE . 'dashboard/accountDeletionStatus'],
            ];
        }

        if (ClassAccess::hasLimitedPlatformAccess($user)) {
            return [
                ['key' => 'account_status', 'label' => 'A minha conta', 'icon' => 'fa-hourglass-half', 'href' => DIRPAGE . 'dashboard/accountStatus'],
                ['key' => 'properties', 'label' => 'Ver imóveis', 'icon' => 'fa-search', 'href' => DIRPAGE . 'properties'],
            ];
        }

        $items = [];
        $items[] = ['key' => 'index', 'label' => 'Visão Geral', 'icon' => 'fa-th-large', 'href' => DIRPAGE . 'dashboard?view=overview'];
        $items[] = ['key' => 'profile', 'label' => 'Meu Perfil', 'icon' => 'fa-user-circle', 'href' => DIRPAGE . 'profile'];

        $hasRequestsMenu = false;
        if (!ClassAccess::isAdmin($user)) {
            $items[] = ['key' => 'requests', 'label' => 'Solicitações', 'icon' => 'fa-inbox', 'href' => DIRPAGE . 'requests'];
            $items[] = ['key' => 'conversations', 'label' => 'Conversas', 'icon' => 'fa-comments', 'href' => DIRPAGE . 'dashboard/requestChats'];
            $hasRequestsMenu = true;
        }

        if (empty($user['is_admin'])) {
            $items[] = ['key' => 'subscription', 'label' => 'Meu Plano', 'icon' => 'fa-diamond', 'href' => DIRPAGE . 'dashboard/subscription'];
            $items[] = ['key' => 'my_properties', 'label' => 'Meus Imóveis', 'icon' => 'fa-building', 'href' => DIRPAGE . 'dashboard/myProperties'];
            $items[] = ['key' => 'commission_payments', 'label' => 'Pagar Comissões', 'icon' => 'fa-money', 'href' => DIRPAGE . 'dashboard/commissionPayments'];
            $items[] = ['key' => 'favorites', 'label' => 'Favoritos', 'icon' => 'fa-heart', 'href' => DIRPAGE . 'dashboard/myFavorites'];
        }

        if (empty($user['is_admin'])) {
            $items[] = ['key' => 'afiliados', 'label' => 'Afiliados', 'icon' => 'fa-handshake-o', 'href' => DIRPAGE . 'dashboard/afiliados'];
        }

        if (ClassAccess::can('users.review', $user)) {
            $items[] = ['key' => 'moderate_users', 'label' => 'Perfis', 'icon' => 'fa-users', 'href' => DIRPAGE . 'dashboard/moderate_users'];
        }

        if (ClassAccess::can('properties.moderate', $user)) {
            $items[] = ['key' => 'property_moderate', 'label' => 'Imóveis', 'icon' => 'fa-building-o', 'href' => DIRPAGE . 'property/moderate'];
        }

        if (ClassAccess::can('documents.review', $user)) {
            $items[] = ['key' => 'review_documents', 'label' => 'Documentos', 'icon' => 'fa-file-text-o', 'href' => DIRPAGE . 'dashboard/reviewDocuments'];
        }

        if (ClassAccess::isSuperAdmin($user)) {
            $items[] = ['key' => 'kpi', 'label' => 'KPIs', 'icon' => 'fa-line-chart', 'href' => DIRPAGE . 'dashboard/kpi'];
        }

        if (ClassAccess::can('requests.manage', $user)) {
            if (!$hasRequestsMenu) {
                $items[] = ['key' => 'requests', 'label' => 'Solicitações', 'icon' => 'fa-inbox', 'href' => DIRPAGE . 'requests'];
                $items[] = ['key' => 'conversations', 'label' => 'Conversas', 'icon' => 'fa-comments', 'href' => DIRPAGE . 'dashboard/requestChats'];
            }
            $items[] = ['key' => 'disputes', 'label' => 'Disputas', 'icon' => 'fa-balance-scale', 'href' => DIRPAGE . 'dashboard/disputes'];
        }

        if (ClassAccess::can('payments.manage', $user)) {
            $items[] = ['key' => 'payments', 'label' => 'Pagamentos', 'icon' => 'fa-credit-card', 'href' => DIRPAGE . 'dashboard/payments'];
            $items[] = ['key' => 'payment_transactions', 'label' => 'Transações', 'icon' => 'fa-exchange', 'href' => DIRPAGE . 'payment_transactions'];
        }

        if (ClassAccess::isSuperAdmin($user)) {
            $items[] = ['key' => 'payment_methods', 'label' => 'Métodos', 'icon' => 'fa-list', 'href' => DIRPAGE . 'payment_methods'];
            $items[] = ['key' => 'payment_channels', 'label' => 'Canais', 'icon' => 'fa-bank', 'href' => DIRPAGE . 'payment_channels'];
            $items[] = ['key' => 'admin_subscriptions', 'label' => 'Subscrições', 'icon' => 'fa-id-card', 'href' => DIRPAGE . 'dashboard/adminSubscriptions'];
        }

        if (!ClassAccess::can('payments.manage', $user) && ClassAuth::check()) {
            $items[] = ['key' => 'payment_accounts', 'label' => 'Dados de Pagamento', 'icon' => 'fa-university', 'href' => DIRPAGE . 'dashboard/paymentAccounts'];
            $items[] = ['key' => 'payment_history', 'label' => 'Histórico', 'icon' => 'fa-history', 'href' => DIRPAGE . 'dashboard/paymentHistory'];
            $items[] = ['key' => 'property_reports', 'label' => 'Relatórios', 'icon' => 'fa-bar-chart', 'href' => DIRPAGE . 'dashboard/propertyReports'];
        }

        if (ClassAccess::can('audit.view', $user)) {
            $items[] = ['key' => 'audit_log', 'label' => 'Auditoria', 'icon' => 'fa-shield', 'href' => DIRPAGE . 'dashboard/auditLog'];
        }

        return $items;
    }
}
