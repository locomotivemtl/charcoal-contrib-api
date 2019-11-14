<?php

namespace Charcoal\Api\Http\Middleware;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-user'
use Charcoal\User\AuthenticatorInterface;

/**
 * Middleware: User Authentication
 */
class AuthenticateMiddleware
{
    /**
     * @var AuthenticatorInterface
     */
    private $authenticator;

    /**
     * @param AuthenticatorInterface $authenticator The JWT authenticator.
     */
    public function __construct(AuthenticatorInterface $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    /**
     * @param  RequestInterface  $request  The PSR-7 HTTP request.
     * @param  ResponseInterface $response The PSR-7 HTTP response.
     * @param  callable          $next     The next middleware callable in the stack.
     * @return ResponseInterface
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        if (!$this->authenticator->check()) {
            $this->authenticator->authenticateByRequest($request);
        }

        return $next($request, $response);
    }
}
