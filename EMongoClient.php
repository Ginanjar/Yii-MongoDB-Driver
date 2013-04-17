<?php

/**
 * Check the version of MongoDB, this class is designed to work with the version >= 1.3.0
 */
if(version_compare(phpversion('mongo'), '1.3.0', '<')) {
    throw new CException('MongoDB driver version under 1.3.0. Please update MongoDB driver.');
}

/**
 * Class to work with MongoDB
 */
class EMongoClient extends CApplicationComponent
{
    /**
     * A path alias for Yii
     */
    const PATH_ALIAS = 'mongoExtension';

    /**
     * Connection string (pre-1.3)
     * @example mongodb://[username:password@]host1[:port1][,host2[:port2:],...]/db
     *
     * @var string
     */
    public $connectionString;

    /**
     * Options for the MongoClient constructor (@link http://www.php.net/manual/en/mongoclient.construct.php)
     *
     * @var array
     */
    public $options = array();

    /**
     * Database name
     *
     * @var string
     */
    public $dbName;

    /**
     * An instance of MongoClient
     *
     * @var MongoClient
     */
    private $mongo;

    /**
     * An instance of MongoDB
     *
     * @var MongoDB
     */
    private $db;

    /**
     * Creates an instance, include libraries
     */
    public function __construct()
    {
        // Подключить необходимые классы
        Yii::setPathOfAlias(self::PATH_ALIAS, dirname(__FILE__));
        Yii::import(self::PATH_ALIAS . '.*');
    }

    /**
     * Initializes and connect to the database
     */
    public function init()
    {
        $this->connect();
        parent::init();
    }

    /**
     * Open database connection, preparatory operations
     */
    public function connect()
    {
        // Соединяемся с базой
        $this->mongo = new MongoClient($this->connectionString, $this->options);
    }

    /**
     * Get a database connection
     *
     * @return MongoClient
     */
    public function getConnection()
    {
        if (empty($this->mongo)) {
            $this->connect();
        }

        return $this->mongo;
    }

    /**
     * Set database
     *
     * @param string $dbName
     */
    public function setDatabase($dbName)
    {
        $this->db = $this->getConnection()->selectDB($dbName);
    }

    /**
     * Get database
     *
     * @return MongoDB
     */
    public function getDatabase()
    {
        if (empty($this->db)) {
            $this->setDatabase($this->dbName);
        }

        return $this->db;
    }

    /**
     * Get a list of databases
     *
     * @return array
     */
    public function databaseNames()
    {
        $databases = $this->getConnection()->listDBs();

        // Error
        if (!$databases['ok']) {
            return array();
        }

        // Just the names
        $result = array();
        foreach($databases['databases'] as $db) {
            $result[] = $db['name'];
        }
        return $result;
    }

    /**
     * ---------------------------
     * Wrappers for DB function
     * ---------------------------
     */

    /**
     * Result of the command
     *
     * @param array $command
     * @param array $options
     * @return array
     */
    public function command(array $command, array $options = array())
    {
        return $this->getDatabase()->command($command, $options);
    }

    /**
     * Execute JS code on the database
     *
     * @param string $javascriptCode
     * @param array $args
     * @return mixed
     */
    public function execute($javascriptCode, array $args = array())
    {
        $result = $this->getDatabase()->execute($javascriptCode, $args);
        return $result['ok'] ? $result['retval'] : false;
    }

    /**
     * Get a pointer to the collection
     *
     * @param $collectionName
     * @return MongoCollection
     */
    public function selectCollection($collectionName)
    {
        return $this->getDatabase()->selectCollection($collectionName);
    }

    /**
     * Get a list of collections in the database
     *
     * @return MongoCollection[]
     */
    public function getCollections()
    {
        return $this->getDatabase()->listCollections();
    }

    /**
     * Get a list of collection names in the database
     *
     * @return array
     */
    public function collectionNames()
    {
        $collections = $this->getCollections();

        if (empty($collections)) {
            return array();
        }

        $result = array();
        foreach($collections as $collection) {
            $result[] = $collection->getName();
        }

        return $result;
    }

    /**
     * ---------------------------
     * Wrappers for collections function
     * ---------------------------
     */
}