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
 * Exception thrown when something goes wrong with the connection.
 * @package Net_RouterOS
 */
class SocketException extends Exception
{

    /**
     * @var int The error code of the last error on the socket.
     */
    protected $error_no = 0;

    /**
     * @var string The error message of the last error on the socket.
     */
    protected $error_str = '';

    /**
     * Creates a new socket exception.
     * @param string $message The Exception message to throw.
     * @param int $code The Exception code.
     * @param Exception $previous The previous exception used for the exception
     * chaining.
     * @param int $error_no If provided, holds the system level error number
     * that occurred in the system-level connect() call.
     * @param string $error_str The error message as a string.
     */
    public function __construct($message = '', $code = 0, $previous = null,
                                $error_no = null, $error_str = null
    )
    {
        parent::__construct($message, $code, $previous);
        $this->error_no = $error_no;
        $this->error_str = $error_str;
    }

    /**
     * Gets the error code of the last error on the socket.
     * @return int NULL if none was provided or the number itself.  
     */
    public function getSocketErrorNumber()
    {
        return $this->error_no;
    }

    // @codeCoverageIgnoreStart
    // Unreliable in testing.

    /**
     * Gets the error message of the last error on the socket.
     * @return string The error message.
     */
    public function getSocketErrorMessage()
    {
        return $this->error_str;
    }

    /**
     * Returns a string representation of the exception.
     * @return string The exception as a string.
     */
    public function __toString()
    {
        $result = parent::__toString();
        if (0 !== $this->getSocketErrorNumber()) {
            $result .= "\nSocket error number:" . $this->getSocketErrorNumber();
        }
        if ('' !== $this->getSocketErrorMessage()) {
            $result .= "\nSocket error message:"
                . $this->getSocketErrorMessage();
        }
        return $result;
    }

    // @codeCoverageIgnoreEnd
}