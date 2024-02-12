<?php

declare(strict_types=1);

namespace LupaSearch\LupaSearchConnector\Controller;

use LupaSearch\LupaSearchConnector\Services\AuthorizationValidator;
use LupaSearch\LupaSearchConnector\Services\ProductFormatter;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class ProductController
{
    public function __construct(
        private AuthorizationValidator $authorizationValidator,
        private EntityRepository $productRepository,
        private ProductFormatter $productFormatter,
    ) {
    }

    #[
        Route(
            path: '/api/lupasearch/products',
            name: 'api.action.lupaSearch.products.getList',
            defaults: ['auth_required' => false],
            methods: ['GET']
        )
    ]

    public function getProducts(Request $request, Context $context): JsonResponse {
        $this->authorizationValidator->validateRequest($request);

        $limit = (int) $request->query->get('limit', 10);
        $page = (int) $request->query->get('page', 1);
        $offset = ($page - 1) * $limit;
        $salesChannelId = $request->query->get('salesChannelId');

        $context->setConsiderInheritance(true);
        $criteria = $this->buildCriteria($limit, $offset, $salesChannelId);
        $products = $this->productRepository->search($criteria, $context);
        $total = $products->getTotal();

        $formattedProducts = $this->productFormatter->formatProductCollection(
            $products,
            $context,
            $salesChannelId,
            $criteria,
        );

        return new JsonResponse([
            'data' => $formattedProducts,
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
            'totalPages' => ceil($total / $limit),
        ]);
    }

    private function buildCriteria(int $limit, int $offset, ?string $salesChannelId): Criteria
    {
        $criteria = (new Criteria())
            ->addAssociations([
                'categories',
                'properties',
                'properties.group',
                'media',
                'manufacturer',
                'seoUrls',
                'customFields',
                'children',
                'options.group',
            ])
            ->setLimit($limit)
            ->setOffset($offset)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        if (null !== $salesChannelId) {
            $criteria->addFilter(
                new ProductAvailableFilter($salesChannelId, ProductVisibilityDefinition::VISIBILITY_SEARCH),
            );
        }

        return $criteria;
    }
}
