<?php

class YMongoArrayModel extends CList
{
    /**
     * @var string
     */
    public $modelClass;

    /**
     * @param string|object $modelClass
     * @param array $values
     */
    public function __construct($modelClass, array $values = array())
    {
        $this->modelClass = !is_object($modelClass) ? $modelClass : get_class($modelClass);
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
        $model = new $this->modelClass(!empty($data) ? YMongoModel::SCENARIO_UPDATE : YMongoModel::SCENARIO_INSERT);
        $model->setAttributes($data, false);
        return $model;
    }
}