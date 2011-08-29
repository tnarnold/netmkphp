<?php

namespace Net\RouterOS;

class RequestHandlingTest extends \PHPUnit_Framework_TestCase
{

    public function testNonAbsoluteCommand()
    {
        $nonAbsoluteCommands = array(
            'print',
            '',
            'ip arp print',
            'login'
        );
        foreach ($nonAbsoluteCommands as $command) {
            try {
                $invalidCommand = new Request($command);
            } catch (ArgumentException $e) {
                $this->assertEquals(
                    202, $e->getCode(),
                    "Improper exception thrown for the command '{$command}'."
                );
            }
        }
    }

    public function testUnresolvableCommand()
    {
        $unresolvableCommands = array(
            '/ip .. ..',
            '/ip .. arp .. arp .. .. print'
        );
        foreach ($unresolvableCommands as $command) {
            try {
                $invalidCommand = new Request($command);
            } catch (ArgumentException $e) {
                $this->assertEquals(
                    203, $e->getCode(),
                    "Improper exception thrown for the command '{$command}'."
                );
            }
        }
    }

    public function testInvalidCommand()
    {
        $invalidCommands = array(
            '/ip/arp/ print',
            '/ip /arp /print',
            '/ip /arp /print',
        );
        foreach ($invalidCommands as $command) {
            try {
                $invalidCommand = new Request($command);
            } catch (ArgumentException $e) {
                $this->assertEquals(
                    204, $e->getCode(),
                    "Improper exception thrown for the command '{$command}'."
                );
            }
        }
    }

    public function testCommandTranslation()
    {
        $commands = array(
            '/ip arp print' => '/ip/arp/print',
            '/ip arp .. address print' => '/ip/address/print',
            '/queue simple .. tree .. simple print' => '/queue/simple/print',
            '/login goback ..' => '/login'
        );
        $request = new Request('/cancel');
        foreach ($commands as $command => $expected) {
            $request->setCommand($command);
            $this->assertEquals(
                $expected, $request->getCommand(),
                "Command '{$command}' was not translated properly."
            );
        }
    }

    public function testInvalidArgumentName()
    {
        $invalidNames = array(
            '=',
            '',
            '=eqStart',
            'eq=middle',
            'eqEnd=',
            'name spaced',
            'name with multiple spaces',
            "Two\nLines"
        );
        foreach ($invalidNames as $name) {
            try {
                $request = new Request('/ping');
                $request->setArgument($name);
            } catch (ArgumentException $e) {
                $this->assertEquals(
                    200, $e->getCode(),
                    "Improper exception thrown for the name '{$name}'."
                );
            }
        }
    }

    public function testInvalidArgumentValue()
    {
        $invalidValues = array(
            fopen('php://input', 'r')
        );
        foreach ($invalidValues as $value) {
            try {
                $request = new Request('/ping');
                $request->setArgument('address', $value);
            } catch (ArgumentException $e) {
                $this->assertEquals(
                    201, $e->getCode(),
                    "Improper exception thrown for the value '{$value}'."
                );
            }
        }
    }

    public function testInvalidQueryArgumentName()
    {
        $invalidNames = array(
            '=',
            '',
            '=eqStart',
            'eq=middle',
            'eqEnd=',
            'name spaced',
            'name with multiple spaces',
            "Two\nLines"
        );
        foreach ($invalidNames as $name) {
            try {
                $query = Query::where($name);
            } catch (ArgumentException $e) {
                $this->assertEquals(
                    200, $e->getCode(),
                    "Improper exception thrown for the name '{$name}'."
                );
            }
        }
    }

    public function testInvalidQueryArgumentAction()
    {
        $invalidActions = array(
            ' ',
            '?',
            '#',
            'address',
            '>=',
            '<=',
            '=>',
            '=<',
            1,
            0
        );
        foreach ($invalidActions as $action) {
            try {
                $query = Query::where('address', null, $action);
            } catch (ArgumentException $e) {
                $this->assertEquals(
                    208, $e->getCode(),
                    "Improper exception thrown for the action '{$action}'."
                );
            }
        }
    }

    public function testInvalidQueryArgumentValue()
    {
        $invalidValues = array(
            fopen('php://input', 'r')
        );
        foreach ($invalidValues as $value) {
            try {
                $query = Query::where('address', $value);
            } catch (ArgumentException $e) {
                $this->assertEquals(
                    201, $e->getCode(),
                    "Improper exception thrown for the value '{$value}'."
                );
            }
        }
    }

    public function testLengthEncoding()
    {
        $lengths = array(
            chr(0) => 0,
            chr(0x1) => 0x1,
            chr(0x7E) => 0x7E,
            chr(0x7F) => 0x7F,
            chr(0x80) . chr(0x80) => 0x80,
            chr(0x80) . chr(0x81) => 0x81,
            chr(0xBF) . chr(0xFE) => 0x3FFE,
            chr(0xBF) . chr(0xFF) => 0x3FFF,
            chr(0xC0) . chr(0x40) . chr(0x00) => 0x4000,
            chr(0xC0) . chr(0x40) . chr(0x01) => 0x4001,
            chr(0xDF) . chr(0xFF) . chr(0xFE) => 0x1FFFFE,
            chr(0xDF) . chr(0xFF) . chr(0xFF) => 0x1FFFFF,
            chr(0xE0) . chr(0x20) . chr(0x00) . chr(0x00) => 0x200000,
            chr(0xE0) . chr(0x20) . chr(0x00) . chr(0x01) => 0x200001,
            chr(0xEF) . chr(0xFF) . chr(0xFF) . chr(0xFE) => 0xFFFFFFE,
            chr(0xEF) . chr(0xFF) . chr(0xFF) . chr(0xFF) => 0xFFFFFFF,
            chr(0xF0) . chr(0x10) . chr(0x00) . chr(0x00) . chr(0x00) =>
            0x10000000,
            chr(0xF0) . chr(0x10) . chr(0x00) . chr(0x00) . chr(0x01) =>
            0x10000001,
            chr(0xF0) . chr(0xFF) . chr(0xFF) . chr(0xFF) . chr(0xFE) =>
            0xFFFFFFFE,
            chr(0xF0) . chr(0xFF) . chr(0xFF) . chr(0xFF) . chr(0xFF) =>
            0xFFFFFFFF,
            chr(0xF1) . chr(0x00) . chr(0x00) . chr(0x00) . chr(0x00) =>
            0x100000000,
            chr(0xF1) . chr(0x00) . chr(0x00) . chr(0x00) . chr(0x01) =>
            0x100000001,
            chr(0xF7) . chr(0xFF) . chr(0xFF) . chr(0xFF) . chr(0xFE)
            => 0x7FFFFFFFE,
            chr(0xF7) . chr(0xFF) . chr(0xFF) . chr(0xFF) . chr(0xFF)
            => 0x7FFFFFFFF
        );
        foreach ($lengths as $expected => $length) {
            $actual = Communicator::encodeLength($length);
            $this->assertEquals(
                $expected, $actual,
                "Length '0x" . dechex($length) .
                "' is not encoded correctly. It was encoded as '0x" .
                bin2hex($actual) . "' instead of '0x" .
                bin2hex($expected) . "'."
            );
        }
    }

    public function testLengthEncodingExceptions()
    {
        $smallLength = -1;
        try {
            Communicator::encodeLength($smallLength);
        } catch (NotSupportedException $e) {
            $this->assertEquals(5, $e->getCode(),
                                "Length '{$smallLength}' must not be encodable."
            );
            $this->assertEquals($smallLength, $e->getValue(),
                                'Exception is misleading.'
            );
        }
        $largeLength = 0x800000000;
        try {
            Communicator::encodeLength($largeLength);
        } catch (NotSupportedException $e) {
            $this->assertEquals(6, $e->getCode(),
                                "Length '{$largeLength}' must not be encodable."
            );
            $this->assertEquals($largeLength, $e->getValue(),
                                'Exception is misleading.'
            );
        }
    }

    public function testLengthDecoding()
    {
        try {
            $com = new Communicator('127.0.0.1', PSEUDO_SERVER_PORT);
        } catch (\Exception $e) {
            $this->markTestSkipped('The testing server is not running.');
        }


        $lengths = array(
            0x1,
            0x7E,
            0x7F,
            0x80,
            0x81,
            0x3FFE,
            0x3FFF,
            0x4000,
            0x4001,
            0x1FFFFE,
            0x1FFFFF,
            0x200000,
            0x200001,
            0xFFFFFFE,
            0xFFFFFFF,
            0x10000000,
//            0x10000001,
//            0xFFFFFFFE,
//            0xFFFFFFFF,
//            0x100000000,
//            0x100000001,
//            0x7FFFFFFFE,
//            0x7FFFFFFFF
        );

        foreach ($lengths as $length) {

            $com->sendWord('r' .
                str_pad(base_convert($length, 10, 16), 9, '0', STR_PAD_LEFT)
            );
            $response = $com->getNextWordAsStream();

            $responseSize = 0;
            while (!feof($response)) {
                $responseSize += strlen(fread($response, 0xFFFFF));
            }
            $this->assertEquals(
                $length, $responseSize, 'Content mismatch!'
            );
        }

        $com->sendWord('q000000000');
        $com->close();
    }

    public function testControlByteException()
    {
        try {
            $com = new Communicator('127.0.0.1', PSEUDO_SERVER_PORT);
        } catch (\Exception $e) {
            $this->markTestSkipped('The testing server is not running.');
        }


        $controlBytes = array(
            0xF8,
            0xF9,
            0xFA,
            0xFB,
            0xFC,
            0xFD,
            0xFE,
            0xFF
        );

        foreach ($controlBytes as $controlByte) {
            $com->sendWord(
                'c'
                . str_pad(
                    base_convert($controlByte, 10, 16), 9, '0', STR_PAD_RIGHT
                )
            );
            try {
                $response = $com->getNextWordAsStream();
            } catch (NotSupportedException $e) {
                $this->assertEquals(
                    9, $e->getCode(), 'Improper exception code.'
                );
                $this->assertEquals($controlByte, $e->getValue(),
                                    'Improper exception value.'
                );
            }
        }

        $com->sendWord('q000000000');
        $com->close();
    }

    public function testSendingLargeWords()
    {
        try {
            $com = new Communicator('127.0.0.1', PSEUDO_SERVER_PORT);
        } catch (\Exception $e) {
            $this->markTestSkipped('The testing server is not running.');
        }

        $lengths = array(
            0x1,
            0x7E,
            0x7F,
            0x80,
            0x81,
            0x3FFE,
            0x3FFF,
            0x4000,
            0x4001,
            0x1FFFFE,
            0x1FFFFF,
            0x200000,
            0x200001,
            0xFFFFFFE,
            0xFFFFFFF,
//            0x10000000,
//            0x10000001,
//            0xFFFFFFFE,
//            0xFFFFFFFF,
//            0x100000000,
//            0x100000001,
//            0x7FFFFFFFE,
//            0x7FFFFFFFF
        );

        foreach ($lengths as $length) {

            $com->sendWord('s' .
                str_pad(base_convert($length, 10, 16), 9, '0', STR_PAD_LEFT)
            );

            $stream = fopen('php://temp', 'r+b');
            $streamSize = 0;
            while ($streamSize < $length) {
                $streamSize += fwrite($stream,
                                      str_pad('t',
                                              min($length - $streamSize, 0xFFFFF),
                                                  't'));
            }
            rewind($stream);

            $com->sendWordFromStream('', $stream);

            $receivedLength = (double) base_convert($com->getNextWord(), 16, 10);
            $this->assertEquals(
                (double) $length, $receivedLength, 'Content mismatch!'
            );
        }

        $com->sendWord('q000000000');
        $com->close();
    }

    public function testIncompleteResponse()
    {
        try {
            $com = new Communicator('127.0.0.1', PSEUDO_SERVER_PORT);
        } catch (\Exception $e) {
            $this->markTestSkipped('The testing server is not running.');
        }

        $com->sendWord('i00000000f');
        try {
            $response = $com->getNextWordAsStream();
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(11, $e->getCode(), 'Improper exception code.');
        }
    }

//    public function testPrematureDisconnect()
//    {
//        try {
//            $com = new Communicator('127.0.0.1', PSEUDO_SERVER_PORT);
//        } catch (\Exception $e) {
//            $this->markTestSkipped('The testing server is not running.');
//        }
//
//        $com->sendWord('p00000000f');
//        try {
//            $com->sendWord('ttttttttttttttt');
//            $this->fail('Sending had to fail.');
//        } catch (SocketException $e) {
//            $this->assertEquals(7, $e->getCode(), 'Improper exception code.');
//        }
//    }
//
//    public function testPrematureDisconnectWithStream()
//    {
//        try {
//            $com = new Communicator('127.0.0.1', PSEUDO_SERVER_PORT);
//        } catch (\Exception $e) {
//            $this->markTestSkipped('The testing server is not running.');
//        }
//
//        $com->sendWord('p00000000f');
//        try {
//            $stream = fopen('php://temp', 'r+b');
//            fwrite($stream, 'ttttttttttttttt');
//            rewind($stream);
//            $com->sendWordFromStream('', $stream);
//            $this->fail('Sending had to fail.');
//        } catch (SocketException $e) {
//            $this->assertEquals(8, $e->getCode(), 'Improper exception code.');
//        }
//    }

    public function testInvalidSocketOnClose()
    {
        try {
            $com = new Communicator(HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            $com->close();
            new Response($com);
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(206, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidSocketOnReceive()
    {
        try {
            $com = new Communicator(HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            new Response($com);
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(10, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidSocketOnStreamReceive()
    {
        try {
            $com = new Communicator(HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            new Response($com, true);
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(10, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testQuitMessage()
    {
        $com = new Communicator(HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);

        $quitRequest = new Request('/quit');
        $quitRequest->send($com);
        $quitResponse = new Response($com);
        $this->assertEquals(1, count($quitResponse->getUnrecognizedWords()),
                                     'No message.'
        );
        $this->assertEquals(0, count($quitResponse->getAllArguments()),
                                     'There should be no arguments.'
        );
        $com->close();
    }

    public function testQuitMessageStream()
    {
        $com = new Communicator(HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);

        $quitRequest = new Request('/quit');
        $quitRequest->send($com);
        $quitResponse = new Response($com, true);
        $this->assertEquals(1, count($quitResponse->getUnrecognizedWords()),
                                     'No message.'
        );
        $this->assertEquals(0, count($quitResponse->getAllArguments()),
                                     'There should be no arguments.'
        );
        $com->close();
    }

    public function testInvalidQuerySending()
    {
        $com = new Communicator(HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);

        $com->sendWord('/ip/arp/print');
        $com->close();
        try {
            Query::where('address', HOSTNAME_INVALID)->send($com);
            $com->sendWord('');
            $this->fail('The query had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(209, $e->getCode(), 'Improper exception code.');
        }
    }

}