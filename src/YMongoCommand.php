<?php

class YMongoCommand extends CComponent
{
    const MAX_LIMIT = 999999;

    /**
     * Current MongoDb connection
     *
     * @var YMongoClient
     */
    private $_connection;

    /**
     * Collection name
     *
     * @var string
     */
    private $_collectionName;

    /**
     * Updates array.
     *
     * @var array
     */
    private $_updates = array();

    /**
     * Where array.
     *
     * @var array
     */
    private $_wheres = array();

    /**
     * Selects array.
     *
     * @var array
     */
    private $_selects = array();

    /**
     * Sorts array.
     *
     * @var array
     */
    private $_sorts = array();

    /**
     * Default limit value.
     *
     * @var int
     */
    private $_limit = self::MAX_LIMIT;

    /**
     * Default offset value.
     *
     * @var int
     */
    private $_offset = 0;

    /**
     * Query log.
     *
     * @var array
     */
    private $_queryLog = array();

    /**
     * @param YMongoClient $connection
     * @param string $collectionName
     * @return YMongoCommand
     */
    public function __construct(YMongoClient $connection, $collectionName = null)
    {
        $this->setConnection($connection)
            ->setCollection($collectionName);
        return $this;
    }

    /**
     * Set current collection name
     *
     * @param string $collectionName
     * @return YMongoCommand
     */
    public function setCollection($collectionName)
    {
        $this->_collectionName = $collectionName;
        return $this;
    }

    /**
     * Get current collection name
     *
     * @return string
     */
    public function getCollection()
    {
        return $this->_collectionName;
    }

    /**
     * Set current YMongoClient connection
     *
     * @param YMongoClient $connection
     * @return YMongoCommand
     */
    public function setConnection(YMongoClient $connection)
    {
        $this->_connection = $connection;
        return $this;
    }

    /**
     * Get current YMongoClient connection
     *
     * @return YMongoClient
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * Get the documents based upon the passed parameters
     *
     * @param array $where
     * @param string $collectionName
     * @return array|MongoCursor
     * @throws YMongoException
     */
    public function getWhere($where = array(), $collectionName = null)
    {
        return $this->where($where)->get(false, $collectionName);
    }

    /**
     * Return the found documents
     *
     * @param bool $returnCursor
     * @param string $collectionName
     * @return array|MongoCursor
     * @throws YMongoException
     */
    public function get($returnCursor = false, $collectionName = null)
    {
        return $this->runGet('get', false, $returnCursor, $collectionName);
    }

    /**
     * Get one document based upon the passed parameters
     *
     * @param array $where
     * @param string $collectionName
     * @return array
     * @throws YMongoException
     */
    public function getOneWhere($where = array(), $collectionName = null)
    {
        return $this->where($where)->getOne($collectionName);
    }

    /**
     * Return one document
     *
     * @param string $collectionName
     * @return array
     * @throws YMongoException
     */
    public function getOne($collectionName = null)
    {
        return $this->runGet('getOne', true, false, $collectionName);
    }

    /**
     * Return the found documents
     *
     * @param string $action
     * @param bool $one
     * @param bool $returnCursor
     * @param string $collectionName
     * @return array|MongoCursor
     * @throws YMongoException
     */
    private function runGet($action = 'runFind', $one = false, $returnCursor = false, $collectionName = null)
    {
        $this->prepareCollection($collectionName);

        $collection = $this->getCollection();

        // Start profiling
        $profile = $this->beginProfile($action, $collection);

        // What the function do i need?
        if ($one) {
            $res = $this->getConnection()
                ->getCollection($collection)
                ->findOne($this->_wheres, $this->_selects);
        } else {
            $res = $this->getConnection()
                ->getCollection($collection)
                ->find($this->_wheres, $this->_selects)
                ->limit($this->_limit)
                ->skip($this->_offset)
                ->sort($this->_sorts);
        }

        // Clear instance
        $this->clear($collection, $action);

        // Only one document
        if ($one) {
            // End profiling
            if ($profile) {
                $this->endProfile($profile);
            }
            return $res;
        }

        // Return raw MongoCursor
        if (true === $returnCursor) {
            // End profiling
            if ($profile) {
                $this->endProfile($profile);
            }
            return $res;
        }

        // Walk through the entire collection
        $documents = array();
        /** @var MongoCursor $res */
        while ($res->hasNext()) {
            try {
                $documents[] = $res->getNext();
            }
            catch (Exception $e) { }
        }

        // End profiling
        if ($profile) {
            $this->endProfile($profile);
        }
        return $documents;
    }

    /**
     * Count the number of found documents
     *
     * @param string $collectionName
     * @return int
     */
    public function count($collectionName = null)
    {
        $this->prepareCollection($collectionName);

        $collection = $this->getCollection();

        // Start profiling
        $profile = $this->beginProfile('count', $collection);

        $count = $this->getConnection()
            ->getCollection($collection)
            ->find($this->_wheres, $this->_selects)
            ->limit($this->_limit)
            ->skip($this->_offset)
            ->count();

        $this->clear($collection, 'count');

        // End profiling
        if ($profile) {
            $this->endProfile($profile);
        }

        return $count;
    }

    /**
     * Insert a new document
     *
     * @param array $document
     * @param array $options
     * @param string $collectionName
     * @return bool|MongoId
     * @throws YMongoException
     */
    public function insert($document = array(), $options = array(), $collectionName = null)
    {
        if (!is_array($document) || empty($document)) {
            throw new YMongoException('Nothing to insert into Mongo collection or insert is not an array');
        }

        $this->prepareCollection($collectionName);

        try {
            $collection = $this->getCollection();

            // Start profiling
            $profile = $this->beginProfile('insert', $collection, $document);

            $res = $this->getConnection()
                ->getCollection($collection)
                ->insert(
                    $document,
                    $this->getWriteOptions($options)
                );

            // End profiling
            if ($profile) {
                $this->endProfile($profile);
            }

            /**
             * Returns an array containing the status of the insertion if the "w" option is set.
             * Otherwise, returns TRUE if the inserted array is not empty
             */
            if (true === $res || (is_array($res) && !empty($res['ok']))) {
                return isset($document['_id']) ? $document['_id'] : false;
            } else {
                return false;
            }
        } catch (Exception $e) {
            throw new YMongoException('Insert of data into MongoDB failed: ' . $e->getMessage());
        }
    }

    /**
     * Insert a new documents
     *
     * @param array $documents
     * @param array $options
     * @param string $collectionName
     * @return bool
     * @throws YMongoException
     */
    public function batchInsert($documents = array(), $options = array(), $collectionName = null)
    {
        if (!is_array($documents) || empty($documents)) {
            throw new YMongoException('Nothing to insert into Mongo collection or insert is not an array');
        }

        $this->prepareCollection($collectionName);

        try {
            $collection = $this->getCollection();

            // Start profiling
            $profile = $this->beginProfile('batchInsert', $collection, sizeof($documents) . ' documents');

            $res = $this->getConnection()
                ->getCollection($collection)
                ->batchInsert(
                    $documents,
                    $this->getWriteOptions($options)
                );

            // End profiling
            if ($profile) {
                $this->endProfile($profile);
            }

            /**
             * If the w parameter is set to acknowledge the write, returns an associative array with the status
             * of the inserts ("ok") and any error that may have occurred ("err").
             * Otherwise, returns TRUE if the batch insert was successfully sent, FALSE otherwise.
             */
            if (true === $res || (is_array($res) && !empty($res['ok']))) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            throw new YMongoException('Insert of data into MongoDB failed: ' . $e->getMessage());
        }
    }

    /**
     * Update a document
     *
     * @param array $options
     * @param string $collectionName
     * @return bool
     * @throws YMongoException
     */
    public function update($options = array(), $collectionName = null)
    {
        $options = CMap::mergeArray(array('multiple' => false), $options);
        return $this->runUpdate('update', $options, $collectionName);
    }

    /**
     * Upsert a document
     *
     * @param array $options
     * @param string $collectionName
     * @return bool
     * @throws YMongoException
     */
    public function upsert($options = array(), $collectionName = null)
    {
        $options = CMap::mergeArray(array('upsert'=>true, 'multiple' => false), $options);
        return $this->runUpdate('update', $options, $collectionName);
    }

    /**
     * Update a document
     *
     * @param array $options
     * @param string $collectionName
     * @return bool
     * @throws YMongoException
     */
    public function updateAll($options = array(), $collectionName = null)
    {
        $options = CMap::mergeArray(array('multiple' => true), $options);
        return $this->runUpdate('updateAll', $options, $collectionName);
    }

    /**
     * Execute update
     *
     * @param string $action
     * @param array $options
     * @param string $collectionName
     * @return bool
     * @throws YMongoException
     */
    private function runUpdate($action = 'runUpdate', $options = array(), $collectionName = null)
    {
        if (!is_array($this->_updates) || empty($this->_updates)) {
            throw new YMongoException('Nothing to update in Mongo collection or update is not an array');
        }

        $this->prepareCollection($collectionName);

        try {
            $collection = $this->getCollection();

            // Start profiling
            $profile = $this->beginProfile($action, $collection);

            $res = $this->getConnection()
                ->getCollection($collection)
                ->update(
                    $this->_wheres,
                    $this->_updates,
                    $this->getWriteOptions($options)
                );
            $this->clear($collection, $action);

            // End profiling
            if ($profile) {
                $this->endProfile($profile);
            }

            /**
             * Returns an array containing the status of the update if the "w" option is set. Otherwise, returns TRUE.
             */
            if (true === $res || (is_array($res) && !empty($res['ok']))) {
                if (isset($res['updatedExisting'])) {
                    return $res['updatedExisting'] > 0;
                }
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            throw new YMongoException('Update of data into MongoDB failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete document from the passed collection based upon certain criteria
     *
     * @param string $collectionName
     * @return bool
     * @throws YMongoException
     */
    public function delete($collectionName = null)
    {
        return $this->runDelete('delete', array('justOne' => true), $collectionName);
    }

    /**
     * Delete all documents from the passed collection based upon certain criteria
     *
     * @param string $collectionName
     * @return bool
     * @throws YMongoException
     */
    public function deleteAll($collectionName = null)
    {
        return $this->runDelete('deleteAll', array('justOne' => false), $collectionName);
    }

    /**
     * Run delete command
     *
     * @param string $action
     * @param array $options
     * @param string $collectionName
     * @return bool
     * @throws YMongoException
     */
    private function runDelete($action = 'runDelete', $options = array(), $collectionName = null)
    {
        $this->prepareCollection($collectionName);

        try {
            $collection = $this->getCollection();

            // Start profiling
            $profile = $this->beginProfile($action, $collection);

            $this->getConnection()
                ->getCollection($collection)
                ->remove(
                    $this->_wheres,
                    $this->getWriteOptions($options)
                );
            $this->clear($collection, $action);

            // End profiling
            if ($profile) {
                $this->endProfile($profile);
            }

            return true;
        } catch (Exception $e) {
            throw new YMongoException('Delete of data into MongoDB failed: ' . $e->getMessage());
        }
    }

    /**
     * Sort the documents based on the parameters passed.
     *
     * To set values to descending order, you must pass values of either:
     * - MongoCollection::DESCENDING,
     * - false,
     * - 'desc', or 'DESC',
     *
     * else they will be set to 1 (ASC).
     *
     * @param array $fields
     * @return YMongoCommand
     */
    public function orderBy(array $fields)
    {
        foreach ($fields as $field => $order) {
            if (MongoCollection::DESCENDING === $order || false === $order || 'desc' === strtolower($order)) {
                $this->_sorts[$field] = MongoCollection::DESCENDING;
            } else {
                $this->_sorts[$field] = MongoCollection::ASCENDING;
            }
        }

        return $this;
    }

    /**
     * Limit the result set to $limit number of documents
     *
     * @param int $limit
     * @return YMongoCommand
     */
    public function limit($limit = self::MAX_LIMIT)
    {
        if (null !== $limit && is_numeric($limit) && $limit >= 1) {
            $this->_limit = (int) $limit;
        }

        return $this;
    }

    /**
     * Offset the result set to skip $x number of documents
     *
     * @param int $offset
     * @return YMongoCommand
     */
    public function offset($offset = 0)
    {
        if (null !== $offset && is_numeric($offset) AND $offset >= 1) {
            $this->_offset = (int) $offset;
        }

        return $this;
    }

    /**
     * Set select parameters.
     *
     * Determine which fields to include OR which to exclude during the query process.
     * Currently, including and excluding at the same time is not available, so the $includes array
     * will take precedence over the $excludes array.
     * If you want to only choose fields to exclude, leave $includes an empty array().
     *
     * @param array|string $includes
     * @param array|string $excludes
     * @return YMongoCommand
     */
    public function select($includes = array(), $excludes = array())
    {
        if (!is_array($includes)) {
            $includes = array($includes);
        }

        if (!is_array($excludes)) {
            $excludes = array($excludes);
        }

        if (!empty($includes)) {
            foreach ($includes as $include) {
                $this->_selects[$include] = 1;
            }
        } else {
            foreach ($excludes as $exclude) {
                $this->_selects[$exclude] = 0;
            }
        }

        return $this;
    }

    /**
     * Set where parameters
     *
     * Get the documents based on these search parameters. The $wheres array should be an associative array
     * with the field as the key and the value as the search criteria.
     *
     * @param array|string $wheres
     * @param mixed $value
     * @return YMongoCommand
     */
    public function where($wheres = array(), $value = null)
    {
        if (is_array($wheres)) {
            foreach ($wheres as $where => $value) {
                $this->_wheres[$where] = $value;
            }
        } else {
            $this->_wheres[$wheres] = $value;
        }

        return $this;
    }

    /**
     * Get the documents where the value of a $field may be something else
     *
     * @param array $wheres
     * @return YMongoCommand
     */
    public function orWhere(array $wheres = array())
    {
        if (!empty($wheres)) {
            $ready = array();

            foreach ($wheres as $value) {
                if (is_array($value)) {
                    $ready[] = $value;
                }

            }

            if (!empty($ready)) {
                // Prepare $or wheres statement
                if (!isset($this->_wheres['$or']) || !is_array($this->_wheres['$or'])) {
                    $this->_wheres['$or'] = array();
                }
                // Set statement
                $this->_wheres['$or'] = $ready;
            }
        }

        return $this;
    }

    /**
     * Get the documents where the value of a $field is in a given $in array().
     *
     * @param string $field
     * @param array $inValues
     * @return YMongoCommand
     */
    public function whereIn($field, array $inValues)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$in'] = $inValues;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is in all of a given $in array()
     *
     * @param string $field
     * @param array $inValues
     * @return YMongoCommand
     */
    public function whereInAll($field, array $inValues)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$all'] = $inValues;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is not in a given $in array()
     *
     * @param string $field
     * @param array $inValues
     * @return YMongoCommand
     */
    public function whereNotIn($field, array $inValues)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$nin'] = $inValues;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is greater than $value.
     *
     * @param string $field
     * @param mixed $value
     * @return YMongoCommand
     */
    public function whereGt($field, $value)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$gt'] = $value;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is greater than or equal to $value.
     *
     * @param string $field
     * @param mixed $value
     * @return YMongoCommand
     */
    public function whereGte($field, $value)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$gte'] = $value;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is less than $value.
     *
     * @param string $field
     * @param mixed $value
     * @return YMongoCommand
     */
    public function whereLt($field, $value)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$lt'] = $value;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is less than or equal to $value.
     *
     * @param string $field
     * @param mixed $value
     * @return YMongoCommand
     */
    public function whereLte($field, $value)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$lte'] = $value;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is between $valueX and $valueY.
     *
     * @param string $field
     * @param mixed $valueX
     * @param mixed $valueY
     * @return YMongoCommand
     */
    public function whereBetween($field, $valueX, $valueY)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$gte'] = $valueX;
        $this->_wheres[$field]['$lte'] = $valueY;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is between but not equal to $valueX and $valueY.
     *
     * @param string $field
     * @param mixed $valueX
     * @param mixed $valueY
     * @return YMongoCommand
     */
    public function whereBetweenNe($field, $valueX, $valueY)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$gt'] = $valueX;
        $this->_wheres[$field]['$lt'] = $valueY;

        return $this;
    }

    /**
     * Get the documents where the value of a $field is not equal to $value.
     *
     * @param string $field
     * @param $value
     * @return YMongoCommand
     */
    public function whereNe($field, $value)
    {
        $this->whereInit($field);
        $this->_wheres[$field]['$ne'] = $value;

        return $this;
    }

    /**
     * Get the documents nearest to an array of coordinates (your collection must have a geospatial index)
     *
     * @param string $field
     * @param array $cords
     * @param mixed $distance
     * @param bool $spherical
     * @return YMongoCommand
     */
    public function whereNear($field, array $cords, $distance = null, $spherical = false)
    {
        $this->whereInit($field);

        if ($spherical) {
            $this->_wheres[$field]['$nearSphere'] = $cords;
        } else {
            $this->_wheres[$field]['$near'] = $cords;
        }

        if (null !== $distance) {
            $this->_wheres[$field]['$maxDistance'] = $distance;
        }

        return $this;
    }

    /**
     * Get the documents where the (string) value of a $field is like a value.
     * The defaults allow for a case-insensitive search.
     *
     * @param string $field
     * @param string $value
     * @param string $flags
     * @param bool $enableStartWildcard
     * @param bool $enableEndWildcard
     * @return YMongoCommand
     */
    public function whereLike($field, $value, $flags = 'i', $enableStartWildcard = true, $enableEndWildcard = true)
    {
        $this->whereInit($field);
        $value = quotemeta(trim((string) $value));

        if (true !== $enableStartWildcard) {
            $value = '^' . $value;
        }
        if (true !== $enableEndWildcard) {
            $value .= '$';
        }

        $this->_wheres[$field] = new MongoRegex('/' . $value . '/' . $flags);

        return $this;
    }

    /**
     * Increments the value of a field
     *
     * @param array|string $fields
     * @param int $value
     * @return YMongoCommand
     */
    public function inc($fields = array(), $value = 1)
    {
        $this->updateInit('$inc');

        if (is_string($fields)) {
            $this->_updates['$inc'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->_updates['$inc'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Decrements the value of a field
     *
     * @param array|string $fields
     * @param int $value
     * @return YMongoCommand
     */
    public function dec($fields = array(), $value = 1)
    {
        $this->updateInit('$inc');

        if (is_string($fields)) {
            $this->_updates['$inc'][$fields] = ($value > 0 ? (0 - $value) : 0);
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->_updates['$inc'][$field] = ($value > 0 ? (0 - $value) : 0);
            }
        }

        return $this;
    }

    /**
     * Sets a field to a value
     *
     * @param array|string $fields
     * @param mixed $value
     * @return YMongoCommand
     */
    public function set($fields, $value = null)
    {
        $this->updateInit('$set');

        if (is_string($fields)) {
            $this->_updates['$set'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->_updates['$set'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Unsets a field (or fields)
     *
     * @param array|string $fields
     * @return YMongoCommand
     */
    public function unsetField($fields)
    {
        $this->updateInit('$unset');

        if (is_string($fields)) {
            $this->_updates['$unset'][$fields] = 1;
        } elseif (is_array($fields)) {
            foreach ($fields as $field) {
                $this->_updates['$unset'][$field] = 1;
            }
        }

        return $this;
    }

    /**
     * Adds value to the array only if its not in the array already
     *
     * @param string $field
     * @param array|string $values
     * @return YMongoCommand
     */
    public function addToSet($field, $values)
    {
        $this->updateInit('$addToSet');

        if (is_string($values)) {
            $this->_updates['$addToSet'][$field] = $values;
        } elseif (is_array($values)) {
            $this->_updates['$addToSet'][$field] = array('$each' => $values);
        }

        return $this;
    }

    /**
     * Pushes values into a field (field must be an array)
     *
     * @param array|string $fields
     * @param array $value
     * @return YMongoCommand
     */
    public function push($fields, $value = array())
    {
        $this->updateInit('$push');

        if (is_string($fields)) {
            $this->_updates['$push'][$fields] = $value;
        } elseif (is_array($fields)) {
            foreach ($fields as $field => $value) {
                $this->_updates['$push'][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Pops the last value from a field (field must be an array)
     *
     * @param array|string $field
     * @return YMongoCommand
     */
    public function pop($field)
    {
        $this->updateInit('$pop');

        if (is_string($field)) {
            $this->_updates['$pop'][$field] = -1;
        } elseif (is_array($field)) {
            foreach ($field as $pop_field) {
                $this->_updates['$pop'][$pop_field] = -1;
            }
        }

        return $this;
    }

    /**
     * Removes by an array by the value of a field
     *
     * @param string $field
     * @param array $value
     * @return YMongoCommand
     */
    public function pull($field = '', $value = array())
    {
        $this->updateInit('$pull');
        $this->_updates['$pull'] = array($field => $value);

        return $this;
    }

    /**
     * Renames a field
     *
     * @param string $oldName
     * @param string $newName
     * @return YMongoCommand
     */
    public function renameField($oldName, $newName)
    {
        $this->updateInit('$rename');
        $this->_updates['$rename'][$oldName] = $newName;

        return $this;
    }

    public function lastQuery()
    {
        return $this->_queryLog;
    }

    /**
     * Reset the class variables to default settings.
     *
     * @param string $collection
     * @param string $action
     */
    private function clear($collection, $action)
    {
        $this->_queryLog = array(
            'collection' => $collection,
            'action'     => $action,
            'wheres'     => $this->_wheres,
            'updates'    => $this->_updates,
            'selects'    => $this->_selects,
            'limit'      => $this->_limit,
            'offset'     => $this->_offset,
            'sorts'      => $this->_sorts
        );

        $this->_selects = array();
        $this->_updates = array();
        $this->_wheres  = array();
        $this->_sorts   = array();
        $this->_limit   = self::MAX_LIMIT;
        $this->_offset  = 0;
    }

    /**
     * Prepares parameters for insertion in $wheres array().
     *
     * @param string $field
     */
    private function whereInit($field)
    {
        if (!isset($this->_wheres[$field])) {
            $this->_wheres[$field] = array();
        }
    }

    /**
     * Prepares parameters for insertion in $updates array().
     *
     * @param string $field
     */
    private function updateInit($field = '')
    {
        if (!isset($this->_updates[$field])) {
            $this->_updates[$field] = array();
        }
    }

    /**
     * Prepare collection
     *
     * @param string $collectionName
     * @throws YMongoException
     */
    private function prepareCollection($collectionName= null)
    {
        if (null === $this->_collectionName && null === $collectionName) {
            throw new YMongoException('No Mongo collection selected to insert into');
        }

        // Save current collection
        if (null !== $collectionName) {
            $this->setCollection($collectionName);
        }
    }

    /**
     * Merge default write concern with given options
     *
     * @param array $options
     * @return array
     */
    private function getWriteOptions($options = array())
    {
        return CMap::mergeArray(
            $this->getConnection()->getDefaultWriteConcern(),
            $options
        );
    }

    /**
     * @param string $action
     * @param string $collection
     * @param mixed $profile
     * @return bool|string
     */
    private function beginProfile($action, $collection, $profile = null)
    {
        if ($this->getConnection()->enableProfiling) {
            if (null === $profile) {
                $profile = 'MongoCommand "' . $action . '" from: ' . $collection;
                if (!empty($this->_updates)) {
                    $profile .= ', update: ' . $this->makeMeShorterProfile($this->_updates);
                }
                if (!empty($this->_wheres)) {
                    $profile .= ', where: ' . $this->makeMeShorterProfile($this->_wheres);
                }
                if (!empty($this->_selects)) {
                    $profile .= ', select: ' . $this->makeMeShorterProfile($this->_selects);
                }
                if (0 !== $this->_offset) {
                    $profile .= ', offset: ' . $this->_offset;
                }
                if (self::MAX_LIMIT !== $this->_limit) {
                    $profile .= ', limit: ' . $this->_limit;
                }
                if (!empty($this->_sorts)) {
                    $profile .= ', sort: ' . $this->makeMeShorterProfile($this->_sorts);
                }
            }
            // Non-scalar
            elseif (!is_scalar($profile)) {
                if (is_array($profile)) {
                    $profile = $this->makeMeShorterProfile($profile);
                } else {
                    $profile = print_r($profile, true); // production-ok
                }
            }
            Yii::beginProfile($profile, 'system.mongoDb.YMongoCommand');
            return $profile;
        }
        return false;
    }

    /**
     * @param $array
     * @return string
     */
    private function makeMeShorterProfile(array $array)
    {
        if (($size = sizeof($array)) > 2) {
            return print_r(array_slice($array, 0 , 2), true) . ' AND ' . ($size - 2) . ' ELEMENTS'; // production-ok
        } else {
            return print_r($array, true); // production-ok
        }
    }

    /**
     * @param string $profile
     */
    private function endProfile($profile)
    {
        Yii::endProfile($profile, 'system.mongoDb.YMongoCommand');
    }

    /**
     * Create new MongoDate object from current time or pass timestamp to create MongoDate
     * .
     * @param mixed $value
     * @return MongoDate
     */
    public static function mDate($value = null)
    {
        // Just create new one
        if (null === $value) {
            return new MongoDate();
        }

        // Parse string or int
        if (!($value instanceof MongoDate)) {
            if (is_string($value)) {
                $value = new MongoDate(strtotime($value));
            }
            elseif (is_int($value)) {
                $value = new MongoDate($value);
            }
            else {
                $value = new MongoDate();
            }
        }
        return $value;
    }

    /**
     * Create new MongoId object
     * .
     * @param mixed $value
     * @return MongoId
     */
    public static function mId($value = null)
    {
        // Just create new one
        if (null === $value) {
            return new MongoId();
        }

        return ($value instanceof MongoId) ? $value : new MongoId($value);
    }
}
