<?php

/**
 * Proration autoloader for PHP < 5.3
 */
class AutoloadProration
{
    /**
     * Attempt to load the given class
     *
     * @param string $class The class to load
     */
    public static function load($class)
    {
        $baseDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;

        $classes = array(
            'Proration' => $baseDir . 'Proration.php'
        );

        if (isset($classes[$class])) {
            include $classes[$class];
        }
    }
}
