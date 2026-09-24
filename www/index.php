<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$application = Bootstrap::boot()
    ->createContainer()
    ->getByType(Nette\Application\Application::class);

$application->run();
