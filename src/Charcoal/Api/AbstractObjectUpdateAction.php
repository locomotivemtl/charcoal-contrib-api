<?php

namespace Charcoal\Api;

// From 'psr/http-message'
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// From 'charcoal-core'
use Charcoal\Model\ModelInterface;

/**
 * The Charcoal API Object Update Action.
 */
abstract class AbstractObjectUpdateAction extends AbstractObjectDetailsAction
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

        $bodyValidation = $this->validateBody($request->getParsedBody());
        if ($bodyValidation !== true) {
            return $this->sendJsonErrors($bodyValidation, 400, $response);
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

        $object = $this->updateObject($object, $request);

        $validation = $this->validateObject($object);
        if ($validation !== true) {
            return $this->sendJsonErrors($validation, 404, $response);
        }

        return $this->sendJsonArray($this->objectPresenter->transform($object), $response);
    }

    /**
     * Update the object using the request.
     *
     * @param  ModelInterface $object  The object to update.
     * @param  Request        $request The HTTP Request.
     * @return ModelInterface|null
     */
    abstract protected function updateObject(ModelInterface $object, Request $request);

    /**
     * Parse the request body using predefined params to search for.
     *
     * @param Request $request   The Psr-7 request.
     * @param mixed   ...$params Array of params to look for.
     * @return array
     */
    protected function parseRequestParams(Request $request, ...$params)
    {
        $requestParams = $request->getParsedBody();
        if (empty($requestParams)) {
            return [];
        }

        $parsedParams = [];

        foreach ($params as $param) {
            if (is_string($param) && isset($requestParams[$param])) {
                $parsedParams[$param] = $requestParams[$param];
            }
        }

        return $parsedParams;
    }
}
