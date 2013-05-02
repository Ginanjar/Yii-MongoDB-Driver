<?php

/**
 * public function behaviors(){
 *     return array(
 *         'YMongoDateBehaviour' => array(
 *             'class' => 'ext.mongoDb.behaviors.YMongoDateBehaviour',
 *             'dateAttributes' => 'date',
 *         )
 *     );
 * }
 */
class YMongoDateBehaviour extends CActiveRecordBehavior
{
    public $dateAttributes = array();

    /**
     * @param CModelEvent $event
     */
    public function beforeSave($event)
    {
        if (!is_array($this->dateAttributes)) {
            $this->dateAttributes = array($this->dateAttributes);
        }

        /** @var YMongoDocument $owner */
        $owner = $this->getOwner();

        foreach ($this->dateAttributes as $attribute) {
            if (!$owner->hasAttribute($attribute)) {
                continue;
            }
            if (!($owner->{$attribute} instanceof MongoDate)) {
                $currentValue = $owner->{$attribute};

                if (is_string($currentValue)) {
                    $owner->{$attribute} = new MongoDate(strtotime($currentValue));
                }
                elseif (is_int($currentValue)) {
                    $owner->{$attribute} = new MongoDate($currentValue);
                }
                else {
                    $owner->{$attribute} = new MongoDate();
                }
            }
        }
    }
}