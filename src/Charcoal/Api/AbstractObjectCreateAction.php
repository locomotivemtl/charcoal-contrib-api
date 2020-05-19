<?php

namespace Charcoal\Api;

use InvalidArgumentException;
use RuntimeException;

// From 'psr/http-message'
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// From 'mcaskill/charcoal-model-collection'
use Charcoal\Support\Model\Repository\ModelCollectionLoader;

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
     * Validate the created object.
     *
     * @param  ModelInterface|null $object The object to validate.
     * @throws RuntimeException If no errors are defined but the object is invalid. We need to know why.
     * @return array|bool
     */
    protected function validateObject($object)
    {
        if (!$object || !$object['id']) {
            if (empty($this->errors())) {
                throw new RuntimeException('The object is not valid, but a list of errors was not defined.');
            }
            return $this->errors();
        }

        return true;
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
