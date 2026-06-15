<?php

namespace Src\classes;

use Src\traits\TraitUrlParser;

/**
 * Fallback router: maps the first URL segment to a controller when
 * config/routes.php (RouteRegistry) does not match the full path.
 *
 * Only controllers that still receive nested legacy URLs belong here.
 * Single-segment pages (login, properties, cookies, etc.) are fully
 * covered by declarative routes — they do not need entries below.
 */
class ClassRoutes
{
    use TraitUrlParser;

    /** @var array<string, string> */
    public const LEGACY_SEGMENT_CONTROLLERS = [
        'property' => 'ControllerProperty',
        'dashboard' => 'ControllerDashboard',
        'request' => 'ControllerRequest',
        'payment_methods' => 'ControllerPayment',
        'payment_channels' => 'ControllerPayment',
        'payment_transactions' => 'ControllerPayment',
        'notification' => 'ControllerNotification',
        'api' => 'ControllerApi',
        'file' => 'ControllerFile',
        'media' => 'ControllerMedia',
    ];

    private $Rotas;

    public function getRota()
    {
        $url = $this->parseUrl();
        $I = $url[0];

        $this->Rotas = self::LEGACY_SEGMENT_CONTROLLERS;

        if (array_key_exists($I, $this->Rotas)) {
            if (file_exists(DIRREQ . "app/controller/{$this->Rotas[$I]}.php")) {
                return $this->Rotas[$I];
            }

            return 'ControllerHome';
        }

        return 'Controller404';
    }
}
