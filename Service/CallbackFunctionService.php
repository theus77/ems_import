<?php
/**
 * 	The Callback Function must be a class in this namespace, under the directory "CallbackFunctions"
 *  You must provide a function name with the classname in json array or the class must have a method named "execute"
 */
namespace EmsImportBundle\Service;

use Monolog\Logger;
require 'vendor/autoload.php';

class CallbackFunctionService
{
	private $logger;
	private $config;
	
	public function __construct(Logger $logger)
	{
		$this->logger = $logger;
	}
	
	function execute($config, $entries) {
		// Parsing the config for Callback Functions
		foreach ($config as $field => $fieldConfig) {
			if ($field == "CT") {
				$this->processField($fieldConfig, $config, $entries);
			} elseif(isset($fieldConfig["extra"])) {
				$this->processField($fieldConfig["extra"], $config, $entries);
			}
		}
		return "Done!";
	}
	
	function processField ($fieldConfig, $config, $entries) {
		$jsonConfig = json_decode($fieldConfig);
		if(isset($jsonConfig->CallbackFunction)) {
			if(is_array($jsonConfig->CallbackFunction) && count($jsonConfig->CallbackFunction) == 2){
				if(method_exists(__NAMESPACE__ ."\\CallbackFunctions\\". $jsonConfig->CallbackFunction[0], $jsonConfig->CallbackFunction[1])) {
					call_user_func(array(__NAMESPACE__ ."\\CallbackFunctions\\". $jsonConfig->CallbackFunction[0],$jsonConfig->CallbackFunction[1]), $config, $entries);
				} else {
					//TODO THROW Exception Callback function not found
					print("Method NOT found! "."EmsImportBundle\CallbackFunctions\\".$jsonConfig->CallbackFunction[0]."::".$jsonConfig->CallbackFunction[1]."\n");
				}
			} elseif(method_exists(__NAMESPACE__ ."\\CallbackFunctions\\".$jsonConfig->CallbackFunction, "execute")) {
				//"EmsImportBundle\\CallbackFunctions\\". $jsonConfig->CallbackFunction();
				//call_user_func("EmsImportBundle\\CallbackFunctions\\". $jsonConfig->CallbackFunction."\\execute", "Var_extern");
				//call_user_func("EmsImportBundle::CallbackFunctions::". $jsonConfig->CallbackFunction."::execute", "Var_extern");
				//call_user_func(array("EmsImportBundle\\CallbackFunctions\\". $jsonConfig->CallbackFunction,"execute"), "Var_extern");
				//call_user_func(array("EmsImportBundle::CallbackFunctions::". $jsonConfig->CallbackFunction,"execute"), "Var_extern");
				//call_user_func(array("EmsImportBundle\\CallbackFunctions::". $jsonConfig->CallbackFunction,"execute"), "Var_extern");
				//call_user_func(array("CallbackFunctions::". $jsonConfig->CallbackFunction,"execute"), "Var_extern");
				//call_user_func(array("CallbackFunctions\\". $jsonConfig->CallbackFunction,"execute"), "Var_extern");
				call_user_func(array(__NAMESPACE__ ."\\CallbackFunctions\\". $jsonConfig->CallbackFunction,"execute"), $config, $entries);
			} else {
				//TODO THROW Exception Callback function not found
				print("Method NOT found! ".__NAMESPACE__ ."\\CallbackFunctions\\".$jsonConfig->CallbackFunction."\n");
			}
		}
	}
}
