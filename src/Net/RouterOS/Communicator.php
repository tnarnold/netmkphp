<?php

/**
 * ~~summary~~
 * 
 * ~~description~~
 * 
 * PHP version 5
 * 
 * @category  Net
 * @package   PEAR2_Net_RouterOS
 * @author    Vasil Rangelov <boen.robot@gmail.com>
 * @copyright 2011 Vasil Rangelov
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @version   SVN: $WCREV$
 * @link      http://netrouteros.sourceforge.net/
 */
/**
 * The namespace declaration.
 */
namespace PEAR2\Net\RouterOS;

/**
 * A RouterOS communicator.
 * 
 * Implementation of the RouterOS API protocol. Unlike the other classes in this
 * package, this class doesn't provide any conviniences beyond the low level
 * implementation details (automatic word length encoding/decoding and data
 * integrity), and because of that, its direct usage is strongly discouraged.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://netrouteros.sourceforge.net/
 * @see      Client
 */
class Communicator
{

    /**
     * @var SocketClientTransmitter The transmitter for the connection.
     */
    protected $trans;

    /**
     * Creates a new connection with the specified options.
     * 
     * @param string   $host    Hostname (IP or domain) of the RouterOS server.
     * @param int      $port    The port on which the RouterOS server provides
     * the API service.
     * @param bool     $persist Whether or not the connection should be a
     * persistent one.
     * @param float    $timeout The timeout for the connection.
     * @param string   $key     A string that uniquely identifies the
     * connection.
     * @param resource $context A context for the socket.
     * 
     * @see sendWord()
     */
    public function __construct($host, $port = 8728, $persist = false,
        $timeout = null, $key = '', $context = null
    ) {
        $this->trans = new SocketClientTransmitter(
            $host, $port, $persist, $timeout, $key, $context
        );
    }

    /**
     * Gets the transmitter for this connection.
     * 
     * @return SocketClientTransmitter The transmitter for this connection.
     */
    public function getTransmitter()
    {
        return $this->trans;
    }

    /**
     * Sends a word.
     * 
     * Sends a word and automatically encodes its length when doing so.
     * 
     * @param string $word The word to send.
     * 
     * @return int The number of bytes sent.
     * @see sendWordFromStream()
     * @see getNextWord()
     */
    public function sendWord($word)
    {
        $length = strlen($word);
        self::verifyLengthSupport($length);
        return $this->trans->send(self::encodeLength($length) . $word);
    }

    /**
     * Sends a word based on a stream.
     * 
     * Sends a word based on a stream and automatically encodes its length when
     * doing so. The stream is read from its current position to its end, and
     * then returned to its current position. Because of those operations, the
     * supplied stream must be seekable.
     * 
     * @param string   $prefix A string to prepend before the stream contents.
     * @param resource $stream The stream to send.
     * 
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

        $bytes = $this->trans->send(self::encodeLength($totalLength) . $prefix);
        $bytes += $this->trans->sendStream($stream);
        flock($stream, LOCK_UN);
        return $bytes;
    }

    /**
     * Verifies that the length is supported.
     * 
     * Verifies if the specified length is supported by the API. Throws a
     * {@link LengthException} if that's not the case. Currently, RouterOS
     * supports words up to 0xFFFFFFF in length, so that's the only check
     * performed.
     * 
     * @param int $length The length to verify.
     * 
     * @return null
     */
    protected static function verifyLengthSupport($length)
    {
        if ($length > 0xFFFFFFF) {
            throw new LengthException(
                'Words with length above 0xFFFFFFF are not supported.', 10,
                null, $length
            );
        }
    }

    /**
     * Encodes the length as requred by the RouterOS API.
     * 
     * @param int $length The length to encode
     * 
     * @return string The encoded length
     */
    public static function encodeLength($length)
    {
        if ($length < 0) {
            throw new LengthException(
                'Length must not be negative.', 11, null, $length
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
        throw new LengthException(
            'Length must not be above 0x7FFFFFFFF.', 12, null, $length
        );
    }

    /**
     * Get the next word in queue as a string.
     * 
     * Get the next word in queue as a string, after automatically decoding its
     * length.
     * 
     * @return string The word.
     * @see close()
     */
    public function getNextWord()
    {
        return $this->trans->receive(self::decodeLength($this->trans), 'word');
    }

    /**
     * Get the next word in queue as a stream.
     * 
     * Get the next word in queue as a stream, after automatically decoding its
     * length.
     * 
     * @return resource The word, as a stream.
     * @see close()
     */
    public function getNextWordAsStream()
    {
        return $this->trans->receiveStream(
            self::decodeLength($this->trans), 'stream word'
        );
    }

    /**
     * Decodes the lenght of the incoming message.
     * 
     * Decodes the lenght of the incoming message, as specified by the RouterOS
     * API.
     * 
     * @param Transmitter $trans The transmitter from which to decode the length
     * of the incoming message.
     * 
     * @return int The decoded length
     */
    public static function decodeLength(Transmitter $trans)
    {
        $byte = ord($trans->receive(1, 'initial length byte'));
        if ($byte & 0x80) {
            if (($byte & 0xC0) === 0x80) {
                return (($byte & 63) << 8 ) + ord($trans->receive(1));
            } elseif (($byte & 0xE0) === 0xC0) {
                $u = unpack('n~', $trans->receive(2));
                return (($byte & 31) << 16 ) + $u['~'];
            } elseif (($byte & 0xF0) === 0xE0) {
                $u = unpack('n~/C~~', $trans->receive(3));
                return (($byte & 15) << 24 ) + ($u['~'] << 8) + $u['~~'];
            } elseif (($byte & 0xF8) === 0xF0) {
                $u = unpack('N~', $trans->receive(4));
                return (($byte & 7) * 0x100000000/* '<< 32' or '2^32' */)
                    + (double) sprintf('%u', $u['~']);
            }
            throw new NotSupportedException(
                'Unknown control byte encountered.', 13, null, $byte
            );
        } else {
            return $byte;
        }
    }

    /**
     * Closes the opened connection, even if it is a persistent one.
     * 
     * @return bool TRUE on success, FALSE on failure.
     */
    public function close()
    {
        return $this->trans->close();
    }

}