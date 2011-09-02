<?php

/**
 * ~~summary~~
 * 
 * ~~description~~
 * 
 * PHP version 5
 * 
 * @category  Net
 * @package   Net_RouterOS
 * @author    Vasil Rangelov <boen.robot@gmail.com>
 * @copyright 2011 Vasil Rangelov
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @version   SVN: $Revision$
 * @link      http://netrouteros.sourceforge.net/
 */
/**
 * The namespace declaration.
 */
namespace Net\RouterOS;

/**
 * A stream transmitter.
 * 
 * This is a convinience wrapper for stream functionality. Used to ensure data
 * integrity. Designed for sockets, but it has intentionally been made to accept
 * any stream.
 * 
 * @category Net
 * @package  Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://netrouteros.sourceforge.net/
 * @see      Client
 */
class Transmitter
{

    /**
     * @var resource The stream to wrap around.
     */
    protected $stream;

    /**
     * @var bool A flag that tells whether or not the stream is persistent.
     */
    protected $persist;

    /**
     * Wraps around the specified stream.
     * 
     * @param resource $stream The stream to wrap around.
     * 
     * @see isFresh()
     */
    public function __construct($stream)
    {
        if (!self::isStream($stream)) {
            throw $this->createException('Invalid stream supplied.', 1);
        }
        $this->stream = $stream;
        $this->persist = (bool) preg_match(
            '#persistent#sm', get_resource_type($stream)
        );
    }

    /**
     * Checks if a given variable is a stream resource.
     * 
     * @param mixed $var The variable to check.
     * 
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function isStream($var)
    {
        return is_resource($var)
            && (bool) preg_match('#\s?stream$#sm', get_resource_type($var));
    }

    /**
     * Checks whether the wrapped stream is fresh.
     * 
     * Checks whether the wrapped stream is fresh. A stream is considered fresh
     * if there hasn't been any activity on it. Particularly useful for
     * detecting reused persistent connections.
     * 
     * @return bool TRUE if the socket is fresh, FALSE otherwise.
     */
    public function isFresh()
    {
        return ftell($this->stream) === 0;
    }

    /**
     * Sends a string over the wrapped stream.
     * 
     * @param string $string The string to send.
     * 
     * @return int The number of bytes sent.
     */
    public function send($string)
    {
        $bytes = 0;
        $bytesToSend = (double) sprintf('%u', strlen($string));
        while ($bytes < $bytesToSend) {
            if ($this->isAcceptingData()) {
                $bytesNow = @fwrite(
                    $this->stream, substr($string, $bytes, 0xFFFFF)
                );
                if (0 != $bytesNow) {
                    $bytes += $bytesNow;
                } else {
                    throw $this->createException(
                        'Failed while sending string.', 2
                    );
                }
            }
        }
        return $bytes;
    }

    /**
     * Sends a stream over the wrapped stream.
     * 
     * @param resource $stream The stream to send.
     * 
     * @return int The number of bytes sent.
     */
    public function sendStream($stream)
    {
        $bytes = 0;
        while (!feof($stream)) {
            if ($this->isAcceptingData()) {
                $bytesNow = @stream_copy_to_stream(
                    $stream, $this->stream, 0xFFFFF
                );
                if (0 != $bytesNow) {
                    $bytes += $bytesNow;
                } else {
                    throw $this->createException(
                        'Failed while sending stream.', 3
                    );
                }
            }
        }
        fseek($stream, -$bytes, SEEK_CUR);
        return $bytes;
    }

    /**
     * Reads from the wrapped stream to receive.
     * 
     * Reads from the wrapped stream to receive content as a string.
     * 
     * @param int    $length The number of bytes to read.
     * @param string $what   Descriptive string about what is being received
     * (used in exception messages).
     * 
     * @return string The received content.
     */
    public function receive($length, $what = 'data')
    {
        $result = '';
        while ($length > 0) {
            if ($this->isAvailable()) {
                while ($this->isDataAwaiting()) {
                    $fragment = fread($this->stream, min($length, 0xFFFFF));
                    if ('' !== $fragment) {
                        $length -= strlen($fragment);
                        $result .= $fragment;
                        continue 2;
                    }
                }
            }
            throw $this->createException(
                "Failed while receiving {$what}", 4
            );
        }
        return $result;
    }

    /**
     * Reads from the wrapped stream to receive.
     * 
     * Reads from the wrapped stream to receive content as a stream.
     * 
     * @param int    $length The number of bytes to read.
     * @param string $what   Descriptive string about what is being received (used
     * in exception messages).
     * 
     * @return resource The received content.
     */
    public function receiveStream($length, $what = 'stream data')
    {
        $result = fopen('php://temp', 'r+b');
        while ($length > 0) {
            if ($this->isAvailable()) {
                while ($this->isDataAwaiting()) {
                    $fragment = fread($this->stream, min($length, 0xFFFFF));
                    if ('' !== $fragment) {
                        $length -= strlen($fragment);
                        fwrite($result, $fragment);
                        continue 2;
                    }
                }
            }
            throw $this->createException(
                "Failed while receiving {$what}", 5
            );
        }
        rewind($result);
        return $result;
    }

    /**
     * Checks whether the stream is available for operations.
     * 
     * @return bool TRUE if the stream is available, FALSE otherwise.
     */
    public function isAvailable()
    {
        return self::isStream($this->stream) && !feof($this->stream);
    }

    /**
     * Checks whether there is data to be read from the wrapped stream.
     * 
     * @return bool TRUE if there is data to be read, FALSE otherwise.
     */
    public function isDataAwaiting()
    {
        return $this->isAvailable();
    }

    /**
     * Checks whether the wrapped stream can be written to without a block.
     * 
     * @return bool TRUE if the wrapped stream would not block on a write, FALSE
     * otherwise.
     */
    public function isAcceptingData()
    {
        $r = $e = null;
        $w = array($this->stream);
        return self::isStream($this->stream)
            && 1 === @/* due to PHP bug #54563 */stream_select($r, $w, $e, 0);
    }

    /**
     * Creates a new exception.
     * 
     * Creates a new exception. Used by the rest of the functions in this class.
     * Override in derived classes for custom exception handling.
     * 
     * @param string $message The exception message.
     * @param int    $code    The exception code.
     * 
     * @return Exception The exception to then be thrown.
     */
    protected function createException($message, $code = 0)
    {
        return new Exception($message, $code);
    }

    /**
     * Closes the opened connection, unless it's a persistent one.
     */
    public function __destruct()
    {
        if (!$this->persist) {
            $this->close();
        }
    }

    /**
     * Closes the opened stream, even if it is a persistent one.
     * 
     * @return bool TRUE on success, FALSE on failure.
     */
    public function close()
    {
        return self::isStream($this->stream) && fclose($this->stream);
    }

}