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

class YMongoArrayModel extends CList
{
    /**
     * @var string
     */
    public $modelClass;

    /** @var string */
    public $scenario;

    /**
     * @param string|object $modelClass
     * @param array $values
     * @param $scenario
     */
    public function __construct($modelClass, array $values = array(), $scenario = null)
    {
        $this->modelClass = !is_object($modelClass) ? $modelClass : get_class($modelClass);

        $this->scenario = $scenario;
        $this->copyFrom($values);
    }

    /**
     * @param array $values
     */
    public function populate(array $values = array())
    {
        $this->clear();
        $this->copyFrom($values);
    }

    /**
     * @return array
     */
    public function getDocuments()
    {
        return $this->toArray();
    }

    /**
     * Inserts an item at the specified position.
     *
     * @param int $index
     * @param mixed $item
     */
    public function insertAt($index, $item)
    {
        parent::insertAt($index, $this->createObject($item));
    }

    /**
     * @param array|YMongoModel $data
     * @return YMongoModel
     */
    protected function createObject($data = array())
    {
        if (($data instanceof YMongoModel)) {
            return $data;
        }

        /** @var YMongoModel $model */
        $model = new $this->modelClass(($this->scenario ? $this->scenario : (!empty($data) ? YMongoModel::SCENARIO_UPDATE : YMongoModel::SCENARIO_INSERT)));
        $model->setAttributes($data, false);
        return $model;
    }
}