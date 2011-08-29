<?php
namespace \Net\RouterOS;
require_once 'Net/RouterOS/Autoload.php';

$client = new Client('192.168.0.1', 'admin');

//Custom function, defined specifically for the example
function ResponseHandler($response) {
    if ($response->getType() === Response::TYPE_FINAL) {
        echo "{$response->getTag()} is done.\n";
    }
}

$addRequest = new Request('/ip/arp/add');

$addRequest->setArgument('address', '192.168.0.100');
$addRequest->setArgument('mac-address', '00:00:00:00:00:01');
$addRequest->setTag('arp1');
$client->sendAsync($addRequest, 'ResponseHandler');

$addRequest->setArgument('address', '192.168.0.101');
$addRequest->setArgument('mac-address', '00:00:00:00:00:02');
$addRequest->setTag('arp2');
$client->sendAsync($addRequest, 'ResponseHandler');

$client->loop();
//Example output:
/*
arp1 is done.
arp2 is done.
*/
?>
