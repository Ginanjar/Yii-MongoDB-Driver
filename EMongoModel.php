<?php

class EMongoModel extends CModel
{
    /**
     * @var
     */
    public static $db;

    /**
     * Static cache for attribute names
     *
     * @var array
     */
    private static $names = array();

    /**
     * @var array
     */
    private $attributes = array();

    /**
     * @var array
     */
    private $errors = array();

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
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
            return $this->setAttribute($name, $value);
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        if (isset($this->attributes[$name])) {
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
        if (isset($this->attributes[$name])) {
            unset($this->attributes[$name]);
        } else {
            parent::__unset($name);
        }
    }

    /**
     * Get the names of the attributes of the class
     *
     * @return array
     */
    public function attributeNames()
    {
        $className = get_class($this);

        if (!isset(self::$names[$className])) {
            /**
             * Initialize an empty array with the names of the attributes.
             * Static cache is still necessary, even with the finding that no attributes.
             */
            self::$names[$className] = array();

            // Class data
            $class = new ReflectionClass($className);

            // Lets go over all the properties
            foreach ($class->getProperties() as $property) {
                // Only the public, but not static
                if ($property->isPublic() && !$property->isStatic()) {
                    self::$names[$className][] = $property->getName();
                }
            }
        }

        return self::$names[$className];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute($name)
    {
        $attributes = $this->attributes;
        return isset($attributes[$name]) || property_exists($this, $name) ? true : false;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function setAttribute($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        } else {
            $this->attributes[$name] = $value;
        }
        return true;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        } elseif (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }
        return null;
    }

    /**
     * @param array|bool $names
     * @return array
     */
    public function getAttributes($names = true)
    {
        $attributes = $this->attributes;
        $fields = $this->attributeNames();

        if (is_array($fields)) {
            foreach ($fields as $name) {
                $attributes[$name] = $this->$name;
            }
        }

        if (is_array($names)) {
            $result = array();
            foreach($names as $name) {
                if (property_exists($this, $name)) {
                    $result[$name] = $this->$name;
                } else {
                    $result[$name] = isset($attributes[$name]) ? $attributes[$name] : null;
                }
            }
            return $result;
        }

        return $attributes;
    }

    /**
     * @param array $values
     * @param bool $safeOnly
     */
    public function setAttributes($values, $safeOnly = true)
    {
        if (!is_array($values)) {
            return;
        }

        $attributes = array_flip($safeOnly ? $this->getSafeAttributeNames() : $this->attributeNames());

        foreach ($values as $name => $value) {
            if ($safeOnly) {
                if (isset($attributes[$name])) {
                    $this->$name= !is_array($value) && preg_match('/^[0-9]+$/', $value) > 0 ? (int) $value : $value;
                }
                elseif ($safeOnly) {
                    $this->onUnsafeAttribute($name, $value);
                }
            } else {
                $this->$name= !is_array($value) && preg_match('/^[0-9]+$/', $value) > 0 ? (int) $value : $value;
            }
        }
    }

    /**
     * @param array $names
     */
    public function unsetAttributes($names = null)
    {
        if (null == $names) {
            $names = $this->attributeNames();
        }
        foreach ($names as $name) {
            $this->$name = null;
        }
    }

    /**
     * @param string $attribute
     * @param array $errors
     */
    public function setAttributeErrors($attribute, $errors)
    {
        $this->errors[$attribute] = $errors;
    }

    /**
     * @param string $attribute
     * @return bool
     */
    public function hasErrors($attribute = null)
    {
        if (null === $attribute) {
            return array() !== $this->errors;
        } else {
            return isset($this->errors[$attribute]);
        }
    }

    /**
     * @param string $attribute
     * @return array
     */
    public function getErrors($attribute = null)
    {
        if (null === $attribute) {
            return $this->errors;
        } else {
            return isset($this->errors[$attribute]) ? $this->errors[$attribute] : array();
        }
    }

    /**
     * @param string $attribute
     * @return array
     */
    public function getError($attribute)
    {
        return isset($this->errors[$attribute]) ? reset($this->errors[$attribute]) : null;
    }

    /**
     * @param string $attribute
     * @param string $error
     */
    public function addError($attribute, $error)
    {
        $this->errors[$attribute][] = $error;
    }

    /**
     * @param array $errors
     */
    public function addErrors($errors)
    {
        foreach ($errors as $attribute => $error) {
            if (is_array($error)) {
                foreach ($error as $e) {
                    $this->addError($attribute, $e);
                }
            } else {
                $this->addError($attribute, $error);
            }
        }
    }

    /**
     * @param array $attribute
     */
    public function clearErrors($attribute = null)
    {
        if (null === $attribute) {
            $this->errors = array();
        } else {
            unset($this->errors[$attribute]);
        }
    }

    /**
     * @return bool
     */
    public function clean()
    {
        $this->attributes = array();

        foreach($this->attributeNames() as $name) {
            $this->$name = null;
        }
        return true;
    }

    /**
     * Returns the database connection used by active record.
     *
     * @return EMongoClient
     * @throws CException
     */
    public function getConnection()
    {
        if (null !== self::$db) {
            return self::$db;
        }

        /** @var $connection EMongoClient */
        $connection = Yii::app()->mongodb;

        if ($connection instanceof EMongoClient) {
            self::$db = $connection;
            return self::$db;
        }

        throw new CException(Yii::t('yii','MongoDB Active Record requires a "mongodb" EMongoClient application component.'));
    }

    /**
     * Gets the formed document with MongoYii objects included
     *
     * @return array
     */
    public function getDocument()
    {
        $attributes = $this->attributeNames();
        $doc = array();

        foreach($attributes as $field) {
            $doc[$field] = $this->{$field};
        }

        return array_merge($doc, $this->attributes);
    }

    /**
     * Filters a provided document to take out MongoYii objects.
     *
     * @param mixed $doc
     * @return array
     */
    public function filterRawDocument($doc)
    {
        if (is_array($doc)) {
            /** @var $v array|EMongoDocument */
            foreach($doc as $k => $v) {
                if(is_array($v)) {
                    $doc[$k] = $this->{__FUNCTION__}($v);
                } elseif($v instanceof EMongoModel || $v instanceof EMongoDocument) {
                    $doc[$k] = $v->getRawDocument();
                }
            }
        }
        return $doc;
    }

    /**
     * Gets the raw document with MongoYii objects taken out
     *
     * @return mixed
     */
    public function getRawDocument()
    {
        return $this->filterRawDocument($this->getDocument());
    }

    /**
     * Gets the JSON encoded document
     *
     * @return string
     */
    public function getJSONDocument()
    {
        return CJSON::encode($this->getRawDocument());
    }

    /**
     * Gets the BSON encoded document (never normally needed)
     *
     * @return string
     */
    public function getBSONDocument()
    {
        if (!function_exists('bson_encode')) {
            function bson_encode($value) {
                return json_encode($value);
            }
        }
        return bson_encode($this->getRawDocument());
    }
}