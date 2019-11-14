<?php

namespace Charcoal\Api\Http\Handler;

use Exception;

// From PSR-7
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-app'
use Charcoal\App\Handler\Error as BaseError;

/**
 * Error Handler
 *
 * Enhanced version of {@see \Charcoal\App\Handler\Error}.
 */
class Error extends BaseError
{
    use HandlesCorsTrait;

    /**
     * Invoke "Not Allowed" Handler
     *
     * @param  ServerRequestInterface $request  The most recent Request object.
     * @param  ResponseInterface      $response The most recent Response object.
     * @param  Exception              $error    The caught Exception object.
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Exception $error
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
