<?php

namespace PicoFeed;
use PicoFeed\Reader;

class Logging
{
    public static $messages = array();

    public static function log($message)
    {
        $date = new \DateTime("now",new \DateTimeZone(Reader::getTimeZone()));
        self::$messages[] = '['.$date->format('Y-m-d H:i:s').'] '.$message;
    }
}
