<?php

class EMongoDocument extends EMongoModel
{
    // Behavior scenarios
    const SCENARIO_INSERT = 'insert';
    const SCENARIO_UPDATE = 'update';

    /**
     * This is a new record?
     *
     * @var bool
     */
    private $new = false;

    /**
     * Holds criteria information for scopes
     *
     * @var array
     */
    private $criteria = array();

    /**
     * Static cache for models
     *
     * @var array
     */
    private static $models = array();

    /**
     * The base model creation
     *
     * @param string $scenario
     */
    public function __construct($scenario = self::SCENARIO_INSERT)
    {
        // Maybe from populateRecord () and model ()
        if (null == $scenario) {
            return;
        }

        $this->setScenario($scenario);
        $this->setIsNewRecord(true);

        $this->init();

        $this->attachBehaviors($this->behaviors());
        $this->afterConstruct();
    }

    /**
     * This, in addition to EMongoModels edition, will also call scopes on the model
     *
     * @param string $name
     * @param array $parameters
     * @return EMongoDocument|mixed
     */
    public function __call($name, $parameters)
    {
        $scopes = $this->scopes();
        if (isset($scopes[$name])) {
            $this->setDbCriteria($this->mergeCriteria($this->criteria, $scopes[$name]));
            return $this;
        }
        return parent::__call($name, $parameters);
    }

    /**
     * Additional model initialization
     *
     * @return bool
     */
    public function init()
    {
        return true;
    }

    /**
     * Get the name of the collection
     *
     * @return string
     */
    public function collectionName() {}

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
        $this->criteria = array();
        return $this;
    }

    /**
     * Is this a new record?
     *
     * @return bool
     */
    public function getIsNewRecord()
    {
        return $this->new;
    }

    /**
     * Set new record flag
     *
     * @param bool $value
     */
    public function setIsNewRecord($value)
    {
        $this->new = (bool) $value;
    }

    /**
     * You can change the primarykey but due to how MongoDB actually works this IS NOT RECOMMENDED
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
     * @param MongoId|string $value
     * @return MongoId
     */
    public function getPrimaryKey($value = null)
    {
        if (null == $value) {
            $value = $this->{$this->primaryKey()};
        }
        return ($value instanceof MongoId) ? $value : new MongoId($value);
    }

    /**
     * Get the name of the attribute
     *
     * @param string $attribute
     * @return string
     */
    public function getAttributeLabel($attribute)
    {
        $labels = $this->attributeLabels();

        if (isset($labels[$attribute])) {
            return $labels[$attribute];
        }

        return $this->generateAttributeLabel($attribute);
    }

    /**
     * @return EMongoDocument
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
     * @return EMongoDocument
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
     * Saves this record
     *
     * If an attributes specification is sent in it will only validate and save those attributes
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
     * Saves only a specific subset of attributes as defined by the param
     *
     * @param array $attributes
     * @return bool
     * @throws CException
     */
    public function saveAttributes($attributes)
    {
        if(!$this->getIsNewRecord()) {
            $this->trace(__FUNCTION__);

            // There will be values that need to be updated
            $values = array();

            // Go on
            foreach($attributes as $name => $value) {
                // An array with atomic keys
                if (is_int($name)) {
                    $v = $this->$value;
                    if (is_array($this->$value)){
                        $v = $this->filterRawDocument($this->$value);
                    }
                    $values[$value]=$v;
                } else {
                    $values[$name] = $this->{$name} = $value;
                }
            }

            $pk = $this->primaryKey();

            if (!isset($this->{$pk}) || $this->{$pk} === null) {
                throw new CException(Yii::t('yii','The active record cannot be updated because its _id is not set!'));
            }

            return $this->updateByPk($this->{$pk}, $values);
        }
        throw new CException(Yii::t('yii','The active record cannot be updated because it is new.'));
    }

    /**
     * Inserts this record
     *
     * @return bool
     * @throws CException
     */
    public function insert()
    {
        if (!$this->getIsNewRecord()) {
            throw new CException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
        }

        if ($this->beforeSave()) {
            $this->trace(__FUNCTION__);

            $pk = $this->primaryKey();

            // Create new MongoId if not set
            if (!isset($this->{$pk})) {
                $this->{$pk} = new MongoId();
            }

            // Insert new record
            if ($this->getCollection()->insert($this->getRawDocument())) {
                $this->afterSave();
                $this->setIsNewRecord(false);
                $this->setScenario(self::SCENARIO_UPDATE);
                return true;
            }
        }
        return false;
    }

    /**
     * Updates this record
     *
     * @param array $attributes
     * @return bool
     * @throws CException
     */
    public function update($attributes = null)
    {
        if ($this->getIsNewRecord()) {
            throw new CException(Yii::t('yii','The active record cannot be updated because it is new.'));
        }

        if ($this->beforeSave()) {
            $this->trace(__FUNCTION__);

            $pk = $this->primaryKey();

            if(null === $this->{$pk}) {
                throw new CException(Yii::t('yii','The active record cannot be updated because it has no _id.'));
            }

            if (null !== $attributes) {
                $attributes = $this->getAttributes($attributes);
                unset($attributes[$pk]);
                $this->updateByPk($this->{$pk}, array('$set' => $attributes));
            } else {
                $this->getCollection()->save($this->getAttributes($attributes));
            }

            $this->afterSave();
            return true;
        }
        return false;
    }

    /**
     * Update record by PK
     *
     * @param string|MongoId $pk
     * @param array $updateDoc
     * @param array|EMongoCriteria $criteria
     * @param array $options
     * @return bool
     */
    public function updateByPk($pk, $updateDoc = array(), $criteria = array(), $options = array())
    {
        $this->trace(__FUNCTION__);

        if ($criteria instanceof EMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        return $this->getCollection()->update(
            $this->mergeCriteria(
                $criteria,
                array($this->primaryKey() => $this->getPrimaryKey($pk))
            ),
            $updateDoc,
            $options
        );
    }

    /**
     * Update all records matching a criteria
     *
     * @param array|EMongoCriteria $criteria
     * @param array $updateDoc
     * @param array $options
     * @return bool
     */
    public function updateAll($criteria = array(), $updateDoc = array(), $options = array('multiple' => true))
    {
        $this->trace(__FUNCTION__);

        if ($criteria instanceof EMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        return $this->getCollection()->update($criteria, $updateDoc, $options);
    }

    /**
     * Saves one or several counter columns for the current AR object.
     *
     * @param array $counters
     * @return bool
     * @throws CException
     */
    public function saveCounters(array $counters)
    {
        $this->trace(__FUNCTION__);

        if ($this->getIsNewRecord()) {
            throw new CException(Yii::t('yii', 'The active record cannot be updated because it is new.'));
        }

        if (sizeof($counters)>0) {
            foreach($counters as $k => $v) {
                $this->{$k} = $this->{$k} + $v;
            }
            return $this->updateByPk($this->{$this->primaryKey()}, array('$inc' => $counters));
        }

        return true;
    }

    /**
     * Count() allows you to count all the documents returned by a certain condition, it is analogous
     * to $db->collection->find()->count() and basically does exactly that...
     *
     * @param array|EMongoCriteria $criteria
     * @return int
     */
    public function count($criteria = array())
    {
        $this->trace(__FUNCTION__);

        // If we provide a manual criteria via EMongoCriteria or an array we do not use the models own DbCriteria
        $criteria = !empty($criteria) && !($criteria instanceof EMongoCriteria) ? $criteria : $this->getDbCriteria();

        if ($criteria instanceof EMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        return $this->getCollection()->find(isset($criteria) ? $criteria : array())->count();
    }

    /**
     * Deletes this record
     *
     * @return bool
     * @throws CException
     */
    public function delete()
    {
        if ($this->getIsNewRecord()) {
            throw new CException(Yii::t('yii','The active record cannot be deleted because it is new.'));
        }

        $this->trace(__FUNCTION__);

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
     * @param array|EMongoCriteria $criteria
     * @param array $options
     * @return bool|array
     */
    public function deleteByPk($pk, $criteria = array(), $options = array())
    {
        $this->trace(__FUNCTION__);

        if ($criteria instanceof EMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        return $this->getCollection()->remove(
            array_merge(
                array($this->primaryKey() => $this->getPrimaryKey($pk)),
                $criteria
            ),
            $options
        );
    }

    /**
     * Delete all records matching a criteria
     *
     * @param array|EMongoCriteria $criteria
     * @param array $options
     * @return bool|array
     */
    public function deleteAll($criteria = array(), $options = array())
    {
        $this->trace(__FUNCTION__);

        if ($criteria instanceof EMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        return $this->getCollection()->remove($criteria, $options);
    }

    /**
     * Checks if a record exists in the database
     *
     * @param array|EMongoCriteria $criteria
     * @return bool
     */
    public function exists($criteria = array())
    {
        $this->trace(__FUNCTION__);

        if ($criteria instanceof EMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        return null !== $this->getCollection()->findOne($criteria);
    }

    /**
     * Compares current active record with another one.
     *
     * @param EMongoDocument $record
     * @return bool
     */
    public function equals($record)
    {
        return $this->collectionName() === $record->collectionName() && (string) $this->getPrimaryKey() === (string) $record->getPrimaryKey();
    }

    /**
     * Find one record
     *
     * @param array|EMongoCriteria $criteria
     * @return EMongoDocument
     */
    public function findOne($criteria = array())
    {
        $this->trace(__FUNCTION__);

        if ($criteria instanceof EMongoCriteria) {
            $criteria = $criteria->getCondition();
        }

        $dbCriteria = $this->getDbCriteria();

        $record = $this->getCollection()->findOne(
            $this->mergeCriteria(
                isset($dbCriteria['condition']) ? $dbCriteria['condition'] : array(),
                $criteria
            )
        );

        if (null !== $record) {
            $this->resetScope();
            return $this->populateRecord($record);
        }

        return null;
    }

    /**
     * Find some records
     *
     * @param array|EMongoCriteria $criteria
     * @return EMongoCursor
     */
    public function find($criteria = array())
    {
        $this->trace(__FUNCTION__);

        if ($criteria instanceof EMongoCriteria) {
            $dbCriteria = $criteria->mergeWith($this->getDbCriteria())->toArray();
            $criteria = array();
        } else {
            $dbCriteria = $this->getDbCriteria();
        }

        if (array() !== $dbCriteria) {
            $cursor = new EMongoCursor($this,
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

            $this->resetScope();var_dump($cursor);
            return $cursor;
        } else {
            return new EMongoCursor($this, $criteria);
        }
    }

    /**
     * Finds one
     *
     * @param string|MongoId $pk
     * @return EMongoDocument
     */
    public function findByPk($pk)
    {
        $this->trace(__FUNCTION__);
        return $this->findOne(array($this->primaryKey() => $this->getPrimaryKey($pk)));
    }

    /**
     * This is an aggregate helper on the model
     * Note: This does not return the model but instead the result array directly from MongoDB.
     *
     * @param array $pipeline
     * @return array
     */
    public function aggregate(array $pipeline)
    {
        $this->trace(__FUNCTION__);
        $result = $this->getCollection()->aggregate($pipeline);
        return $result['ok'] ? $result['result'] : null;
    }

    /**
     * Gets the collection for this model
     *
     * @return MongoCollection
     */
    public function getCollection()
    {
        return $this->getConnection()->selectCollection($this->collectionName());
    }

    /**
     * Sets the db criteria for this model
     *
     * @param array $criteria
     * @return mixed
     */
    public function setDbCriteria($criteria)
    {
        return $this->criteria = $criteria;
    }

    /**
     * Gets and if null sets the db criteria for this model
     *
     * @param bool $createIfNull
     * @return array
     */
    public function getDbCriteria($createIfNull = true)
    {
        if (empty($this->criteria)) {
            $defaultScope = $this->defaultScope();
            if (array() !== $defaultScope || $createIfNull) {
                $this->criteria = $defaultScope;
            }
        }
        return $this->criteria;
    }

    /**
     * Merges the current DB Criteria with the inputted one
     *
     * @param array $newCriteria
     * @return mixed
     */
    public function mergeDbCriteria($newCriteria)
    {
        return $this->criteria = $this->mergeCriteria($this->getDbCriteria(), $newCriteria);
    }

    /**
     * Merges two criteria objects. Best used for scopes
     *
     * @param array $oldCriteria
     * @param array $newCriteria
     * @return array
     */
    public function mergeCriteria($oldCriteria, $newCriteria)
    {
        return CMap::mergeArray($oldCriteria, $newCriteria);
    }

    /**
     * Get model
     *
     * @param string $className
     * @return EMongoDocument
     */
    public static function model($className = __CLASS__)
    {
        if (!isset(self::$models[$className])) {
            /** @var $model EMongoDocument */
            $model = self::$models[$className] = new $className(null);
            $model->attachBehaviors($model->behaviors());
            return $model;
        }

        return self::$models[$className];
    }

    /**
     * @param CEvent $event
     */
    public function onBeforeSave($event)
    {
        $this->raiseEvent('onBeforeSave', $event);
    }

    /**
     * @param CEvent $event
     */
    public function onAfterSave($event)
    {
        $this->raiseEvent('onAfterSave', $event);
    }

    /**
     * @param CEvent $event
     */
    public function onBeforeDelete($event)
    {
        $this->raiseEvent('onBeforeDelete', $event);
    }

    /**
     * @param CEvent $event
     */
    public function onAfterDelete($event)
    {
        $this->raiseEvent('onAfterDelete', $event);
    }

    /**
     * @param CEvent $event
     */
    public function onBeforeFind($event)
    {
        $this->raiseEvent('onBeforeFind', $event);
    }

    /**
     * @param CEvent $event
     */
    public function onAfterFind($event)
    {
        $this->raiseEvent('onAfterFind', $event);
    }

    /**
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
     *
     */
    protected function afterSave()
    {
        if ($this->hasEventHandler('onAfterSave')) {
            $this->onAfterSave(new CEvent($this));
        }
    }

    /**
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
     *
     */
    protected function afterDelete()
    {
        if ($this->hasEventHandler('onAfterDelete')) {
            $this->onAfterDelete(new CEvent($this));
        }
    }

    /**
     *
     */
    protected function beforeFind()
    {
        if ($this->hasEventHandler('onBeforeFind')) {
            $this->onBeforeFind(new CModelEvent($this));
        }
    }

    /**
     *
     */
    protected function afterFind()
    {
        if ($this->hasEventHandler('onAfterFind')) {
            $this->onAfterFind(new CEvent($this));
        }
    }

    /**
     * Produces a trace message for functions in this class
     *
     * @param string $function
     */
    public function trace($function)
    {
        Yii::trace(get_class($this).'.'.$function.'()','extensions.mongoDB.EMongoDocument');
    }
}