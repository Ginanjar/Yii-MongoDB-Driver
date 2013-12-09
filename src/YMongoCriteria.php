<?php

/**
 * CDbCriteria analogue for MongoDB. Use this class is not required, but may be useful.
 */
class YMongoCriteria extends CComponent
{
    /**
     * Terms of selection
     *
     * @var array
     */
    private $condition = array();

    /**
     * Sorting
     *
     * @var array
     */
    private $sort = array();

    /**
     * Skip
     *
     * @var int
     */
    private $skip = 0;

    /**
     * Limit
     *
     * @var int
     */
    private $limit = 0;

    /**
     * @var array
     */
    private $allowItems = array('condition', 'limit', 'skip', 'sort');

    /**
     * Initialization is made by analogy with CDbCriteria
     *
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        foreach ($data as $name => $value) {
            if (in_array($name, $this->allowItems)) {
                $this->$name = $value;
            }
        }
    }

    /**
     * Set the selection conditions
     *
     * @param array $condition
     * @return YMongoCriteria
     */
    public function setCondition(array $condition = array())
    {
        $this->condition = CMap::mergeArray($condition, $this->condition);
        return $this;
    }

    /**
     * Add new condition selection
     *
     * @param string $column
     * @param mixed $value
     * @param string $operator
     */
    public function addCondition($column, $value, $operator = null)
    {
        $this->condition[$column] = $operator === null ? $value : array($operator => $value);
    }

    /**
     * Add OR condition
     *
     * @param array $condition
     */
    public function addOrCondition($condition)
    {
        $this->condition['$or'] = $condition;
    }

    /**
     * @param $column
     * @param $valueStart
     * @param $valueEnd
     */
    public function addBetweenCondition($column, $valueStart, $valueEnd)
    {
        $this->condition[$column] = array(
            '$gte' => $valueStart,
            '$lte' => $valueEnd,
        );
    }

    /**
     * Get the selection conditions
     *
     * @return array
     */
    public function getCondition()
    {
        return $this->condition;
    }

    /**
     * Set sort
     *
     * @param array $sort
     * @return YMongoCriteria
     */
    public function setSort(array $sort)
    {
        $this->sort = CMap::mergeArray($sort, $this->sort);
        return $this;
    }

    /**
     * Get sort
     *
     * @return array
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * Set skip
     *
     * @param int $skip
     * @return YMongoCriteria
     */
    public function setSkip($skip)
    {
        $this->skip = (int) $skip;
        return $this;
    }

    /**
     * Get skip
     *
     * @return int
     */
    public function getSkip()
    {
        return $this->skip;
    }

    /**
     * Set limit
     *
     * @param int $limit
     * @return YMongoCriteria
     */
    public function setLimit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    /**
     * Get limit
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Simple search implementation
     *
     * @param string $column
     * @param mixed $value
     * @param bool $strong
     * @return YMongoCriteria
     */
    public function compare($column, $value = null, $strong = true)
    {
        // Not empty
        if (null === $value || '' === $value) {
            return $this;
        }

        $query = array();

        if (preg_match('/^(?:\s*(<>|!=|<=|>=|<|>|=))?(.*)$/', $value, $matches)) {
            // Operator and value
            list(/* full expression */, $operator, $value) = $matches;

            // If this is not a strong compliance and not a number, create MongoRegex, and if you give a number to an int
            if (!$strong && !preg_match('/^[0-9\.]+$/', $value)) {
                $value = new MongoRegex('/' . preg_quote($value) . '/i');

            } elseif (preg_match('/^[0-9]+$/', $value)) {
                $value = (int) $value;

            } elseif (preg_match('/^[0-9\.]+$/', $value)) {
                $value = (float) $value;
            }

            // Let us consider each operator
            switch($operator) {
                case "<=":
                    $query[$column] = array('$lte' => $value);
                    break;

                case ">=":
                    $query[$column] = array('$gte' => $value);
                    break;

                case "<":
                    $query[$column] = array('$lt' => $value);
                    break;

                case ">":
                    $query[$column] = array('$gt' => $value);
                    break;

                case '!=':
                case '<>':
                    $query[$column] = array('$ne' => $value);
                    break;

                default:
                    $query[$column] = $value;
                    break;
            }
        }

        // Empty query?
        if (empty($query)) {
            $query[$column] = $value;
        }

        $this->condition = CMap::mergeArray($query, $this->condition);
        return $this;
    }

    /**
     * Combine two conditions
     *
     * @param array|YMongoCriteria $criteria
     * @return YMongoCriteria
     */
    public function mergeWith($criteria)
    {
        if ($criteria instanceof YMongoCriteria) {
            // Conditions
            $this->condition = CMap::mergeArray($this->getCondition(), $criteria->getCondition());

            // Sort
            $this->sort = CMap::mergeArray($this->getSort(), $criteria->getSort());

            // Skip
            $this->skip = $criteria->getSkip();

            // Limit
            $this->limit = $criteria->getLimit();

        } elseif (is_array($criteria)) {
            // Conditions
            if (isset($criteria['condition']) && is_array($criteria['condition'])) {
                $this->condition = CMap::mergeArray($this->getCondition(), $criteria['condition']);
            }

            // Sort
            if (isset($criteria['sort']) && is_array($criteria['sort'])) {
                $this->sort = CMap::mergeArray($this->getSort(), $criteria['sort']);
            }

            // Skip
            if (isset($criteria['skip'])) {
                $this->skip = (int) $criteria['skip'];
            }

            // Limit
            if (isset($criteria['limit'])) {
                $this->limit = (int) $criteria['limit'];
            }
        }

        return $this;
    }

    /**
     * Get an array of criteria
     *
     * @param bool $onlyCondition
     * @return array
     */
    public function toArray($onlyCondition = false)
    {
        $result = array();
        if (true === $onlyCondition) {
            $result = $this->getCondition();
        } else {
            foreach ($this->allowItems as $name) {
                $result[$name] = $this->$name;
            }
        }
        return $result;
    }
}