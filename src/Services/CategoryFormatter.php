<?php

declare(strict_types=1);

namespace LupaSearch\LupaSearchConnector\Services;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class CategoryFormatter
{
    public function formatCategories(EntitySearchResult $categoryCollection, ?CategoryCollection $categories): array
    {
        $hierarchies = $this->collectCategoryHierarchies($categoryCollection, $categories);

        $categoriesFlat = [];
        $categoriesLast = [];
        foreach ($hierarchies as $hierarchy) {
            $parts = explode(' > ', $hierarchy);
            foreach ($parts as $categoryName) {
                $categoriesFlat[] = $categoryName;
            }
            $categoriesLast[] = end($parts);
        }

        return [
            'categories_hierarchy' => $hierarchies,
            'categories_flat' => array_values(array_unique($categoriesFlat)),
            'categories_last' => array_values(array_unique($categoriesLast)),
        ];
    }

    private function collectCategoryHierarchies(
        EntitySearchResult $categoryCollection,
        ?CategoryCollection $categories,
    ): array {
        if (!$categories) {
            return [];
        }

        $hierarchies = [];

        foreach ($categories as $productCategory) {
            $categoryPathIds = array_filter(explode('|', $productCategory->getPath() ?? ''));
            $categoryNames = $this->getCategoryNamesByPathIds($categoryCollection, $categoryPathIds);
            if ($productCategory->getActive()) {
                $categoryNames[] = $productCategory->getName();
            }

            if (!empty($categoryNames)) {
                $hierarchicalPath = implode(' > ', $categoryNames);
                $hierarchies[] = $hierarchicalPath;
            }
        }

        return $hierarchies;
    }

    private function getCategoryNamesByPathIds(EntitySearchResult $categoryCollection, array $pathIds): array
    {
        $categoryNames = [];

        foreach ($pathIds as $pathId) {
            $category = $categoryCollection->get($pathId);
            if ($category && $category->getActive() && $category->getParentId()) {
                $categoryNames[] = $category->getName();
            }
        }

        return $categoryNames;
    }
}
