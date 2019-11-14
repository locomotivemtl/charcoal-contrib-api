<?php

namespace Charcoal\Api;

use Throwable;

// From PSR-7
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// from 'pimple/pimple'
use Pimple\Container;

// From 'psr/log'
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

// From 'charcoal-core'
use Charcoal\Model\ModelFactoryTrait;
use Charcoal\Model\ModelInterface;

// From 'charcoal-user'
use Charcoal\User\AuthAwareTrait;

// From 'charcoal-api'
use Charcoal\Api\Service\ParameterValidator;

/**
 * Basic API Controller
 */
abstract class AbstractApiAction implements
    LoggerAwareInterface
{
    use AuthAwareTrait;
    use LoggerAwareTrait;
    use ModelFactoryTrait;

    /** @const array Unauthorized request error. */
    const ERROR_UNAUTHORIZED_REQUEST = [ 'message' => 'Unauthorized request.' ];

    /** @const array Unauthorized request error. */
    const ERROR_RESSOURCE_ERROR = [ 'message' => 'Ressource could not be processed.' ];

    /** @const array Unauthorized request error. */
    const ERROR_RESSOURCE_DOES_NOT_EXIST = [ 'message' => 'Resource does not exist.' ];

    /** @const Allowed uploaded file types */
    const ALLOWED_FILE_TYPES = [ 'image/jpeg', 'image/png' ];

    /** @const Maximum upload file size */
    const MAX_FILE_SIZE = 5242880;

    /** @const PHP file upload error codes and their meaning. */
    const PHP_FILE_UPLOAD_ERRORS = [
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    ];

    /**
     * A store of errors related to the action.
     *
     * @var array $errors
     */
    protected $errors = [];

    /**
     * The cache of camel-cased words.
     *
     * @var array
     */
    protected static $camelCache = [];

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @param array $data Action dependencies.
     */
    public function __construct(array $data = [])
    {
    }

    /**
     * Implement in child actions.
     *
     * @param  Request  $request  The HTTP Request.
     * @param  Response $response The HTTP Response.
     * @return Response
     */
    abstract public function __invoke(Request $request, Response $response);

    /**
     * Inject dependencies from a DI Container.
     *
     * @param  Container $container A Pimple DI service container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        $this->setAuthenticator($container['api/auth/authenticator']);
        $this->setLogger($container['logger']);
        $this->setModelFactory($container['model/factory']);

        $this->debug = (bool)$container['debug'];
    }

    /**
     * @param  Response $response The HTTP response to copy.
     * @return Response The new HTTP response.
     */
    protected function sendAsJson(Response $response)
    {
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param  array    $objects  The objects to return.
     * @param  Response $response The HTTP response.
     * @return Response
     */
    protected function sendJsonArray(array $objects, Response $response)
    {
        $response->getBody()->write(json_encode($objects));

        return $this->sendAsJson($response->withStatus(200));
    }

    /**
     * Returns errors as JSON, with a custom error code (ex: 400).
     *
     * @param  mixed    $errors   The errors to report.
     * @param  int      $code     The HTTP status code.
     * @param  Response $response The HTTP response.
     * @return Response
     */
    protected function sendJsonErrors($errors, $code, Response $response)
    {
        if ($errors instanceof Throwable) {
            if ($this->debug()) {
                $thrown = $errors;
                $errors = [
                    'message' => 'API Application Error',
                    'error'   => [],
                ];

                do {
                    $errors['error'][] = [
                        'type'    => get_class($thrown),
                        'code'    => $thrown->getCode(),
                        'message' => $thrown->getMessage(),
                        'file'    => $thrown->getFile(),
                        'line'    => $thrown->getLine(),
                        'trace'   => explode("\n", $thrown->getTraceAsString()),
                    ];
                } while ($thrown = $thrown->getPrevious());
            } else {
                $errors = [
                    'message' => 'An unknown error occured. Please try again.',
                ];
            }
        }

        if (is_array($errors)) {
            $response->getBody()->write(json_encode($errors));
        }

        return $this->sendAsJson($response->withStatus($code));
    }

    /**
     * Validate the query parameters from a Request object.
     *
     * @param  array $queryParams The request parameters to validate.
     * @return array|bool
     */
    protected function validateQuery(array $queryParams)
    {
        $queryValidator = new ParameterValidator(
            $this->acceptedQueryParameters(),
            $this->parameterValidatorStrictMode()
        );

        return $queryValidator->validate($queryParams);
    }

    /**
     * Retrieve the accepted query parameters for the action.
     * Stub method. No query validation when empty array.
     *
     * @return array
     */
    protected function acceptedQueryParameters()
    {
        return [];
    }

    /**
     * Validate the body parameters from a Request object.
     *
     * @param  mixed $requestBody The request body to validate.
     * @return array|bool
     */
    protected function validateBody($requestBody)
    {
        $requestBody = empty($requestBody) ? [] : $requestBody;
        $requestBodyValidator = new ParameterValidator(
            $this->acceptedBodyParameters(),
            $this->parameterValidatorStrictMode()
        );

        return $requestBodyValidator->validate($requestBody);
    }

    /**
     * Retrieve the accepted body parameters for the action.
     * Stub method. No body validation when empty array.
     *
     * @return array
     */
    protected function acceptedBodyParameters()
    {
        return [];
    }

    /**
     * Retrieve the strict mode flag for the parameter validation.
     * False by default.
     *
     * @return bool
     */
    protected function parameterValidatorStrictMode()
    {
        return false;
    }

    /**
     * @return array|bool
     */
    protected function validateAuth()
    {
        $userValidation = $this->validateUser();
        if ($userValidation !== true) {
            return $userValidation;
        }

        $aclValidation = $this->validatePermissions();
        if ($aclValidation !== true) {
            return $aclValidation;
        }

        return true;
    }

    /**
     * @return array|bool
     */
    private function validateUser()
    {
        $options = $this->authOptions();
        if ($options['userRequired'] !== true) {
            return true;
        }

        $auth = $this->authenticator();
        if (!$auth->check()) {
            return [
                'message' => 'Needs to be authenticated.',
            ];
        }

        return true;
    }

    /**
     * @return array|bool
     */
    private function validatePermissions()
    {
        $options = $this->authOptions();
        if (!$options['permissions']) {
            return true;
        }

        // @todo Check permissions
        return [
            'message' => 'Unauthorized.',
        ];
    }

    /**
     * @return ModelInterface|UserInterface
     */
    protected function user()
    {
        return $this->authenticator()->user();
    }

    /**
     * Retrieve the model data keyed in camelCase.
     *
     * Until charcoal-config v0.9.0, {@see \Charcoal\Config\AbstractEntity::data()}
     * retrieved values keyed in snake_case.  Conversion is needed to merge with camelCase
     * parameters received by actions.
     *
     * @param  ModelInterface $model The model to probe.
     * @return array The model's data.
     */
    protected function normalizeModelData(ModelInterface $model)
    {
        $data = $model->data();
        $data = array_combine(array_map('self::camel', array_keys($data)), array_values($data));
        return $data;
    }

    /**
     * Convert a value to camelCase.
     *
     * Note: Adapted from Illuminate\Support.
     *
     * @see https://github.com/illuminate/support/blob/v5.8.30/LICENSE.md
     *
     * @param  string  $value
     * @return string
     */
    public static function camel($value)
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        $value = ucwords(str_replace([ '-', '_' ], ' ', $value));
        $value = str_replace(' ', '', $value);
        $value = lcfirst($value);

        static::$camelCache[$value] = $value;
        return $value;
    }

    /**
     * @return bool
     */
    protected function debug()
    {
        return $this->debug;
    }

    /**
     * @return array
     */
    protected function authOptions()
    {
        return [
            'userRequired'    => true,
            'permissions'     => null,
        ];
    }



    // Error Handling
    // =========================================================================

    /**
     * Add an error to the error store.
     *
     * @param array $error
     * @return self
     */
    protected function addError($error)
    {
        $this->errors[] = $error;

        return $this;
    }

    /**
     * Add a list of error to the error store.
     *
     * @param array $errors
     * @return self
     */
    protected function addErrors($errors)
    {
        $this->errors = array_values(array_merge($this->errors, $errors));

        return $this;
    }

    /**
     * Set the error store.
     *
     * @param array $errors
     * @return self
     */
    protected function setErrors($errors)
    {
        $this->errors = array_values($errors);

        return $this;
    }

    /**
     * Retrieve the error store.
     *
     * @return array
     */
    protected function errors()
    {
        return array_values($this->errors);
    }

    /**
     * Determines if the error store is populated.
     *
     * @return boolean
     */
    protected function hasErrors()
    {
        return count($this->errors) > 0;
    }
    /**
     * Validate a Model and retrieve the errors if any are detected.
     *
     * @param  ModelInterface $model
     * @return array
     */
    protected function validateModel(ModelInterface $model) : array
    {
        $errors = [];
        if (!$model->validate()) {
            $errors = $this->extractValidatorResults($model);
        }

        return $errors;
    }

    /**
     * Extract and parse the results found in a model's validator instance.
     *
     * @param  ModelInterface $model
     * @return array
     */
    protected function extractValidatorResults(ModelInterface $model) : array
    {
        $errors = [];
        $validation = $model->validator()->results();
        foreach ($validation as $level => $results) {
            foreach ($results as $result) {
                $ident = $result->ident();
                // Ignore duplicated errors. They can be validated on the next attempt. One at a time.
                if (!isset($errors[$ident])) {
                    $errors[$ident] = [
                        'property' => $ident,
                        'message'  => $result->message()
                    ];
                }
            }
        }

        return $errors;
    }
}
