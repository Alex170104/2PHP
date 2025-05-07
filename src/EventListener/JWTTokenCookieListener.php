<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class JWTTokenCookieListener
{
    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->headers->has('Authorization') && $request->cookies->has('BEARER')) {
            $token = $request->cookies->get('BEARER');
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }
    }
}
