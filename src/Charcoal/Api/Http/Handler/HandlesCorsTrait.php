<?php

namespace Charcoal\Api\Http\Handler;

use Pimple\Container;

// From PSR-7
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Implements {@link https://en.wikipedia.org/wiki/Cross-origin_resource_sharing cross-origin resource sharing}.
 *
 * Based on Slim's {@link https://www.slimframework.com/docs/v3/cookbook/enable-cors.html solution for simple CORS requests}.
 */
trait HandlesCorsTrait
{
    /**
     * Create a new response with CORS headers.
     *
     * @param  Response $response The HTTP Response object.
     * @param  string[] $methods  Allowed HTTP methods.
     * @return Response
     */
    public function respondWithCorsHeaders(Response $response, array $methods = null)
    {
        if ($this->container['debug']) {
            $origin = '*';
        } else {
            $origin = $this->container['config']['app_url'];
        }

        $headers = implode(',', [
            'Accept',
            'Authorization',
            'Cache-Control',
            'Content-Type',
            'Origin',
            'X-App-Version',
            'X-Requested-With',
        ]);

        $response = $response->withHeader('Access-Control-Allow-Origin', $origin)
                             ->withHeader('Access-Control-Allow-Headers', $headers);

        if ($methods === null) {
            if ($response->hasHeader('Allow')) {
                $response = $response->withHeader('Access-Control-Allow-Methods', $response->getHeader('Allow'));
            }
        } else {
            $methods  = implode(', ', $methods);
            $response = $response->withHeader('Access-Control-Allow-Methods', $methods);
        }

        return $response;
    }

    /**
     * Create a new middleware function to add CORS headers.
     *
     * @return callable
     */
    public function createCorsMiddleware()
    {
        /**
         * @var HandlesApp
         */
        $handler = $this;

        /**
         * @this   Container
         * @param  Request  $request  The HTTP Request object.
         * @param  Response $response The HTTP Response object.
         * @param  callable $next     The next middleware object.
         * @return Response
         */
        return function (Request $request, Response $response, callable $next) use ($handler) {
            $methods  = $handler->getMethodsFromRequest($request);
            $response = $next($request, $response);

            return $handler->respondWithCorsHeaders($response, $methods);
        };
    }

    /**
     * Retrieve the allowed methods from the HTTP request.
     *
     * @param  Request $request The Request object to lookup.
     * @return string[]
     */
    protected function getMethodsFromRequest(Request $request)
    {
        $methods = [];

        $route = $request->getAttribute('route');
        if (empty($route)) {
            $methods[] = $request->getMethod();
        } else {
            $pattern = $route->getPattern();

            foreach ($this->container['router']->getRoutes() as $route) {
                if ($pattern === $route->getPattern()) {
                    $methods = array_merge_recursive($methods, $route->getMethods());
                }
            }
        }

        return $methods;
    }
}
