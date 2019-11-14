<?php

namespace Charcoal\Api\Http\Handler;

// From PSR-7
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-app'
use Charcoal\App\Handler\NotFound as BaseNotFound;

/**
 * "Not Found" Handler
 *
 * Enhanced version of {@see \Charcoal\App\Handler\NotFound}.
 */
class NotFound extends BaseNotFound
{
    use HandlesCorsTrait;

    /**
     * Invoke "Not Allowed" Handler
     *
     * @param  ServerRequestInterface $request  The most recent Request object.
     * @param  ResponseInterface      $response The most recent Response object.
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $response = parent::__invoke($request, $response);

        return $this->respondWithCorsHeaders($response);
    }
}
