<?php

namespace App\controller;

/**
 * Legacy facade: keeps dashboard/* URLs working while domain logic lives in ControllerDashboard* classes.
 */
class ControllerDashboard
{
    private ?ControllerDashboardHome $dashboardHome = null;

    private function dashboardHome(): ControllerDashboardHome
    {
        return $this->dashboardHome ??= new ControllerDashboardHome();
    }

    public function index()
    {
        $this->dashboardHome()->index();
    }

    public function dashboard()
    {
        $this->dashboardHome()->dashboard();
    }

    public function myFavorites()
    {
        $this->dashboardHome()->myFavorites();
    }


    private ?ControllerDashboardNotifications $dashboardNotifications = null;

    private function dashboardNotifications(): ControllerDashboardNotifications
    {
        return $this->dashboardNotifications ??= new ControllerDashboardNotifications();
    }

    public function markNotificationsRead()
    {
        $this->dashboardNotifications()->markNotificationsRead();
    }

    public function markNotificationRead($id)
    {
        $this->dashboardNotifications()->markNotificationRead($id);
    }

    public function markNotificationUnread($id)
    {
        $this->dashboardNotifications()->markNotificationUnread($id);
    }

    public function notificationsFeed()
    {
        $this->dashboardNotifications()->notificationsFeed();
    }


    private ?ControllerDashboardRequests $dashboardRequests = null;

    private function dashboardRequests(): ControllerDashboardRequests
    {
        return $this->dashboardRequests ??= new ControllerDashboardRequests();
    }

    public function requests()
    {
        $this->dashboardRequests()->requests();
    }

    public function requestChatSummariesFeed()
    {
        $this->dashboardRequests()->requestChatSummariesFeed();
    }

    public function requestChats()
    {
        $this->dashboardRequests()->requestChats();
    }

    public function disputes()
    {
        $this->dashboardRequests()->disputes();
    }

    public function dispute($id)
    {
        $this->dashboardRequests()->dispute($id);
    }

    public function requestChat($id)
    {
        $this->dashboardRequests()->requestChat($id);
    }

    public function requestChatFeed($id)
    {
        $this->dashboardRequests()->requestChatFeed($id);
    }

    public function requestChatMarkRead($id)
    {
        $this->dashboardRequests()->requestChatMarkRead($id);
    }

    public function requestChatMarkUnread($id)
    {
        $this->dashboardRequests()->requestChatMarkUnread($id);
    }


    private ?ControllerDashboardAffiliate $dashboardAffiliate = null;

    private function dashboardAffiliate(): ControllerDashboardAffiliate
    {
        return $this->dashboardAffiliate ??= new ControllerDashboardAffiliate();
    }

    public function commissions()
    {
        $this->dashboardAffiliate()->commissions();
    }

    public function promotor()
    {
        $this->dashboardAffiliate()->promotor();
    }

    public function afiliados()
    {
        $this->dashboardAffiliate()->afiliados();
    }

    public function myProperties()
    {
        $this->dashboardAffiliate()->myProperties();
    }

    public function referrals()
    {
        $this->dashboardAffiliate()->referrals();
    }

    public function myAffiliates()
    {
        $this->dashboardAffiliate()->myAffiliates();
    }


    private ?ControllerDashboardPayments $dashboardPayments = null;

    private function dashboardPayments(): ControllerDashboardPayments
    {
        return $this->dashboardPayments ??= new ControllerDashboardPayments();
    }

    public function payments()
    {
        $this->dashboardPayments()->payments();
    }

    public function confirmPayment($id)
    {
        $this->dashboardPayments()->confirmPayment($id);
    }

    public function rejectCommissionOwnerPayment($id)
    {
        $this->dashboardPayments()->rejectCommissionOwnerPayment($id);
    }

    public function confirmAffiliatePayout($id)
    {
        $this->dashboardPayments()->confirmAffiliatePayout($id);
    }

    public function cancelPayment($id)
    {
        $this->dashboardPayments()->cancelPayment($id);
    }

    public function commissionPayments()
    {
        $this->dashboardPayments()->commissionPayments();
    }

    public function commissionPayment($id)
    {
        $this->dashboardPayments()->commissionPayment($id);
    }

    public function submitCommissionPayment($id)
    {
        $this->dashboardPayments()->submitCommissionPayment($id);
    }

    public function paymentAccounts()
    {
        $this->dashboardPayments()->paymentAccounts();
    }

    public function addPaymentAccount()
    {
        $this->dashboardPayments()->addPaymentAccount();
    }

    public function setDefaultPaymentAccount($id)
    {
        $this->dashboardPayments()->setDefaultPaymentAccount($id);
    }

    public function deactivatePaymentAccount($id)
    {
        $this->dashboardPayments()->deactivatePaymentAccount($id);
    }

    public function paymentHistory()
    {
        $this->dashboardPayments()->paymentHistory();
    }

    public function exportPaymentHistoryCsv()
    {
        $this->dashboardPayments()->exportPaymentHistoryCsv();
    }

    public function exportPaymentHistoryPdf()
    {
        $this->dashboardPayments()->exportPaymentHistoryPdf();
    }

    public function exportPaymentsHistoryCsv()
    {
        $this->dashboardPayments()->exportPaymentsHistoryCsv();
    }

    public function exportPaymentsHistoryPdf()
    {
        $this->dashboardPayments()->exportPaymentsHistoryPdf();
    }


    private ?ControllerDashboardModeration $dashboardModeration = null;

    private function dashboardModeration(): ControllerDashboardModeration
    {
        return $this->dashboardModeration ??= new ControllerDashboardModeration();
    }

    public function moderateUsers()
    {
        $this->dashboardModeration()->moderateUsers();
    }

    public function blockUserAccess($id)
    {
        $this->dashboardModeration()->blockUserAccess($id);
    }

    public function unblockUserAccess($id)
    {
        $this->dashboardModeration()->unblockUserAccess($id);
    }

    public function suspendUserAccess($id)
    {
        $this->dashboardModeration()->suspendUserAccess($id);
    }

    public function unsuspendUserAccess($id)
    {
        $this->dashboardModeration()->unsuspendUserAccess($id);
    }

    public function purgeUserAccountNow($id)
    {
        $this->dashboardModeration()->purgeUserAccountNow($id);
    }

    public function cancelUserDeletion($id)
    {
        $this->dashboardModeration()->cancelUserDeletion($id);
    }

    public function setAdminRole($id)
    {
        $this->dashboardModeration()->setAdminRole($id);
    }

    public function approveUser($id)
    {
        $this->dashboardModeration()->approveUser($id);
    }

    public function requestTrustedBadge()
    {
        $this->dashboardModeration()->requestTrustedBadge();
    }

    public function approveTrustedBadge($id)
    {
        $this->dashboardModeration()->approveTrustedBadge($id);
    }

    public function rejectTrustedBadge($id)
    {
        $this->dashboardModeration()->rejectTrustedBadge($id);
    }

    public function confirmTrustedBadgePayment($id)
    {
        $this->dashboardModeration()->confirmTrustedBadgePayment($id);
    }

    public function rejectUser($id)
    {
        $this->dashboardModeration()->rejectUser($id);
    }

    public function reviewDocuments()
    {
        $this->dashboardModeration()->reviewDocuments();
    }

    public function approveDocument($id)
    {
        $this->dashboardModeration()->approveDocument($id);
    }

    public function rejectDocument($id)
    {
        $this->dashboardModeration()->rejectDocument($id);
    }

    public function resubmitDocument($documentId)
    {
        $this->dashboardModeration()->resubmitDocument($documentId);
    }

    public function submitAccountDocument()
    {
        $this->dashboardModeration()->submitAccountDocument();
    }

    public function createAdministrativeUser()
    {
        $this->dashboardModeration()->createAdministrativeUser();
    }

    public function revokeAdministrativeAccess($id)
    {
        $this->dashboardModeration()->revokeAdministrativeAccess($id);
    }

    public function suspendAdministrativeAccess($id)
    {
        $this->dashboardModeration()->suspendAdministrativeAccess($id);
    }

    public function unsuspendAdministrativeAccess($id)
    {
        $this->dashboardModeration()->unsuspendAdministrativeAccess($id);
    }


    private ?ControllerDashboardProfile $dashboardProfile = null;

    private function dashboardProfile(): ControllerDashboardProfile
    {
        return $this->dashboardProfile ??= new ControllerDashboardProfile();
    }

    public function accountStatus()
    {
        $this->dashboardProfile()->accountStatus();
    }

    public function accountDeletionStatus()
    {
        $this->dashboardProfile()->accountDeletionStatus();
    }

    public function requestAccountDeletion()
    {
        $this->dashboardProfile()->requestAccountDeletion();
    }

    public function cancelAccountDeletion()
    {
        $this->dashboardProfile()->cancelAccountDeletion();
    }

    public function profile()
    {
        $this->dashboardProfile()->profile();
    }

    public function getPromoterTerms()
    {
        $this->dashboardProfile()->getPromoterTerms();
    }

    public function becomeAffiliate()
    {
        $this->dashboardProfile()->becomeAffiliate();
    }

    public function update()
    {
        $this->dashboardProfile()->update();
    }


    private ?ControllerDashboardAdmin $dashboardAdmin = null;

    private function dashboardAdmin(): ControllerDashboardAdmin
    {
        return $this->dashboardAdmin ??= new ControllerDashboardAdmin();
    }

    public function auditLog($entityType = null, $entityId = null)
    {
        $this->dashboardAdmin()->auditLog($entityType, $entityId);
    }

    public function kpi()
    {
        $this->dashboardAdmin()->kpi();
    }

    public function propertyReports()
    {
        $this->dashboardAdmin()->propertyReports();
    }

    public function settings()
    {
        $this->dashboardAdmin()->settings();
    }


    private ?ControllerDashboardSubscriptions $dashboardSubscriptions = null;

    private function dashboardSubscriptions(): ControllerDashboardSubscriptions
    {
        return $this->dashboardSubscriptions ??= new ControllerDashboardSubscriptions();
    }

    public function subscription()
    {
        $this->dashboardSubscriptions()->subscription();
    }

    public function subscriptionCheckout()
    {
        $this->dashboardSubscriptions()->subscriptionCheckout();
    }

    public function confirmSubscriptionCheckout()
    {
        $this->dashboardSubscriptions()->confirmSubscriptionCheckout();
    }

    public function changeSubscription()
    {
        $this->dashboardSubscriptions()->changeSubscription();
    }

    public function adminSubscriptions()
    {
        $this->dashboardSubscriptions()->adminSubscriptions();
    }

    public function adminSetSubscription()
    {
        $this->dashboardSubscriptions()->adminSetSubscription();
    }

    public function adminSubscriptionCheckout()
    {
        $this->dashboardSubscriptions()->adminSubscriptionCheckout();
    }

    public function confirmAdminSubscriptionCheckout()
    {
        $this->dashboardSubscriptions()->confirmAdminSubscriptionCheckout();
    }
}
