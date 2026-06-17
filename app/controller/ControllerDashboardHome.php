<?php

namespace App\controller;

use App\model\Document;
use App\model\Favorite;
use App\model\Notification;
use App\model\Property;
use App\model\Request;
use App\model\User;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\ClassRender;

class ControllerDashboardHome
{
    public function index()
    {
        ClassAuth::requireAuth();

        $user = ClassAuth::user();
        if (ClassAccess::canUseDeletionStatusPage($user)) {
            header('Location: ' . DIRPAGE . 'dashboard/accountDeletionStatus');
            exit;
        }
        if (ClassAccess::canUseAccountStatusPage($user)) {
            header('Location: ' . DIRPAGE . 'dashboard/accountStatus');
            exit;
        }
        $requests = Request::getByUser($user['id']);
        $recentRequests = array_slice($requests, 0, 3);
        $stats = User::getAffiliateStats($user['id']);
        $trust = User::getTrustMetrics($user['id']);
        $notifications = Notification::getLatestByUser((int) $user['id'], 5);
        $unreadNotifications = Notification::countUnreadByUser((int) $user['id']);
        $rejectedDocuments = Document::getRejectedByUser((int) $user['id']);

        $render = new ClassRender();
        $render->setTitle('O seu painel');
        $render->setDescription('Acompanhe pedidos, alertas e a sua conta');
        $render->setKeywords('painel, solicitações');
        $render->setData([
            'user' => $user,
            'requests' => $requests,
            'recentRequests' => $recentRequests,
            'stats' => $stats,
            'trust' => $trust,
            'notifications' => $notifications,
            'unreadNotifications' => $unreadNotifications,
            'rejectedDocuments' => $rejectedDocuments,
            'csrfField' => ClassCsrf::field(),
        ]);
        $render->setDir('dashboard/index');
        $render->renderLayout();
    }

    public function dashboard()
    {
        $this->index();
    }

    public function myFavorites()
    {
        $user = ClassAccess::requireNonAdmin('dashboard', 'Administradores não têm favoritos');

        $propertyIds = Favorite::getPropertyIdsByUser((int) $user['id']);
        $properties  = Property::getByIds($propertyIds);

        $render = new ClassRender();
        $render->setTitle('Meus Favoritos');
        $render->setDescription('Imóveis que você marcou como favorito');
        $render->setKeywords('favoritos, imóveis');
        $render->setData([
            'user'       => $user,
            'properties' => $properties,
        ]);
        $render->setDir('dashboard/favorites');
        $render->renderLayout();
    }
}
