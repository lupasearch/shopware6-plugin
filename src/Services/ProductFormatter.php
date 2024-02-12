<?php

declare(strict_types=1);

namespace LupaSearch\LupaSearchConnector\Services;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ProductFormatter
{
    public function __construct(
        private EntityRepository $productRepository,
        private EntityRepository $categoryRepository,
        private CategoryFormatter $categoryFormatter,
    ) {
    }

    public function formatProductCollection(
        EntitySearchResult $products,
        Context $context,
        ?string $salesChannelId,
        Criteria $criteria,
    ): array {
        $categoryCollection = $this->getCategoryCollection($context);
        $formattedParentProductsMap = $this->getFormattedParentProductMap(
            $products,
            $categoryCollection,
            $salesChannelId,
            $criteria,
            $context,
        );

        return $this->formatProducts($products, $categoryCollection, $salesChannelId, $formattedParentProductsMap);
    }

    private function formatProducts(
        EntitySearchResult $products,
        EntitySearchResult $categoryCollection,
        ?string $salesChannelId,
        array $formattedParentProductsMap,
    ): array {
        $formattedProducts = [];
        foreach ($products as $product) {
            if (null === $product->getParentId()) {
                $formattedProducts[] =
                    $formattedParentProductsMap[$product->getId()] ??
                    $this->formatProduct($product, $categoryCollection, $salesChannelId);
            } else {
                $formattedProducts[] = $this->formatVariant(
                    $product,
                    $formattedParentProductsMap[$product->getParentId()],
                    $salesChannelId,
                );
            }
        }

        return $formattedProducts;
    }

    private function getFormattedParentProductMap(
        EntitySearchResult $products,
        EntitySearchResult $categoryCollection,
        ?string $salesChannelId,
        Criteria $criteria,
        Context $context,
    ): array {
        $parentProducts = $products->filter(static function ($product) {
            return $product->getParentId() === null;
        });
        $parentProductIds = $parentProducts->map(static function ($product) {
            return $product->getId();
        });
        $variantParentIds = array_unique(
            $products
                ->filter(static function ($product) {
                    return $product->getParentId() !== null;
                })
                ->map(static function ($product) {
                    return $product->getParentId();
                }),
        );
        $missingParentIds = array_filter($variantParentIds, static function ($parentId) use ($parentProductIds) {
            return !in_array($parentId, $parentProductIds);
        });

        if (!empty($missingParentIds)) {
            $missingCriteria = $criteria
                ->setLimit(null)
                ->setOffset(null)
                ->addFilter(new EqualsAnyFilter('id', array_values($missingParentIds)));
            $missingProducts = $this->productRepository->search($missingCriteria, $context);
            foreach ($missingProducts as $product) {
                $parentProducts->add($product);
            }
        }

        $formattedParentProductsMap = [];
        foreach ($parentProducts as $product) {
            $formattedParentProductsMap[$product->getId()] = $this->formatProduct(
                $product,
                $categoryCollection,
                $salesChannelId,
            );
        }

        return $formattedParentProductsMap;
    }

    public function formatProduct(
        ProductEntity $product,
        EntitySearchResult $categoryCollection,
        ?string $salesChannelId,
    ): array {
        return array_merge(
            $this->formatBaseProductFields($product, $salesChannelId),
            $this->categoryFormatter->formatCategories($categoryCollection, $product->getCategories()),
            $this->formatPrice($product),
            $this->formatPropertyGroups($product->getProperties()),
            $this->formatCustomFields($product->getCustomFields()),
        );
    }

    public function formatVariant(ProductEntity $variant, ?array $parentData, ?string $salesChannelId): array
    {
        $data = array_merge(
            $this->formatBaseProductFields($variant, $salesChannelId),
            $this->formatPrice($variant),
            $this->formatPropertyGroupOptions($variant->getOptions()),
        );

        if (is_array($parentData)) {
            foreach ($parentData as $key => $value) {
                if (str_starts_with($key, 'property_group_id_') || isset($data[$key])) {
                    continue;
                }

                $data[$key] = $value;
            }
        }

        return $data;
    }

    private function formatBaseProductFields(ProductEntity $product, ?string $salesChannelId): array
    {
        return [
            'id' => $product->getId(),
            'parent_id' => $product->getParentId(),
            'product_group_id' => $product->getParentId() ?? $product->getId(),
            'product_number' => $product->getProductNumber(),
            'name' => $product->getTranslation('name'),
            'stock_available' => $product->getAvailableStock() > 0,
            'stock_qty' => $product->getStock(),
            'active' => $product->getActive(),
            'description' => $product->getDescription(),
            'manufacturer_name' => $product->getManufacturer()?->getName(),
            'url' => $this->getProductDetailsUrl($product, $salesChannelId),
            'image' => $this->getCoverImage($product),
        ];
    }

    private function formatPropertyGroupOptions(?PropertyGroupOptionCollection $optionCollection): array
    {
        $data = [];
        foreach ($optionCollection as $option) {
            $propertyGroupId = 'property_group_id_' . $option->getGroupId();
            if (!\array_key_exists($propertyGroupId, $data)) {
                $data[$propertyGroupId] = [];
            }
            $data[$propertyGroupId][] = $option->getTranslation('name');
        }
        return $data;
    }

    private function formatPropertyGroups(?PropertyGroupOptionCollection $groupOptionCollection): array
    {
        $data = [];
        foreach ($groupOptionCollection as $property) {
            $propertyGroupId = 'property_group_id_' . $property->getGroupId();
            if (!\array_key_exists($propertyGroupId, $data)) {
                $data[$propertyGroupId] = [];
            }
            $data[$propertyGroupId][] = $property->getTranslation('name');
        }

        return $data;
    }

    private function formatCustomFields(?array $customFields): array
    {
        if (!is_array($customFields)) {
            return [];
        }

        $data = [];
        foreach ($customFields as $code => $value) {
            $data['custom_field_' . str_replace(' ', '_', $code)] = $value;
        }

        return $data;
    }

    private function formatPrice(ProductEntity $product): array
    {
        if (!$product->getPrice() || !$product->getPrice()->first()) {
            return [];
        }

        $price = $product->getPrice()->first();

        $data = [
            'price_gross' => $price->getGross(),
            'price_net' => $price->getNet(),
        ];

        $listPrice = $price->getListPrice();
        if ($listPrice) {
            $data = array_merge($data, [
                'list_price_gross' => $listPrice->getGross(),
                'list_price_net' => $listPrice->getNet(),
            ]);
        }

        return $data;
    }

    private function getCategoryCollection(Context $context): EntitySearchResult
    {
        return $this->categoryRepository->search(
            (new Criteria())->addSorting(new FieldSorting('level', FieldSorting::ASCENDING)),
            $context,
        );
    }

    private function getProductDetailsUrl(ProductEntity $product, ?string $salesChannelId): ?string
    {
        $seoUrl = $product
            ->getSeoUrls()
            ->filter(static function ($seoUrl) use ($salesChannelId) {
                return $seoUrl->getSalesChannelId() === $salesChannelId && $seoUrl->getIsCanonical();
            })
            ->first();

        return $seoUrl ? '/' . $seoUrl->getSeoPathInfo() : "/detail/{$product->getId()}";
    }

    private function getCoverImage(ProductEntity $product): ?string
    {
        if (!$product->getMedia()?->count()) {
            return null;
        }

        $coverId = $product->getCoverId();
        $cover = $product
            ->getMedia()
            ->filterByProperty('id', $coverId)
            ->first();

        if (!$cover) {
            return null;
        }

        return '/' . $cover->getMedia()->getPath();
    }
}
