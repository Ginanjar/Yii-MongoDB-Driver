<?php

class YMongoCursor implements Iterator, Countable
{
    public $criteria = array();

    /**
     * The name of the model class
     * @var string
     */
    public $modelClass;

    /**
     * MongoDB document
     *
     * @var YMongoDocument
     */
    public $model;

    /**
     * @var MongoCursor
     */
    private $cursor;

    /**
     * Current YMongoDocument document
     * @var YMongoDocument
     */
    private $current;

    /**
     * @param string|YMongoDocument $modelClass
     * @param array|MongoCursor|YMongoCriteria $criteria
     * @return YMongoCursor
     */
    public function __construct($modelClass, $criteria = array())
    {
        // Store model class
        if (is_string($modelClass)) {
            $this->modelClass = $modelClass;
            $this->model = YMongoDocument::model($this->modelClass);
        } elseif ($modelClass instanceof YMongoDocument) {
            $this->modelClass = get_class($modelClass);
            $this->model = $modelClass;
        }

        if ($criteria instanceof MongoCursor) {
            $this->cursor = $criteria;
            $this->cursor->reset();
        } elseif($criteria instanceof YMongoCriteria) {
            $this->criteria = $criteria;
            $this->cursor = $this->model->getCollection()->find($criteria->getCondition())->sort($criteria->getSort());
            if ($criteria->getSkip() != 0) {
                $this->cursor->skip($criteria->getSkip());
            }
            if ($criteria->getLimit() != 0) {
                $this->cursor->limit($criteria->getLimit());
            }
        } else {
            // Then we are doing an active query
            $this->criteria = $criteria;
            $this->cursor = $this->model->getCollection()->find($criteria);
        }

        return $this;
    }

    /**
     * Holds the MongoCursor
     *
     * @return MongoCursor
     */
    public function cursor()
    {
        return $this->cursor;
    }

    /**
     * Gets the active record for the current row
     *
     * @return YMongoDocument
     * @throws YMongoException
     */
    public function current()
    {
        if (null === $this->model) {
            throw new YMongoException(Yii::t('yii', "The MongoCursor must have a model"));
        }
        return $this->current = $this->model->populateRecord($this->cursor()->current());
    }

    /**
     * @param bool $takeSkip
     * @return int
     */
    public function count($takeSkip = false /* Was true originally but it was to change the way the driver worked which seemed wrong */)
    {
        return $this->cursor()->count($takeSkip);
    }

    /**
     * @param array $fields
     * @return YMongoCursor
     */
    public function sort(array $fields)
    {
        $this->cursor()->sort($fields);
        return $this;
    }

    /**
     * @param int $skip
     * @return YMongoCursor
     */
    public function skip($skip = 0)
    {
        $this->cursor()->skip($skip);
        return $this;
    }

    /**
     * @param int $limit
     * @return YMongoCursor
     */
    public function limit($limit = 0)
    {
        $this->cursor()->limit($limit);
        return $this;
    }

    /**
     * @return YMongoCursor
     */
    public function rewind()
    {
        $this->cursor()->rewind();
        return $this;
    }

    /**
     * @return string
     */
    public function key()
    {
        return $this->cursor()->key();
    }

    /**
     *
     */
    public function next()
    {
        $this->cursor()->next();
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->cursor()->valid();
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws YMongoException
     */
    public function __call($method, $params = array())
    {
        $cursor = $this->cursor();
        if ($cursor instanceof MongoCursor && method_exists($cursor, $method)){
            return call_user_func_array(array($cursor, $method), $params);
        }
        throw new YMongoException(Yii::t('yii', "Call to undefined function {method} on the cursor", array('{method}' => $method)));
    }
}