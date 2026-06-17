<?php

namespace App\controller;

use App\model\Commission;
use App\model\Property;
use App\model\PropertyAffiliate;
use App\model\PropertyBoostRequest;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\ClassRender;

class ControllerDashboardAffiliate
{

    public function commissions()
    {
        header('Location: ' . DIRPAGE . 'dashboard/afiliados?tab=commissions');
        exit;
    }


    public function promotor()
    {
        header('Location: ' . DIRPAGE . 'dashboard/afiliados');
        exit;
    }


    public function afiliados()
    {
        ClassAuth::requireAuth();
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não acedem a esta área');

        $hasProperties = Property::countByOwner((int) $user['id']) > 0;
        $validTabs = ['referrals', 'commissions', 'my_affiliates', 'affiliate_requests'];
        $defaultTab = $hasProperties ? 'affiliate_requests' : 'referrals';
        $activeTab = in_array($_GET['tab'] ?? '', $validTabs, true) ? $_GET['tab'] : $defaultTab;

        // If user chose an affiliate-only tab but is not an affiliate, fall back
        if (in_array($activeTab, ['referrals', 'commissions'], true) && empty($user['is_affiliate'])) {
            $activeTab = $hasProperties ? 'affiliate_requests' : 'referrals';
        }

        // Affiliate data (only loaded when needed)
        $commissions            = [];
        $summary                = [];
        $myAffiliatedProperties = [];

        // Owner-only tabs are hidden when the user has no properties.
        if (in_array($activeTab, ['my_affiliates', 'affiliate_requests'], true) && !$hasProperties) {
            $activeTab = 'referrals';
        }

        $perPage    = 20;
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $offset     = ($page - 1) * $perPage;
        $totalPages = 1;
        $commissionsTotal  = 0;
        $myAffiliatesTotal = 0;
        $myAffiliates      = [];

        if (!empty($user['is_affiliate'])) {
            $summary = Commission::getAffiliateSummary((int) $user['id']);
            if ($activeTab === 'commissions') {
                $commissionsTotal = Commission::countByAffiliate((int) $user['id']);
                $commissions      = Commission::getByAffiliate($user['id'], $perPage, $offset);
                $totalPages       = (int) ceil($commissionsTotal / max(1, $perPage));
            } else {
                // referrals tab: affiliated_properties is usually small, load all
                $myAffiliatedProperties = Property::getActiveAffiliationsForUser((int) $user['id']);
            }
        }

        if (in_array($activeTab, ['my_affiliates', 'affiliate_requests'], true)) {
            $statusFilter = $activeTab === 'affiliate_requests' ? 'pendente' : null;
            $myAffiliatesTotal = PropertyAffiliate::countByOwner((int) $user['id'], $statusFilter);
            $myAffiliates      = PropertyAffiliate::getByOwner((int) $user['id'], $perPage, $offset, $statusFilter);
            $totalPages        = (int) ceil($myAffiliatesTotal / max(1, $perPage));
        }

        $render = new ClassRender();
        $render->setTitle('Afiliados');
        $render->setDescription('Indicações, comissões e promotores dos seus imóveis');
        $render->setKeywords('afiliados, indicações, comissões, promotores');
        $render->setData([
            'user'                   => $user,
            'commissions'            => $commissions,
            'commissionsTotal'       => $commissionsTotal,
            'summary'                => $summary,
            'affiliated_properties'  => $myAffiliatedProperties,
            'affiliate_code'         => $user['affiliate_code'] ?? '',
            'my_affiliates'          => $myAffiliates,
            'myAffiliatesTotal'      => $myAffiliatesTotal,
            'has_properties'         => $hasProperties,
            'active_tab'             => $activeTab,
            'csrfField'              => ClassCsrf::field(),
            'page'                   => $page,
            'totalPages'             => $totalPages,
        ]);
        $render->setDir('dashboard/afiliados');
        $render->renderLayout();
    }


    public function myProperties()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não têm portfólio de imóveis');
        $properties = Property::getByAffiliate($user['id']);

        $pendingBoostIds = [];
        foreach (PropertyBoostRequest::getPending() as $boostRequest) {
            $pid = (int) ($boostRequest['property_id'] ?? 0);
            if ($pid > 0) {
                $pendingBoostIds[$pid] = true;
            }
        }

        $render = new ClassRender();
        $render->setTitle('Minhas Propriedades');
        $render->setDescription('Gerencie suas propriedades');
        $render->setKeywords('propriedades, gerencie');
        $render->setData([
            'user' => $user,
            'properties' => $properties,
            'pendingBoostIds' => $pendingBoostIds,
            'boostPricing' => PropertyBoostRequest::getBoostPricingConfig(),
            'propertyDeletionGraceDays' => Property::getPropertyDeletionGraceDays(),
        ]);
        $render->setDir('dashboard/my_properties');
        $render->renderLayout();
    }


    public function referrals()
    {
        header('Location: ' . DIRPAGE . 'dashboard/afiliados?tab=referrals');
        exit;
    }


    public function myAffiliates()
    {
        header('Location: ' . DIRPAGE . 'dashboard/afiliados?tab=my_affiliates');
        exit;
    }

}
