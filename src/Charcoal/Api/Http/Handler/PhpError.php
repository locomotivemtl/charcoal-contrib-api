<?php

namespace Charcoal\Api\Http\Handler;

use Throwable;

// From PSR-7
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-app'
use Charcoal\App\Handler\PhpError as BasePhpError;

/**
 * Error Handler
 *
 * Enhanced version of {@see \Charcoal\App\Handler\PhpError}.
 */
class PhpError extends BasePhpError
{
    use HandlesCorsTrait;

    /**
     * Invoke "Not Allowed" Handler
     *
     * @param  ServerRequestInterface $request  The most recent Request object.
     * @param  ResponseInterface      $response The most recent Response object.
     * @param  Throwable              $error    The caught Throwable object.
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Throwable $error
    ) {
        $response = parent::__invoke($request, $response, $error);

        return $this->respondWithCorsHeaders($response);
    }

    /**
     * Retrieve the handler's message.
     *
     * @return string
     */
    public function getMessage()
    {
        if ($this->displayErrorDetails() && $this->hasThrown()) {
            return $this->getThrown()->getMessage();
        } else {
            return $this->translator()->translate('An unknown error occured. Please try again.');
        }
    }
}
