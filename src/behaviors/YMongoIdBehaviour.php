<?php

/**
 * @author Maksim Naumov <me@yukki.name>
 * @link http://yukki.name/
 *
 * @version 1.0.0
 *
 * GitHub Repo: @link https://github.com/fromYukki/Yii-MongoDB-Driver
 * Issues: @link https://github.com/fromYukki/Yii-MongoDB-Driver/issues
 * Documentation: @link https://github.com/fromYukki/Yii-MongoDB-Driver/wiki
 */

class YMongoIdBehaviour extends CActiveRecordBehavior
{
    public $idAttributes = array();
    public $skipNull = true;

    /**
     * @param CModelEvent $event
     */
    public function beforeSave($event)
    {
        if (!is_array($this->idAttributes)) {
            $this->idAttributes = array($this->idAttributes);
        }

        /** @var YMongoDocument $owner */
        $owner = $this->getOwner();

        foreach ($this->idAttributes as $attribute) {
            if (!$owner->hasAttribute($attribute)) {
                continue;
            }
            if ($this->skipNull && null === $owner->{$attribute}) {
                continue;
            }
            if (!($owner->{$attribute} instanceof MongoId)) {
                $owner->{$attribute} = $this->createId($owner->{$attribute});
            }
        }
    }

    /**
     * @param mixed $value
     * @return MongoId[]|MongoId
     */
    private function createId($value = null)
    {
        if ($value instanceof MongoId) {
            return $value;
        }
        // Array
        elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->createId($v);
            }
            return $value;
        }
        // Single element
        else {
            return new MongoId($value);
        }
    }
}
