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
     * class name => model
     *
     * @var array
     */
    private static $_models = array();

    /**
     * whether this instance is new or not
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
        if (isset(self::$_models[$className])) {
            return self::$_models[$className];
        }

        /** @var YMongoDocument $model */
        $model = self::$_models[$className] = new $className(null);
        $model->attachBehaviors($model->behaviors());
        return $model;
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
}