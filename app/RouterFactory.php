<?php

declare(strict_types=1);

use Nette\Application\Routers\RouteList;
use Nette\Routing\Router;
use Nette\Routing\Route;

final class RouterFactory
{
    public static function createRouter(): Router
    {
        $router = new RouteList;
        $router->addRoute('admin[/<action>]', 'Admin:default');
        $router->addRoute('kategorie/<slug>', 'Home:default');
        $router->addRoute('<presenter>/<action>[/<slug>]', 'Home:default');

        return $router;
    }
}