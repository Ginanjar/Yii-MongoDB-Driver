<?php

class YMongoModel extends CModel
{
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
        return array_merge($this->getConnection()->getDocumentFields(get_class($this)), array_keys($this->_attributes));
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
                elseif($value instanceof YMongoModel) {
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