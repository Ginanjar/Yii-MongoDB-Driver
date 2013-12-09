<?php

// Check for loaded MongoDB extension
if (!extension_loaded('mongo')) {
    throw new CException('MongoDB extension is not loaded.');
}
// Check the version of MongoDB, this class is designed to work with the version >= 1.3.0
if (version_compare(phpversion('mongo'), '1.3.0', '<')) {
    throw new CException('Please update MongoDB driver to version 1.3.0 or later.');
}

// Set MongoDb settings
if ((bool) ini_get('mongo.long_as_object')) {
    ini_set('mongo.long_as_object', 0);
}
if (! (bool) ini_get('mongo.native_long')) {
    ini_set('mongo.native_long', 1);
}

class YMongoClient extends CApplicationComponent
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
     * @var bool
     */
    public $enableProfiling = false;

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
     * Static cache for attribute names
     *
     * @var array
     */
    private static $_attributeNames = array();

    /**
     *
     */
    public function __construct()
    {
        CValidator::$builtInValidators['mongoSubDocument'] = 'YSubDocumentValidator';
    }

    /**
     * Initialize application
     */
    public function init()
    {
        if (YII_DEBUG) {
            Yii::trace('MongoDb driver is initialized.', 'ext.mongoDb.YMongoClient');
        }

        // Load classes
        Yii::setPathOfAlias('mongoDb', dirname(__FILE__));
        Yii::import('mongoDb.*');
        Yii::import('mongoDb.cache.*');
        Yii::import('mongoDb.behaviors.*');
        Yii::import('mongoDb.validators.*');

        parent::init();

        if (!is_array($this->options)) {
            $this->options = array('connect' => true);
        }

        // Connect to the server
        $this->connect();
    }

    /**
     * Connect and set read preference
     *
     * @throws YMongoException
     */
    public function connect()
    {
        // Set default Write Concern
        if (null !== $this->w && !isset($this->options['w'])) {
            $this->options['w'] = $this->w;
        }

        // Create connection
        try {
            $this->mongo = new MongoClient($this->server, $this->options);
        }
        // Lets throw YMongoException
        catch(Exception $e) { /** MongoConnectionException */
            throw YMongoException::copy($e);
        }

        if (YII_DEBUG) {
            Yii::trace('MongoDb connected (' . $this->server . ').', 'ext.mongoDb.YMongoClient');
        }

        // Set read preference
        if (MongoClient::RP_PRIMARY === $this->readPreference) {
            $this->mongo->setReadPreference($this->readPreference);
        } else {
            $this->mongo->setReadPreference($this->readPreference, $this->readPreferenceTags);
        }
    }

    /**
     * Get a database connection
     *
     * @return MongoClient
     * @throws YMongoException
     */
    public function getConnection()
    {
        if (null === $this->mongo) {
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
     * @throws YMongoException
     */
    public function setDatabase($dbName = 'admin')
    {
        try {
            $this->db = $this->getConnection()->selectDB($dbName);
        }
        // Lets throw YMongoException
        catch (Exception $e) {
            throw YMongoException::copy($e);
        }

        if (YII_DEBUG) {
            Yii::trace('MongoDb database selected (' . $dbName . ').', 'ext.mongoDb.YMongoClient');
        }
    }

    /**
     * Get database
     *
     * @return MongoDB
     * @throws YMongoException
     */
    public function getDatabase()
    {
        if (null === $this->db) {
            $this->setDatabase($this->dbName);
        }
        return $this->db;
    }

    /**
     * @param string $collectionName
     * @return YMongoCommand
     */
    public function createCommand($collectionName = null)
    {
        return new YMongoCommand($this, $collectionName);
    }

    /**
     * ---------------------------
     * Wrappers for DB function
     * ---------------------------
     */

    /**
     * Get a pointer to the collection
     *
     * @param string $collectionName
     * @return MongoCollection
     * @throws YMongoException
     */
    public function getCollection($collectionName = 'test')
    {
        try {
            return $this->getDatabase()->selectCollection($collectionName);
        }
        // Lets throw YMongoException
        catch (Exception $e) {
            throw YMongoException::copy($e);
        }
    }

    /**
     * @param mixed $code
     * @param array $args
     * @return mixed
     */
    public function execute($code, array $args = array())
    {
        if ($this->enableProfiling) {
            Yii::beginProfile('MongoClient execute "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoClient');
        }

        $response = $this->getDatabase()->execute($code, $args);

        if ($this->enableProfiling) {
            Yii::endProfile('MongoClient execute "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoClient');
        }

        return empty($response['ok']) ? false : $response['retval'];
    }

    /**
     * @param array $command
     * @param array $options
     * @return array
     */
    public function command(array $command, array $options = array())
    {
        if ($this->enableProfiling) {
            Yii::beginProfile('MongoClient command "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoClient');
        }

        $result = $this->getDatabase()->command($command, $options);

        if ($this->enableProfiling) {
            Yii::endProfile('MongoClient command "'.$this->getCollection()->getName().'"', 'system.mongoDb.YMongoClient');
        }

        return $result;
    }

    public function setDocumentCache($object)
    {
        if (!is_object($object)) {
            return;
        }

        $objectName = get_class($object);
        if (!isset(self::$_attributeNames[$objectName])) {
            /**
             * Initialize an empty array with the names of the attributes.
             * Static cache is still necessary, even with the finding that no attributes.
             */
            self::$_attributeNames[$objectName] = array();

            // Class data
            $class = new ReflectionClass($objectName);
            $classProperties = $class->getProperties(ReflectionProperty::IS_PUBLIC);

            foreach($classProperties as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                // Property comments
                $docComment = $property->getDocComment();

                // Detect type of field
                $type = false;
                if (preg_match('/@var ([a-zA-Z0-9_]+)/', $docComment, $matches)) {
                    $type = $matches[1];
                }

                self::$_attributeNames[$objectName][] = array(
                    'name' => $property->getName(),
                    'virtual' => $property->isProtected() || (bool) preg_match("/@virtual/i", $docComment),
                    'type' => $type,
                );
            }
        }
    }

    /**
     * @param string|object $objectName
     * @return array
     */
    public function getDocumentCache($objectName)
    {
        if (is_object($objectName)) {
            $objectName = get_class($objectName);
        }
        return isset(self::$_attributeNames[$objectName]) ? self::$_attributeNames[$objectName] : array();
    }

    /**
     * Get the names of the attributes of the class kept in cache
     *
     * @param string|object $objectName
     * @param bool $withVirtual
     * @return array
     */
    public function getDocumentFields($objectName, $withVirtual = false)
    {
        $result = array();
        $cache = $this->getDocumentCache($objectName);

        if (!empty($cache)) {
            foreach ($cache as $item) {
                if (!$withVirtual && $item['virtual']) {
                    continue;
                }
                $result[] = $item['name'];
            }
        }

        return $result;
    }
}