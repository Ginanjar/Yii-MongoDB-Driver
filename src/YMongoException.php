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

class YMongoException extends CException
{
    /**
     * Additional information
     *
     * @var mixed
     */
    public $errorInfo;

    /**
     * @param string $message
     * @param int $code
     * @param mixed $errorInfo
     */
    public function __construct($message, $code = 0, $errorInfo = null)
    {
        $this->errorInfo = $errorInfo;
        parent::__construct($message, $code);
    }

    /**
     * @param Exception $e
     * @return YMongoException
     */
    public static function copy(Exception $e)
    {
        return new self($e->getMessage(), $e->getCode());
    }
}