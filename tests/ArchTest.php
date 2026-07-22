<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('source files use strict types')
    ->expect('Ahmdrv\\PermissionRegistry')
    ->toUseStrictTypes();

arch('resources have no UI or HTTP coupling')
    ->expect('Ahmdrv\\PermissionRegistry')
    ->not->toUse([
        'Illuminate\\Routing',
        'Illuminate\\View',
        'Livewire',
    ]);
