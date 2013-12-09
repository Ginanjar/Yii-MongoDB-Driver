<?php

class YMongoIntBehaviour extends CActiveRecordBehavior
{
    const INT_32 = 'MongoInt32';
    const INT_64 = 'MongoInt64';

    public $int32Attributes = array();
    public $int64Attributes = array();

    /**
     * @param string $type
     * @return array
     */
    public function getAttributes($type)
    {
        if (self::INT_64 === $type) {
            return !is_array($this->int64Attributes) ? array($this->int64Attributes) : $this->int64Attributes;
        } else {
            return !is_array($this->int32Attributes) ? array($this->int32Attributes) : $this->int32Attributes;
        }
    }

    /**
     * @param CModelEvent $event
     */
    public function beforeSave($event)
    {
        $this->run($event);
    }

    /**
     * @param CModelEvent $event
     */
    public function afterFind($event)
    {
        $this->run($event);
    }

    /**
     * @param CModelEvent $event
     */
    private function run($event)
    {
        /** @var YMongoDocument $owner */
        $owner = $this->getOwner();

        foreach ($this->getAttributes(self::INT_32) as $attribute) {
            if (!$owner->hasAttribute($attribute)) {
                continue;
            }
            if (!($owner->{$attribute} instanceof MongoInt32)) {
                $owner->{$attribute} = $this->createInt($owner->{$attribute}, self::INT_32);
            }
        }

        foreach ($this->getAttributes(self::INT_64) as $attribute) {
            if (!$owner->hasAttribute($attribute)) {
                continue;
            }
            if (!($owner->{$attribute} instanceof MongoInt64)) {
                $owner->{$attribute} = $this->createInt($owner->{$attribute}, self::INT_64);
            }
        }
    }

    /**
     * @param mixed $value
     * @param string $classType
     * @return MongoId[]|MongoId
     */
    private function createInt($value = null, $classType = self::INT_32)
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