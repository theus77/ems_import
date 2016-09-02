<?php
/**
 * A callback function must be a class placed in the directory EmsImportBundle\Service\CallbackFunctions, 
 * Only static methods can be called.
 * These methods must have 2 parameters:
 * - $config that hold oll the config
 * - $entries that hold all records to import
 */
namespace EmsImportBundle\Service\CallbackFunctions;

class ComingImport {
	//This method is the default method called when no method is provided in the json.
	static function execute(&$config, &$entries) {
		print("\nComingImport execute method called\n");
	}
	//This method can be called but must be explicit mentioned in the json.
	static function customExecute($config, $entries) {
		print("\nComingImport customExecute method called\n");
	}
}
