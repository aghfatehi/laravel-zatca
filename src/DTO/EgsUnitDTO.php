<?php

namespace Aghfatehi\Zatca\DTO;

class EgsUnitDTO
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $customId,
        public readonly string $model,
        public readonly string $vatNumber,
        public readonly string $vatName,
        public readonly string $crnNumber,
        public readonly string $city,
        public readonly string $citySubdivision,
        public readonly string $street,
        public readonly string $building,
        public readonly string $plotIdentification,
        public readonly string $postalZone,
        public readonly string $branchName,
        public readonly string $branchIndustry,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'] ?? '',
            customId: $data['custom_id'] ?? '',
            model: $data['model'] ?? 'Desktop',
            vatNumber: $data['vat_number'] ?? '',
            vatName: $data['vat_name'] ?? '',
            crnNumber: $data['crn_number'] ?? '',
            city: $data['city'] ?? '',
            citySubdivision: $data['city_subdivision'] ?? '',
            street: $data['street'] ?? '',
            building: $data['building'] ?? '0000',
            plotIdentification: $data['plot_identification'] ?? '0000',
            postalZone: $data['postal_zone'] ?? '00000',
            branchName: $data['branch_name'] ?? '',
            branchIndustry: $data['branch_industry'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'custom_id' => $this->customId,
            'model' => $this->model,
            'vat_number' => $this->vatNumber,
            'vat_name' => $this->vatName,
            'crn_number' => $this->crnNumber,
            'location' => [
                'city' => $this->city,
                'city_subdivision' => $this->citySubdivision,
                'street' => $this->street,
                'building' => $this->building,
                'plot_identification' => $this->plotIdentification,
                'postal_zone' => $this->postalZone,
            ],
            'branch_name' => $this->branchName,
            'branch_industry' => $this->branchIndustry,
        ];
    }
}
