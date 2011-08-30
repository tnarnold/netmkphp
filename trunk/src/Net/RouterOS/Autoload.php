<?php

/**
 * ~~summary~~
 * 
 * ~~description~~
 * 
 * PHP version 5
 * 
 * @link http://netrouteros.sourceforge.net/
 * @category Net
 * @package Net_RouterOS
 * @version ~~version~~
 * @author Vasil Rangelov <boen.robot@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @copyright 2011 Vasil Rangelov
 */
/**
 * The namespace declaration.
 */
namespace Net\RouterOS;

/**
 * Loads a specified class.
 * 
 * Loads a specified class from the namespace.
 * @param string $class The classname (with namespace) to load.
 */
function Autoload($class)
{
    $namespace = __NAMESPACE__ . '\\';
    if (strpos($class, $namespace) === 0) {
        $path = __DIR__ . DIRECTORY_SEPARATOR .
            strtr(
                substr($class, strlen($namespace)), '\\', DIRECTORY_SEPARATOR
            ) . '.php';
        $file = realpath($path);
        if (is_string($file) && strpos($file, __DIR__) === 0) {
            include_once $file;
        }
    }
}

spl_autoload_register(__NAMESPACE__ . '\Autoload', true);