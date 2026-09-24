<?php

declare(strict_types=1);

use Nette\Bootstrap\Configurator;

final class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator;
        $configurator->setDebugMode((bool) ($_ENV['NETTE_DEBUG'] ?? false));
        $configurator->enableTracy(__DIR__ . '/../log');
        $configurator->setTempDirectory(__DIR__ . '/../temp');
        $configurator->createRobotLoader()
            ->addDirectory(__DIR__)
            ->register();
        $configurator->addConfig(__DIR__ . '/../config/common.neon');

        return $configurator;
    }
}
