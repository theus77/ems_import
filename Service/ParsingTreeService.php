<?php

namespace EmsImportBundle\Service;

use EmsImportBundle\Service\ExtractXmlService;

class ParsingTreeService
{
	private $entries;
	private $xmlExtractor;
	
	public function __construct(ExtractXmlService $xmlExtractor)
	{
		$this->xmlExtractor = $xmlExtractor;
	}
	
	function processDir($sourceDir, $config) {
		//Parsing the directory tree
		if(is_dir($sourceDir)) {
			$currentDir = scandir($sourceDir);
			foreach ($currentDir as $key => $value)	{
				if (!in_array($value,array(".", "..", ".DS_Store")))	{
					if (is_dir($sourceDir . "/" . $value)) {
						$this->processDir($sourceDir . "/" . $value, $config);//Recursive
					} else {
						$this->entries = $this->xmlExtractor->extract($sourceDir . "/" . $value, $config);
					}
				}
			}
		}
		return $this->entries;
	}
}
