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
        // Get sub documents & determinate type of sub document
        list(, $type) = $this->getSubDocumentsAndType($object, $attribute);

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
     * Returns the JavaScript needed for performing client-side validation.
     *
     * @param YMongoModel $object the data object being validated
     * @param string $attribute the name of the attribute to be validated.
     * @return string the client-side validation script. Null if the validator does not support client-side validation.
     */
    public function clientValidateAttribute($object, $attribute)
    {
        // Save original
        $originalAttributeName = $attribute;

        // Find out if it is nested
        $attribute = $object->parseAttributeName($attribute);

        // Get model
        $document = $object->{$attribute};

        // Extract nested name
        if (preg_match_all("/\[(.*?)\]/", $originalAttributeName, $matches)) {
            $matches = $matches[1];

            // nested[0][attribute] - multi
            if ('' === preg_replace("/\d+/", '', $matches[0])) {
                $attribute = $matches[1];
            }
            // nested[attribute] - single
            else {
                $attribute = $matches[0];
            }

        }

        $validators = array();

        // Check for required
        if ($document instanceof YMongoModel) {
            // Add rules from parent
            if (!empty($this->rules)) {
                $this->addRulesToModel($document, true);
            }

            foreach ($document->getValidators($attribute) as $validator) {
                /** @var $validator CValidator */
                if ($validator->enableClientValidation) {
                    if (($js = $validator->clientValidateAttribute($document, $attribute)) != '') {
                        $validators[] = $js;
                    }
                }
            }
        }
        elseif ($document instanceof YMongoArrayModel) {
            /** @var $item YMongoModel */
            foreach ($document as $item) {
                // Add rules from parent
                if (!empty($this->rules)) {
                    $this->addRulesToModel($item, true);
                }

                foreach ($item->getValidators($attribute) as $validator) {
                    /** @var $validator CValidator */
                    if ($validator->enableClientValidation) {
                        if (($js = $validator->clientValidateAttribute($item, $attribute)) != '') {
                            $validators[] = $js;
                        }
                    }
                }
            }
        }

        return !empty($validators) ? implode(PHP_EOL, $validators) : '';
    }

    /**
     * @param YMongoModel $object
     * @param string $attribute
     * @return array
     */
    private function getSubDocumentsAndType($object, $attribute)
    {
        // Object sub documents
        $subDocuments = $object->subDocuments();

        return array(
            $subDocuments,
            // Determinate type of sub document
            isset($subDocuments[$attribute]['type']) ? $subDocuments[$attribute]['type'] : YMongoModel::SUB_DOCUMENT_SINGLE,
        );
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