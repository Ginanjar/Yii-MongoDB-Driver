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

/**
 * @property ExampleUserSession[] $sessions
 * @property ExampleCountry $country
 * @property YMongoArrayModel|ExampleUseDeviceNested[] $devices
 * @property ExampleUseInfoNested $info
 *
 * Next methods defined in {@see \ext\mongoDb\behaviors\YMongoSoftDeleteBehaviour}
 * @method ExampleUser notRemoved()
 * @method ExampleUser removed()
 * @method boolean isRemoved()
 * @method ExampleUser restore()
 * @method ExampleUser remove()
 * @property int $is_deleted
 */
class ExampleUser extends YMongoDocument
{
    /** @var string */
    public $username;
    /** @var string */
    public $password;
    /** @var string */
    public $country_code;
    /** @var MongoDate */
    public $some_date;
    /** @var MongoId */
    public $some_id;
    /** @var MongoInt32 */
    public $some_int32;
    /** @var MongoInt64 */
    public $some_int64;
    /** @virtual */
    public $notBeSaved = 'This variable will not be saved!';

    /**
     * Returns the static model of the specified AR class.
     * @param string $className
     * @return ExampleUser
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * Returns name of the collection associated with this model
     * @return string
     */
    public function collectionName()
    {
        return 'users';
    }

    /**
     * Returns attribute labels for this model
     * @return array
     */
    public function attributeLabels()
    {
        return array(
            '_id' => 'Id',
            'id' => 'Id',
            'username' => 'Username',
            'password' => 'Password',
        );
    }

    /**
     * Returns the validation rules for attributes.
     *
     * @return array
     */
    public function rules()
    {
        return array(
            // Required fields
            array('username, password', 'required', 'on' => array(self::SCENARIO_INSERT, self::SCENARIO_UPDATE)),

            // Unique fields
            array('username', 'YMongoUniqueValidator',
                'on' => array(
                    self::SCENARIO_INSERT,
                    self::SCENARIO_UPDATE
                ),
                'caseSensitive' => false,
                'skipOnError' => true
            ),

            // Safe attributes
            array('_id, username', 'safe', 'on' => self::SCENARIO_SEARCH),

            // Sub documents
            array('devices', 'mongoSubDocument'),
            array('info', 'mongoSubDocument', 'rules' => array(
                array('first_name, last_name', 'required'),
            ))
        );
    }

    /**
     * Returns relations with other models
     * @return array
     */
    public function relations()
    {
        return array(
            'sessions' => array(
                YMongoModel::RELATION_MANY,
                'ExampleUserSession',
                'user_id',
                'on' => '_id'
            ),
            'country' => array(
                YMongoModel::RELATION_ONE,
                'ExampleCountry',
                'code',
                'on' => 'country_code',
            ),
        );
    }

    /**
     * Return config for nested documents
     * @return array
     */
    public function subDocuments()
    {
        return array(
            'info' => array(
                'ExampleUseInfoNested',
            ),
            'devices' => array(
                'ExampleUseDeviceNested',
                'type' => YMongoModel::SUB_DOCUMENT_MULTI,
            ),
        );
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @return YMongoDataProvider
     */
    public function search()
    {
        $criteria = new YMongoCriteria();
        $criteria->compare('_id', $this->_id);
        $criteria->compare('username', $this->username, false);

        // Ordering
        $criteria->setSort(array(
            'username',
        ));

        return new YMongoDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }

    /**
     * Returns a list of behaviors that this model should behave as.
     * @return array
     */
    public function behaviors()
    {
        return array(
            'createUpdate' => array(
                'class' => 'ext.mongoDb.behaviors.YMongoTimestampBehavior',
                'createAttribute' => 'create_time',
                'updateAttribute' => 'update_time',
                'setUpdateOnCreate' => true,
            ),
            'otherDates' => array(
                'class' => 'ext.mongoDb.behaviors.YMongoDateBehaviour',
                'dateAttributes' => array(
                    'some_date',
                ),
            ),
            'ids' => array(
                'class' => 'ext.mongoDb.behaviors.YMongoIdBehaviour',
                'idAttributes' => array(
                    'some_id',
                ),
            ),
            'integers' => array(
                'class' => 'ext.mongoDb.behaviors.YMongoIntBehaviour',
                'int32Attributes' => array(
                    'some_int32',
                ),
                'int64Attributes' => array(
                    'some_int64',
                ),
            ),
            'softDelete' => array(
                'class' => 'ext.mongoDb.behaviors.YMongoSoftDeleteBehaviour',
                'fieldName' => 'is_deleted',
            ),
        );
    }
}
