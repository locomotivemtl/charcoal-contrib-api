<?php

namespace Charcoal\Api;

use InvalidArgumentException;

// From 'psr/http-message'
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// From 'mcaskill/charcoal-model-collection'
use Charcoal\Support\Model\Repository\ModelCollectionLoader;

/**
 * The Charcoal API Object Collection Action.
 */
abstract class AbstractObjectCollectionAction extends AbstractApiAction
{
    /**
     * @var ModelCollectionLoader
     */
    protected $objectRepository;

    /**
     * @var callable
     */
    protected $objectPresenter;

    /**
     * @param array $data Action dependencies.
     */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->setObjectRepository($data['objectRepository']);
        $this->setObjectPresenter($data['objectPresenter']);
    }

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

        $validation = $this->validateQuery($request->getQueryParams());
        if ($validation !== true) {
            return $this->sendJsonErrors($validation, 400, $response);
        }


        $objects = $this->loadObjects($request->getQueryParams());
        return $this->sendJsonArray($objects, $response);
    }

    /**
     * @param  array $params Request query parameters.
     * @return array
     */
    protected function loadObjects(array $params)
    {
        $objects = $this->getPreparedObjectRepository($params)->load();

        return array_map([ $this->objectPresenter, 'transform' ], $objects);
    }

    /**
     * Manipulate the collection loader instance used by the action, possibly with the request query parameters.
     * This method is a stub that does nothing. Reimplement in sub-class to add, for examples, filters and orders.
     *
     * @param  array $params Request query parameters.
     * @return ModelCollectionLoader
     */
    protected function getPreparedObjectRepository(array $params)
    {
        return $this->objectRepository;
    }

    /**
     * @param  ModelCollectionLoader $repository Model collection loader.
     * @return void
     */
    protected function setObjectRepository(ModelCollectionLoader $repository)
    {
        $this->objectRepository = $repository;
    }

    /**
     * @param  object $presenter Presenter and proper transformer.
     * @return void
     */
    protected function setObjectPresenter($presenter)
    {
        if (!is_callable([ $presenter, 'transform' ])) {
            throw new InvalidArgumentException(
                'Presenter must have a \'transform\' method'
            );
        }

        $this->objectPresenter = $presenter;
    }
}
