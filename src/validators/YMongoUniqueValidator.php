<?php

class YMongoUniqueValidator extends CValidator
{
    /**
     * Whether the comparison is case sensitive. Defaults to true.
     * Note, by setting it to false, you are assuming the attribute type is string.
     *
     * @var bool
     */
    public $caseSensitive = true;

    /**
     * Whether the attribute value can be null or empty. Defaults to true,
     * meaning that if the attribute is empty, it is considered valid.
     *
     * @var bool
     */
    public $allowEmpty = true;

    /**
     * The ActiveRecord class name that should be used to
     * look for the attribute value being validated. Defaults to null, meaning using
     * the class of the object currently being validated.
     * You may use path alias to reference a class name here.
     *
     * @var string
     */
    public $className;

    /**
     * The ActiveRecord class attribute name that should be
     * used to look for the attribute value being validated. Defaults to null,
     * meaning using the name of the attribute being validated.
     *
     * @var string
     */
    public $attributeName;

    /**
     * Additional query criteria. Either an array or YMongoCriteria.
     * This will be combined with the condition that checks if the attribute
     * value exists in the corresponding table column.
     * This array will be used to instantiate a {@link YMongoCriteria} object.
     *
     * @var mixed
     */
    public $criteria = array();

    /**
     * The user-defined error message. The placeholders "{attribute}" and "{value}"
     * are recognized, which will be replaced with the actual attribute name and value, respectively.
     *
     * @var string
     */
    public $message;

    /**
     * Whether this validation rule should be skipped if when there is already a validation
     * error for the current attribute. Defaults to true.
     *
     * @var boolean
     */
    public $skipOnError = true;

    /**
     * Validates a single attribute.
     *
     * @param YMongoDocument $object the data object being validated
     * @param string $attribute the name of the attribute to be validated.
     */
    protected function validateAttribute($object, $attribute)
    {
        $value = $object->{$attribute};

        if ($this->allowEmpty && $this->isEmpty($value)) {
            return;
        }

        if (is_array($value)) {
            $this->addError($object,$attribute,Yii::t('yii','{attribute} is invalid.'));
            return;
        }

        // Trim a little bit
        $value = trim((string) $value);

        $className = null === $this->className ? get_class($object) : Yii::import($this->className);
        $attributeName = null === $this->attributeName ? $attribute : $this->attributeName;

        if ($this->criteria instanceof YMongoCriteria) {
            $this->criteria = $this->criteria->getCondition();
        }

        $res = YMongoDocument::model($className)
            ->getCollection()
            ->findOne(CMap::mergeArray(
                $this->criteria,
                array(
                    $attributeName => $this->caseSensitive ? $value : new MongoRegex('/^' . quotemeta($value) . '$/i')
                )
            ));

        // We found something, lets check this out
        if ($res && (string) $res[$object->primaryKey()] != (string) $object->getPrimaryKey()) {
            $message = null !== $this->message ? $this->message : Yii::t('yii','{attribute} "{value}" has already been taken.');
            $this->addError($object, $attribute, $message, array('{value}' => CHtml::encode($value)));
        }
    }
}