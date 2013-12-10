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

class YMongoSoftDeleteBehaviour extends CActiveRecordBehavior
{
    /**
     *
     * @var string
     */
    protected $fieldName = 'is_deleted';

    /**
     * Mark record as deleted
     * @return $this
     */
    public function remove()
    {
        $this->getOwner()->{$this->fieldName} = true;
        return $this;
    }

    /**
     * Mark record as not deleted
     * @return $this
     */
    public function restore()
    {
        $this->getOwner()->{$this->fieldName} = false;
        return $this;
    }

    /**
     * Check the status of row
     * @return bool
     */
    public function isRemoved()
    {
        return (bool) $this->getOwner()->{$this->fieldName};
    }

    /**
     * Add criteria to find fields marked as NOT deleted
     * @return CComponent
     */
    public function notRemoved()
    {
        /** @var YMongoDocument $owner */
        $owner = $this->getOwner();

        /**
         * We need to check FALSE and NULL value, because before we delete any record
         * {$this->fieldName} flag may not exists!!
         */
        $owner->mergeDbCriteria(array(
            'condition' => array(
                $this->fieldName => array(
                    '$in' => array(false, null)
                ),
            ),
        ));

        return $owner;
    }

    /**
     * Add criteria to find fields marked as deleted
     * @return CComponent
     */
    public function removed()
    {
        /** @var YMongoDocument $owner */
        $owner = $this->getOwner();

        $owner->mergeDbCriteria(array(
            'condition' => array($this->fieldName => true)
        ));

        return $owner;
    }
}