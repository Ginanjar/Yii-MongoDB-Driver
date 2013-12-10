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

class YMongoTimestampBehavior extends CActiveRecordBehavior
{
    /**
     * The name of the attribute to store the creation time.  Set to null to not
     * use a timestamp for the creation attribute.  Defaults to 'create_time'
     *
     * @var string
     */
    public $createAttribute = 'create_time';

    /**
     * The name of the attribute to store the modification time.  Set to null to not
     * use a timestamp for the update attribute.  Defaults to 'update_time'
     *
     * @var string
     */
    public $updateAttribute = 'update_time';

    /**
     * Whether to set the update attribute to the creation timestamp upon creation.
     * Otherwise it will be left alone.  Defaults to false.
     *
     * @var bool
     */
    public $setUpdateOnCreate = false;

    /**
     * The expression that will be used for generating the timestamp.
     * This can be either a string representing a PHP expression (e.g. 'time()'),
     *
     * A PHP expression can be any PHP code that has a value. To learn more about what an expression is,
     * please refer to the {@link http://www.php.net/manual/en/language.expressions.php php manual}.
     *
     * @var mixed
     */
    public $timestampExpression;

    /**
     * Sets the values of the creation or modified attributes as configured
     *
     * @param CEvent $event event parameter
     */
    public function beforeSave($event)
    {
        /** @var YMongoDocument $owner */
        $owner = $this->getOwner();

        if ($owner->getIsNewRecord() && (null !== $this->createAttribute)) {
            $owner->{$this->createAttribute} = $this->getTimestampByAttribute($this->createAttribute);
        }
        if ((!$owner->getIsNewRecord() || $this->setUpdateOnCreate) && (null !== $this->updateAttribute)) {
            $owner->{$this->updateAttribute} = $this->getTimestampByAttribute($this->updateAttribute);
        }
    }

    /**
     *  Gets the appropriate timestamp depending on the column type $attribute is
     *
     * @param $attribute
     * @return MongoDate
     */
    protected function getTimestampByAttribute($attribute)
    {
        if ($this->timestampExpression instanceof MongoDate) {
            return $this->timestampExpression;
        }
        // Lets try to eval this PPH code
        elseif (null !== $this->timestampExpression) {
            return @eval('return '.$this->timestampExpression.';');
        }
        // Create new one
        else {
            return new MongoDate();
        }
    }
}