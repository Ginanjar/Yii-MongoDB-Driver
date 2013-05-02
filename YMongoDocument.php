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