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
    public $skipNull = true;

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
            if ($this->skipNull && null === $owner->{$attribute}) {
                continue;
            }
            $owner->{$attribute} = YMongoCommand::mDate($owner->{$attribute});
        }
    }
}