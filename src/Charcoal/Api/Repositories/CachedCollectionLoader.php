<?php

namespace Charcoal\Api\Repositories;

use InvalidArgumentException;

// From 'charcoal-cache'
use Charcoal\Cache\CachePoolAwareTrait;

// From 'charcoal-core'
use Charcoal\Model\ModelInterface;

/**
 * Cached Object Collection Loader
 */
class CachedCollectionLoader extends ScopedCollectionLoader
{
    use CachePoolAwareTrait;

    /**
     * The prefix for the cache key.
     *
     * @var string
     */
    private $cacheKeyPrefix;

    /**
     * Return a new CollectionLoader object.
     *
     * @param array $data The loader's dependencies.
     */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->setCachePool($data['cache']);
    }

    /**
     * Clone the collection loader.
     *
     * @param  mixed $data An array of customizations for the clone or an object model.
     * @return static
     */
    public function cloneWith($data)
    {
        if (!is_array($data)) {
            $data = [
                'model' => $data,
            ];
        }

        $data['cache'] = $this->cachePool();

        return parent::cloneWith($data);
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed $id The model identifier.
     * @param  callable $before  Process each entity before applying raw data.
     * @param  callable $after   Process each entity after applying raw data.
     * @return ModelInterface|null
     */
    public function loadOne($id, callable $before = null, callable $after = null)
    {
        $model = $this->getModelFromCache($id);
        if ($model !== null) {
            return $model;
        }

        return parent::loadOne($id, $before, $after);
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param  array    $ids One or many model identifiers.
     * @param  callable $before  Process each entity before applying raw data.
     * @param  callable $after   Process each entity after applying raw data.
     * @return ModelInterface[]
     */
    public function loadMany(array $ids, callable $before = null, callable $after = null)
    {
        $models = [];
        foreach ($ids as $id) {
            $models[$id] = $this->getModelFromCache($id);
        }

        $ids = array_keys($models, null, true);
        if (empty($ids)) {
            return $models;
        }

        $missing = parent::loadMany($ids, $before, $after);
        foreach ($missing as $model) {
            $models[$model['id']] = $model;
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     *
     * @overrides CollectionLoader::processModel()
     *
     * @return ModelInterface|null
     */
    protected function processModel($objData, callable $before = null, callable $after = null)
    {
        $obj = parent::processModel($objData, $before, $after);

        if ($obj instanceof ModelInterface) {
            $this->addModelToCache($obj);
        }

        return $obj;
    }



    // Cache
    // -------------------------------------------------------------------------

    /**
     * Fetch a model from the cache.
     *
     * @param  mixed $id The model identifier.
     * @return ModelInterface|null
     */
    protected function getModelFromCache($id)
    {
        $pool = $this->cachePool();
        $key  = $this->getModelCacheKey($id);
        $item = $pool->getItem($key);

        if ($item->isHit()) {
            $data  = $item->get();
            $model = $this->createModelFromData($data);
            $model->setData($data);

            return $model;
        }

        return null;
    }

    /**
     * Add a model to the cache.
     *
     * @param  ModelInterface $model The model to store.
     * @throws InvalidArgumentException If the model is invalid.
     * @return void
     */
    protected function addModelToCache(ModelInterface $model)
    {
        $id = $model['id'];

        if (empty($id)) {
            throw new InvalidArgumentException('Model must have an ID');
        }

        $data = $model->data();
        if (!is_array($data)) {
            throw new InvalidArgumentException('Model must return a dataset');
        }

        $pool = $this->cachePool();
        $key  = $this->getModelCacheKey($id);
        $item = $pool->getItem($key);

        $item->set($data);
        $pool->save($item);
    }

    /**
     * Determines whether a model is present in the cache.
     *
     * @param  mixed $id The model identifier.
     * @return bool
     */
    protected function hasModelInCache($id)
    {
        $pool = $this->cachePool();
        $key  = $this->getModelCacheKey($id);
        $item = $pool->getItem($key);

        return $item->isHit();
    }

    /**
     * Generate a model loader cache key.
     *
     * @param  mixed $id The model identifier to hash.
     * @return string
     */
    private function getModelCacheKey($id)
    {
        if ($this->cacheKeyPrefix === null) {
            $model = $this->model();
            $this->cacheKeyPrefix = 'object/'.str_replace('/', '.', $model::objType().'.'.$model->key());
        }

        return $this->cacheKeyPrefix.'.'.str_replace('/', '.', $id);
    }
}
