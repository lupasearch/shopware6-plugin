<?php

declare(strict_types=1);

namespace LupaSearch\LupaSearchConnector\Services;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthorizationValidator
{
    private const HEADER_X_LUPA_INDEX_ID = 'X-Lupa-Index-ID';

    public function __construct(private SystemConfigService $systemConfigService)
    {
    }

    public function validateRequest(Request $request): void
    {
        $unauthorizedException = new UnauthorizedHttpException(
            'json',
            'Invalid or missing header "' . self::HEADER_X_LUPA_INDEX_ID . '".',
        );

        $headerValue = $request->headers->get(self::HEADER_X_LUPA_INDEX_ID);
        if (!$headerValue || strlen($headerValue) !== 36) {
            throw $unauthorizedException;
        }

        $indexId = $this->systemConfigService->get('LupaSearchConnector.config.productIndexId');

        if (!$indexId || $headerValue !== $indexId) {
            throw $unauthorizedException;
        }
    }
}
