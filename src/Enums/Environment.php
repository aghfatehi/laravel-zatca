<?php

namespace Aghfatehi\Zatca\Enums;

enum Environment: string
{
    case Sandbox    = 'sandbox';
    case Production = 'production';

    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    public function isSandbox(): bool
    {
        return $this === self::Sandbox;
    }
}
