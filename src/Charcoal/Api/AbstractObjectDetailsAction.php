<?php

namespace Charcoal\Api;

// From PSR-7
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// From 'charcoal-core'
use Charcoal\Model\ModelInterface;

// From 'charcoal-presenter'
use Charcoal\Presenter\Presenter;

// From 'charcoal-api'
use Charcoal\Api\Repositories\ModelCollectionLoader;

/**
 * The Charcoal API Object Details Action.
 */
abstract class AbstractObjectDetailsAction extends AbstractApiAction
{
    /**
     * @var string
     */
    protected $objectId;

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

        $this->setObjectId($data['objectId']);
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

        $queryValidation = $this->validateQuery($request->getQueryParams());
        if ($queryValidation !== true) {
            return $this->sendJsonErrors($queryValidation, 400, $response);
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

        return $this->sendJsonArray($this->objectPresenter->transform($object), $response);
    }

    /**
     * Load an object according to the objectId parameter.
     *
     * @return ModelInterface|null
     */
    protected function loadObject()
    {
        return $this->objectRepository->load($this->objectId);
    }

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

    /**
     * @param  string $id The object Id.
     * @return void
     */
    private function setObjectId($id)
    {
        $this->objectId = $id;
    }

    /**
     * @param  ModelCollectionLoader $repository Model collection loader.
     * @return void
     */
    private function setObjectRepository(ModelCollectionLoader $repository)
    {
        $this->objectRepository = $repository;
    }

    /**
     * @param  object $presenter Presenter and proper transformer.
     * @return void
     */
    private function setObjectPresenter($presenter)
    {
        if (!is_callable([ $presenter, 'transform' ])) {
            throw new InvalidArgumentException(
                'Presenter must have a \'transform\' method'
            );
        }

        $this->objectPresenter = $presenter;
    }
}
