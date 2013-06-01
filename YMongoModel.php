<?php

/**
 * WARNING! Do not inherit from this class documents stored in the database.
 * This class can be used to sub documents.
 */
class YMongoModel extends CModel
{
    // Behavior scenarios
    const SCENARIO_INSERT = 'insert';
    const SCENARIO_UPDATE = 'update';
    const SCENARIO_SEARCH = 'search';

    // Sub document types
    const SUB_DOCUMENT_SINGLE = 'single';
    const SUB_DOCUMENT_MULTI = 'multi';

    // Relation types
    const RELATION_ONE = 'one';
    const RELATION_MANY = 'many';

    // In what format give the result
    const RELATION_RETURN_ARRAY = 'array';
    const RELATION_RETURN_MODEL = 'model';
    const RELATION_RETURN_CURSOR = 'cursor';

    /**
     * By default, this is the 'mongoDb' application component.
     *
     * @var YMongoClient
     */
    public static $db;

    /**
     * @var array
     */
    private $_attributes = array();

    /**
     * Sub documents models
     *
     * @var array
     */
    private $_subDocuments = array();

    /**
     * Related documents
     *
     * @var array
     */
    private $_related = array();

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
        // Some variables
        if (isset($this->_attributes[$name])) {
            return $this->_attributes[$name];
        }
        // Sub documents
        elseif(isset($this->_subDocuments[$name])) {
            return $this->_subDocuments[$name];
        } elseif(array_key_exists($name, $this->subDocuments())) {
            return $this->_subDocuments[$name] = $this->getSubDocumentModel($name);
        }
        // Related documents
        elseif(isset($this->_related[$name])) {
            return $this->_related[$name];
        }
        elseif(array_key_exists($name, $this->relations())) {
            return $this->_related[$name] = $this->getRelated($name);
        }
        // Basic variables access
        else {
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
        // Related documents
        if(isset($this->_related[$name]) || array_key_exists($name, $this->relations())) {
            $this->_related[$name] = $value;
        }
        // Sub documents
        if (isset($this->_subDocuments[$name]) || array_key_exists($name, $this->subDocuments())) {
            return $this->setSubDocument($name, $value);
        }
        // Basic set
        else {
            try {
                return parent::__set($name,$value);
            } catch (CException $e) {
                return $this->_attributes[$name] = $value;
            }
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
        }
        // Sub documents
        elseif (isset($this->_subDocuments[$name])) {
            return true;
        }
        elseif(array_key_exists($name, $this->subDocuments())) {
            return true;
        }
        // Related documents
        elseif(isset($this->_related[$name])) {
            return true;
        }
        elseif (array_key_exists($name, $this->relations())) {
            return null !== $this->getRelated($name);
        }
        // Basic isset
        else {
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
        }
        // Sub documents
        elseif (isset($this->_subDocuments[$name])) {
            unset($this->_subDocuments[$name]);
        }
        // Related documents
        elseif (isset($this->_related[$name])) {
            unset($this->_related[$name]);
        }
        // Basic unset
        else {
            parent::__unset($name);
        }
    }

    /**
     * @param string $name
     * @param array $parameters
     * @return mixed
     */
    public function __call($name, $parameters)
    {
        if(array_key_exists($name, $this->relations())) {
            if (empty($parameters)) {
                return $this->getRelated($name, false);
            } else {
                return $this->getRelated($name, false, $parameters[0]);
            }
        }
        return parent::__call($name, $parameters);
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
     * @param string $attribute
     * @return bool
     */
    public function hasAttribute($attribute)
    {
        return in_array($attribute, $this->attributeNames());
    }

    /**
     * Get the names of the attributes of the class
     *
     * @return array
     */
    public function attributeNames()
    {
        return array_merge($this->getConnection()->getDocumentFields(get_class($this)), array_keys($this->_attributes), array_keys($this->subDocuments()));
    }

    /**
     * Holds all subDocuments
     *
     * @return array
     */
    public function subDocuments()
    {
        return array();
    }

    /**
     * @param string $name
     * @param YMongoArrayModel|YMongoModel|array|null $value
     * @return YMongoArrayModel|YMongoModel
     * @throws YMongoException
     */
    public function setSubDocument($name, $value)
    {
        if (
            !($value instanceof YMongoArrayModel) &&
            !($value instanceof YMongoModel) &&
            !is_array($value) &&
            !is_null($value)
        ) {
            throw new YMongoException(Yii::t('yii','Unexpected type {type} of subDocument value (null, array, YMongoModel or YMongoArrayModel expected)', array('{type}' => gettype($value))));
        }

        // Current model, lets try to make some changes
        $model = !isset($this->_subDocuments[$name]) ?  $this->getSubDocumentModel($name) : $this->_subDocuments[$name];

        if ($value instanceof YMongoArrayModel || $value instanceof YMongoModel) {
            $model = $value;
        } else {
            // Work with YMongoArrayModel
            if ($model instanceof YMongoArrayModel) {
                // Null, remove
                if (is_null($value)) {
                    $model->populate();
                }
                // Array
                elseif (is_array($value)) {
                    $model->populate($value);
                }
            }

            // Work with YMongoModel
            elseif ($model instanceof YMongoModel) {
                // Null, remove
                if (is_null($value)) {
                    $model->setAttributes(
                        array_fill_keys(array_keys($model->getAttributes()), null),
                        false
                    );
                }
                // Array
                elseif (is_array($value)) {
                    $model->setAttributes($value, false);
                }
            }
        }

        // Set this model back to the stack
        return $this->_subDocuments[$name] = $model;
    }

    /**
     * @param string $name
     * @param array $value
     * @return YMongoModel|YMongoArrayModel
     * @throws YMongoException
     */
    public function getSubDocumentModel($name, $value = array())
    {
        $subDocuments = $this->subDocuments();
        if (empty($subDocuments[$name][0])) {
            throw new YMongoException(Yii::t('yii','{class} does not have subDocument "{name}".', array('{class}' => get_class($this), '{name}' => $name)));
        }

        $type = self::SUB_DOCUMENT_SINGLE;
        if (isset($subDocuments[$name]['type']) && in_array($subDocuments[$name]['type'], array(self::SUB_DOCUMENT_SINGLE, self::SUB_DOCUMENT_MULTI))) {
            $type = $subDocuments[$name]['type'];
        }

        $className = $subDocuments[$name][0];

        switch ($type) {
            // Array of documents
            case self::SUB_DOCUMENT_MULTI:
                $model = new YMongoArrayModel($className, $value);
                break;

            // Single document
            default:
                /** @var YMongoModel $model */
                $model = new $className(null);
                $model->setAttributes($value, false);
                break;
        }
        return $this->_subDocuments[$name] = $model;
    }

    /**
     * Holds all our relations
     *
     * @return array
     */
    public function relations()
    {
        return array();
    }

    /**
     * Returns the related records
     *
     * @param string $name
     * @param bool $refresh
     * @param array $where
     * @return YMongoModel|YMongoArrayModel|array|null
     * @throws YMongoException
     */
    public function getRelated($name, $refresh = false, array $where = array())
    {
        if (!$refresh && empty($where) && (isset($this->_related[$name]) || array_key_exists($name, $this->_related))) {
            return $this->_related[$name];
        }

        $relations = $this->relations();

        if (!isset($relations[$name]) || !is_array($relations[$name]) || sizeof($relations[$name]) < 2) {
            throw new YMongoException(Yii::t('yii','{class} does not have relation "{name}".',
                array('{class}' => get_class($this), '{name}' => $name)));
        }

        Yii::trace('Lazy loading ' . get_class($this) . '.' . $name, 'ext.mongoDb.YMongoModel');

        // Shortcuts to relation properties
        $relation = $relations[$name];

        $type = $relation[0];
        /** @var YMongoDocument|string $className */
        $className = $relation[1];
        $foreignKey = isset($relation[2]) ? $relation[2] : $this->primaryKey();
        $primaryKey = isset($relation['on']) ? $this->{$relation['on']} : $this->{$this->primaryKey()};

        // In what format give the result
        $returnAs = self::RELATION_RETURN_MODEL;
        if (isset($relation['returnAs'])) {
            if (in_array($relation['returnAs'], array(self::RELATION_RETURN_CURSOR, self::RELATION_RETURN_ARRAY, self::RELATION_RETURN_MODEL))) {
                $returnAs = $relation['returnAs'];
            }
        }

        // Merge where clause
        if (isset($relation['where']) && is_array($relation['where'])) {
            $where = CMap::mergeArray($where, $relation['where']);
        }

        // Final prepare
        if (is_array($primaryKey)) {
            // Try to detect if primary key is MongoDBRef
            if (MongoDBRef::isRef($primaryKey)) {
                return $this->populateReference($primaryKey, $className);
            }
            // Array of MongoDBRef
            elseif (MongoDBRef::isRef(reset($primaryKey))) {
                $result = array();
                foreach ($primaryKey as $singleKey) {
                    if (MongoDBRef::isRef($singleKey)) {
                        $item = $this->populateReference($singleKey, $className);
                        if ($item) {
                            $result[] = $item;
                        }
                    }
                }
                return $result;
            }
            $clause = CMap::mergeArray($where, array($foreignKey => array('$in' => $primaryKey)));
        } else {
            $clause = CMap::mergeArray($where, array($foreignKey => $primaryKey));
        }


        // Default empty result
        $cursor = array();

        /** @var YMongoDocument $model */
        $model = $className::model();

        if (self::RELATION_ONE === $type) {
            /** @var YMongoDocument $cursor */
            $cursor = $model->findOne($clause);

            if ($cursor && self::RELATION_RETURN_ARRAY === $returnAs) {
                $cursor = $cursor->getDocument();
            }
        }
        elseif (self::RELATION_MANY === $type) {
            $cursor = $model->find($clause);

            // As array of array
            if (self::RELATION_RETURN_ARRAY === $returnAs) {
                $cursor = iterator_to_array($cursor);
            }
            // As models
            elseif (self::RELATION_RETURN_MODEL === $returnAs) {
                $result = array();
                foreach($cursor as $item) {
                    $result[] = $model->populateRecord($item);
                }
                $cursor = $result;
                unset($result);
            }
        }

        return $cursor;
    }

    /**
     * @param array $reference
     * @param YMongoDocument $className
     * @return YMongoDocument
     */
    public function populateReference(array $reference, $className = null)
    {
        $record = MongoDBRef::get($this->getConnection()->getDatabase(), $reference);
        if (null === $className) {
            $className = get_class($this);
        }
        return $className::model()->populateRecord($record);
    }

    /**
     * eturns a value indicating whether the named related object(s) has been loaded.
     *
     * @param $name
     * @return bool
     */
    public function hasRelated($name)
    {
        return isset($this->_related[$name]) || array_key_exists($name, $this->_related);
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
     * Cleans or rather resets the document
     */
    public function clean()
    {
        $this->_related = array();

        $attributes = $this->attributeNames();
        foreach ($attributes as $name) {
            $this->{$name} = null;
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
            /** @var $value array|YMongoDocument|YMongoModel|YMongoArrayModel */
            foreach($document as $key => $value) {
                // Recursive
                if (is_array($value)) {
                    $document[$key] = $this->filterDocument($value);
                }
                // Nested multi documents
                elseif ($value instanceof YMongoArrayModel) {
                    $document[$key] = $this->filterDocument($value->getDocuments());
                }
                // Nested single document
                elseif ($value instanceof YMongoModel) {
                    $document[$key] = $value->getDocument();
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
            $attributes = $this->attributeNames();
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
}