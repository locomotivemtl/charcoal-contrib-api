<?php

namespace Charcoal\Api;

// From PSR-7
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// From 'charcoal-presenter'
use Charcoal\Presenter\Presenter;

// From 'charcoal-api'
use Charcoal\Api\Repositories\ModelCollectionLoader;

/**
 * The Charcoal API Object Create Action.
 */
abstract class AbstractObjectCreateAction extends AbstractApiAction
{
    /**
     * @var ModelCollectionLoader
     */
    protected $objectRepository;

    /**
     * @var Presenter
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

        $bodyValidation = $this->validateBody($request->getParsedBody());
        if ($bodyValidation !== true) {
            return $this->sendJsonErrors($bodyValidation, 400, $response);
        }

        $object = $this->createObject($request);

        $objectValidation = $this->validateObject($object);
        if ($objectValidation !== true) {
            return $this->sendJsonErrors($objectValidation, 404, $response);
        }

        return $this->sendJsonArray($this->objectPresenter->transform($object), $response);
    }

    /**
     * Create the object using the request.
     *
     * @param Request  $request  PSR7 Request.
     * @return ModelInterface|null
     */
    abstract protected function createObject(Request $request);

    /**
     * Validate the loaded object.
     *
     * @param  ModelInterface|null $object The object to validate.
     * @return array|bool
     */
    protected function validateObject($object)
    {
        if (!$object || !$object['id']) {
            return [ 'message' => 'Resource not found' ];
        }

        return true;
    }
}
