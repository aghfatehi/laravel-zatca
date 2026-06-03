<?php

namespace Aghfatehi\Zatca\Facades;

use Illuminate\Support\Facades\Facade;

class Zatca extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zatca';
    }
}
