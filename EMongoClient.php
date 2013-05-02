<?php

// Check for loaded MongoDB extension
if (!extension_loaded('mongo')) {
    throw new CException('MongoDB extension is not loaded.');
}
// Check the version of MongoDB, this class is designed to work with the version >= 1.3.0
if (version_compare(phpversion('mongo'), '1.3.0', '<')) {
    throw new CException('Please update MongoDB driver to version 1.3.0 or earlier.');
}

class EMongoClient extends CApplicationComponent
{
    /**
     * Connection string (pre-1.3)
     *
     * @example mongodb://[username:password@]host1[:port1][,host2[:port2:],...]/db
     * @var string
     */
    public $server;

    /**
     * Database name, will overwrite database from server string
     *
     * @var string
     */
    public $dbName;

    /**
     * Options for the MongoClient constructor. By default you need not to change this options.
     *
     * @link http://www.php.net/manual/en/mongoclient.construct.php
     * @var array
     */
    public $options = array(
        'connect' => true,
    );

    /**
     * Specifies the read preference type.
     *
     * @link http://www.php.net/manual/en/mongo.readpreferences.php
     * @var array
     */
    public $readPreference = MongoClient::RP_PRIMARY;

    /**
     * Specifies the read preference tags as an array of strings.
     *
     * @link http://www.php.net/manual/en/mongo.readpreferences.php
     * @var array
     */
    public $readPreferenceTags = array();

    /**
     * The w option specifies the Write Concern for the driver, which determines how long the driver blocks when writing. The default value is 1.
     *
     * @link http://www.php.net/manual/en/mongo.writeconcerns.php
     * @var mixed
     */
    public $w = 1;

    /**
     * The write will be acknowledged by primary and the journal flushed to disk
     *
     * @link http://www.php.net/manual/en/mongo.writeconcerns.php
     * @var bool
     */
    public $j = false;

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
     * Initialize application
     */
    public function init()
    {
        parent::init();

        if (!is_array($this->options)) {
            $this->options = array('connect' => true);
        }

        // Connect to the server
        $this->connect();
    }

    /**
     * Connect and set read preference
     */
    public function connect()
    {
        // Set default Write Concern
        if (null !== $this->w && !isset($this->options['w'])) {
            $this->options['w'] = $this->w;
        }

        // Create connection
        $this->mongo = new MongoClient($this->server, $this->options);

        // Set read preference
        $this->mongo->setReadPreference($this->readPreference, $this->readPreferenceTags);
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
     * Gets the default write concern options for all queries through active record
     *
     * @return array
     */
    public function getDefaultWriteConcern()
    {
        return array('w' => $this->w, 'j' => $this->j);
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
     * ---------------------------
     * Wrappers for DB function
     * ---------------------------
     */

    /**
     * Get a pointer to the collection
     *
     * @param $collectionName
     * @return MongoCollection
     */
    public function getCollection($collectionName)
    {
        return $this->getDatabase()->selectCollection($collectionName);
    }
}