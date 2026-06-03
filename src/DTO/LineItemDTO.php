<?php

namespace Aghfatehi\Zatca\DTO;

class LineItemDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly float  $quantity,
        public readonly float  $taxExclusivePrice,
        public readonly float  $vatPercent = 0.15,
        public readonly array  $otherTaxes = [],
        public readonly array  $discounts = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? '1'),
            name: $data['name'] ?? '',
            quantity: (float)($data['quantity'] ?? 1),
            taxExclusivePrice: (float)($data['tax_exclusive_price'] ?? 0),
            vatPercent: (float)($data['vat_percent'] ?? 0.15),
            otherTaxes: $data['other_taxes'] ?? [],
            discounts: $data['discounts'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'tax_exclusive_price' => $this->taxExclusivePrice,
            'VAT_percent' => $this->vatPercent,
            'other_taxes' => $this->otherTaxes,
            'discounts' => $this->discounts,
        ];
    }
}
