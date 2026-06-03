{{-- Uses built-in SVG QR generator when endroid/qr-code is not installed --}}
{!! app(\Aghfatehi\Zatca\Services\SvgQrGenerator::class)->generate($qrData, $size) !!}
