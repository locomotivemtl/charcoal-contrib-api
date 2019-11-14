<?php

namespace Charcoal\Api\Http\Handler;

// From PSR-7
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-app'
use Charcoal\App\Handler\NotAllowed as BaseNotAllowed;

/**
 * "Not Allowed" Handler
 *
 * Enhanced version of {@see \Charcoal\App\Handler\NotAllowed}.
 */
class NotAllowed extends BaseNotAllowed
{
    use HandlesCorsTrait;

    /**
     * Invoke "Not Allowed" Handler
     *
     * @param  ServerRequestInterface $request  The most recent Request object.
     * @param  ResponseInterface      $response The most recent Response object.
     * @param  string[]               $methods  Allowed HTTP methods.
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $methods
    ) {
        $methods  = array_unique($methods);
        $response = parent::__invoke($request, $response, $methods);

        return $this->respondWithCorsHeaders($response);
    }
}
