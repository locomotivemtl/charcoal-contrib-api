<?php

namespace Charcoal\Api\Repositories;

use PDO;
use RuntimeException;
use InvalidArgumentException;

// From 'charcoal-core'
use Charcoal\Model\ModelInterface;

/**
 * Model Collection Loader
 */
class ModelCollectionLoader extends CollectionLoaderIterator
{
    /**
     * Total number of objects found via `SQL_CALC_FOUND_ROWS`.
     *
     * @var int|null
     */
    protected $foundRows;

    /**
     * Track whether collection loader has changed.
     *
     * @var bool
     */
    protected $isDirty = false;

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

        $defaults = [
            'logger'     => $this->logger,
            'collection' => $this->collectionClass(),
            'factory'    => $this->factory(),
            'model'      => $this->model(),
        ];

        $data = array_merge($defaults, $data);

        $clone = new static($data);

        $typeField = $this->dynamicTypeField();
        if ($typeField) {
            $clone->setDynamicTypeField($typeField);
        }

        $callback = $this->callback();
        if ($callback) {
            $clone->setCallback($callback);
        }

        return $clone;
    }

    /**
     * Reset everything but the model.
     *
     * @return self
     */
    public function reset()
    {
        if ($this->isDirty) {
            parent::reset();

            $this->foundRows = null;
            $this->isDirty   = false;
        }

        return $this;
    }

    /**
     * Get the total number of items for the last query.
     *
     * @return int|null
     */
    public function foundRows()
    {
        if ($this->foundRows === null) {
            $this->foundRows = $this->loadFound();
        }

        return $this->foundRows;
    }

    /**
     * Set the model to use for the loaded objects.
     *
     * @param  string|ModelInterface $model An object model.
     * @throws RuntimeException If this method is called a second time.
     * @return self
     */
    public function setModel($model)
    {
        if ($this->hasModel()) {
            throw new RuntimeException(
                sprintf(
                    'A model is already assigned to this collection loader: %s',
                    get_class($this->model())
                )
            );
        }

        parent::setModel($model);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::setCallback()
     *
     * @param  callable $callback The callback routine.
     * @return self
     */
    public function setCallback(callable $callback)
    {
        $this->isDirty = true;
        parent::setCallback($callback);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::setDynamicTypeField()
     *
     * @param  string $field The field to use for dynamic object type.
     * @return self
     */
    public function setDynamicTypeField($field)
    {
        $this->isDirty = true;
        parent::setDynamicTypeField($field);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::setProperties()
     *
     * @param  array $properties An array of property identifiers.
     * @return self
     */
    public function setProperties(array $properties)
    {
        $this->isDirty = true;
        parent::setProperties($properties);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::addProperty()
     *
     * @param  string $property A property identifier.
     * @return self
     */
    public function addProperty($property)
    {
        $this->isDirty = true;
        parent::addProperty($property);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::addKeyword()
     *
     * @param  string $keyword    A value to match among $properties.
     * @param  array  $properties One or more of properties to search amongst.
     * @return self
     */
    public function addKeyword($keyword, array $properties = null)
    {
        $this->isDirty = true;
        parent::addKeyword($keyword, $properties);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::addFilter()
     *
     * @param  mixed $param   The property to filter by.
     * @param  mixed $value   Optional value for the property to compare against.
     * @param  array $options Optional extra settings to apply on the filter.
     * @return self
     */
    public function addFilter($param, $value = null, array $options = null)
    {
        $this->isDirty = true;
        $this->source()->addFilter($param, $value, $options);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::addOrder()
     *
     * @param  mixed  $param   The property to sort by.
     * @param  string $mode    Optional sorting mode.
     * @param  array  $options Optional extra settings to apply on the order.
     * @return self
     */
    public function addOrder($param, $mode = 'asc', array $options = null)
    {
        $this->isDirty = true;
        $this->source()->addOrder($param, $mode, $options);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::setPagination()
     *
     * @param  mixed $param An associative array of pagination settings.
     * @return self
     */
    public function setPagination($param)
    {
        $this->isDirty = true;
        $this->source()->setPagination($param);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::setPage()
     *
     * @param  int $page A page number.
     * @return self
     */
    public function setPage($page)
    {
        $this->isDirty = true;
        $this->pagination()->setPage($page);
        return $this;
    }

    /**
     * @overrides \Charcoal\Loader\CollectionLoader::setNumPerPage()
     *
     * @param  int $num The number of items to display per page.
     * @return self
     */
    public function setNumPerPage($num)
    {
        $this->isDirty = true;
        $this->pagination()->setNumPerPage($num);
        return $this;
    }

    /**
     * Get the number of items for this collection query.
     *
     * @overrides \Charcoal\Loader\CollectionLoader::loadCount()
     *     BREAKING: This method returns count with pagination considered.
     *
     * @throws RuntimeException If the database connection fails.
     * @return int
     */
    public function loadCount()
    {
        $source = $this->source();

        $sql  = $source->sqlLoadCount();
        $sql .= $source->sqlPagination();

        $dbh = $source->db();
        if (!$dbh) {
            throw new RuntimeException(
                'Could not instanciate a database connection'
            );
        }

        $this->logger->debug($sql);

        $sth = $dbh->prepare($sql);
        $sth->execute();
        $num = $sth->fetchColumn(0);

        return (int)$num;
    }

    /**
     * Get the total number of items for this collection query.
     *
     * @overrides \Charcoal\Loader\CollectionLoader::loadCount()
     *     BREAKING: This method returns total number of items
     *     found matching the current query parameters.
     *
     * @throws RuntimeException If the database connection fails.
     * @return int
     */
    public function loadFound()
    {
        $source = $this->source();

        $sql = $source->sqlLoadCount();

        $dbh = $source->db();
        if (!$dbh) {
            throw new RuntimeException(
                'Could not instanciate a database connection'
            );
        }

        $this->logger->debug($sql);

        $sth = $dbh->prepare($sql);
        $sth->execute();
        $num = (int)$sth->fetchColumn(0);

        return $num;
    }

    /**
     * @param  array    $filters    One or many filters.
     * @param  callable $callback   Process each entity after applying raw data.
     * @param  callable $before     Process each entity before applying raw data.
     * @param  int      &$foundRows If provided, then it is filled with the number of found rows.
     * @return ModelInterface[]
     */
    public function findBy(
        array $filters = [],
        callable $callback = null,
        callable $before = null,
        &$foundRows = null
    ) {
        $this->addFilters($filters);

        return $this->load(null, $callback, $before, $foundRows);
    }

    /**
     * {@inheritdoc}
     *
     * @overrides \Charcoal\Loader\CollectionLoader::load()
     *     This method adds support for `SQL_CALC_FOUND_ROWS`
     *     and repurposes the first parameter.
     *
     * @param  mixed    $ident      The model identifier.
     * @param  callable $callback   Process each entity after applying raw data.
     * @param  callable $before     Process each entity before applying raw data.
     * @param  int      &$foundRows If provided, then it is filled with the number of found rows.
     * @return ModelInterface[]
     */
    public function load(
        $ident = null,
        callable $callback = null,
        callable $before = null,
        &$foundRows = null
    ) {
        if ($ident !== null) {
            return $this->loadOne($ident, $before, $callback);
        }

        $source  = $this->source();
        $selects = $source->sqlSelect();
        $tables  = $source->sqlFrom();
        $filters = $source->sqlFilters();
        $orders  = $source->sqlOrders();
        $limits  = $source->sqlPagination();

        $calcFoundRows = $limits ? 'SQL_CALC_FOUND_ROWS ' : '';

        $sql = 'SELECT '.$calcFoundRows.$selects.' FROM '.$tables.$filters.$orders.$limits;
        $results = $this->loadFromQuery($sql, $callback, $before, $foundRows);

        return $results;
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed    $id The model identifier.
     * @param  callable $before  Process each entity before applying raw data.
     * @param  callable $after   Process each entity after applying raw data.
     * @throws InvalidArgumentException If the $id does not resolve to a queryable statement.
     * @return ModelInterface|null
     */
    public function loadOne($id, callable $before = null, callable $after = null)
    {
        if (empty($id) || !is_scalar($id)) {
            throw new InvalidArgumentException('One model ID is required');
        }

        $source = $this->source();
        $model  = $this->model();

        $this->addFilter([
            'property' => $model->key(),
            'operator' => '=',
            'value'    => $id,
        ]);

        $selects = $source->sqlSelect();
        $tables  = $source->sqlFrom();
        $filters = $source->sqlFilters();

        $sql = 'SELECT '.$selects.' FROM '.$tables.$filters.' LIMIT 1';

        $this->logger->debug($sql);
        $dbh = $source->db();
        $sth = $dbh->prepare($sql);
        $sth->execute();

        if ($sth->execute() === false) {
            return null;
        }

        $data = $sth->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            return $this->processModel($data, $before, $after);
        }

        return null;
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param  array    $ids One or many model identifiers.
     * @param  callable $before  Process each entity before applying raw data.
     * @param  callable $after   Process each entity after applying raw data.
     * @throws InvalidArgumentException If the $ids do not resolve to a queryable statement.
     * @return ModelInterface[]
     */
    public function loadMany(array $ids, callable $before = null, callable $after = null)
    {
        $ids = array_values(array_filter($ids, 'strlen'));

        if (empty($ids)) {
            throw new InvalidArgumentException('At least one model ID is required');
        }

        $source = $this->source();
        $model  = $this->model();
        $key    = $model->key();

        $this->addFilter([
            'property' => $key,
            'operator' => 'IN',
            'values'   => $ids,
        ]);

        $this->addOrder([
            'property' => $key,
            'values'   => $ids,
        ]);

        $selects = $source->sqlSelect();
        $tables  = $source->sqlFrom();
        $filters = $source->sqlFilters();
        $orders  = $source->sqlOrders();

        $sql = 'SELECT '.$selects.' FROM '.$tables.$filters.$orders.' LIMIT '.count($ids);
        $results = $this->loadFromQuery($sql, $after, $before);

        return $results;
    }

    /**
     * {@inheritdoc}
     *
     * @overrides \Charcoal\Loader\CollectionLoader::loadFromQuery()
     *     This method adds support for `SQL_CALC_FOUND_ROWS`.
     *
     * @return ModelInterface[]
     */
    public function loadFromQuery(
        $query,
        callable $callback = null,
        callable $before = null,
        &$foundRows = null
    ) {
        $source = $this->source();

        $dbh = $source->db();
        if (!$dbh) {
            throw new RuntimeException(
                'Could not instanciate a database connection'
            );
        }

        /** @todo Filter binds */
        if (is_string($query)) {
            $query = trim($query);
            $this->logger->debug($query);
            $sth = $dbh->prepare($query);
            $sth->execute();
        } elseif (is_array($query)) {
            list($query, $binds, $types) = array_pad($query, 3, []);
            $query = trim($query);

            $sth = $source->dbQuery($query, $binds, $types);
        } else {
            throw new InvalidArgumentException(sprintf(
                'The SQL query must be a string or an array: '.
                '[ string $query, array $binds, array $dataTypes ]; '.
                'received %s',
                is_object($query) ? get_class($query) : $query
            ));
        }

        if (strpos($query, 'SELECT SQL_CALC_FOUND_ROWS') === 0) {
            $sql = 'SELECT FOUND_ROWS()';
            $this->logger->debug($sql);

            $cfr = $dbh->prepare($sql);
            $cfr->execute();

            $foundRows = (int)$cfr->fetchColumn(0);
            $this->foundRows = $foundRows;
        }

        $sth->setFetchMode(PDO::FETCH_ASSOC);

        if ($callback === null) {
            $callback = $this->callback();
        }

        return $this->processCollection($sth, $before, $callback);
    }

    /**
     * Create a new model.
     *
     * @return ModelInterface
     */
    public function createModel()
    {
        $model = $this->factory()->create($this->modelClass());
        $model->setSource($this->source());
        return $model;
    }

    /**
     * Create a new model from a dataset.
     *
     * @param  array $data The model data.
     * @return ModelInterface
     */
    protected function createModelFromData(array $data)
    {
        $model = $this->factory()->create($this->dynamicModelClass($data));
        $model->setSource($this->source());
        return $model;
    }
}
