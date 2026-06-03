<?php

namespace Aghfatehi\Zatca\Contracts;

interface QrCodeGeneratorInterface
{
    public function generateTlv(array $tags): string;
    public function render(string $tlvData, int $size = 200): string;
}
