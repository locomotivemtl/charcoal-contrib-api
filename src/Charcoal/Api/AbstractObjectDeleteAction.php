<?php

namespace Charcoal\Api;

// From 'psr/http-message'
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// From 'charcoal-core'
use Charcoal\Model\ModelInterface;

/**
 * The Charcoal API Object Delete Action.
 */
abstract class AbstractObjectDeleteAction extends AbstractObjectDetailsAction
{
    /**
     * @param  Request  $request  The HTTP Request.
     * @param  Response $response The HTTP Response.
     * @return Response
     */
    public function __invoke(Request $request, Response $response)
    {
        $authValidation = $this->validateAuth();
        if ($authValidation !== true) {
            return $this->sendJsonErrors($authValidation, 401, $response);
        }

        $object = $this->loadObject();

        $objectValidation = $this->validateObject($object);
        if ($objectValidation !== true) {
            return $this->sendJsonErrors($objectValidation, 404, $response);
        }

        /*
        $objectAuthValidation = !$this->validateObjectAuthorization($object, $request)
        if ($objectAuthValidation !== true) {
            return $this->>sendJsonErrors($objectAuthValidation, 401, $response);
        }
        */

        if ($object->delete() !== true) {
            return $this->sendJsonErrors([ 'message' => 'Object could not be deleted.' ], 404, $response);
        }

        return $response->withStatus(204);
    }
}
