<?php

declare(strict_types=1);

namespace LupaSearch\LupaSearchConnector\Controller;

use LupaSearch\LupaSearchConnector\Services\AuthorizationValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class SalesChannelController extends StorefrontController
{
    public function __construct(
        private AuthorizationValidator $authorizationValidator,
        private EntityRepository $salesChannelRepository,
    ) {
    }

    #[
        Route(
            path: '/api/lupasearch/salesChannels',
            name: 'api.action.lupaSearch.salesChannels.getList',
            defaults: ['auth_required' => false],
            methods: ['GET']
        )
    ]

    public function getSalesChannels(Request $request, Context $context): JsonResponse {
        $this->authorizationValidator->validateRequest($request);

        $salesChannels = $this->salesChannelRepository
            ->search((new Criteria())->addAssociation('salesChannels'), $context)
            ->getEntities();

        $result = [];
        foreach ($salesChannels as $salesChannel) {
            $result[] = [
                'id' => $salesChannel->getId(),
                'name' => $salesChannel->getName(),
            ];
        }

        return new JsonResponse($result);
    }
}
