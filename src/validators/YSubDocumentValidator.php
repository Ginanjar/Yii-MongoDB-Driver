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

class YSubDocumentValidator extends CValidator
{
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
        // Object sub documents
        $subDocuments = $object->subDocuments();

        // Determinate type of sub document
        $type = isset($subDocuments[$attribute]['type']) ? $subDocuments[$attribute]['type'] : YMongoModel::SUB_DOCUMENT_SINGLE;

        // Multi objects
        if (YMongoModel::SUB_DOCUMENT_MULTI === $type) {

            $errors = array();

            /** @var YMongoModel $model */
            foreach ($object->{$attribute} as $i => $model) {
                if (!empty($this->rules)) {
                    $this->addRulesToModel($model, true);
                }
                if (!$model->validate()) {
                    $errors[$i] = $model->getErrors();
                }
            }

            if (!empty($errors)) {
                if (null !== $this->message) {
                    $this->addError($object, $attribute, $this->message);
                } else {
                    $object->addError($attribute, $errors);
                }
            }
        }

        // Single object
        elseif (YMongoModel::SUB_DOCUMENT_SINGLE === $type) {
            /** @var YMongoModel $model */
            $model = $object->{$attribute};
            if (!empty($this->rules)) {
                $this->addRulesToModel($model, true);
            }
            if (!$model->validate()) {
                if (null !== $this->message) {
                    $this->addError($object, $attribute, $this->message);
                } else {
                    $object->addError($attribute, $model->getErrors());
                }
            }
        }
    }

    /**
     * @param YMongoModel $model
     * @param bool $clearBefore
     * @throws YMongoException
     */
    private function addRulesToModel($model, $clearBefore = false)
    {
        if ($clearBefore) {
            $model->getValidatorList()->clear();
        }
        foreach($this->rules as $rule) {
            // attributes, validator name
            if (isset($rule[0], $rule[1])) {
                $model->getValidatorList()->add(CValidator::createValidator($rule[1], $model, $rule[0], array_slice($rule, 2)));
            } else {
                throw new YMongoException(Yii::t('yii','{class} has an invalid validation rule. The rule must specify attributes to be validated and the validator name.', array('{class}' => get_class($this))));
            }
        }
    }
}