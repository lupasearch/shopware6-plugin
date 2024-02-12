<?php

declare(strict_types=1);

namespace LupaSearch\LupaSearchConnector\Services;

use Shopware\Core\Content\Property\PropertyGroupEntity;

class PropertyFormatter
{
    public function format(PropertyGroupEntity $property): array
    {
        return [
            'name' => $property->getName(),
            'filterable' => $property->getFilterable(),
        ];
    }
}
