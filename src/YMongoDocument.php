<?php

/**
 *
 * Sub documents can be used like this (!!! sub documents need to extend YMongoModel class !!!):
 *
 * public function subDocuments() {
 *     return array(
 *         'attributeName1' => array('SubDocClass1', 'type' => 'single'),
 *         'attributeName2' => array('SubDocClass2', 'type' => 'multi'),
 *     );
 * }
 *
 * To gain access to the nested document:
 * - single:
 *   $object->attributeName1->subAttributeName;
 *
 * - multi:
 *   $object->attributeName2; // this is YMongoArrayModel Iterator
 *
 * - multi:
 *   $object->attributeName2[0]->subAttributeName; // Attribute of the first sub document
 *
 * You can set new values to sub documents this way:
 * - single:
 *   $object->attributeName1 = array('subAttributeName' => 'value');
 *
 * - multi:
 *   $object->attributeName2[0] = array('subAttributeName' => 'value');
 *
 * - multi:
 *   $object->attributeName2 = array(
 *       array('subAttributeName' => 'value')
 *   );
 *
 * How to validate sub documents?
 * Validation rules can be specified in the model sub document, or through parameter 'rules'.
 *
 * public function rules() {
 *     return array(
 *         array('attributeName1', 'mongoSubDocument'), // All rules must be specified in 'attributeName1' class
 *         array('attributeName1', 'mongoSubDocument', 'rules' => array( // Or right here
 *             array('name', 'required')
 *         )),
 *     );
 * }
 *
 *
 * References
 *
 * For example this is 'Book' class with array of ids of 'BookAuthor' in 'authors_id' field:
 *
 * public function relations() {
 *     return array(
 *         'authors' => array(YMongoModel::RELATION_MANY, 'BookAuthor', '_id', 'on' => 'authors_id'),
 *     );
 * }
 *
 * This is 'BookAuthor' class:
 *
 * public function relations() {
 *     return array(
 *          // Here no 'on' attribute because by default uses '_id' (primary key).
 *          // In additional you can specify 'where' attribute to get more exactly result.
 *         'books' => array(YMongoModel::RELATION_MANY, 'Book', 'authors_id', 'returnAs' => YMongoModel::RELATION_RETURN_CURSOR),
 *     );
 * }
 *
 * You can use this very simple:
 *
 * foreach($bookObject->authors as $author) {
 *     // $author can be instance of BookAuthor or array or YMongoCursor
 *     // you can specify this by 'returnAs' attribute
 * }
 *
 */
abstract class YMongoDocument extends YMongoModel
{
    /**
     * Default name of the Mongo primary key
     *
     * @var MongoId
     */
    public $_id;

    /**
     * class name => model
     *
     * @var array
     */
    private static $_models = array();

    /**
     * Whether this instance is new or not
     *
     * @var bool
     */
    private $_new = false;

    /**
     * Holds criteria information for scopes
     *
     * @var array
     */
    private $_criteria = array();

    /**
     * The base model creation
     *
     * @param string $scenario
     */
    public function __construct($scenario = self::SCENARIO_INSERT)
    {
        // Save document fields list in cache
        $this->getConnection()->setDocumentCache($this);

        if (null === $scenario) { // Maybe from populateRecord () and model ()
            return;
        }

        $this->setScenario($scenario);
        $this->setIsNewRecord(true);

        $this->init();

        $this->attachBehaviors($this->behaviors());
        $this->afterConstruct();
    }

    /**
     * @param bool $asString
     * @return MongoId|string
     */
    public function getId($asString = false)
    {
        return $asString ? $this->_id->__toString() : $this->_id;
    }

    /**
     * @param MongoId|string $id
     */
    public function setId($id)
    {
        $this->_id = ($id instanceof MongoId) ? $id : new MongoId($id);
    }

    /**
     * Returns if the current record is new.
     *
     * @return bool
     */
    public function getIsNewRecord()
    {
        return $this->_new;
    }

    /**
     * Sets if the record is new.
     *
     * @param bool $value
     */
    public function setIsNewRecord($value)
    {
        $this->_new = (bool) $value;
    }

    /**
     * Saves the current record.
     *
     * @param bool $runValidation
     * @param array $attributes
     * @return bool
     */
    public function save($runValidation = true, $attributes = null)
    {
        if (!$runValidation || $this->validate($attributes)) {
            return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
        }
        return false;
    }

    /**
     * Inserts a row into the table based on this active record attributes.
     *
     * @return bool
     * @throws YMongoException
     */
    public function insert()
    {
        if (!$this->getIsNewRecord()) {
            throw new YMongoException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
        }

        if ($this->beforeSave()) {
            Yii::trace(get_class($this).'.insert()', 'ext.mongoDb.YMongoDocument');

            // Create new MongoId if not set
            $this->{$this->primaryKey()} = $this->getPrimaryKey();

            // Insert new record
            try {
                if ($this->getConnection()->enableProfiling) {
                    Yii::beginProfile('MongoDocument insert into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                }

                $res = $this->getCollection()->insert(
                    $this->getDocument(),
                    $this->getConnection()->getDefaultWriteConcern()
                );

                if ($this->getConnection()->enableProfiling) {
                    Yii::endProfile('MongoDocument insert into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                }

                /**
                 * Returns an array containing the status of the insertion if the "w" option is set.
                 * Otherwise, returns TRUE if the inserted array is not empty
                 */
                if (true === $res || (is_array($res) && !empty($res['ok']))) {
                    $this->afterSave();
                    $this->setIsNewRecord(false);
                    $this->setScenario(self::SCENARIO_UPDATE);
                    return true;
                }
            } catch (Exception $e) {
                if ($this->getConnection()->enableProfiling) {
                    Yii::endProfile('MongoDocument insert into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                }
            }
        }
        return false;
    }

    /**
     * Updates the row represented by this active record.
     *
     * @param array $attributes
     * @return bool
     * @throws YMongoException
     */
    public function update($attributes = null)
    {
        if ($this->getIsNewRecord()) {
            throw new YMongoException(Yii::t('yii','The active record cannot be updated because it is new.'));
        }

        if ($this->beforeSave()) {
            Yii::trace(get_class($this).'.update()', 'ext.mongoDb.YMongoDocument');

            $pk = $this->primaryKey();

            if (null === $this->{$pk}) {
                throw new YMongoException(Yii::t('yii','The active record cannot be updated because it has no _id.'));
            }

            $result = false;

            // Save whole document
            if (null === $attributes) {
                try {
                    if ($this->getConnection()->enableProfiling) {
                        Yii::beginProfile('MongoDocument update into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                    }

                    $res = $this->getCollection()->save(
                        $this->getDocument(),
                        $this->getConnection()->getDefaultWriteConcern()
                    );

                    if ($this->getConnection()->enableProfiling) {
                        Yii::endProfile('MongoDocument update into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                    }

                    /**
                     * If w was set, returns an array containing the status of the save.
                     * Otherwise, returns a boolean representing if the array was not empty.
                     */
                    if (true === $res || (is_array($res) && !empty($res['ok']))) {
                        $result = true;
                    }
                } catch (Exception $e) {
                    if ($this->getConnection()->enableProfiling) {
                        Yii::endProfile('MongoDocument update into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                    }
                }
            }
            // Save only specify attributes
            else {
                // Prepare document
                $document = $this->getDocument($attributes);

                if (isset($document[$pk])) {
                    unset($document[$pk]);
                }

                try {
                    if ($this->getConnection()->enableProfiling) {
                        Yii::beginProfile('MongoDocument update into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                    }

                    $res = $this->getCollection()->update(
                        array($pk => $this->{$pk}), // criteria
                        array('$set' => $document), // new object
                        $this->getConnection()->getDefaultWriteConcern()
                    );

                    if ($this->getConnection()->enableProfiling) {
                        Yii::endProfile('MongoDocument update into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                    }

                    /**
                     * Returns an array containing the status of the update if the "w" option is set.
                     * Otherwise, returns TRUE.
                     */
                    if (true === $res || (is_array($res) && !empty($res['ok']))) {
                        $result = true;
                    }
                } catch (Exception $e) {
                    if ($this->getConnection()->enableProfiling) {
                        Yii::endProfile('MongoDocument update into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
                    }
                }
            }

            // Everything alright
            if (true === $result) {
                $this->afterSave();
                return true;
            }
        }
        return false;
    }

    /**
     * Update record by PK
     *
     * @param string|MongoId $pk
     * @param array $updateDoc
     * @param array|YMongoCriteria $criteria
     * @param array $options
     * @return bool
     */
    public function updateByPk($pk, $updateDoc = array(), $criteria = array(), $options = array())
    {
        Yii::trace(get_class($this).'.updateByPk()', 'ext.mongoDb.YMongoDocument');

        if ($criteria instanceof YMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        try {
            if ($this->getConnection()->enableProfiling) {
                Yii::beginProfile('MongoDocument update by primary key into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            $res = $this->getCollection()->update(
                $this->mergeCriteria(
                    $criteria,
                    array($this->primaryKey() => $this->getPrimaryKey($pk))
                ),
                $updateDoc,
                CMap::mergeArray(
                    $this->getConnection()->getDefaultWriteConcern(),
                    $options
                )
            );

            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument update by primary key into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            /**
             * Returns an array containing the status of the update if the "w" option is set.
             * Otherwise, returns TRUE.
             */
            if (true === $res || (is_array($res) && !empty($res['ok']))) {
                return true;
            }
        } catch (Exception $e) {
            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument update by primary key into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
        }

        return false;
    }

    /**
     * UpSert record by PK
     *
     * @param string|MongoId $pk
     * @param array $updateDoc
     * @param array $criteria
     * @param array $options
     * @return bool
     */
    public function upsertByPk($pk, $updateDoc = array(), $criteria = array(), $options = array())
    {
        Yii::trace(get_class($this).'.upsertByPk()', 'ext.mongoDb.YMongoDocument');
        return $this->updateByPk($pk, array('$set' => $updateDoc), $criteria, CMap::mergeArray($options, array('upsert' => true)));
    }

    /**
     * Update all records matching a criteria
     *
     * @param array|YMongoCriteria $criteria
     * @param array $updateDoc
     * @param array $options
     * @return bool
     */
    public function updateAll($criteria = array(), $updateDoc = array(), $options = array('multiple' => true))
    {
        Yii::trace(get_class($this).'.updateAll()', 'ext.mongoDb.YMongoDocument');

        if ($criteria instanceof YMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        try {
            if ($this->getConnection()->enableProfiling) {
                Yii::beginProfile('MongoDocument update all into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            $res = $this->getCollection()->update(
                $criteria,
                $updateDoc,
                CMap::mergeArray(
                    $this->getConnection()->getDefaultWriteConcern(),
                    $options
                )
            );

            /**
             * Returns an array containing the status of the update if the "w" option is set.
             * Otherwise, returns TRUE.
             */
            if (true === $res || (is_array($res) && !empty($res['ok']))) {
                return true;
            }
        } catch (Exception $e) {
            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument update all into "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
        }

        return false;
    }

    /**
     * Saves one or several counter columns for the current AR object.
     *
     * @param array $counters
     * @return bool
     * @throws YMongoException
     */
    public function saveCounters(array $counters)
    {
        if ($this->getIsNewRecord()) {
            throw new YMongoException(Yii::t('yii', 'The active record cannot be updated because it is new.'));
        }

        Yii::trace(get_class($this).'.saveCounters()', 'ext.mongoDb.YMongoDocument');

        if (sizeof($counters) > 0) {
            foreach($counters as $k => $v) {
                $this->{$k} = $this->{$k} + $v;
            }
            return $this->updateByPk($this->{$this->primaryKey()}, array('$inc' => $counters));
        }

        return false;
    }

    /**
     * Count() allows you to count all the documents returned by a certain condition, it is analogous
     * to $db->collection->find()->count() and basically does exactly that...
     *
     * @param array|YMongoCriteria $criteria
     * @return int
     */
    public function count($criteria = array())
    {
        Yii::trace(get_class($this).'.count()', 'ext.mongoDb.YMongoDocument');

        // If we provide a manual criteria via YMongoCriteria or an array we do not use the models own DbCriteria
        $criteria = !empty($criteria) && !($criteria instanceof YMongoCriteria) ? $criteria : $this->getDbCriteria();

        if ($criteria instanceof YMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        try {
            if ($this->getConnection()->enableProfiling) {
                Yii::beginProfile('MongoDocument count "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            $result = $this->getCollection()->find(!empty($criteria) ? $criteria : array())->count();

            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument count "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
            return $result;
        } catch (Exception $e) {
            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument count "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
        }

        return 0;
    }

    /**
     * Checks if a record exists in the database
     *
     * @param array|YMongoCriteria $criteria
     * @return bool
     */
    public function exists($criteria = array())
    {
        Yii::trace(get_class($this).'.exists()', 'ext.mongoDb.YMongoDocument');

        if ($criteria instanceof YMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        try {
            if ($this->getConnection()->enableProfiling) {
                Yii::beginProfile('MongoDocument exists "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            $result = null !== $this->getCollection()->findOne($criteria);

            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument exists "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
            return $result;
        } catch (Exception $e) {
            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument exists "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
        }

        return false;
    }

    /**
     * Find one record
     *
     * @param array|YMongoCriteria $criteria
     * @return YMongoDocument
     */
    public function findOne($criteria = array())
    {
        Yii::trace(get_class($this).'.findOne()', 'ext.mongoDb.YMongoDocument');

        if ($criteria instanceof YMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        $dbCriteria = $this->getDbCriteria();

        $this->beforeFind();
        try {
            if ($this->getConnection()->enableProfiling) {
                Yii::beginProfile('MongoDocument find one "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            $record = $this->getCollection()->findOne(
                $this->mergeCriteria(
                    isset($dbCriteria['condition']) ? $dbCriteria['condition'] : array(),
                    $criteria
                )
            );

            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument find one "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
        } catch (Exception $e) {
            $record = null;

            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument find one "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
        }

        // Reset scope
        if (array() !== $dbCriteria) {
            $this->resetScope();
        }

        if (null !== $record) {
            return $this->populateRecord($record);
        }
        return null;
    }

    /**
     * @param string|MongoId $pk
     * @return YMongoDocument
     */
    public function findByPk($pk)
    {
        Yii::trace(get_class($this).'.findByPk()', 'ext.mongoDb.YMongoDocument');
        return $this->findOne(array($this->primaryKey() => $this->getPrimaryKey($pk)));
    }

    /**
     * Find some records
     *
     * @param array|YMongoCriteria $criteria
     * @return YMongoCursor
     */
    public function find($criteria = array())
    {
        Yii::trace(get_class($this).'.find()', 'ext.mongoDb.YMongoDocument');

        if ($criteria instanceof YMongoCriteria) {
            $dbCriteria = $criteria->mergeWith($this->getDbCriteria())->toArray();
            $criteria = array();
        } else {
            $dbCriteria = $this->getDbCriteria();
        }

        if (array() !== $dbCriteria) {
            $cursor = new YMongoCursor($this,
                $this->mergeCriteria(
                    isset($dbCriteria['condition']) ? $dbCriteria['condition'] : array(),
                    $criteria
                )
            );

            // Set cursor conditions
            if (isset($dbCriteria['sort'])) {
                $cursor->sort($dbCriteria['sort']);
            }
            if (isset($dbCriteria['skip'])) {
                $cursor->skip($dbCriteria['skip']);
            }
            if (isset($dbCriteria['limit'])) {
                $cursor->limit($dbCriteria['limit']);
            }

            $this->resetScope();
            return $cursor;
        } else {
            return new YMongoCursor($this, $criteria);
        }
    }

    /**
     * Deletes this record
     *
     * @return bool
     * @throws YMongoException
     */
    public function delete()
    {
        if ($this->getIsNewRecord()) {
            throw new YMongoException(Yii::t('yii','The active record cannot be deleted because it is new.'));
        }

        Yii::trace(get_class($this).'.delete()', 'ext.mongoDb.YMongoDocument');

        if ($this->beforeDelete()) {
            $result = $this->deleteByPk($this->{$this->primaryKey()});
            $this->afterDelete();
            return $result;
        }
        return false;
    }

    /**
     * Delete record by pk
     *
     * @param string|MongoId $pk
     * @param array|YMongoCriteria $criteria
     * @param array $options
     * @return bool|array
     */
    public function deleteByPk($pk, $criteria = array(), $options = array())
    {
        Yii::trace(get_class($this).'.deleteByPk()', 'ext.mongoDb.YMongoDocument');

        if ($criteria instanceof YMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        try {
            if ($this->getConnection()->enableProfiling) {
                Yii::beginProfile('MongoDocument delete by primary key "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            $res = $this->getCollection()->remove(
                CMap::mergeArray(
                    array($this->primaryKey() => $this->getPrimaryKey($pk)),
                    $criteria
                ),
                CMap::mergeArray(
                    $this->getConnection()->getDefaultWriteConcern(),
                    array('justOne' => true),
                    $options
                )
            );

            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument delete by primary key "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            /**
             * Returns an array containing the status of the update if the "w" option is set.
             * Otherwise, returns TRUE.
             */
            if (true === $res || (is_array($res) && !empty($res['ok']))) {
                return true;
            }
        } catch (Exception $e) {
            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument delete by primary key "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
        }
        return false;
    }

    /**
     * Delete all records matching a criteria
     *
     * @param array|YMongoCriteria $criteria
     * @param array $options
     * @return bool|array
     */
    public function deleteAll($criteria = array(), $options = array())
    {
        Yii::trace(get_class($this).'.deleteAll()', 'ext.mongoDb.YMongoDocument');

        if ($criteria instanceof YMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        try {
            if ($this->getConnection()->enableProfiling) {
                Yii::beginProfile('MongoDocument delete all "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            $res = $this->getCollection()->remove(
                $criteria,
                CMap::mergeArray(
                    $this->getConnection()->getDefaultWriteConcern(),
                    $options
                )
            );

            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument delete all "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }

            /**
             * Returns an array containing the status of the update if the "w" option is set.
             * Otherwise, returns TRUE.
             */
            if (true === $res || (is_array($res) && !empty($res['ok']))) {
                return true;
            }
        } catch (Exception $e) {
            if ($this->getConnection()->enableProfiling) {
                Yii::endProfile('MongoDocument delete all "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoDocument');
            }
        }
        return false;
    }

    /**
     * @return YMongoDocument
     */
    protected function instantiate()
    {
        $class = get_class($this);
        return new $class(null);
    }

    /**
     * Create a new model based on attributes
     *
     * @param array $attributes
     * @param bool $callAfterFind
     * @return YMongoDocument
     */
    public function populateRecord($attributes, $callAfterFind = true)
    {
        if (false === $attributes) {
            return null;
        }

        $record = $this->instantiate();
        $record->setScenario(self::SCENARIO_UPDATE);
        $record->setIsNewRecord(false);
        $record->init();

        // Set the attributes
        foreach($attributes as $name => $value) {
            $record->$name = $value;
        }

        $record->attachBehaviors($record->behaviors());

        if ($callAfterFind) {
            $record->afterFind();
        }

        return $record;
    }

    /**
     * This, in addition to YMongoModels edition, will also call scopes on the model
     *
     * @param string $name
     * @param array $parameters
     * @return mixed
     */
    public function __call($name, $parameters)
    {
        // Related documents
        if (array_key_exists($name, $this->relations())) {
            if(empty($parameters)) {
                return $this->getRelated($name, false);
            } else {
                return $this->getRelated($name, false, $parameters[0]);
            }
        }

        $scopes = $this->scopes();
        if (isset($scopes[$name])) {
            $this->setDbCriteria($this->mergeCriteria($this->_criteria, $scopes[$name]));
            return $this;
        }
        return parent::__call($name, $parameters);
    }

    /**
     * The scope attached to this model
     *
     * @example
     *
     * array(
     *     '10_recently_published' => array(
     *         'condition' => array('published' => 1),
     *         'sort' => array('date_published' => -1),
     *         'skip' => 5,
     *         'limit' => 10,
     *     )
     * )
     *
     * @return array
     */
    public function scopes()
    {
        return array();
    }

    /**
     * Sets the default scope
     *
     * @example
     *
     * array(
     *     'condition' => array('published' => 1),
     *     'sort' => array('date_published' => -1),
     *     'skip' => 5,
     *     'limit' => 10,
     * )
     *
     * @return array
     */
    public function defaultScope()
    {
        return array();
    }

    /**
     * Resets the scopes applied to the model clearing the criteria variable
     *
     * @return $this
     */
    public function resetScope()
    {
        $this->_criteria = array();
        return $this;
    }

    /**
     * Sets the db criteria for this model
     *
     * @param array $criteria
     * @return mixed
     */
    public function setDbCriteria(array $criteria)
    {
        return $this->_criteria = $criteria;
    }

    /**
     * Gets and if null sets the db criteria for this model
     *
     * @param bool $createIfNull
     * @return array
     */
    public function getDbCriteria($createIfNull = true)
    {
        if (empty($this->_criteria)) {
            $defaultScope = $this->defaultScope();
            if (array() !== $defaultScope || $createIfNull) {
                $this->_criteria = $defaultScope;
            }
        }
        return $this->_criteria;
    }

    /**
     * Merges the current DB Criteria with the inputted one
     *
     * @param array $newCriteria
     * @return mixed
     */
    public function mergeDbCriteria(array $newCriteria)
    {
        return $this->_criteria = $this->mergeCriteria($this->getDbCriteria(), $newCriteria);
    }

    /**
     * Merges two criteria objects. Best used for scopes
     *
     * @param array $oldCriteria
     * @param array $newCriteria
     * @return array
     */
    public function mergeCriteria(array $oldCriteria, array $newCriteria)
    {
        return CMap::mergeArray($oldCriteria, $newCriteria);
    }

    /**
     * Returns the static model of the specified AR class.
     *
     * EVERY derived AR class must override this method as follows,
     * <pre>
     * public static function model($className=__CLASS__)
     * {
     *     return parent::model($className);
     * }
     * </pre>
     *
     * @param string $className
     * @return YMongoDocument
     */
    public static function model($className = __CLASS__)
    {
        if (!isset(self::$_models[$className])) {
            /** @var YMongoDocument $model */
            $model = new $className(null);
            $model->attachBehaviors($model->behaviors());

            self::$_models[$className] = $model;
        }

        return self::$_models[$className];
    }

    /**
     * Get the name of the collection
     *
     * @return string
     */
    public function collectionName()
    {
        return get_class($this);
    }

    /**
     * Returns MongoId based on $value
     *
     * @param mixed $value
     * @return MongoId
     */
    public function getPrimaryKey($value = null)
    {
        if (null === $value) {
            $value = $this->{$this->primaryKey()};
        }
        return ($value instanceof MongoId) ? $value : new MongoId($value);
    }

    /**
     * Gets the collection for this model
     *
     * @return MongoCollection
     */
    public function getCollection()
    {
        return $this->getConnection()->getCollection($this->collectionName());
    }
}