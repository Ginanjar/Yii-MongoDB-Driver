<?php

/**
 * EMongoTimestampBheaviour will automatically fill date and time related attributes.
 *
 * EMongoTimestampBheaviour will automatically fill date and time related attributes when the active record
 * is created and/or upadated.
 * You may specify an active record model to use this behavior like so:
 * <pre>
 * public function behaviors(){
 * 	return array(
 * 		'EMongoTimestampBheaviour' => array(
 * 			'class' => 'EMongoTimestampBheaviour',
 * 			'createAttribute' => 'create_time_attribute',
 * 			'updateAttribute' => 'update_time_attribute',
 * 		)
 * 	);
 * }
 * </pre>
 * The {@link createAttribute} and {@link updateAttribute} options actually default to 'create_time' and 'update_time'
 * respectively, so it is not required that you configure them. If you do not wish EMongoTimestampBheaviour
 * to set a timestamp for record update or creation, set the corresponding attribute option to null.
 *
 * By default, the update attribute is only set on record update. If you also wish it to be set on record creation,
 * set the {@link setUpdateOnCreate} option to true.
 *
 * Although EMongoTimestampBheaviour attempts to figure out on it's own what value to inject into the timestamp attribute,
 * you may specify a custom value to use instead via {@link timestampExpression}
 */
class EMongoTimestampBehaviour extends CActiveRecordBehavior
{
    /**
     * @var mixed The name of the attribute to store the creation time.  Set to null to not
     * use a timestamp for the creation attribute.  Defaults to 'create_time'
     */
    public $createAttribute = 'create_time';
    /**
     * @var mixed The name of the attribute to store the modification time.  Set to null to not
     * use a timestamp for the update attribute.  Defaults to 'update_time'
     */
    public $updateAttribute = 'update_time';

    /**
     * @var bool Whether to set the update attribute to the creation timestamp upon creation.
     * Otherwise it will be left alone.  Defaults to false.
     */
    public $setUpdateOnCreate = false;

    /**
     * @var mixed The expression that will be used for generating the timestamp.
     * This can be either a string representing a PHP expression (e.g. 'time()').
     */
    public $timestampExpression;

    /**
     * Responds to {@link CModel::onBeforeSave} event.
     * Sets the values of the creation or modified attributes as configured
     *
     * @param CModelEvent $event event parameter
     */
    public function beforeSave($event)
    {
        /** @var $owner EMongoDocument */
        $owner = $this->getOwner();
        if ($owner->getIsNewRecord() && ($this->createAttribute !== null)) {
            $owner->{$this->createAttribute} = $this->getTimestampByAttribute($this->createAttribute);
        }

        if ((!$owner->getIsNewRecord() || $this->setUpdateOnCreate) && ($this->updateAttribute !== null)) {
            $owner->{$this->updateAttribute} = $this->getTimestampByAttribute($this->updateAttribute);
        }
    }

    /**
     * Gets the appropriate timestamp depending on the column type $attribute is
     *
     * @param string $attribute
     * @return mixed timestamp (eg unix timestamp or a php function)
     */
    protected function getTimestampByAttribute($attribute)
    {
        if ($this->timestampExpression instanceof MongoDate) {
            return $this->timestampExpression;
        } elseif ($this->timestampExpression !== null) {
            return @eval('return '.$this->timestampExpression.';');
        }
        return new MongoDate();
    }
}