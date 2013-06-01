<?php

class YSubDocumentValidator extends CValidator
{
    /**
     * Type of sub documents (one document or array of documents)
     *
     * @var string
     */
    public $type = YMongoModel::SUB_DOCUMENT_SINGLE;

    /**
     * Use this class to validate
     *
     * @var
     */
    public $className;

    /**
     * Validation rules
     *
     * @var array
     */
    public $rules = array();

    /**
     * @param YMongoModel $object
     * @param string $attribute
     * @throws YMongoException
     */
    public function validateAttribute($object, $attribute)
    {
        if (!in_array($this->type, array(YMongoModel::SUB_DOCUMENT_SINGLE, YMongoModel::SUB_DOCUMENT_MULTI))) {
            throw new YMongoException(Yii::t('yii', 'You must supply a sub document type of either "multi" or "single" in order to validate sub documents.'));
        }

        if (empty($this->rules) && empty($this->className)) {
            throw new YMongoException(Yii::t('yii','You must supply either some rules to validate by or a class name to use.'));
        }

        if ($this->className) {
            /** @var YMongoModel $model */
            $model = new $this->className();
        } else {
            // Create new one
            $model = new YMongoModel();

            foreach($this->rules as $rule) {
                // attributes, validator name
                if (isset($rule[0], $rule[1])) {
                    $model->getValidatorList()->add(CValidator::createValidator($rule[1], $model, $rule[0], array_slice($rule, 2)));
                }
                else {
                    throw new YMongoException(Yii::t('yii','{class} has an invalid validation rule. The rule must specify attributes to be validated and the validator name.', array('{class}' => get_class($this))));
                }
            }
        }

        if (YMongoModel::SUB_DOCUMENT_MULTI === $this->type) {
            if (is_array($object->{$attribute}) || ($object->{$attribute} instanceof YMongoArrayModel)) {
                // Error collections
                $values = array();
                $errors = array();

                // List of data need to be validated
                $data = $object->{$attribute} instanceof YMongoArrayModel ? $object->$attribute->getDocuments() : $object->{$attribute};

                // Validate every document
                foreach($data as $i => $item) {
                    $model->clean();
                    $value = $values[$i] = $item instanceof $model ? $item->getDocument() : $item;
                    $model->setAttributes($value);
                    if (!$model->validate()) {
                        $errors[$i] = $model->getErrors();
                    }
                }

                if (null !== $this->message) {
                    $this->addError($object, $attribute, $this->message);
                } elseif (sizeof($model->getErrors()) > 0) {
                    $object->addError($attribute, $errors);
                }

                // Strip the models etc from the field value
                $object->{$attribute} = $values;
            }
        }
        // Single object
        else {
            $model->clean();
            $value = $object->{$attribute} instanceof $model ? $object->{$attribute}->getDocument() : $object->{$attribute};
            $model->setAttributes($value);
            if (!$model->validate()) {
                if (null !== $this->message) {
                    $this->addError($object, $attribute, $this->message);
                } elseif (sizeof($model->getErrors()) > 0) {
                    $object->addError($attribute, $model->getErrors());
                }
            }

            // Strip the models etc from the field value
            $object->{$attribute} = $value;
        }
    }
}