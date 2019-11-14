<?php

namespace Charcoal\Api\Repositories;

use ArrayIterator;
use IteratorAggregate;

// From 'charcoal-core'
use Charcoal\Loader\CollectionLoader;

/**
 * Iterable Object Collection Loader
 *
 * Useful for chaining the loader directly into a iterator construct
 * or appending additional criteria.
 */
class CollectionLoaderIterator extends CollectionLoader implements IteratorAggregate
{
    /**
     * @var bool
     */
    private $cursor = false;

    /**
     * Load a collection from source and return a generator.
     *
     * @param  string|null   $ident  Optional. A pre-defined list to use from the model.
     * @param  callable|null $after  Process each entity after applying raw data.
     * @param  callable|null $before Process each entity before applying raw data.
     * @return ModelInterface[]|\Generator
     */
    public function cursor(
        $ident = null,
        callable $after = null,
        callable $before = null
    ) {
        $this->cursor = true;

        if ($ident !== null) {
            yield $this->loadOne($ident, $before, $callback);
        } else {
            yield from $this->load($ident, $after, $before);
        }

        $this->cursor = false;
    }

    /**
     * Process the collection of raw data.
     *
     * @param  mixed[]|Traversable $results The raw result set.
     * @param  callable|null       $before  Process each entity before applying raw data.
     * @param  callable|null       $after   Process each entity after applying raw data.
     * @return ModelInterface[]|\Generator
     */
    protected function processCollection($results, callable $before = null, callable $after = null)
    {
        if ($this->cursor) {
            return $this->processCursor($results, $before, $after);
        } else {
            return parent::processCollection($results, $before, $after);
        }
    }

    /**
     * Process the collection of raw data.
     *
     * @param  mixed[]|Traversable $results The raw result set.
     * @param  callable|null       $before  Process each entity before applying raw data.
     * @param  callable|null       $after   Process each entity after applying raw data.
     * @return ModelInterface[]|\Generator
     */
    protected function processCursor($results, callable $before = null, callable $after = null)
    {
        foreach ($results as $objData) {
            $obj = $this->processModel($objData, $before, $after);

            if ($obj instanceof ModelInterface) {
                yield $obj;
            }
        }
    }

    /**
     * Get an iterator for the collection.
     *
     * This method will {@see CollectionLoader::load() load the results}
     * using the current criteria.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->load());
    }
}
