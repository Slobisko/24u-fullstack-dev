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
        $router->addRoute('<presenter>/<action>[/<id>]', 'Home:default');

        return $router;
    }
}