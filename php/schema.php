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

/**
 * Schema class to handle schema parsing
 */
class Schema
{

    // formating indication
    const DATE = "e.g: 1980-12-01";
    const MAIL = "e.g: api@ml.ovh.net";
    const LANGUAGE = "e.g: fr_FR / en_GB / de_DE";

    /**
     * Simple description or example for user input
     *
     * @package Ovh
     * @category Schema
     * @param string $label          A user input type
     * @param string $typeIndication A user input type
     */
    public static function displayShortDescription($label, $typeIndication)
    {
        $descriptionMapping = array(
            'nichandle.GenderEnum'   => "e.g: male / female",
            'nichandle.CountryEnum'  => "e.g: FR / US / DE", //more about on https://en.wikipedia.org/wiki/ISO_3166-2
            'nichandle.LegalFormEnum'=> "e.g: individual / corporation / association",
            'duration'               => "e.g: P1Y", //more about on  https://en.wikipedia.org/wiki/ISO_8601#Durations
            'datetime'               => "e.g: 1980-12-01",
            'date'                   => self::DATE,
            'birthDay'               => self::DATE,
            'mail'                   => self::MAIL,
            'email'                  => self::MAIL,
            'language'               => self::LANGUAGE,
            'nichandle.LanguageEnum' => self::LANGUAGE,
            'phoneNumber'            => "e.g: +33 9 72 10 10 07",
            'dns'                    => "e.g: dns10.ovh.net"
        );
        if (array_key_exists($typeIndication, $descriptionMapping)) {
            echo ", "  . $descriptionMapping{$typeIndication};
        } elseif (array_key_exists($label, $descriptionMapping)) {
            echo ", "  . $descriptionMapping{$label};
        }
    }

    /**
     * Search for a model definition of an api v6 type
     *
     * @package Ovh
     * @category Schema
     * @param  array  $models      An associative array which keep apiv6 models
     * @param  array  $cursor      An array which
     * @param  string $configLabel A configuration label
     * @param  string $typeName    A configuration type
     *
     * @return string              Return a simple type for cli inputs
     * @throws Ovh\Exception
     */
    public static function getModels($models, $cursor, $configLabel, $typeName)
    {
        // simple types
        if (in_array(
            $typeName,
            array('boolean','string')
        )) {
            return "string";
        } elseif (strcmp($typeName, 'long') == 0) {
            return "number";
        } elseif (strcmp($typeName, 'date') == 0) {
            return "date";
        } elseif (strcmp($typeName, 'datetime') == 0) {
            return "datetime";
        } elseif (strcmp($typeName, 'phoneNumber') == 0) {
            return "phoneNumber";
        } elseif (strcmp($configLabel, 'birthDay') == 0) {
            return "date";
        } elseif (isset($models{$typeName}{'enumType'})) {
            // enum types
            return $models{$typeName}{'enumType'};
        } elseif (isset($cursor{$typeName})) {
            // extended types
            $extendedType = array();
            foreach ($models[$typeName]['properties'] as $key => $value) {
                $type =  $value['fullType'];
                $nestedType = Schema::getModels($models, $value, $key, $type);
                $mandatory;
                if ($value{'canBeNull'} == 0) {
                    $mandatory = true;
                } else {
                    $mandatory = false;
                }
                array_push(
                    $extendedType,
                    array(
                        "label"     => $key,
                        "inputType" => $nestedType,
                        "apiType"   => $value['fullType'],
                        "mandatory" => $mandatory
                    )
                );
            }
            return $extendedType;
        }
        throw new Ovh\Exception("unsupported schema type : " . $typeName);
    }

    /**
     * Search for an API V6 definition
     * path is about api resource you are searching for
     *
     * @package Ovh
     * @category Schema
     * @param  array  $schemaDef An associative array to store API v6 schema definition
     * @param  string $path      Path to a schema definition
     *
     * @return array
     */
    public static function getAPIs($schemaDef, $path)
    {
        $apiDefs = $schemaDef{"apis"};
        $apiSchema = array();

        foreach ($apiDefs as $api) {

            if (strcasecmp($api{'path'}, $path) == 0) {
                $operations = $api{'operations'};
                foreach ($operations as $operation) {
                    if (strcasecmp($operation{'httpMethod'}, "POST") == 0) {
                        $parameters = $operation{'parameters'};

                        foreach ($parameters as $parameter) {
                            $mandatory = $parameter{'required'};
                            $datatype  = $parameter{'dataType'};
                            $models    = $schemaDef{"models"};
                            array_push(
                                $apiSchema,
                                array(
                                    "label"     => $parameter{'name'},
                                    "mandatory" => $mandatory,
                                    "inputType" => Schema::getModels($models, $models, $parameter{'name'}, $datatype),
                                    "apiType"   => $datatype
                                )
                            );
                        }
                    }
                }
                // display GET but it's just to inform, if current status exist
                if (!isset($apiSchema)) {
                    foreach ($operations as $operation) {
                        if (strcasecmp($operation{'httpMethod'}, "GET") == 0) {
                            array_push(
                                $apiSchema,
                                array(
                                    "label"     => $parameter{'name'},
                                    "mandatory" => false,
                                    "inputType" => Schema::getModels($models, $models, $parameter{'name'}, $datatype),
                                    "apiType"   => "get"
                                )
                            );
                        }
                    }
                }
            }
        }
        return $apiSchema;
    }

    /**
     * Schema selection based on item's configuration
     *
     * @package Ovh
     * @category Schema
     * @param  Api    $apiv6        OvhApi php object
     * @param  array  $configLabel  Label of configuration field
     * @param  string $configType   Type of configuration field, which match schema definition
     *
     * @return array                Return an array of form struct
     */
    public static function getSchema($apiv6, $configLabel, $configType)
    {
        if (strcasecmp($configType, 'string') == 0) {
            return array($configLabel => array(array(
                            "label"     => "string",
                            'mandatory' => false,
                            'apiType'   => "string",
                            'inputType' => "string"
                            )));
        } elseif (strcasecmp($configType, 'DNS') == 0) {
            return array($configLabel => array(array(
                            "label"     => "server",
                            'mandatory' => false,
                            'apiType'   => "string",
                            'inputType' => "fqdn"
                            )));
        } elseif (strcasecmp($configType, 'boolean') == 0) {
            return array( $configLabel => "boolean");

        // if configuration type start with /domain
        // we load schema related to this data configuration
        } elseif (preg_match('/^\/domain/i', $configType)) {
            $domainSchema = $apiv6->get('/domain.json');
            $fields = Schema::getAPIs($domainSchema, $configType);
            return array($configLabel => $fields);
        } elseif (preg_match('/^\/me/i', $configType)) {
            $meSchema = $apiv6->get('/me.json');
            $fields = Schema::getAPIs($meSchema, $configType);
            return array($configLabel => $fields);
        } else {
            echo 'This configuration type is currently not supported by your cli :' . $configType;
            return array();
        }
    }

    /**
     * Utility cli to display your item configuration
     *
     * @package OVH
     * @category OVH
     * @param  string $formName Data type of expected
     * @param  array  $formStruct from user input
     *
     * @return value
     */
    public static function displayFormStruct($formName, $formStruct)
    {
        echo $formName . " : " . PHP_EOL;
        foreach ($formStruct as $field) {
            echo "    ";
            if ($field{'mandatory'}) {
                echo "* ";
            }
            echo $field{'label'} . " => ";
            if (is_array($field{'inputType'})) {
                Schema::displayFormStruct($field{'label'}, $field{'inputType'});
            } else {
                echo $field{'inputType'} . PHP_EOL;
            }
        }
    }

    /**
     * Enhance form information with user specific requirement
     *
     * @package Ovh
     * @category Schema
     * @param  array $mandatoryForAccount mandatory configuration fields for a product
     * @param  array $formStruct          FormDatas to handle input
     *
     * @return value
     */
    public static function patchMandatory($mandatoryForAccount, $formStruct)
    {
        $struct = array();
        foreach ($formStruct as $field) {
            if (in_array($field['label'], $mandatoryForAccount)) {
                $field{'mandatory'} = true;
            }

            if (is_array($field['inputType'])) {
                $field['inputType'] = Schema::patchMandatory($mandatoryForAccount, $field['inputType']);
            }
            array_push($struct, $field);
        }
        return $struct;
    }

    /**
     * Utility function to bind api resource to a item's configuration
     * see https://api.ovh.com/console/#/order/cart/{cartid}/item/{itemid}/configuration/{configurationid}#GET
     *
     * @package Ovh
     * @category Schema
     * @param  Api    $apiv6          OvhApi php object
     * @param  string $cartId         Current cart identifier
     * @param  string $itemId         Selected item identifier
     * @param  string $configLabel    A configuration name
     * @param  string $configResource A configuration resource identifier
     */
    public static function bindResource($apiv6, $cartId, $itemId, $configLabel, $configResource)
    {
        echo $configResource . PHP_EOL;
        $payload = array(
            "label" => $configLabel ,
            "value" => $configResource
        );
        $apiv6->post('/order/cart/' . $cartId .
            '/item/' . strval($itemId) . '/configuration', $payload);
    }

    /**
    * Utility function to show a list of related resources
    * e.g: https://api.ovh.com/console/#/me/contact/{contactId}#GET
    *
    * @package Ovh
    * @category Schema
    * @param  Api    $apiv6        OvhApi php object
    * @param  string $resourcePath Path to a given resource
    * @return array                Array of contacts identifier
    */
    public static function showResources($apiv6, $resourcePath)
    {
        $currentResources = $apiv6->get($resourcePath);
        $length = count($currentResources);
        if ($length < 1) {
            return $currentResources;
        }

        echo "List of related resources" . PHP_EOL . "[ " ;
        if ($length > 1) {
            foreach (range(0, $length-2) as $index) {
                echo $currentResources[$index] . ", ";
            }
        }
        echo $currentResources[$length - 1];
        echo "]" . PHP_EOL;
        return $currentResources;
    }
}
