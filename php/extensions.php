#!/usr/bin/env php
<?php
/**
 * @category Ovh
 * @package Ovh
 * @author ApiOrder Team
 * @license https://github.com/ovh/order-cart-examples/blob/master/LICENSE
 *
 * Feel free to use and reuse this source code
 * Sources code samples are deliberatly not trully defensive, to keep clean syntax
 *
 * This file indicate TOP 10 TLDs on FR subsidiary
 */
require __DIR__ . '/vendor/autoload.php';
use \Ovh\Api;

if (is_file(dirname(__FILE__).'order.php') === true) {
    include_once dirname(__FILE__).'./order.php';
} else {
    include_once './order.php';
}

use Ovh\Order;

$applicationKey    = Order::askUserConfig("OVH_APPLICATION_KEY");
$applicationSecret = Order::askUserConfig("OVH_APPLICATION_SECRET");
$endpointName      = Order::askUserConfig("OVH_ENDPOINT", "ovh-eu");
$consumerKey       = Order::askUserConfig("OVH_CONSUMER_KEY");

$apiv6 = new Api(
    $applicationKey,
    $applicationSecret,
    $endpointName,
    $consumerKey
);

try {

    // TLDs from domain API
    $tlds = $apiv6->get("/domain/data/extension", array("country" => "FR" ));
    if ($tlds) {

        # create a cart
        $cartId = Order::assignCart($apiv6);

        $domainname = readline("Please enter a domain-name (without the dot and extension) : ");

        $topext = array_slice($tlds, 0, 10);

        #get availability of top 10 featured extensions
        foreach ($topext as $tld) {
            $fqdn = $domainname . "."  . $tld;
            try {
                $productInfos = Order::searchDomainName($apiv6, $cartId, $fqdn);
                echo $fqdn . " : ";
                Order::showAvailability($productInfos[0]);
                echo " => ";
                Order::showPrice($productInfos[0]);
                echo PHP_EOL;

            } catch (Exception $e) {
                echo "Could not find availability for ". $fqdn . ", please retry" . PHP_EOL;
            }
        }
    }
} catch (Exception $e) {
    echo "Fail to retrieve supported extensions list, please retry". PHP_EOL;
}

?>
