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

class YMongoCache extends CCache
{
    /**
     * @var string the ID of the {@link YMongoClient} application component.
     */
    public $connectionID;

    /**
     * The name of the collection to store session content.
     *
     * @var string
     */
    public $collectionName='yii_cache';

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
    private $_gcProbability = 100;
    private $_gcEd = false;

    /**
     * @return integer the probability (parts per million) that garbage collection (GC) should be performed
     * when storing a piece of data in the cache. Defaults to 100, meaning 0.01% chance.
     */
    public function getGCProbability()
    {
        return $this->_gcProbability;
    }

    /**
     * @param integer $value the probability (parts per million) that garbage collection (GC) should be performed
     * when storing a piece of data in the cache. Defaults to 100, meaning 0.01% chance.
     * This number should be between 0 and 1000000. A value 0 meaning no GC will be performed at all.
     */
    public function setGCProbability($value)
    {
        $value = (int) $value;

        if ($value < 0) {
            $value = 0;
        }
        if ($value > 1000000) {
            $value = 1000000;
        }

        $this->_gcProbability = $value;
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
                'yii','YMongoCache.connectionID "{id}" is invalid. Please make sure it refers to the ID of a YMongoClient application component.',
                array('{id}'=>$this->connectionID)
            )
        );
    }

    /**
     * @return YMongoCommand
     */
    protected function command()
    {
        return $this->getConnection()->createCommand($this->collectionName);
    }

    /**
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return string|bool the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function getValue($key)
    {
        try {
            $item = $this->command()
                ->select('value')
                ->where('key', $key)
                ->orWhere(array(
                    array('expire' => 0),
                    array(
                        'expire' => array(
                            '$gt' => YMongoCommand::mDate()
                        ),
                    ),
                ))
                ->getOne();
            $value = isset($item['value']) ? $item['value'] : false;
            if ($this->saveAsBinary && $value instanceof MongoBinData) {
                $value = $value->bin;
            }
            return $value;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Retrieves multiple values from cache with the specified keys.
     * @param array $keys a list of keys identifying the cached values
     * @return array a list of cached values indexed by the keys
     */
    protected function getValues($keys)
    {
        if(empty($keys)) {
            return array();
        }

        // Predefined results
        $results = array_fill_keys($keys, false);

        try {
            $items = $this->command()
                ->select(array('key', 'value'))
                ->whereIn('key', $keys)
                ->orWhere(array(
                    array('expire' => 0),
                    array(
                        'expire' => array(
                            '$gt' => YMongoCommand::mDate()
                        ),
                    ),
                ))
                ->get();

            // Collect values
            foreach ($items as $item) {
                $value = isset($item['value']) ? $item['value'] : false;
                if ($this->saveAsBinary && $value instanceof MongoBinData) {
                    $value = $value->bin;
                }
                $results[$item['key']] = $value;
            }

        } catch (Exception $e) { }

        return $results;
    }

    /**
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached
     * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function setValue($key, $value, $expire)
    {
        return $this->addValue($key, $value, $expire);
    }

    /**
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached
     * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $expire)
    {
        // Run garbage collector
        if (!$this->_gcEd && mt_rand(0, 1000000) < $this->_gcProbability) {
            $this->gc();
            $this->_gcEd = true;
        }

        // Make expire
        if ($expire > 0) {
            $expire = YMongoCommand::mDate($expire + time());
        } else {
            $expire = 0;
        }

        // Make value
        if ($this->saveAsBinary) {
            $value = new MongoBinData($value, $this->binaryType);
        }

        try {
            $this->command()
                ->where('key', $key)
                ->set(array(
                    'key' => $key,
                    'value' => $value,
                    'expire' => $expire,
                ))->upsert();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return boolean if no error happens during deletion
     */
    protected function deleteValue($key)
    {
        try {
            $this->command()
                ->where('key', $key)
                ->deleteAll();
            return true;
        } catch(Exception $e) {
            return false;
        }
    }

    /**
     * Removes the expired data values.
     */
    protected function gc()
    {
        try {
            $this->command()
                ->whereNe('expire', 0)
                ->whereLt('expire', YMongoCommand::mDate())
                ->deleteAll();
        } catch (Exception $e) { }
    }

    /**
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return boolean whether the flush operation was successful.
     */
    protected function flushValues()
    {
        try {
            $this->command()
                ->deleteAll();
            return true;
        } catch(Exception $e) {
            return false;
        }
    }
} 