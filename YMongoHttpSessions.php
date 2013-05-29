<?php

class YMongoHttpSessions extends CHttpSession
{
    /**
     * The ID of a {@link YMongoClient} application component.
     *
     * @var string
     */
    public $connectionID;

    /**
     * The name of the collection to store session content.
     *
     * @var string
     */
    public $collectionName = 'yii_session';

    /**
     * Save session data as binary object
     *
     * @var bool
     */
    public $saveAsBinary = false;

    /**
     * Type of mongo binary data (@see MongoBinData)
     *
     * @var int
     */
    public $binaryType = MongoBinData::BYTE_ARRAY;

    /**
     * The MongoDB connection instance
     *
     * @var YMongoClient
     */
    private $_connection;

    /**
     * Returns a value indicating whether to use custom session storage.
     * This method overrides the parent implementation and always returns true.
     *
     * @return boolean
     */
    public function getUseCustomStorage()
    {
        return true;
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

        $db = Yii::app()->getComponent($this->connectionID);
        if ($db instanceof YMongoClient) {
            return $this->_connection = $db;
        }

        throw new CException(
            Yii::t(
                'yii','YMongoHttpSessions.connectionID "{id}" is invalid. Please make sure it refers to the ID of a YMongoClient application component.',
                array('{id}'=>$this->connectionID)
            )
        );
    }

    /**
     * Updates the current session id with a newly generated one.
     *
     * @param bool $deleteOldSession Whether to delete the old associated session file or not.
     */
    public function regenerateID($deleteOldSession = false)
    {
        $oldID = session_id();

        // if no session is started, there is nothing to regenerate
        if (empty($oldID)) {
            return;
        }

        parent::regenerateID(false);
        $newID = session_id();

        try {
            // Mongo command instance
            $command = $this->getConnection()->createCommand($this->collectionName);

            $item = $command->getOneWhere(array('_id' => $oldID));
            // Gotcha
            if ($item) {
                if ($deleteOldSession) {
                    $command->where('_id', $oldID)->delete();
                }
                $item['_id'] = $newID;
                $command->insert($item);
            }
            // shouldn't reach here normally
            else {
                $command->insert(array(
                    '_id' => $newID,
                    'expire' => YMongoCommand::mDate(time() + $this->getTimeout())
                ));
            }
        } catch (Exception $e) { }
    }

    /**
     * Session open handler.
     * Do not call this method directly.
     *
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return bool
     */
    public function openSession($savePath, $sessionName)
    {
        return true;
    }

    /**
     * Session close handler.
     * Do not call this method directly.
     *
     * @return bool
     */
    public function closeSession()
    {
        return true;
    }

    /**
     * Session read handler.
     * Do not call this method directly.
     *
     * @param string|MongoId $id
     * @return string
     */
    public function readSession($id)
    {
        try {
            $item = $this->getConnection()
                ->createCommand($this->collectionName)
                ->select('data')
                ->where('_id', $id)
                ->whereGt('expire', YMongoCommand::mDate())
                ->getOne();
            $data = isset($item['data']) ? $item['data'] : '';
            Yii::trace('User session (' . $id . ') read successfully finished. Data: ' . print_r($data, true), 'ext.mongoDb.YMongoHttpSessions');
            return $data;
        } catch (Exception $e) {
            Yii::log('User session (' . $id . ') read FAILED: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'ext.mongoDb.YMongoHttpSessions');
            return '';
        }
    }

    /**
     * Session write handler.
     * Do not call this method directly.
     *
     * @param string|MongoId $id
     * @param string $data
     * @return bool
     */
    public function writeSession($id, $data)
    {
        try {
            $expire = YMongoCommand::mDate(time() + $this->getTimeout());

            // Mongo command instance
            $command = $this->getConnection()->createCommand($this->collectionName);

            if ($this->saveAsBinary) {
                $data = new MongoBinData($data, $this->binaryType);
            }

            // Update if already exists
            if ($command->getOneWhere(array('_id' => $id))) {
                $command->where('_id', $id)
                    ->set('data', $data)
                    ->set('expire', $expire)
                    ->update();
            }
            // Insert new one
            else {
                $command->insert(array(
                    '_id' => $id,
                    'data' => $data,
                    'expire' => $expire,
                ));
            }
            Yii::trace('User session (' . $id . ') write successfully finished. Data: ' . print_r($data, true), 'ext.mongoDb.YMongoHttpSessions');
            return true;
        } catch (Exception $e) {
            Yii::log('User session (' . $id . ') write FAILED: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'ext.mongoDb.YMongoHttpSessions');
            return false;
        }
    }

    /**
     * Session destroy handler.
     * Do not call this method directly.
     *
     * @param string|MongoId $id session ID
     * @return bool
     */
    public function destroySession($id)
    {
        try {
            $this->getConnection()
                ->createCommand($this->collectionName)
                ->where('_id', $id)
                ->delete();
            Yii::trace('User session (' . $id . ') destroy successfully finished.', 'ext.mongoDb.YMongoHttpSessions');
            return true;
        } catch (Exception $e) {
            Yii::log('User session (' . $id . ') destroy FAILED: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'ext.mongoDb.YMongoHttpSessions');
            return false;
        }
    }

    /**
     * Session GC (garbage collection) handler.
     * Do not call this method directly.
     *
     * @param int $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return bool
     */
    public function gcSession($maxLifetime)
    {
        try {
            $this->getConnection()
                ->createCommand($this->collectionName)
                ->whereLt('expire', YMongoCommand::mDate())
                ->deleteAll();
            Yii::trace('Garbage collection of user session successfully finished.', 'ext.mongoDb.YMongoHttpSessions');
            return true;
        } catch (Exception $e) {
            Yii::log('Garbage collection of user session FAILED: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'ext.mongoDb.YMongoHttpSessions');
            return false;
        }
    }
}
