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
     * Errors moved here because of private
     *
     * @var array
     */
    private $_errors = array();

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
        if (isset($this->_attributes[$name])) {
            return $this->_attributes[$name];
        } elseif(isset($this->_subDocuments[$name])) {
            return $this->_subDocuments[$name];
        } elseif(array_key_exists($name, $this->subDocuments())) {
            return $this->_subDocuments[$name] = $this->getSubDocumentModel($name);
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
        // Sub documents
        if (isset($this->_subDocuments[$name]) || array_key_exists($name, $this->subDocuments())) {
            return $this->setSubDocument($name, $value);
        }
        // Default set
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
        } elseif(array_key_exists($name, $this->subDocuments())) {
            return true;
        }
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
        } elseif (isset($this->_subDocuments[$name])) {
            unset($this->_subDocuments[$name]);
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
     * Cleans or rather resets the document
     */
    public function clean()
    {
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