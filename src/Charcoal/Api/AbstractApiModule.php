<?php

namespace Charcoal\Api;

// From Pimple
use Pimple\Container;

// From 'charcoal-app'
use Charcoal\App\App;
use Charcoal\App\Module\AbstractModule;

// From 'charcoal-api'
use Charcoal\Api\Http\Handler\HandlesCorsTrait;

/**
 * Abstract API Module
 */
abstract class AbstractApiModule extends AbstractModule
{
    use HandlesCorsTrait;

    /**
     * The service locator.
     *
     * @var Container
     */
    protected $container;

    /**
     * @return self
     */
    public function setUp()
    {
        $app = $this->app();

        // Used by HandlesCors
        $this->setContainer($app->getContainer());

        $apiRoutes = $app->group($this->apiPath(), [ $this, 'mapApiRoutes' ]);
        foreach ($this->apiRoutesMiddleware() as $middleware) {
            $apiRoutes->add($middleware);
        }

        return $this;
    }

    /**
     * Retrieve the middleware to apply on the API routes group.
     *
     * @return array
     */
    protected function apiRoutesMiddleware()
    {
        return [
            $this->createCorsMiddleware(),
        ];
    }

    /**
     * Define the "api" routes for the application.
     *
     * @param  App $app The main application.
     * @return void
     */
    abstract public static function mapApiRoutes(App $app);

    /**
     * Retrieve the API path.
     *
     * @return string
     */
    abstract protected function apiPath();

    /**
     * Set container for use with the template controller
     *
     * @param  Container $container A dependencies container instance.
     * @return void
     */
    private function setContainer(Container $container)
    {
        $this->container = $container;
    }
}
