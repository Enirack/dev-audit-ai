<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler as LexikAuthenticationFailureHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Decorates Lexik's default failure handler to report the correct HTTP
 * status for throttled login attempts. Lexik's handler only maps an
 * exception's ->getCode() to an HTTP status when it's already in [400, 500)
 * — TooManyLoginAttemptsAuthenticationException never sets one, so without
 * this it silently reports 401 for a rate-limited attempt too, which is
 * indistinguishable from "wrong password" and hides the Retry-After
 * information already present in the exception's message.
 */
final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private readonly LexikAuthenticationFailureHandler $inner)
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['error' => strtr($exception->getMessageKey(), $exception->getMessageData())],
                429,
            );
        }

        return $this->inner->onAuthenticationFailure($request, $exception);
    }
}
