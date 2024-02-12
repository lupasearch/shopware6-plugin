<?php

declare(strict_types=1);

namespace LupaSearch\LupaSearchConnector\Subscriber;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RenderClientPluginSubscriber implements EventSubscriberInterface
{
    public function __construct(private SystemConfigService $systemConfigService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $javascriptFileUrl = $this->systemConfigService->get('LupaSearchConnector.config.javascriptPluginUrl');

        if ($javascriptFileUrl) {
            $event->setParameter('lupaJavascriptFileUrl', $javascriptFileUrl);
        }
    }
}
