<?php

/**
 * A data Provider helper for interacting with the YMongoCursor
 */
class YMongoDataProvider extends CActiveDataProvider
{
    /**
     * @var string
     */
    public $modelClass;

    /**
     * @var YMongoDocument
     */
    public $model;

    /**
     * @var string
     */
    public $keyAttribute = '_id';

    /**
     * @var array|YMongoCriteria
     */
    private $_criteria;

    /**
     * @var string The internal MongoDB cursor as a MongoCursor instance
     */
    private $_cursor;

    /**
     * @var CSort
     */
    private $_sort;

    /**
     * Creates the YMongoDataProvider instance
     *
     * @param string|YMongoDocument $modelClass
     * @param array $config
     */
    public function __construct($modelClass, $config = array())
    {
        if (is_string($modelClass)) {
            $this->modelClass = $modelClass;
            $this->model = YMongoDocument::model($this->modelClass);
        }
        elseif ($modelClass instanceof YMongoDocument) {
            $this->modelClass = get_class($modelClass);
            $this->model = $modelClass;
        }
        $this->setId($this->modelClass);
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * @return array
     */
    public function getCriteria()
    {
        return ($this->_criteria instanceof YMongoCriteria) ? $this->_criteria->toArray() : $this->_criteria;
    }

    /**
     * @param array|YMongoCriteria $value
     */
    public function setCriteria($value)
    {
        if ($value instanceof YMongoCriteria) {
            $value = $value->toArray();
        }
        $this->_criteria = $value;
    }

    /**
     * @param string $className
     * @return CSort
     */
    public function getSort($className = 'YMongoSort')
    {
        if (null === $this->_sort) {
            $this->_sort = new $className();
            $this->_sort->modelClass = $this->modelClass;

            if ('' !== ($id = $this->getId())) {
                $this->_sort->sortVar = $id . '_sort';
            }
        }
        return $this->_sort;
    }

    /**
     * @return YMongoDocument[]
     */
    public function fetchData()
    {
        $criteria = $this->getCriteria();

        // I have not refactored this line considering that the condition may have changed from total item count to here, maybe.
        $this->_cursor = $this->model->find(isset($criteria['condition']) && is_array($criteria['condition']) ? $criteria['condition'] : array());

        // If we have sort and limit and skip setup within the incoming criteria let's set it
        if(isset($criteria['sort']) && is_array($criteria['sort'])) {
            $this->_cursor->sort($criteria['sort']);
        }
        if(isset($criteria['skip']) && is_int($criteria['skip'])) {
            $this->_cursor->skip($criteria['skip']);
        }
        if(isset($criteria['limit']) && is_int($criteria['limit'])) {
            $this->_cursor->limit($criteria['limit']);
        }

        // Pagination
        if(false !== ($pagination=$this->getPagination())) {
            $pagination->setItemCount($this->getTotalItemCount());
            $this->_cursor->limit($pagination->getLimit());
            $this->_cursor->skip($pagination->getOffset());
        }

        // Ordering
        if(false !== ($sort=$this->getSort())) {
            $sort = $sort->getOrderBy();
            if(sizeof($sort) > 0){
                $this->_cursor->sort($sort);
            }
        }

        return iterator_to_array($this->_cursor, false);
    }

    /**
     * @return array
     */
    public function fetchKeys()
    {
        $result = array();
        /** @var YMongoDocument $data */
        foreach($this->getData() as $i => $data) {
            $key = null === $this->keyAttribute ? $data->{$data->primaryKey()} : $data->{$this->keyAttribute};
            $result[$i] = is_array($key) ? implode(',', $key) : $key;
        }
        return $result;
    }

    /**
     * @return int
     */
    public function calculateTotalItemCount()
    {
        if (null === $this->_cursor) {
            $criteria = $this->getCriteria();
            $this->_cursor = $this->model->find(isset($criteria['condition']) && is_array($criteria['condition']) ? $criteria['condition'] : array());
        }
        return $this->_cursor->count();
    }
}