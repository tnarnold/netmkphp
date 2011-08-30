<?php
namespace Net\RouterOS;

class ClientFeaturesTest extends \PHPUnit_Framework_TestCase
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

    public function testSendSyncReturningArray()
    {
        $list = $this->object->sendSync(new Request('/ip/arp/print'));
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertInternalType('string', $list[0]->getArgument('address'),
                                                                  'The address is not a string'
        );
    }

    public function testSendSyncReturningArrayWithStreams()
    {
        $this->assertFalse($this->object->getStreamResponses());
        $this->assertFalse($this->object->setStreamResponses(true));
        $this->assertTrue($this->object->getStreamResponses());
        $list = $this->object->sendSync(new Request('/ip/arp/print'));
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertInternalType('resource', $list[0]->getArgument('address'),
                                                                    'The address is not a stream'
        );
    }

    public function testSendAsyncTagRequirement()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID);
        try {
            $this->object->sendAsync($ping);

            $this->fail('The call had to fail.');
        } catch (DataFlowException $e) {
            $this->assertEquals(102, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testSendAsyncUniqueTagRequirement()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID);
        $ping->setTag('ping');
        $ping2 = new Request('/ping');
        $ping2->setArgument('address', HOSTNAME_INVALID);
        $ping2->setTag('ping');
        $this->object->sendAsync($ping);
        try {
            $this->object->sendAsync($ping2);

            $this->fail('The call had to fail.');
        } catch (DataFlowException $e) {
            $this->assertEquals(103, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testSendAsyncWithCallbackAndTempLoop()
    {
        $ping = new Request('/ping');
        $ping->setTag('ping');
        $ping->setArgument('address', HOSTNAME);
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function($response, $client) use (&$repliesCount) {
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Response', $response,
                    'A callback must receive a single response per call'
                );
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Client', $client,
                    'A callback must receive a copy of the client object'
                );

                \PHPUnit_Framework_TestCase::assertEquals(
                    'ping', $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
            }
        );

        $this->object->loop(2);
        $this->assertGreaterThan(
            0, $repliesCount,
            "No responses for '" . HOSTNAME . "' in 2 seconds."
        );
    }

    public function testSendAsyncAndFullCancel()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID);
        $ping->setTag('ping1');
        $ping2 = new Request('/ping');
        $ping2->setArgument('address', HOSTNAME_INVALID);
        $ping2->setTag('ping2');
        $this->object->sendAsync($ping);
        $this->object->sendAsync($ping2);
        $this->object->loop(2);
        $this->object->cancelRequest();
        try {
            $this->object->extractNewResponses('ping1');
            $this->fail('The call had to fail.');
        } catch (DataFlowException $e) {
            $this->assertEquals(104, $e->getCode(), 'Improper exception code.');
        }
        try {
            $this->object->extractNewResponses('ping2');
            $this->fail('The call had to fail.');
        } catch (DataFlowException $e) {
            $this->assertEquals(104, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidCancel()
    {
        $this->assertEquals(0, $this->object->getPendingRequestsCount(),
                            'There should be no active requests.'
        );
        try {
            $this->object->cancelRequest('ping1');
        } catch (DataFlowException $e) {
            $this->assertEquals(105, $e->getCode(), 'Improper exception code.');
        }
        $this->assertEquals(0, $this->object->getPendingRequestsCount(),
                            'There should be no active requests.'
        );
    }

    public function testSendAsyncAndInvalidCancel()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID);
        $ping->setTag('ping1');
        $ping2 = new Request('/ping');
        $ping2->setArgument('address', HOSTNAME_INVALID);
        $ping2->setTag('ping2');
        $this->object->sendAsync($ping);
        $this->object->sendAsync($ping2);
        $this->assertEquals(2, $this->object->getPendingRequestsCount(),
                            'Improper active request count before cancel test.'
        );
        $this->object->loop(2);
        try {
            $this->object->cancelRequest('ping3');
        } catch (DataFlowException $e) {
            $this->assertEquals(105, $e->getCode(), 'Improper exception code.');
        }
        $this->assertEquals(2, $this->object->getPendingRequestsCount(),
                            'Improper active request count after cancel test.'
        );
    }

    public function testSendAsyncAndFullExtract()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID);
        $ping->setTag('ping1');
        $ping2 = new Request('/ping');
        $ping2->setArgument('address', HOSTNAME_INVALID);
        $ping2->setTag('ping2');
        $this->object->sendAsync($ping);
        $this->object->sendAsync($ping2);
        $this->assertEquals(2, $this->object->getPendingRequestsCount(),
                            'Improper active request count before cancel test.'
        );
        $this->object->loop(2);
        $responses = $this->object->extractNewResponses();

        $this->assertEquals(2, $this->object->getPendingRequestsCount(),
                            'Improper active request count after cancel test.'
        );

        $hasPing1 = false;
        $hasPing2 = false;
        foreach ($responses as $response) {
            if (!$hasPing1 && $response->getTag() === 'ping1') {
                $hasPing1 = true;
            }
            if (!$hasPing2 && $response->getTag() === 'ping2') {
                $hasPing2 = true;
            }
        }
        $this->assertTrue($hasPing1, "No responses for 'ping1' in 2 seconds.");
        $this->assertTrue($hasPing2, "No responses for 'ping2' in 2 seconds.");
    }

    public function testSendAsyncWithCallbackAndCancel()
    {
        $ping = new Request('/ping');
        $ping->setTag('ping');
        $ping->setArgument('address', HOSTNAME);
        $finalRepliesCount = -1;
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function($response, $client) use (&$repliesCount) {
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Response', $response,
                    'A callback must receive a single response per call'
                );
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Client', $client,
                    'A callback must receive a copy of the client object'
                );

                \PHPUnit_Framework_TestCase::assertEquals(
                    'ping', $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
            }
        );

        $this->object->loop(2);
        $bufferedReplies = count($this->object->extractNewResponses('ping'));
        $this->assertEquals(
            0, $bufferedReplies,
            'Responses for requests with callbacks must not be buffered.'
        );
        $finalRepliesCount = $repliesCount;
        $this->object->cancelRequest('ping');
        $this->object->loop(2);
        $this->assertGreaterThan(
            0, $repliesCount,
            "No responses for '" . HOSTNAME . "' in 2 seconds."
        );
        $this->assertEquals($finalRepliesCount + 1/* The !trap */,
                            $repliesCount,
                            "Extra callbacks were executed during second loop."
        );
    }

    public function testSendAsyncWithCallbackAndCancelWithin()
    {
        $limit = 5;
        $ping = new Request('/ping');
        $ping->setTag('ping');
        $ping->setArgument('address', HOSTNAME);
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function($response, $client) use (&$repliesCount, $limit) {
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Response', $response,
                    'A callback must receive a single response per call'
                );
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Client', $client,
                    'A callback must receive a copy of the client object'
                );

                \PHPUnit_Framework_TestCase::assertEquals(
                    'ping', $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
                return $repliesCount === $limit;
            }
        );

        $this->object->loop();
        $this->assertEquals($limit + 1/* The !trap */, $repliesCount,
                            "Extra callbacks were executed during second loop."
        );
    }

    public function testSendAsyncWithCallbackAndFullLoop()
    {
        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $repliesCount = 0;
        $arpCallback = function($response, $client) use (&$repliesCount) {
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Response', $response,
                    'A callback must receive a single response per call'
                );
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Client', $client,
                    'A callback must receive a copy of the client object'
                );

                \PHPUnit_Framework_TestCase::assertEquals(
                    'arp', $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
            };
        $this->object->sendAsync($arpPrint, $arpCallback);

        $this->object->loop();

        $this->assertGreaterThan(0, $repliesCount, "No callbacks.");
        $repliesCount = 0;

        $this->object->sendAsync($arpPrint, $arpCallback);

        $this->object->loop();

        $this->assertGreaterThan(0, $repliesCount, "No callbacks.");
    }

    public function testSendAsyncAndCompleteRequest()
    {
        $ping = new Request('/ping');
        $ping->setTag('ping');
        $ping->setArgument('address', HOSTNAME);
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function($response, $client) use (&$repliesCount) {
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Response', $response,
                    'A callback must receive a single response per call'
                );
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Client', $client,
                    'A callback must receive a copy of the client object'
                );

                \PHPUnit_Framework_TestCase::assertEquals(
                    'ping', $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
            }
        );


        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $this->object->sendAsync($arpPrint);

        $replies = $this->object->completeRequest('arp');

        $this->assertInternalType(
            'array', $replies, 'ARP list must be an array'
        );

        $this->assertGreaterThan(
            0, $repliesCount,
            "No responses for '" . HOSTNAME . "' before of 'arp' is done."
        );
    }

    public function testSendAsyncAndCompleteRequestWithStream()
    {
        $ping = new Request('/ping');
        $ping->setTag('ping');
        $ping->setArgument('address', HOSTNAME);
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function($response, $client) use (&$repliesCount) {
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Response', $response,
                    'A callback must receive a single response per call'
                );
                \PHPUnit_Framework_TestCase::assertInstanceOf(
                    __NAMESPACE__ . '\Client', $client,
                    'A callback must receive a copy of the client object'
                );

                \PHPUnit_Framework_TestCase::assertEquals(
                    'ping', $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
            }
        );


        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $this->object->sendAsync($arpPrint);
        $this->assertFalse($this->object->getStreamResponses());
        $this->assertFalse($this->object->setStreamResponses(true));
        $this->assertTrue($this->object->getStreamResponses());

        $replies = $this->object->completeRequest('arp');

        $this->assertInternalType(
            'array', $replies, 'ARP list must be an array'
        );

        $this->assertGreaterThan(
            0, $repliesCount,
            "No responses for '" . HOSTNAME . "' before of 'arp' is done."
        );
        $this->assertInternalType(
            'resource', $replies[0]->getArgument('address'),
                                                 'The address is not a stream'
        );
    }

    public function testCompleteRequestEmptyQueue()
    {
        try {
            $this->object->completeRequest('invalid');

            $this->fail('No exception was thrown.');
        } catch (DataFlowException $e) {
            $this->assertEquals(104, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testCompleteRequestInvalid()
    {
        try {
            $arpPrint = new Request('/ip/arp/print');
            $arpPrint->setTag('arp');
            $this->object->sendAsync($arpPrint);
            $this->object->completeRequest('invalid');

            $this->fail('No exception was thrown.');
        } catch (DataFlowException $e) {
            $this->assertEquals(104, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testSendAsyncWithoutCallbackAndLoop()
    {
        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $this->object->sendAsync($arpPrint);

        $this->object->loop();
        $replies = $this->object->extractNewResponses('arp');
        $this->assertInternalType('array', $replies, 'Improper type.');
        $this->assertGreaterThan(0, count($replies), 'No responses.');

        $ping = new Request('/ping');
        $ping->setTag('ping');
        $ping->setArgument('address', HOSTNAME);
        $this->object->sendAsync($ping);

        $this->object->loop(2);
        $replies = $this->object->extractNewResponses('ping');
        $this->assertInternalType('array', $replies, 'Improper type.');
        $this->assertGreaterThan(0, count($replies), 'No responses.');
        $this->object->cancelRequest('ping');
    }

    public function testStreamEquality()
    {
        $request = new Request('/queue/simple/print');

        $request->setQuery(Query::where('target-addresses',
                                        HOSTNAME_INVALID . '/32'));

        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $list);

        $this->object->setStreamResponses(true);
        $streamList = $this->object->sendSync($request);
        $this->assertInternalType('array', $streamList);

        foreach ($list as $index => $response) {
            $streamListArgs = $streamList[$index]->getAllArguments();
            foreach ($response->getAllArguments() as $argName => $value) {
                $this->assertArrayHasKey($argName, $streamListArgs,
                                         'Missing argument.'
                );
                $this->assertEquals($value,
                                    stream_get_contents($streamListArgs[$argName]),
                                                        'Argument values are not equivalent.'
                );
                unset($streamListArgs[$argName]);
            }
            $this->assertEmpty($streamListArgs, 'Extra arguments.');
        }
    }

    public function testSendSyncWithQueryEquals()
    {
        $request = new Request('/queue/simple/print');

        $request->setQuery(Query::where('target-addresses',
                                        HOSTNAME_INVALID . '/32'));
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            2, count($list),
                     'The list should have only one item and a "done" reply.');

        $request->setQuery(Query::where('target-addresses',
                                        HOSTNAME_INVALID . '/32',
                                        Query::ACTION_EQUALS));
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            2, count($list),
                     'The list should have only one item and a "done" reply.'
        );

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $request->setQuery(Query::where('target-addresses',
                                        $invalidAddressStream));
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            2, count($list),
                     'The list should have only one item and a "done" reply.'
        );

        $request->setQuery(Query::where('target-addresses',
                                        $invalidAddressStream,
                                        Query::ACTION_EQUALS));
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            2, count($list),
                     'The list should have only one item and a "done" reply.'
        );
    }

    public function testSendSyncWithQueryEqualsNot()
    {
        $request = new Request('/queue/simple/print');
        $fullList = $this->object->sendSync($request);

        $request->setQuery(Query::where('target-addresses',
                                        HOSTNAME_INVALID . '/32')->not());
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $fullList,
                                  'The full list is not an array');
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            count($fullList) - 1, count($list), 'The list was never filtered.'
        );

        $request->setQuery(Query::where('target-addresses',
                                        HOSTNAME_INVALID . '/32',
                                        Query::ACTION_EQUALS)->not());
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $fullList,
                                  'The full list is not an array');
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            count($fullList) - 1, count($list), 'The list was never filtered.'
        );

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $request->setQuery(Query::where('target-addresses',
                                        $invalidAddressStream)->not());
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $fullList,
                                  'The full list is not an array');
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            count($fullList) - 1, count($list), 'The list was never filtered.'
        );

        $request->setQuery(Query::where('target-addresses',
                                        $invalidAddressStream,
                                        Query::ACTION_EQUALS)->not());
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $fullList,
                                  'The full list is not an array');
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            count($fullList) - 1, count($list), 'The list was never filtered.'
        );
    }

    public function testSendSyncWithQueryEnum()
    {
        $request = new Request('/queue/simple/print');
        $fullList = $this->object->sendSync($request);

        $request->setQuery(
            Query::where('target-addresses', HOSTNAME_SILENT . '/32')
                ->orWhere('target-addresses', HOSTNAME_INVALID . '/32')
        );
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $fullList,
                                  'The full list is not an array');
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(3, count($list), 'The list was never filtered.');

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $silentAddressStream = fopen('php://temp', 'r+b');
        fwrite($silentAddressStream, HOSTNAME_SILENT . '/32');
        rewind($silentAddressStream);

        $request->setQuery(
            Query::where('target-addresses', $silentAddressStream)
                ->orWhere('target-addresses', $invalidAddressStream)
        );
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $fullList,
                                  'The full list is not an array');
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(3, count($list), 'The list was never filtered.');
    }

    public function testSendSyncWithQueryEnumNot()
    {
        $request = new Request('/queue/simple/print');
        $fullList = $this->object->sendSync($request);

        $request->setQuery(
            Query::where('target-addresses', HOSTNAME_SILENT . '/32')
                ->orWhere('target-addresses', HOSTNAME_INVALID . '/32')
                ->not()
        );
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $fullList,
                                  'The full list is not an array');
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            count($fullList) - 2, count($list), 'The list was never filtered.'
        );

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $silentAddressStream = fopen('php://temp', 'r+b');
        fwrite($silentAddressStream, HOSTNAME_SILENT . '/32');
        rewind($silentAddressStream);

        $request->setQuery(
            Query::where('target-addresses', $silentAddressStream)
                ->orWhere('target-addresses', $invalidAddressStream)
                ->not()
        );
        $list = $this->object->sendSync($request);
        $this->assertInternalType('array', $fullList,
                                  'The full list is not an array');
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertEquals(
            count($fullList) - 2, count($list), 'The list was never filtered.'
        );
    }

    public function testSendSyncWithQueryBetween()
    {
        $request = new Request('/ip/arp/print');
        $fullList = $this->object->sendSync($request);

        $request->setQuery(
            Query::where('address', HOSTNAME, Query::ACTION_GREATHER_THAN)
                ->andWhere('address', HOSTNAME_INVALID, Query::ACTION_LESS_THAN)
        );
        $list = $this->object->sendSync($request);
        $this->assertInternalType(
            'array', $fullList, 'The list is not an array'
        );
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertLessThan(
            count($fullList), count($list), 'The list was never filtered.'
        );

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $addressStream = fopen('php://temp', 'r+b');
        fwrite($addressStream, HOSTNAME . '/32');
        rewind($addressStream);

        $request->setQuery(
            Query::where('address', $addressStream, Query::ACTION_GREATHER_THAN)
                ->andWhere(
                    'address', $invalidAddressStream, Query::ACTION_LESS_THAN
                )
        );
        $list = $this->object->sendSync($request);
        $this->assertInternalType(
            'array', $fullList, 'The list is not an array'
        );
        $this->assertInternalType('array', $list, 'The list is not an array');
        $this->assertLessThan(
            count($fullList), count($list), 'The list was never filtered.'
        );
    }

}