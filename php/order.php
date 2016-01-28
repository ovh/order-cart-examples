<?php
namespace Ovh;

/*
 * @category Ovh
 * @package Ovh
 * @author ApiOrder Team <api@ml.ovh.net>
 * @license https://github.com/ovh/order-cart-examples/blob/master/LICENSE
 *
 * Feel free to use and reuse this source code
 * Sources code samples are deliberatly not defensive, to keep clean syntax
 */

require __DIR__ . '/vendor/autoload.php';

use Ovh\Api;
use Ovh\Exceptions;
use Ovh\Schema;
use GuzzleHttp\Exception\RequestException;

require "schema.php";

/**
 * Order class to make fully automated ordering via Ovh API
 */
class Order
{
    /**
     * Main to start CLI
     * cli : Command Line Interface
     *
     * @category Ovh
     */
    public static function main()
    {
        $applicationKey    = Order::askForConfig("OVH_APPLICATION_KEY");
        $applicationSecret = Order::askForConfig("OVH_APPLICATION_SECRET");
        $endpointName      = Order::askForConfig("OVH_ENDPOINT", "ovh-eu");
        $consumerKey       = Order::askForConfig("OVH_CONSUMER_KEY");

        $apiv6 = new Api($applicationKey, $applicationSecret, $endpointName, $consumerKey);
        try {
            $cartId = Order::assignCart($apiv6);
            Order::waitProduct($apiv6, $cartId);
            $salesorderId = Order::editSalesorder($apiv6, $cartId);

            if (isset($salesorderId)) {
                echo PHP_EOL;
                echo  "Account balances :" . PHP_EOL;
                Order::showMyOvhAccount($apiv6);
                Order::showMyFidelityAccount($apiv6);
                Order::askForPaymentMeanAndDecide($apiv6, $salesorderId);
            }

        } catch (Exception $e) {
             echo "Abort" . PHP_EOL;
             echo "Fail to create a cart, please retry". PHP_EOL;
             exit(1);
        }
    }

    // -------------------------------------------------------------------------
    /**
     * Utility to ask user credentials when they are not provided
     * Try with
     * - Environment
     * - Init file "ovh.conf"
     * - Cli input
     * - Defaults from sources
     *
     * @category Ovh
     * @param  string $varEnv   Environment variable name
     * @param  string $default  Default env value
     *
     * @return value
     */
    public static function askForConfig($varEnv, $default = "")
    {
        $env = null;
        try {
             $env = Order::askUserConfig($varEnv, $default);
        } catch (Exceptions\InvalidParameterException $e) {
            if (empty($env)) {
                $input = readline("Enter an " . $varEnv . " : " . PHP_EOL);
                if (isset($input)) {
                    return $input;
                }
            }
        }
        return $env;
    }

    /**
     * Utility pick from Shell environment
     *
     * @category Ovh
     * @param  string $varEnv   Environment variable name
     * @param  string $default  A default value if you put it directly into source
     *
     * @return $configValue     A environment value, like credentials, endpoint
     * @throws Exceptions\InvalidParameterException Credentials should be provided
     */
    public static function askUserConfig($varEnv, $default = "")
    {
        $configValue = getenv($varEnv);
        if (empty($configValue)) {

            $credentialsFile = '../ovh.conf';
            if (is_readable($credentialsFile)) {
                $configsFromFile = parse_ini_file($credentialsFile);
                $varEnvForFile = strtolower(substr($varEnv, 4));
                if (isset($configsFromFile[$varEnvForFile])) {
                    return $configsFromFile[$varEnvForFile];
                } elseif (empty($default)) {
                    throw new Exceptions\InvalidParameterException("No credential provided");
                } else {
                    return $default;
                }
            } elseif (empty($default)) {
                throw new Exceptions\InvalidParameterException("No credential provided");
            } else {
                return $default;
            }
        }
        return $configValue;
    }

    // -------------------------------------------------------------------------
    /**
     * Utility to attribute a new cart to yourself
     *
     * @category Ovh
     * @param  Api     $apiv6    OvhApi php object
     *
     * @return String  $cartid   A cart identifier
     */
    public static function assignCart($apiv6)
    {
        $cart = $apiv6->post("/order/cart", ['ovhSubsidiary' => 'FR', 'description' => 'php-order-cli']);
        $cartId = $cart["cartId"];
        echo "Current cart : " . $cartId . PHP_EOL;

        // assign it to current user
        $apiv6->post("/order/cart/".$cartId."/assign");
        return $cartId;
    }

    /**
     * Wait for incoming new product to order
     *
     * @category Ovh
     * @param  Api     $apiv6     OvhApi php object
     * @param  string  $cartId    Current cart identifier
     *
     * @return value
     */
    public static function waitProduct($apiv6, $cartId)
    {
        // add product, here a domain name into your cart
        for (;;) {
            $domainName = readline(PHP_EOL . "Please enter a domain ".
            "(Press Enter if you're done adding domains) : ");

            // edit bc when no product provided
            if (empty($domainName)) {
                break;
            }
            Order::addProduct($apiv6, $cartId, $domainName);
        }

        // check you provide at least 1 item in cart
        $cartInfo = $apiv6->get("/order/cart/" . $cartId);
        $items = $cartInfo["items"];
        $itemsCounter = count($items) || 0;
        if ($itemsCounter < 1) {
            echo "No item provided, abort order" . PHP_EOL;
        }
    }

    /**
     * Show price for a given offer ( TOTAL = PRICE + FEE - DISCOUNT)
     *
     * @category Ovh
     * @param array $offerInfos     A selected offer identifer
     */
    public static function showPrice($offerInfos)
    {
        if (isset($offerInfos)) {
            foreach ($offerInfos["prices"] as $price) {
                if (strcasecmp("TOTAL", $price["label"]) == 0) {
                    echo $price["price"]["text"];
                }
            }
        } else {
            throw new Exceptions\InvalidParameterException("Invalid offer provided");
        }
    }

    /**
     * Show phase for a domain name
     *
     * @category Ovh
     * @param array $offerInfos     A selected offer identifer
     */
    public static function showPhase($offerInfos)
    {
        echo " ( phase : " . $offerInfos['phase'] . ")";
    }

    /**
     * Utility cli to display availability
     *
     * @category Ovh
     * @param array $offerInfos     A selected offer identifer
     *
     * @return value
     */
    public static function showAvailability($offerInfos)
    {
        // looking for product availability
        if (isset($offerInfos)) {
            if ($offerInfos["orderable"] == (bool) true) {
                echo "available";
            } else {
                echo "NOT available";
            }
        } else {
            throw new Exceptions\InvalidParameterException("Invalid offer provided");
        }
    }

    /**
     * Select an offer from a given product
     *
     * @category Ovh
     * @param  array $productInfos   Current product informations
     *
     * @return value   An offer
     */
    public static function selectOffer($productInfos)
    {
         // when multiple offer are available for the same product, e.g: .barcelona
        $selectedOffer = 0;
        if (count($productInfos) > 1) {
            echo PHP_EOL;
            echo "multiples offers exists, " .
                "please select one :" . PHP_EOL;

            $endRange = count($productInfos) - 1;
            foreach (range(0, $endRange) as $offerIndex) {
                echo '['.$offerIndex.'] ';
                Order::showPhase($productInfos[$offerIndex]);
                echo ' => ';
                Order::showPrice($productInfos[$offerIndex]);
                echo PHP_EOL;
            }

            $selectedOffer = intval(readline("Please select your offer: "));
            if ($selectedOffer < 0 || $selectedOffer > (count($productInfos) - 1)) {
                echo "invalid choice" . PHP_EOL;
                continue;
            }
        }

        return $productInfos[$selectedOffer];
    }

    /**
     * Search for a domain name
     *
     * @category Ovh
     * @param  Api     $apiv6        OvhApi php object
     * @param  string  $cartId       Current cart identifier
     * @param  string  $productName  Data from user input
     *
     * @return array   $productInfos Product informations
     */
    public static function searchDomainName($apiv6, $cartId, $domainName)
    {
        $productInfos = $apiv6->get("/order/cart/". $cartId ."/domain", ['domain' => $domainName]);
        return $productInfos;
    }

    /**
     * Add a product to cart
     *
     * @category Ovh
     * @param  Api     $apiv6        OvhApi php object
     * @param  string  $cartId       Current cart identifier
     * @param  string  $productName   Data from user input
     *
     * @return value
     */
    public static function addProduct($apiv6, $cartId, $productName)
    {
        try {
            // search a product description, here a domain name
            $productInfos = Order::searchDomainName($apiv6, $cartId, $productName);

            echo PHP_EOL;
            echo $productName . " is " ;
            Order::showAvailability($productInfos[0]);

            $selectedOffer = Order::selectOffer($productInfos);

            Order::showPrice($selectedOffer);
            echo PHP_EOL;

            // add selected offer to cart
            $offerid = $selectedOffer['offerId'];
            $yesOrNo = readline("Do you want to add it to your cart ? (Y/N) ");

            // store the select offer into cart and keep item line identifier
            $selectedItemId = 0;
            if (in_array($yesOrNo, ['Y','y','yes','YES'])) {
                $addToCartResult = $apiv6->post(
                    '/order/cart/' . $cartId . '/domain',
                    ['domain' => $productName, 'offerId' => $offerid]
                );
                if (isset($addToCartResult['itemId'])) {
                    $selectedItemId = $addToCartResult['itemId'];
                }
            }

            // Look at configuration for this item, required by your account
            $reqConfigsFields = Order::getRequiredConfigurationFields($apiv6, $cartId, $selectedItemId);

            // configurations handling when you need it
            $offerConfigurations = $selectedOffer['configurations'];
            if (isset($offerConfigurations) and count($offerConfigurations) > 0) {
                Order::configurationHandling(
                    $apiv6,
                    $cartId,
                    $selectedItemId,
                    $offerConfigurations,
                    $reqConfigsFields
                );
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() == '400') {
                $apiMessage = json_decode($e->getResponse()->getBody());
                echo "Abort," . PHP_EOL;
                echo $apiMessage->message . PHP_EOL;
            } else {
                echo "Abort" . PHP_EOL;
                echo "Fail to generate a salesorder, please retry". PHP_EOL;
            }
        } catch (Exception $e) {
            echo 'Failed to post domain into cart, please retry' . PHP_EOL;
        }
    }

    /** List contracts from salesOrder
     *
     * @category OVH
     * @param  String $salesOrder A salesorder identifier
     */
    public static function showContractsList($salesOrder)
    {
        if (isset($salesOrder["contracts"])) {
            $salesOrder["contracts"];
            foreach ($salesOrder["contracts"] as $contract) {
                echo $contract["name"]  . " : " . PHP_EOL;
                echo $contract["url"] . PHP_EOL;
            }
        }
    }

    /** Generate a Salesorder from a given cart
     *
     * @category OVH
     * @param  Api     $apiv6       OvhApi php object
     * @param  string  $cartId      Current cart identifier
     *
     * @return string  $salesorderId  A salesorder identifier
     */
    public static function editSalesorder($apiv6, $cartId)
    {
        echo PHP_EOL . "Editing salesorder ..." . PHP_EOL;
        try {
            $salesorder = $apiv6->post("/order/cart/".$cartId."/checkout");

            if (!isset($salesorder)) {
                echo "Fail to generate a salesorder, please retry". PHP_EOL;
            }
            if (isset($salesorder["message"])) {
                echo "Fail to generate a salesorder, please fix requirements:".PHP_EOL;
            }

            // Full Contracts details are located into $salesorder["contracts"]
            // You should look at generated order
            // and considere, you accept contracts clause before payment
            Order::showContractsList($salesorder);

            echo "Order #" . $salesorder["orderId"] .
                ' has been generated, please take a look at : '. PHP_EOL .
                $salesorder["url"] . PHP_EOL;
            return $salesorder["orderId"];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() == '400') {
                $apiMessage = json_decode($e->getResponse()->getBody());
                echo "Abort," . PHP_EOL;
                echo $apiMessage->message . PHP_EOL;
            } else {
                echo "Abort" . PHP_EOL;
                echo "Fail to generate a salesorder, please retry". PHP_EOL;
            }
        }
    }

    // -------------------------------------------------------------------------
    /**
     * Utility cli to display schema based form
     *
     * @category Ovh
     * @param  string $formStruct A form data struct for asking inputs
     *
     * @return value
     */
    public static function formToCli($formStruct)
    {
        if (is_array($formStruct)) {
            $answers = array();

            foreach ($formStruct as $field) {
                $input = null;
                if (is_array($field['inputType'])) {
                    $input = Order::formToCli($field['inputType']);
                } else {
                    $inputType   = $field['inputType'];
                    $apiType     = $field['apiType'];
                    $fieldLabel  = $field['label'];

                    echo $fieldLabel;
                    Schema::displayShortDescription($fieldLabel, $apiType);

                    $fieldMandatory = $field['mandatory'];
                    if ($fieldMandatory == true) {
                        do {
                            $input = readline(" (*) : ");
                        } while (!isset($input) or $input == "");
                    } else {
                        $input = readline(" : ");
                    }
                    if ($inputType == "number") {
                        $input = intval($input);
                    }
                }
                $answers = array_merge($answers, array( $field['label'] => $input));
            }
            return $answers;

        } else {
            // check type with field here
            $input = readline($formStruct['label'] . " : ");
            $answers = array(
               "label"  => $formStruct['label'],
               "value"  => $input
            );
            return $answers;
        }
    }

    // -------------------------------------------------------------------------
    /**
     * Show configurations related to an offer when you are auth
     *
     * @category Ovh
     * @param  Api    $apiv6       OvhApi php object
     * @param  string $cartId      Current cart identifier
     * @param  string $formStruct  A form data struct to ask configurations
     *
     * @return value
     */
    public static function showConfigurations($apiv6, $offerConfigurations, $reqConfigsFields)
    {
        $configs = array();

        // show configuration choices
        foreach (range(1, count($offerConfigurations)) as $configIndex) {

            // show an index for configuration setup
            $configType  = $offerConfigurations[$configIndex-1]['type'];
            $configLabel = $offerConfigurations[$configIndex-1]['label'];

            echo $configIndex . ") ";

            $configForm = Schema::getSchema($apiv6, $configLabel, $configType);

            // patch configuration with requiredConfiguration
            $configForm[$configLabel] = Schema::patchMandatory(
                $reqConfigsFields,
                $configForm[$configLabel]
            );

            Schema::displayFormStruct($configLabel, $configForm[$configLabel]);
            array_push(
                $configs,
                ["path"=>$configType, "form"=>$configForm[$configLabel]]
            );
        }
        echo PHP_EOL;
        return $configs;
    }

     /**
     * Retrieve mandatory configuration from an cart item
     *
     * @category Ovh
     * @param  Api     $apiv6        OvhApi php client object
     * @param  string  $cartId       Current cart identifier
     * @param  string  $selectedItemId Data from user input
     * @return value
     */
    public static function getRequiredConfigurationFields($apiv6, $cartId, $selectedItemId)
    {
        $requiredConfigs =  $apiv6->get(
            "/order/cart/" . $cartId . "/item/" . $selectedItemId . "/requiredConfiguration"
        );
        //TODO merge all fields
        if (isset($requiredConfigs) and is_array($requiredConfigs) and isset($requiredConfigs[0]['fields'])) {
            return $requiredConfigs[0]['fields'];
        }
        return [];
    }

    /**
     * Utility cli for configuration selection
     *
     * @category OVH
     * @param  Api     $apiv6               OvhApi php object
     * @param  string  $cartId              Current cart identifier
     * @param  string  $itemId              Selected item identifier
     * @param  array   $offerConfigurations An associative array
     * @param  array   $requiredConfigs     An associative array for required configuration
     */
    public static function configurationHandling(
        $apiv6,
        $cartId,
        $itemId,
        $offerConfigurations,
        $requiredConfigs
    ) {
        echo "Configurations are needed to obtain this domain, " . PHP_EOL .
            "please select your prefered document ( * for mandatory field):" . PHP_EOL;

        $configs = Order::showConfigurations($apiv6, $offerConfigurations, $requiredConfigs);

        for (;;) {
            // select configuration you want to fill fields
            $indexConfiguration = intval(readline(
                "Please select a configuration (Press Enter if your're done adding configs) :"
            ));

            if (empty($indexConfiguration)
                || $indexConfiguration < 1
                || $indexConfiguration > count($offerConfigurations)
            ) {
                echo "Finish configuration setup" . PHP_EOL;
                break;
                return;
            } else {
                $availableConfig = array_values($configs);
                $selectedConfig = $availableConfig[ $indexConfiguration - 1];

                // take extended type
                if (preg_match('/^\//i', $selectedConfig['path'])) {

                    $readOrCreateResource = null;
                    $resources = Schema::showResources($apiv6, $selectedConfig['path']);

                    if (count($resources) < 1) {
                        $readOrCreateResource = "c";
                    } else {
                        $readOrCreateResource = readline("Please select" .
                            " an existing resource (number) or create a new one (tape c), * are mandatory : ");
                    }

                    if (in_array($readOrCreateResource, array('c','C','create','CREATE'))) {

                        // Do you want to use an already existing config
                        $userInput = Order::formToCli($selectedConfig['form']);

                        // send configuration
                        try {
                            echo "Sending configuration to " . $selectedConfig['path'] . " ..." . PHP_EOL ;
                            $newResc = $apiv6->post($selectedConfig['path'], $userInput);
                            echo "Resource created with identifier : " . strval($newResc{'id'}) . PHP_EOL;

                            $configResource = $selectedConfig['path'] . "/" . $newResc['id'];
                        } catch (\GuzzleHttp\Exception\ClientException $e) {
                            if ($e->getResponse()->getStatusCode() == '400') {
                                $apiMessage = json_decode($e->getResponse()->getBody());
                                echo "Abort" . PHP_EOL;
                                echo "Fail to create resource, " . "
                                please ensure your resource inputs are valid, ".
                                $apiMessage->message . PHP_EOL;
                            } else {
                                echo "Abort" . PHP_EOL;
                                echo "Fail to create resource, please retry". PHP_EOL;
                            }
                        } catch (Exception $e) {
                            echo "Failed to configure this item, please retry : " . $e;
                        }

                    } else {
                        echo "Sending a resource identifier for : " . $selectedConfig['path'] . PHP_EOL ;
                        $configResource = null;
                        $configResource = $selectedConfig['path'] . "/" . strval($readOrCreateResource);
                    }

                } else {
                    $formOut = Order::formToCli($selectedConfig['form']);
                    $configResource  = $formOut[$selectedConfig['path']];
                }

                //Submit a given resource to setup a cart item
                Schema::bindResource(
                    $apiv6,
                    $cartId,
                    $itemId,
                    $offerConfigurations[$indexConfiguration-1]['label'],
                    $configResource
                );
                echo "Resource linked" . PHP_EOL;
            }
        }
    }

    // -------------------------------------------------------------------------
    /**
     * Parse argument from commandline
     *
     * @category Ovh
     * @param  array $argv List of inputs provided to script
     *
     * @return array $args Assocative array of parameters
     */
    public static function arguments($argv)
    {
        $args = array();
        foreach ($argv as $arg) {
            if (ereg('--([^=]+)=(.*)', $arg, $reg)) {
                $args[$reg[1]] = $reg;
            } elseif (ereg('--([a-zA-Z0-9]+)', $arg, $reg)) {
                $args[$reg[1]] = 'true';
            }
        }
        return $args;
    }

    /**
     * basic commandline args handler
     *
     * @category Ovh
     * @param  array $argv List of inputs provided to script
     *
     * @return array $args Assocative array of parameters
     */
    public static function handleArguments($argv)
    {
        $args = Order::arguments($argv);
        foreach ($args as $key => $value) {
            if ($key == "version" && $value == true) {
                echo "OVH Order php Client - 0.1.0" . PHP_EOL;
                exit(0);
            } elseif ($key == "help" && $value == true) {
                echo "--help       For help" . PHP_EOL;
                echo "--version    For help" . PHP_EOL;
                echo "--credential To look at credential currently used" . PHP_EOL;
                exit(0);
            } elseif ($key == "credential" && $value == true) {
                $ak = "OVH_APPLICATION_KEY";
                $as = "OVH_APPLICATION_SECRET";
                $ep = "OVH_ENDPOINT";
                $cs = "OVH_CONSUMER_KEY";

                $applicationKey    = Order::askUserConfig($ak);
                $applicationSecret = Order::askUserConfig($as);
                $endpointName      = Order::askUserConfig($ep, 'ovh-eu');
                $consumerKey       = Order::askUserConfig($cs);

                echo  $ak . ": " . $applicationKey    . PHP_EOL;
                echo  $as . ": " . $applicationSecret . PHP_EOL;
                echo  $ep . ": " . $endpointName      . PHP_EOL;
                echo  $cs . ": " . $consumerKey       . PHP_EOL;
                exit(0);
            }
        }
    }

    // -------------------------------------------------------------------------
    /* show fidelityAccount
     * For simplification, balance is indicated as EURO
     *
     * @category Ovh
     * @param  Api     $apiv6    OvhApi php object
     *
     * @return         $resp     Json decoded of api response
     */
    public static function showMyFidelityAccount($apiv6)
    {
        $resp = $apiv6->get('/me/fidelityAccount');
        echo "    fidelityAccount : ~". $resp['balance'] * 0.01 . " €" . PHP_EOL;
        return $resp;
    }

    /* show ovhAccount
     * For simplification, balance is indicated as EURO on FR subsidiary
     *
     * @category Ovh
     * @param  Api     $apiv6    OvhApi php object
     *
     * @return         $resp     Json decoded of api response
     */
    public static function showMyOvhAccount($apiv6)
    {
        $resp = $apiv6->get('/me/ovhAccount/' . 'FR');
        echo "    ovhAccount : " . $resp['balance']['text'] . PHP_EOL;
        return $resp;
    }

    /* show available payments and prompt to pay an order
     *
     * @category Ovh
     * @param  Api     $apiv6          OvhApi php object
     * @param  string  $sales-orderid   Order identifier
     */
    public static function askForPaymentMeanAndDecide($apiv6, $salesorderid)
    {
        $availablePaymentMean = ['ovhAccount','fidelityAccount'];

        echo  PHP_EOL . "Please choose a payment mean (Press Enter without value to abort) : " . PHP_EOL;
        $pmLength = count($availablePaymentMean);
        foreach (range(1, $pmLength) as $idx) {
            echo strval($idx) . ")  " . $availablePaymentMean[($idx-1)] . PHP_EOL;
        }

        $selectedId = intval(readline(""));
        if (empty($selectedId)) {
            echo "Abort..." . PHP_EOL;
            exit(1);
        }
        $selectedPaymentMean = $availablePaymentMean[$selectedId-1];

        $WantToPay = readline(
            "Are you sure you want to pay your order, this is for REAL (Enter: 'I want to pay')". PHP_EOL
        ) ;
        if (strcasecmp($WantToPay, "I want to pay") == 0) {
            Order::payWithRegisteredPaymentMean($apiv6, $salesorderid, $selectedPaymentMean);
            echo "Done !" . PHP_EOL;
            exit(0);
        } else {
            echo "Abort..." . PHP_EOL;
            exit(1);
        }
    }

    /* pay salesorder with paymentMean = > fidelityAccount OR ovhAccount
     * @category Ovh
     * @param  Api     $apiv6          OvhApi php object
     * @param  string  $salesorderid   Order identifier
     * @param  string  $paymentMean    A payment mean
     *
     * @return string                  Should be null when succeed
     */
    public static function payWithRegisteredPaymentMean($apiv6, $salesorderid, $paymentMean)
    {
        try {
            $resp = $apiv6->post(
                '/me/order/' . $salesorderid . '/payWithRegisteredPaymentMean',
                array("paymentMean" => $paymentMean)
            );
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() == '400') {
                $apiMessage = json_decode($e->getResponse()->getBody());
                echo "Abort" . PHP_EOL;
                echo "Fail to create contact, " . "
                please ensure your information contact are valid, ".
                $apiMessage->message . PHP_EOL;
            } else {
                echo "Abort" . PHP_EOL;
                echo "Fail to create a contact, please retry". PHP_EOL;
            }
        } catch (Exception $e) {
            echo "Failed to configure this item, please retry : " . $e;
        }
        return $resp;
    }
}
