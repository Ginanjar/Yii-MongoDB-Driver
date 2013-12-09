<?php

/**
 * This is only ever used in conjunction with CGridView and CListView. It is not designed to be used independently
 */
class YMongoSort extends CSort
{
    /**
     * @param string $attribute
     * @return mixed
     */
    public function resolveAttribute($attribute)
    {
        if (array() !== $this->attributes) {
            $attributes = $this->attributes;
        } elseif (null !== $this->modelClass) {
            $attributes = YmongoDocument::model($this->modelClass)->attributeNames();
            if(empty($attributes)) {
                $attributes = YmongoDocument::model($this->modelClass)->getSafeAttributeNames();
            }
        } else {
            return false;
        }

        foreach ($attributes as $name => $definition) {
            if (is_string($name)) {
                if ($name === $attribute) {
                    return $definition;
                }
            } elseif('*' === $definition) {
                if (null !== $this->modelClass && YmongoDocument::model($this->modelClass)->hasAttribute($attribute)) {
                    return $attribute;
                }
            } elseif ($definition === $attribute) {
                return $attribute;
            }
        }
        return false;
    }

    /**
     * @param string $attribute
     * @return string
     */
    public function resolveLabel($attribute)
    {
        $definition = $this->resolveAttribute($attribute);
        if (is_array($definition)) {
            if (isset($definition['label'])) {
                return $definition['label'];
            }
        } elseif (is_string($definition)) {
            $attribute = $definition;
        }

        if(null !== $this->modelClass) {
            return YmongoDocument::model($this->modelClass)->getAttributeLabel($attribute);
        }

        return $attribute;
    }

    /**
     * @param mixed $criteria
     * @return array
     */
    public function getOrderBy($criteria = null)
    {
        $directions = $this->getDirections();
        if (empty($directions)) {
            return is_string($this->defaultOrder) ? $this->defaultOrder : array();
        }

        $orders=array();
        foreach($directions as $attribute => $descending) {
            $definition = $this->resolveAttribute($attribute);
            if (is_array($definition)) {
                // Atm only single cell sorting is allowed, this will change to allow you to define
                // a true definition of multiple fields to sort when one sort field is triggered but atm that is not possible
                if ($descending) {
                    $orders[$attribute] = isset($definition['desc']) ? MongoCollection::DESCENDING : MongoCollection::ASCENDING;
                } else {
                    $orders[$attribute] = isset($definition['asc']) ? MongoCollection::ASCENDING : MongoCollection::DESCENDING;
                }
            }
            elseif(false !== $definition) {
                $orders[$definition] = $descending ? MongoCollection::DESCENDING : MongoCollection::ASCENDING;
            }
        }
        return $orders;
    }
}