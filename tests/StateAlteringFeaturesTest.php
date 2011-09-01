<?php
namespace Net\RouterOS;

class StateAlteringFeaturesTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Client
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new Client(HOSTNAME, USERNAME, PASSWORD, PORT);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        unset($this->object);
    }

    public function testMultipleDifferentPersistentConnection()
    {
        try {

            $routerOS1 = new Client(HOSTNAME, USERNAME2, PASSWORD2, PORT, true);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS1,
                'Object initialization failed.'
            );

            $routerOS2 = new Client(HOSTNAME, USERNAME, PASSWORD, PORT, true);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS2,
                'Object initialization failed.'
            );


            $userRequest = new Request('/queue/simple/add');
            $userRequest->setArgument('name', TEST_QUEUE_NAME);
            $response = $routerOS2->sendSync($userRequest);
            $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                    'Response should be one.'
            );
            if ($response instanceof Response
                && $response->getType() === Response::TYPE_FINAL
            ) {
                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $response = $routerOS2->sendSync($removeRequest);
                $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                        'Response should be one.'
                );
            }

            $routerOS1->close();
            $routerOS2->close();
        } catch (Exception $e) {
            $this->fail('Unable to connect normally.');
        }
    }

    public function testSendSyncReturningResponse()
    {
        $userRequest = new Request('/queue/simple/add');
        $userRequest->setArgument('name', TEST_QUEUE_NAME);
        $response = $this->object->sendSync($userRequest);
        $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                'Response should be one.'
        );
        if ($response instanceof Response
            && $response->getType() === Response::TYPE_FINAL
        ) {
            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $response = $this->object->sendSync($removeRequest);
            $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                    'Response should be one.'
            );
        }
    }

    public function testSendSyncReturningResponseStreamData()
    {

        $comment = fopen('php://temp', 'r+b');
        fwrite($comment, str_pad('t', 0xFFF, 't'));
//        for ($i=0; $i<14;$i++) {
//            fwrite($comment, str_pad('t', 0xFFFFFF, 't'));
//        }
//        fwrite($comment,
//            str_pad('t', 0xFFFFFF + 0xF - strlen('=comment='), 't')
//        );
        rewind($comment);

        $addRequest = new Request('/queue/simple/add');
        $addRequest->setArgument('name', TEST_QUEUE_NAME);
        $addRequest->setArgument('comment', $comment);
        $response = $this->object->sendSync($addRequest);
        $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                'Response should be one.');
        if ($response instanceof Response
            && $response->getType() === Response::TYPE_FINAL
        ) {

            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $response = $this->object->sendSync($removeRequest);
            $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                    'Response should be one.');
        }
    }

    public function testSendSyncReturningResponseLarge_3bytesLength()
    {
        $this->markTestIncomplete(
            'For some reason, my RouterOS v5.6 doesn not work with this (bug?).'
        );
        $systemResource = $this->object->sendSync(
            new Request('/system/resource/print')
        );
        $this->assertEquals(2, count($systemResource));
        $freeMemory = 1024
            * (int) $systemResource[0]->getArgument('free-memory');

        $addCommand = '/queue/simple/add';
        $requiredMemory = 0x4000
            + strlen($addCommand) + 1
            + strlen('=name=') + strlen(TEST_QUEUE_NAME) + 1
            + strlen('=comment=') + 1
            + (8 * 1024 * 1024) /* 8MiB for processing's sake */;
        if ($freeMemory < $requiredMemory) {
            $this->markTestSkipped('Not enough memory on router.');
        } else {
            $comment = fopen('php://temp', 'r+b');
            fwrite(
                $comment, str_pad('t', 0x4000 - strlen('=comment=') + 1, 't')
            );
            rewind($comment);

            $addRequest = new Request($addCommand);
            $addRequest->setArgument('name', TEST_QUEUE_NAME);
            $addRequest->setArgument('comment', $comment);
            $response = $this->object->sendSync($addRequest);
            $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                    'Response should be one.');
            if ($response instanceof Response
                && $response->getType() === Response::TYPE_FINAL
            ) {

                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $response = $this->object->sendSync($removeRequest);
                $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                        'Response should be one.');
            }
        }
    }

    public function testSendSyncReturningResponseLarge_4bytesLength()
    {
        $this->markTestIncomplete(
            'For some reason, my RouterOS v5.6 doesn not work with this (bug?).'
        );
        $systemResource = $this->object->sendSync(
            new Request('/system/resource/print')
        );
        $this->assertEquals(2, count($systemResource));
        $freeMemory = 1024
            * (int) $systemResource[0]->getArgument('free-memory');

        $addCommand = '/queue/simple/add';
        $requiredMemory = 0x200000
            + strlen($addCommand) + 1
            + strlen('=name=') + strlen(TEST_QUEUE_NAME) + 1
            + strlen('=comment=') + 1
            + (8 * 1024 * 1024) /* 8MiB for processing's sake */;
        if ($freeMemory < $requiredMemory) {
            $this->markTestSkipped('Not enough memory on router.');
        } else {
            $comment = fopen('php://temp', 'r+b');
            fwrite($comment,
                   str_pad('t', 0x200000 - strlen('=comment=') + 1, 't')
            );
            rewind($comment);

            $addRequest = new Request($addCommand);
            $addRequest->setArgument('name', TEST_QUEUE_NAME);
            $addRequest->setArgument('comment', $comment);
            $response = $this->object->sendSync($addRequest);
            $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                    'Response should be one.');
            if ($response instanceof Response
                && $response->getType() === Response::TYPE_FINAL
            ) {

                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $response = $this->object->sendSync($removeRequest);
                $this->assertInstanceOf(__NAMESPACE__ . '\Response', $response,
                                        'Response should be one.');
            }
        }
    }

    public function testSendSyncReturningResponseLargeDataException()
    {
        //Required for this test
        $memoryLimit = ini_set('memory_limit', -1);
        try {

            $comment = fopen('php://temp', 'r+b');
            fwrite($comment, str_pad('t', 0xFFFFFF, 't'));
            for ($i = 0; $i < 14; $i++) {
                fwrite($comment, str_pad('t', 0xFFFFFF, 't'));
            }
            fwrite($comment,
                   str_pad('t', 0xFFFFFF + 0xF/* - strlen('=comment=') */, 't')
            );
            rewind($comment);

            $commentString = stream_get_contents($comment);
            $maxArgL = 0xFFFFFFF - strlen('=comment=');
            $this->assertGreaterThan(
                $maxArgL, strlen($commentString), '$comment is not long enough.'
            );
            $addRequest = new Request('/queue/simple/add');
            $addRequest->setArgument('name', TEST_QUEUE_NAME);
            $addRequest->setArgument('comment', $commentString);
            $response = $this->object->sendSync($addRequest);
            if ($response instanceof Response
                && $response->getType() === Response::TYPE_FINAL
            ) {
                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $response = $this->object->sendSync($removeRequest);
            }

            $this->fail('Lengths above 0xFFFFFFF should not be supported.');
        } catch (NotSupportedException $e) {
            $this->assertEquals(10, $e->getCode(), 'Improper exception thrown.');
        }

        //Clearing out for other tests.
        ini_set('memory_limit', $memoryLimit);
    }

}