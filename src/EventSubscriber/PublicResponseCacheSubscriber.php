<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PublicResponseCacheSubscriber implements EventSubscriberInterface
{
    private const CACHEABLE_ROUTES = [
        'page_post_index',
        'page_post_show',
        'page_post_catalog',
        'page_privacy',
        'sitemap',
        'robots',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->getMethod() !== 'GET') {
            return;
        }

        $route = $request->attributes->get('_route');
        if (!\in_array($route, self::CACHEABLE_ROUTES, true)) {
            return;
        }

        $response = $event->getResponse();
        if ($response->getStatusCode() !== 200) {
            return;
        }

        $response->setPublic();
        $response->setMaxAge(120);
        $response->setSharedMaxAge(120);
        $response->headers->addCacheControlDirective('stale-while-revalidate', '60');
    }
}
