<?php

/**
 * Class YMongoDocument
 */
abstract class YMongoDocument extends CModel
{
    // Behavior scenarios
    const SCENARIO_INSERT = 'insert';
    const SCENARIO_UPDATE = 'update';
    const SCENARIO_SEARCH = 'search';

    /**
     * By default, this is the 'mongoDb' application component.
     *
     * @var YMongoClient
     */
    public static $db;

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
     * Static cache for attribute names
     *
     * @var array
     */
    private static $_attributeNames = array();

    /**
     * @var array
     */
    private $_attributes = array();

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
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->_attributes[$name])) {
            return $this->_attributes[$name];
        } else {
            try {
                return parent::__get($name);
            } catch (CException $e) {
                return null;
            }
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function __set($name, $value)
    {
        try {
            return parent::__set($name,$value);
        } catch (CException $e) {
            return $this->_attributes[$name] = $value;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        if (isset($this->_attributes[$name])) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __unset($name)
    {
        if (isset($this->_attributes[$name])) {
            unset($this->_attributes[$name]);
        } else {
            parent::__unset($name);
        }
    }

    /**
     * Initializes this model.
     *
     * @return bool
     */
    public function init()
    {
        return true;
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
     * @param array $attributes
     * @return bool
     * @throws YMongoException
     */
    public function insert($attributes = null)
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
                $res = $this->getCollection()->insert(
                    $this->getDocument(),
                    $this->getConnection()->getDefaultWriteConcern()
                );

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
            } catch (Exception $e) { }
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
                    $res = $this->getCollection()->save(
                        $this->getDocument(),
                        $this->getConnection()->getDefaultWriteConcern()
                    );

                    /**
                     * If w was set, returns an array containing the status of the save.
                     * Otherwise, returns a boolean representing if the array was not empty.
                     */
                    if (true === $res || (is_array($res) && !empty($res['ok']))) {
                        $result = true;
                    }
                } catch (Exception $e) { }
            }
            // Save only specify attributes
            else {
                // Prepare document
                $document = $this->getDocument($attributes);

                if (isset($document[$pk])) {
                    unset($document[$pk]);
                }

                try {
                    $res = $this->getCollection()->update(
                        array($pk => $this->{$pk}), // criteria
                        array('$set' => $document), // new object
                        $this->getConnection()->getDefaultWriteConcern()
                    );

                    /**
                     * Returns an array containing the status of the update if the "w" option is set.
                     * Otherwise, returns TRUE.
                     */
                    if (true === $res || (is_array($res) && !empty($res['ok']))) {
                        $result = true;
                    }
                } catch (Exception $e) { }
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

            /**
             * Returns an array containing the status of the update if the "w" option is set.
             * Otherwise, returns TRUE.
             */
            if (true === $res || (is_array($res) && !empty($res['ok']))) {
                return true;
            }
        } catch (Exception $e) { }

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
        } catch (Exception $e) { }

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
            return $this->getCollection()->find(!empty($criteria) ? $criteria : array())->count();
        } catch (Exception $e) { }

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
            return null !== $this->getCollection()->findOne($criteria);
        } catch (Exception $e) { }

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
            $record = $this->getCollection()->findOne(
                $this->mergeCriteria(
                    isset($dbCriteria['condition']) ? $dbCriteria['condition'] : array(),
                    $criteria
                )
            );
        } catch (Exception $e) {
            $record = null;
        }

        if (null !== $record) {
            $this->resetScope();
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
            $this->afterFind();
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
     * Get the names of the attributes of the class
     *
     * @return array
     */
    public function attributeNames()
    {
        $className = get_class($this);

        if (!isset(self::$_attributeNames[$className])) {
            /**
             * Initialize an empty array with the names of the attributes.
             * Static cache is still necessary, even with the finding that no attributes.
             */
            self::$_attributeNames[$className] = array();

            // Class data
            $class = new ReflectionClass($className);
            $classProperties = $class->getProperties(ReflectionProperty::IS_PUBLIC);

            foreach($classProperties as $property) {
                if ($property->isStatic()) {
                    continue;
                }
                self::$_attributeNames[$className][] = $property->getName();
            }
        }

        return self::$_attributeNames[$className];
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
     * You can change the primary key but due to how MongoDB actually works this IS NOT RECOMMENDED
     *
     * @return string
     */
    public function primaryKey()
    {
        return '_id';
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
     * Returns the database connection used by active record.
     *
     * @return YMongoClient
     * @throws YMongoException
     */
    public function getConnection()
    {
        if (null !== self::$db) {
            return self::$db;
        }

        /** @var YMongoClient $db */
        $db = Yii::app()->getComponent('mongoDb');

        if ($db instanceof YMongoClient) {
            return self::$db = $db;
        } else {
            throw new YMongoException(Yii::t('yii','YMongoDocument a "mongoDb" YMongoClient application component.'));
        }
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

    /**
     * This event is raised before the record is saved.
     *
     * @param CEvent $event
     */
    public function onBeforeSave($event)
    {
        $this->raiseEvent('onBeforeSave', $event);
    }

    /**
     * This event is raised after the record is saved.
     *
     * @param CEvent $event
     */
    public function onAfterSave($event)
    {
        $this->raiseEvent('onAfterSave', $event);
    }

    /**
     * This event is raised before the record is deleted.
     *
     * @param CEvent $event
     */
    public function onBeforeDelete($event)
    {
        $this->raiseEvent('onBeforeDelete', $event);
    }

    /**
     * This event is raised after the record is deleted.
     *
     * @param CEvent $event
     */
    public function onAfterDelete($event)
    {
        $this->raiseEvent('onAfterDelete', $event);
    }

    /**
     * This event is raised before an AR finder performs a find call.
     *
     * @param CEvent $event
     */
    public function onBeforeFind($event)
    {
        $this->raiseEvent('onBeforeFind', $event);
    }

    /**
     * This event is raised after the record is instantiated by a find method.
     *
     * @param CEvent $event
     */
    public function onAfterFind($event)
    {
        $this->raiseEvent('onAfterFind', $event);
    }

    /**
     *  This method is invoked before saving a record (after validation, if any).
     *
     * @return bool
     */
    protected function beforeSave()
    {
        if ($this->hasEventHandler('onBeforeSave')) {
            $event = new CModelEvent($this);
            $this->onBeforeSave($event);
            return $event->isValid;
        }
        return true;
    }

    /**
     * This method is invoked after saving a record successfully.
     */
    protected function afterSave()
    {
        if ($this->hasEventHandler('onAfterSave')) {
            $this->onAfterSave(new CEvent($this));
        }
    }

    /**
     * This method is invoked before deleting a record.
     *
     * @return bool
     */
    protected function beforeDelete()
    {
        if ($this->hasEventHandler('onBeforeDelete')) {
            $event = new CModelEvent($this);
            $this->onBeforeDelete($event);
            return $event->isValid;
        }
        return true;
    }

    /**
     * This method is invoked after deleting a record.
     */
    protected function afterDelete()
    {
        if ($this->hasEventHandler('onAfterDelete')) {
            $this->onAfterDelete(new CEvent($this));
        }
    }

    /**
     * This method is invoked before an AR finder executes a find call.
     */
    protected function beforeFind()
    {
        if ($this->hasEventHandler('onBeforeFind')) {
            $this->onBeforeFind(new CModelEvent($this));
        }
    }

    /**
     * This method is invoked after each record is instantiated by a find method.
     */
    protected function afterFind()
    {
        if ($this->hasEventHandler('onAfterFind')) {
            $this->onAfterFind(new CEvent($this));
        }
    }

    /**
     * Filters a provided document to take out mongo objects.
     *
     * @param mixed $document
     * @return array
     */
    public function filterDocument($document)
    {
        if (is_array($document)) {
            /** @var $value array|YMongoDocument */
            foreach($document as $key => $value) {
                // Recursive
                if (is_array($value)) {
                    $document[$key] = $this->filterDocument($value);
                }
                // Nested documents does are not allowed at this time
                elseif($value instanceof YMongoDocument) {
                    unset($document[$key]);
                }
            }
        }
        return $document;
    }

    /**
     * Gets the raw document with mongo objects taken out
     *
     * @param array $attributes
     * @return array
     */
    public function getDocument($attributes = null)
    {
        if (!is_array($attributes) || empty($attributes)) {
            $attributes = CMap::mergeArray($this->attributeNames(), array_keys($this->_attributes));
        }
        $document = array();

        foreach($attributes as $field) {
            $document[$field] = $this->{$field};
        }

        return $this->filterDocument($document);
    }

    /**
     * Gets the JSON encoded document
     *
     * @return string
     */
    public function getJSONDocument()
    {
        return CJSON::encode($this->getDocument());
    }
}