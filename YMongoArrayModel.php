<?php

class YMongoArrayModel implements Iterator, Countable, ArrayAccess
{
    /**
     * @var string
     */
    public $modelClass;

    /**
     * Sub documents
     *
     * @var array
     */
    private $_values = array();

    /**
     * @var int
     */
    private $_currentIndex = 0;

    /**
     * @param string|object $modelClass
     * @param array $values
     */
    public function __construct($modelClass, array $values = array())
    {
        $this->modelClass = !is_object($modelClass) ? $modelClass : get_class($modelClass);
        $this->populate($values);
    }

    /**
     * @param array $values
     */
    public function populate(array $values = array())
    {
        $this->_values = $values;
        $this->_currentIndex = 0;
    }

    /**
     * @return array
     */
    public function getDocuments()
    {
        return $this->_values;
    }

    /**
     * @return int
     */
    public function count()
    {
        return sizeof($this->_values);
    }

    /**
     * @return YMongoModel
     */
    public function current()
    {
        return $this->itemAt($this->_currentIndex);
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->_currentIndex;
    }

    /**
     *
     */
    public function next()
    {
        $this->_currentIndex++;
    }

    /**
     *
     */
    public function rewind()
    {
        $this->_currentIndex = 0;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return isset($this->_values[$this->_currentIndex]);
    }

    /**
     * @param int $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->_values[$offset]);
    }

    /**
     * @param int $offset
     * @return YMongoModel
     */
    public function offsetGet($offset)
    {
        return $this->itemAt($offset);
    }

    /**
     * @param int $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (null === $offset || $offset === $this->count()) {
            $this->_values[] = $value;
        } else {
            $this->_values[$offset] = $value;
        }
    }

    /**
     * @param int $offset
     */
    public function offsetUnset($offset)
    {
        if (isset($this->_values[$offset])) {
            unset($this->_values[$offset]);
        }
    }

    /**
     * @param int $index
     * @return YMongoModel
     */
    public function itemAt($index)
    {
        if (!isset($this->_values[$index])) {
            return null;
        }

        if (!($this->_values[$index] instanceof YMongoModel)) {
            /** @var YMongoModel $model */
            $model = new $this->modelClass;
            $model->setAttributes($this->_values[$index], false);
            $this->_values[$index] = $model;
        }

        return $this->_values[$index];
    }
}