<?php

declare(strict_types=1);

namespace LupaSearch\LupaSearchConnector\Controller;

use LupaSearch\LupaSearchConnector\Services\AuthorizationValidator;
use LupaSearch\LupaSearchConnector\Services\PropertyFormatter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class PropertyController extends StorefrontController
{
    public function __construct(
        private AuthorizationValidator $authorizationValidator,
        private EntityRepository $propertyGroupRepository,
        private PropertyFormatter $propertyFormatter,
    ) {
    }

    #[
        Route(
            path: '/api/lupasearch/properties',
            name: 'api.action.lupaSearch.properties.getList',
            defaults: ['auth_required' => false],
            methods: ['GET']
        )
    ]

    public function getProperties(Request $request, Context $context): JsonResponse {
        $this->authorizationValidator->validateRequest($request);

        $properties = $this->propertyGroupRepository->search(new Criteria(), $context)->getEntities();

        $result = [];
        foreach ($properties as $property) {
            $result[$property->getId()] = $this->propertyFormatter->format($property);
        }

        return new JsonResponse($result);
    }
}
