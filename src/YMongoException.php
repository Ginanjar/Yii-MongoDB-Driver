<?php

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