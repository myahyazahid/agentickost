<?php

use App\Support\Actions\Action;

arch()->preset()->php();

arch()->preset()->security();

arch('shared support code does not depend on modules')
    ->expect('App\Support')
    ->not->toUse('App\Modules');

arch('actions are final classes that extend the base action')
    ->expect('App\Modules\*\Actions')
    ->classes()
    ->toBeFinal()
    ->toExtend(Action::class)
    ->toHaveMethod('handle');
