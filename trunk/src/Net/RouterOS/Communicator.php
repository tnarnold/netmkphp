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
 * A RouterOS communicator.
 * 
 * Implementation of the RouterOS API protocol. Unlike the other classes in this
 * package, this class doesn't provide any conviniences beyond the low level
 * implementation details (automatic word length encoding/decoding and data
 * integrity), and because of that, its direct usage is strongly discouraged.
 * @package Net_RouterOS
 * @see Client
 */
class Communicator
{

    /**
     * @var resource The socket for the connection.
     */
    protected $socket;

    /**
     * @var bool A flag that tells whether or not the connection is a
     * persistent one. 
     */
    protected $persist;

    /**
     * @var int The error code of the last error on the socket.
     */
    protected $error_no;

    /**
     * @var string The error message of the last error on the socket.
     */
    protected $error_str;

    /**
     * Creates a new connection with the specified options.
     * @param string $host Hostname (IP or domain) of the RouterOS server.
     * @param int $port The port on which the RouterOS server provides the API
     * service.
     * @param bool $persist Whether or not the connection should be a persistent
     * one.
     * @param float $timeout The timeout for the connection.
     * @param string $key a string that uniquely identifies the connection.
     * @param resource $context A context for the socket.
     * @see isSocketFresh()
     * @see sendWord()
     */
    public function __construct($host, $port = 8728, $persist = false,
                                $timeout = null, $key = '', $context = null
    )
    {
        $this->persist = $persist;
        $flags = STREAM_CLIENT_CONNECT;
        if ($persist) {
            $flags |= STREAM_CLIENT_PERSISTENT;
        }

        $timeout =
            null == $timeout ? ini_get('default_socket_timeout') : $timeout;

        $key = rawurlencode($key);

        if (null === $context) {
            $context = stream_context_get_default();
        } elseif (
            (!is_resource($context))
            || ('stream-context' !== get_resource_type($context))
        ) {
            throw new SocketException('Invalid context supplied.', 1);
        }

        $this->socket = @stream_socket_client(
                "tcp://{$host}:{$port}/{$key}", $this->error_no,
                $this->error_str, $timeout, $flags, $context
        );
        if (!$this->isSocketValid()) {
            throw new SocketException('Failed to initialize socket.', 2,
                null, $this->error_no, $this->error_str
            );
        }
    }

    /**
     * Checks whether the socket is fresh.
     * 
     * Checks whether the current socket is fresh. A socket is considered fresh
     * if there hasn't been any activity on it. Particularly useful for
     * detecting reused persistent connections.
     * @return bool TRUE if the socket is fresh, FALSE otherwise.
     */
    public function isSocketFresh()
    {
        return ftell($this->socket) === 0;
    }

    /**
     * Checks if a given variable is a stream resource.
     * @param mixed $var The variable to check.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function isStream($var)
    {
        return is_resource($var)
            && preg_match('#\s?stream$#sm', get_resource_type($var));
    }

    /**
     * Checks whether the socket is still valid.
     * 
     * @return bool TRUE on success, FALSE on failure.
     * @see isStream()
     */
    public function isSocketValid()
    {
        if (self::isStream($this->socket) && !feof($this->socket)) {
            $meta = stream_get_meta_data($this->socket);
            return preg_match('#^tcp_socket/?#sm', $meta['stream_type'])
                && !$meta['timed_out'] && !$meta['eof'];
        }
        return false;
    }

    /**
     * Sends a word.
     * 
     * Sends a word and automatically encodes its length when doing so.
     * @param string $word The word to send.
     * @return int The number of bytes sent.
     * @see sendWordFromStream()
     * @see getNextWord()
     */
    public function sendWord($word)
    {
        $length = strlen($word);
        self::verifyLengthSupport($length);
        return $this->send(self::encodeLength($length) . $word);
    }

    /**
     * Sends a word based on a stream.
     * 
     * Sends a word based on a stream and automatically encodes its length when
     * doing so. The stream is read from its current position to its end, and
     * then returned to its current position. Because of those operations, the
     * supplied stream must be seekable.
     * @param string $prefix A string to prepend before the stream contents.
     * @param resource $stream The stream to send.
     * @return int The number of bytes sent.
     * @see sendWord()
     */
    public function sendWordFromStream($prefix, $stream)
    {
        flock($stream, LOCK_SH);

        $streamPosition = (double) sprintf('%u', ftell($stream));
        fseek($stream, 0, SEEK_END);
        $streamLength = ((double) sprintf('%u', ftell($stream)))
            - $streamPosition;
        fseek($stream, $streamPosition, SEEK_SET);
        $totalLength = strlen($prefix) + $streamLength;
        self::verifyLengthSupport($totalLength);

        $bytes = $this->send(self::encodeLength($totalLength) . $prefix);
        $bytes += $this->sendStream($stream);
        flock($stream, LOCK_UN);
        return $bytes;
    }

    /**
     * Verifies that the length is supported.
     * 
     * Verifies if the specified length is supported by the API. Throws a
     * {@link NotSupportedException} if that's not the case. Currently, RouterOS
     * supports words up to 0xFFFFFFF in length, so that's the only check
     * performed.
     * @param int $length The length to verify.
     */
    protected static function verifyLengthSupport($length)
    {
        if ($length > 0xFFFFFFF) {
            throw new NotSupportedException(
                'Words with length above 0xFFFFFFF are not supported.', 4
            );
        }
    }

    /**
     * Encodes the length as requred by the RouterOS API.
     * @param int $length The length to encode
     * @return string The encoded length
     */
    public static function encodeLength($length)
    {
        if ($length < 0) {
            throw new NotSupportedException(
                'Length must not be negative.', 5, null, $length
            );
        } elseif ($length < 0x80) {
            return chr($length);
        } elseif ($length < 0x4000) {
            return pack('n', $length |= 0x8000);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            return pack('n', $length >> 8) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            return pack('N', $length |= 0xE0000000);
        } elseif ($length <= 0xFFFFFFFF) {
            return chr(0xF0) . pack('N', $length);
        } elseif ($length <= 0x7FFFFFFFF) {
            $length = 'f' . base_convert($length, 10, 16);
            return chr(hexdec(substr($length, 0, 2))) .
                pack('N', hexdec(substr($length, 2)));
        }
        throw new NotSupportedException(
            'Length must not be above 0x7FFFFFFFF.', 6, null, $length
        );
    }

    /**
     * Sends a string over the socket.
     * 
     * Sends a string over the socket. Length encoding is not performed. The
     * main purpose of using this function over a plain fwrite() call is that
     * this one ensures all bytes are sent unless the socket becomes invalid.
     * @param string $string The string/stream to send.
     * @return int The number of bytes sent.
     */
    protected function send($string)
    {
        $bytes = 0;
        $bytesToSend = (double) sprintf('%u', strlen($string));
        while ($bytes < $bytesToSend) {
            if ($this->isSocketValid()
                && 0 !== ($bytesNow = fwrite($this->socket,
                                             substr($string, $bytes, 0xFFFFF)
                )
                )
            ) {
                $bytes += $bytesNow;
            } else {
                throw new SocketException('Failed while sending string.', 7,
                    $this->error_no, $this->error_str
                );
            }
        }
        return $bytes;
    }

    /**
     * Sends a string or stream over the socket.
     * 
     * Sends a stream over the socket. Length encoding and locking is not
     * performed. Stream is sent from its current position to its end and the
     * pointer is returned to its initial position. The main purpose of using
     * this function over a plain fwrite() call is that this one ensures all
     * bytes are sent unless the socket becomes invalid.
     * @param resource $stream The stream to send.
     * @return int The number of bytes sent.
     */
    protected function sendStream($stream)
    {
        $bytes = 0;
        while (!feof($stream)) {
            if ($this->isSocketValid()
                && 0 !== ($bytesNow =
                stream_copy_to_stream($stream, $this->socket, 0xFFFFF)
                )
            ) {
                $bytes += $bytesNow;
            } else {
                throw new SocketException('Failed while sending stream.', 8,
                    $this->error_no, $this->error_str
                );
            }
        }
        fseek($stream, -$bytes, SEEK_CUR);
        return $bytes;
    }

    /**
     * Get the next word in queue as a string.
     * 
     * Get the next word in queue as a string, after automatically decoding its
     * length.
     * @return string The word.
     * @see close()
     */
    public function getNextWord()
    {
        return $this->receive($this->decodeLength(), 'word');
    }

    /**
     * Get the next word in queue as a stream.
     * 
     * Get the next word in queue as a stream, after automatically decoding its
     * length.
     * @return resource The word, as a stream.
     * @see close()
     */
    public function getNextWordAsStream()
    {
        return $this->receiveAsStream($this->decodeLength(), 'stream word');
    }

    /**
     * Decodes the lenght of the incoming message.
     * 
     * Decodes the lenght of the incoming message, as specified by the RouterOS
     * API.
     * @return int The decoded length
     */
    protected function decodeLength()
    {
        $byte = ord($this->receive(1, 'initial length byte'));
        if ($byte & 0x80) {
            if (($byte & 0xC0) === 0x80) {
                return (($byte & 63) << 8 ) + ord($this->receive(1));
            } elseif (($byte & 0xE0) === 0xC0) {
                $u = unpack('n~', $this->receive(2));
                return (($byte & 31) << 16 ) + $u['~'];
            } elseif (($byte & 0xF0) === 0xE0) {
                $u = unpack('n~/C~~', $this->receive(3));
                return (($byte & 15) << 24 ) + ($u['~'] << 8) + $u['~~'];
            } elseif (($byte & 0xF8) === 0xF0) {
                $u = unpack('N~', $this->receive(4));
                return (($byte & 7) * 0x100000000/* '<< 32' or '2^32' */)
                    + (double) sprintf('%u', $u['~']);
            }
            throw new NotSupportedException(
                'Unknown control byte encountered.', 9, null, $byte
            );
        } else {
            return $byte;
        }
    }

    /**
     * Reads from the socket to receive.
     * 
     * Reads from the socket to receive content. Doesn't perform decoding. The
     * main purpose of using this function over a plain fread() call is to
     * ensure that all of the required length will be read unless the socket
     * becomes invalid.
     * @param int $length The number of bytes to read.
     * @param string $what Descriptive string about what is being received (used
     * in exception messages).
     * @return string The received content.
     */
    protected function receive($length, $what = 'data')
    {
        $result = '';
        while ($length > 0) {
            if ($this->isSocketValid() &&
                '' !== ($fragment = fread($this->socket, min($length, 0xFFFFF))
                )
            ) {
                $length -= strlen($fragment);
                $result .= $fragment;
            } else {
                throw new SocketException("Failed while receiving {$what}",
                    10, null, $this->error_no, $this->error_str
                );
            }
        }
        return $result;
    }

    /**
     * Reads from the socket to receive.
     * 
     * Reads from the socket to receive content. Doesn't perform decoding. The
     * main purpose of using this function over a plain fread() call is to
     * ensure that all of the required length will be read unless the socket
     * becomes invalid.
     * @param int $length The number of bytes to read.
     * @param string $what Descriptive string about what is being received (used
     * in exception messages).
     * @return string The received content.
     */
    protected function receiveAsStream($length, $what = 'stream data')
    {
        $result = fopen('php://temp', 'r+b');
        while ($length > 0) {
            if ($this->isSocketValid() &&
                '' !== ($fragment = fread($this->socket, min($length, 0xFFFFF))
                )
            ) {
                $length -= strlen($fragment);
                fwrite($result, $fragment);
            } else {
                throw new SocketException("Failed while receiving {$what}",
                    11, null, $this->error_no, $this->error_str
                );
            }
        }
        rewind($result);
        return $result;
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
     * Closes the opened connection, even if it is a persistent one.
     * @return bool TRUE on success, FALSE on failure.
     */
    public function close()
    {
        return $this->isSocketValid() && fclose($this->socket);
    }

}