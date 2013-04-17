<?php

/**
 * Represents the Yii edition to the MongoCursor and allows for lazy loading of objects.
 */
class EMongoCursor implements Iterator, Countable
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
     * @var EMongoDocument
     */
    public $model;

    private $cursor = array();

    /**
     * Current EMongoDocument document
     * @var EMongoDocument
     */
    private $current;

    /**
     * @param string|EMongoDocument $modelClass
     * @param array|MongoCursor|EMongoCriteria $criteria
     */
    public function __construct($modelClass, $criteria = array())
    {
        // Store model class
        if (is_string($modelClass)) {
            $this->modelClass = $modelClass;
            $this->model = EMongoDocument::model($this->modelClass);
        } elseif ($modelClass instanceof EMongoDocument) {
            $this->modelClass = get_class($modelClass);
            $this->model = $modelClass;
        }

        if ($criteria instanceof MongoCursor) {
            $this->cursor = $criteria;
            $this->cursor->reset();
        } elseif($criteria instanceof EMongoCriteria) {
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
     * @return EMongoDocument
     * @throws CException
     */
    public function current()
    {
        if (null === $this->model) {
            throw new CException(Yii::t('yii', "The MongoCursor must have a model"));
        }
        return $this->current = $this->model->populateRecord($this->cursor()->current());
    }

    public function count($takeSkip = false /* Was true originally but it was to change the way the driver worked which seemed wrong */)
    {
        return $this->cursor()->count($takeSkip);
    }

    public function sort(array $fields)
    {
        $this->cursor()->sort($fields);
        return $this;
    }

    public function skip($skip = 0)
    {
        $this->cursor()->skip($skip);
        return $this;
    }

    public function limit($limit = 0)
    {
        $this->cursor()->limit($limit);
        return $this;
    }

    public function rewind()
    {
        $this->cursor()->rewind();
        return $this;
    }

    public function key()
    {
        return $this->cursor()->key();
    }

    public function next()
    {
        $this->cursor()->next();
    }

    public function valid()
    {
        return $this->cursor()->valid();
    }

    public function __call($method, $params = array())
    {
        $cursor = $this->cursor();
        if ($cursor instanceof MongoCursor && method_exists($cursor, $method)){
            return call_user_func_array(array($cursor, $method), $params);
        }
        throw new CException(Yii::t('yii', "Call to undefined function {method} on the cursor", array('{method}' => $method)));
    }
}