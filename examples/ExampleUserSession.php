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

class ExampleUserSession extends YMongoDocument
{
    /** @var string */
    public $user_id;
    /** @var string */
    public $session_key;

    /**
     * Returns the static model of the specified AR class.
     * @param string $className
     * @return ExampleUserSession
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
        return 'users_session';
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
            'user_id' => 'User Id',
            'session_key' => 'Session key',
        );
    }
} 