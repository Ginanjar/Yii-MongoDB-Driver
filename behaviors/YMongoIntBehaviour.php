<?php

class YMongoIntBehaviour extends CActiveRecordBehavior
{
    public $int32Attributes = array();
    public $int64Attributes = array();

    /**
     * @param CModelEvent $event
     */
    public function beforeSave($event)
    {
        if (!is_array($this->int32Attributes)) {
            $this->int32Attributes = array($this->int32Attributes);
        }

        if (!is_array($this->int64Attributes)) {
            $this->int64Attributes = array($this->int64Attributes);
        }

        /** @var YMongoDocument $owner */
        $owner = $this->getOwner();

        foreach ($this->int32Attributes as $attribute) {
            if (!$owner->hasAttribute($attribute)) {
                continue;
            }
            if (!($owner->{$attribute} instanceof MongoInt32)) {
                $owner->{$attribute} = $this->createInt($owner->{$attribute}, 'MongoInt32');
            }
        }

        foreach ($this->int64Attributes as $attribute) {
            if (!$owner->hasAttribute($attribute)) {
                continue;
            }
            if (!($owner->{$attribute} instanceof MongoInt64)) {
                $owner->{$attribute} = $this->createInt($owner->{$attribute}, 'MongoInt64');
            }
        }
    }

    /**
     * @param mixed $value
     * @param string $classType
     * @return MongoId[]|MongoId
     */
    private function createInt($value = null, $classType = 'MongoInt32')
    {
        if ($value instanceof $classType) {
            return $value;
        }
        // Array
        elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->createInt($v, $classType);
            }
            return $value;
        }
        // Single element
        else {
            return new $classType($value);
        }
    }
}