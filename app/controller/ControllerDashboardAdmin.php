<?php

namespace App\controller;

use App\model\Commission;
use App\model\Log;
use App\model\Property;
use App\model\Request;
use App\model\User;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\ClassPlan;
use Src\classes\ClassRender;
use Src\classes\ClassSettings;

class ControllerDashboardAdmin
{

    public function auditLog($entityType = null, $entityId = null)
    {
        $admin = ClassAccess::requirePermission('audit.view', 'dashboard', 'Acesso disponível apenas para perfis autorizados');

        $entityType = !empty($entityType) ? preg_replace('/[^a-z_]/', '', strtolower((string) $entityType)) : null;
        $entityId   = !empty($entityId) ? (int) $entityId : null;

        $perPage = 40;
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        if ($entityType && $entityId) {
            $logs  = Log::getByEntity($entityType, $entityId);
            $total = count($logs);
        } else {
            $logs  = Log::getRecent($perPage, $offset);
            $total = Log::countAll();
        }

        $totalPages = (int) ceil($total / $perPage);

        $render = new ClassRender();
        $render->setTitle('Registo de Auditoria');
        $render->setDescription('Histórico de ações do sistema');
        $render->setKeywords('auditoria, logs, histórico');
        $render->setData([
            'user'       => $admin,
            'logs'       => $logs,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'filterType' => $entityType,
            'filterId'   => $entityId,
        ]);
        $render->setDir('dashboard/audit_log');
        $render->renderLayout();
    }


    public function kpi()
    {
        $user = ClassAccess::requireSuperAdmin('dashboard', 'Acesso disponível apenas para Admin Total');

        $userStats = User::getRegistrationStats();
        $propertyStats = Property::getStatusStats();
        $requestStats = Request::getStatusStats();
        $commissionStats = Commission::getSummaryStats();
        $topAffiliates = Commission::getTopAffiliates(5);

        $render = new ClassRender();
        $render->setTitle('KPIs do Sistema');
        $render->setDescription('Indicadores de desempenho da plataforma');
        $render->setKeywords('kpi, estatísticas, desempenho');
        $render->setData([
            'user' => $user,
            'userStats' => $userStats,
            'propertyStats' => $propertyStats,
            'requestStats' => $requestStats,
            'commissionStats' => $commissionStats,
            'topAffiliates' => $topAffiliates,
        ]);
        $render->setDir('dashboard/kpi');
        $render->renderLayout();
    }


    public function propertyReports()
    {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();

        if (!ClassPlan::canViewReports((int) $user['id'])) {
            header('Location: ' . DIRPAGE . 'dashboard/subscription?error=' . rawurlencode('Esta funcionalidade requer o Plano Profissional ou superior.'));
            exit;
        }

        $stats = Property::getStatsForOwner((int) $user['id']);
        $plan  = ClassPlan::getOfficialPlanByUser((int) $user['id']);
        $isAdvanced = ClassPlan::canViewAdvancedReports((int) $user['id']);

        $render = new ClassRender();
        $render->setTitle('Relatórios de Imóveis');
        $render->setDescription('Estatísticas e desempenho do portfólio');
        $render->setKeywords('relatórios, estatísticas, imóveis');
        $render->setData([
            'user'       => $user,
            'stats'      => $stats,
            'plan'       => $plan,
            'isAdvanced' => $isAdvanced,
        ]);
        $render->setDir('dashboard/property_reports');
        $render->renderLayout();
    }


    public function settings()
    {
        $user = ClassAccess::requireSuperAdmin('dashboard', 'Acesso disponível apenas para Admin Total');

        $errors  = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $allowedKeys = [
                'commission_system_pct',
                'commission_affiliate_pct',
                'commission_system_only_pct',
                'commission_due_days',
                'rate_limit_post_max',
                'rate_limit_post_window_seconds',
                'trust_badge_monthly_fee',
                'trust_badge_min_months',
                'trust_badge_max_months',
                'trust_badge_default_months',
                'trust_badge_min_won_deals',
                'trust_badge_min_account_days',
                'trust_badge_require_confirmed_closing',
                'boost_daily_fee',
                'boost_min_days',
                'boost_max_days',
                'boost_default_days',
                'behavior_ranking_enabled',
                'behavior_ranking_lookback_days',
                'behavior_weight_view',
                'behavior_weight_favorite',
                'behavior_weight_request',
                'behavior_max_score_per_property',
                'behavior_decay_lambda',
                'behavior_view_penalty_threshold',
                'behavior_view_penalty_points',
                'behavior_explore_ratio',
                'behavior_impression_cooldown_hours',
                'behavior_home_carousel_size',
                'behavior_continue_exploring_size',
                'behavior_promoted_interval',
                'account_deletion_grace_days',
                'property_deletion_grace_days',
                'deletion_reminder_days_before',
            ];

            $integerKeys = [
                'commission_due_days',
                'rate_limit_post_max',
                'rate_limit_post_window_seconds',
                'trust_badge_min_months',
                'trust_badge_max_months',
                'trust_badge_default_months',
                'boost_min_days',
                'boost_max_days',
                'boost_default_days',
                'behavior_ranking_lookback_days',
                'behavior_weight_view',
                'behavior_weight_favorite',
                'behavior_weight_request',
                'behavior_max_score_per_property',
                'behavior_view_penalty_threshold',
                'behavior_view_penalty_points',
                'behavior_explore_ratio',
                'behavior_impression_cooldown_hours',
                'behavior_home_carousel_size',
                'behavior_continue_exploring_size',
                'behavior_promoted_interval',
                'account_deletion_grace_days',
                'property_deletion_grace_days',
                'deletion_reminder_days_before',
            ];

            $booleanKeys = [
                'behavior_ranking_enabled',
                'trust_badge_require_confirmed_closing',
            ];

            $pendingSettings = [];

            foreach ($allowedKeys as $key) {
                if (!isset($_POST[$key])) {
                    continue;
                }
                $val = trim($_POST[$key]);

                if (in_array($key, $booleanKeys, true)) {
                    if (!is_numeric($val) || !in_array((int) $val, [0, 1], true)) {
                        $errors[$key] = 'Use 0 (desligado) ou 1 (ligado).';
                        continue;
                    }
                    $pendingSettings[$key] = (string) ((int) $val);
                    continue;
                }

                if (!is_numeric($val) || $val < 0) {
                    $errors[$key] = 'Valor inválido.';
                    continue;
                }

                if ($key === 'behavior_decay_lambda' && ((float) $val <= 0 || (float) $val > 1)) {
                    $errors[$key] = 'Use um valor entre 0.001 e 1.';
                    continue;
                }

                if ($key === 'behavior_explore_ratio' && (int) $val > 30) {
                    $errors[$key] = 'Máximo 30%.';
                    continue;
                }

                if (in_array($key, $integerKeys, true) && (int) $val < 1) {
                    $errors[$key] = 'Use um valor inteiro maior ou igual a 1.';
                    continue;
                }

                $pendingSettings[$key] = $val;
            }

            $minMonths = isset($_POST['trust_badge_min_months']) ? (int) $_POST['trust_badge_min_months'] : 1;
            $maxMonths = isset($_POST['trust_badge_max_months']) ? (int) $_POST['trust_badge_max_months'] : 12;
            $defaultMonths = isset($_POST['trust_badge_default_months']) ? (int) $_POST['trust_badge_default_months'] : 6;

            if ($maxMonths < $minMonths) {
                $errors['trust_badge_max_months'] = 'O máximo não pode ser menor que o mínimo.';
            }
            if ($defaultMonths < $minMonths || $defaultMonths > $maxMonths) {
                $errors['trust_badge_default_months'] = 'O padrão deve estar entre mínimo e máximo.';
            }

            $minDays     = isset($_POST['boost_min_days']) ? (int) $_POST['boost_min_days'] : 7;
            $maxDays     = isset($_POST['boost_max_days']) ? (int) $_POST['boost_max_days'] : 90;
            $defaultDays = isset($_POST['boost_default_days']) ? (int) $_POST['boost_default_days'] : 30;

            if ($maxDays < $minDays) {
                $errors['boost_max_days'] = 'O máximo não pode ser menor que o mínimo.';
            }
            if ($defaultDays < $minDays || $defaultDays > $maxDays) {
                $errors['boost_default_days'] = 'O padrão deve estar entre mínimo e máximo.';
            }

            if (empty($errors)) {
                foreach ($pendingSettings as $key => $val) {
                    ClassSettings::set($key, $val);
                }
                $success = true;
            }
        }

        $render = new ClassRender();
        $render->setTitle('Configurações do Sistema');
        $render->setDescription('Gerencie as configurações operacionais');
        $render->setKeywords('configurações, sistema, comissões');
        $render->setData([
            'user'     => $user,
            'settings' => ClassSettings::all(),
            'errors'   => $errors,
            'success'  => $success,
            'csrf'     => ClassCsrf::get(),
        ]);
        $render->setDir('dashboard/settings');
        $render->renderLayout();
    }

}
