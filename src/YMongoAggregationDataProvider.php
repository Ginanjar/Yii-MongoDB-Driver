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

class YMongoAggregationDataProvider extends CDataProvider
{
    /**
     * The ID of a {@link YMongoClient} application component.
     * If null it is mean that we will use 'mongoDb' component
     * @var string
     */
    public $connectionID;

    /**
     * Collection name
     * @var
     */
    public $collectionName;

    /**
     * An array of pipeline operators
     * @var array
     */
    public $pipeline;

    /**
     * string the name of key field. Defaults to '_id'.
     * @var string
     */
    public $keyField = '_id';

    /**
     * The MongoDB connection instance
     * @var YMongoClient
     */
    private $_connection;

    /**
     * @param array $pipeline
     * @param array $config
     * @throws CException
     */
    public function __construct(array $pipeline, array $config = array())
    {
        $this->pipeline = $pipeline;
        foreach($config as $key => $value) {
            $this->$key = $value;
        }

        if (empty($this->collectionName)) {
            throw new CException(
                Yii::t('yii', 'You should specify collectionName attribute.')
            );
        }
    }

    /**
     * @return YMongoClient
     * @throws CException
     */
    protected function getConnection()
    {
        if (null !== $this->_connection) {
            return $this->_connection;
        }

        if (null === $this->connectionID) {
            $this->connectionID = 'mongoDb';
        }

        $db = Yii::app()->getComponent($this->connectionID);
        if ($db instanceof YMongoClient) {
            return $this->_connection = $db;
        }

        throw new CException(
            Yii::t('yii','YMongoHttpSessions.connectionID "{id}" is invalid. Please make sure it refers to the ID of a YMongoClient application component.',
                array('{id}'=>$this->connectionID)
            )
        );
    }

    /**
     * Fetches the data from the persistent data storage.
     * @return array list of data items
     */
    protected function fetchData()
    {
        $pipeline = $this->pipeline;

        if (($pagination = $this->getPagination()) !== false) {
            $pagination->setItemCount($this->getTotalItemCount());
            $limit = $pagination->getLimit();
            $offset = $pagination->getOffset();

            if ($offset) {
                $pipeline[] = array(
                    '$skip' => $offset,
                );
            }
            if ($limit) {
                $pipeline[] = array(
                    '$limit' => $limit,
                );
            }
        }

        $result = array();

        try {
            $dbResult = $this->getConnection()
                ->getCollection($this->collectionName)
                ->aggregate($pipeline);

            // It is OK
            if (!empty($dbResult['ok'])) {
                $result = $dbResult['result'];
            }
        } catch (Exception $e) { }

        return $result;
    }


    /**
     * Fetches the data item keys from the persistent data storage.
     * @return array list of data item keys.
     */
    protected function fetchKeys()
    {
        $keys = array();
        if ($data = $this->getData()) {
            foreach($data as $i => $item) {
                $keys[$i] = $item[$this->keyField];
            }
        }
        return $keys;
    }

    /**
     * @return int
     */
    protected function calculateTotalItemCount()
    {
        $pipeline = CMap::mergeArray($this->pipeline, array(
            array(
                '$group' => array(
                    '_id' => 1,
                    'count' => array(
                        '$sum' => 1,
                    )
                ),
            ),
            array(
                '$project' => array(
                    '_id' => 0,
                    'count' => 1,
                ),
            ),
        ));

        $result = 0;

        try {
            $dbResult = $this->getConnection()
                ->getCollection($this->collectionName)
                ->aggregate($pipeline);

            // It is OK
            if (!empty($dbResult['ok']) && !empty($dbResult['result'][0])) {
                $result = $dbResult['result'][0]['count'];
            }
        } catch (Exception $e) { }

        return $result;
    }
} 