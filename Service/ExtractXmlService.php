<?php

namespace EmsImportBundle\Service;


class ExtractXmlService
{
	private $xmlExtract;
	
	function extract($fileName, $config, $withReference = true) {
		//TODO: Test the validity of the file
		if(strpos($fileName, ".xml") !== false ) {
			//Open xml file
			if($xml = file_get_contents($fileName)){
				if($xmlStructure = simplexml_load_string($xml)){
					// Extract the content of the xml into an associative array (xmlExtract)
					if(isset($xmlStructure->metadatas) && !empty($xmlStructure->metadatas)) {
						foreach ($xmlStructure->metadatas as $metas) {
							foreach ($metas as $key => $value) {
								$this->xmlExtract[$fileName][$key] = strip_tags($value->asXML());
								if($withReference == true) {
									$array_links = [];
									// Search reference to other CT.
									$children = $value->children();
									if(!empty($children)){//Case when keys are in tags
										foreach ($children as $child){
											$array_links[] = $child->__tostring();
										}
									}
									if(strlen($value) !== 0 && 
									   strpos($value, " ") == false &&
									   strpos($value, "<") == false) {//Case when keys are concatenated
										$array_links = explode(",", $value);
									}
									if(!empty($array_links)){
										$valid = true;
										foreach ($array_links as $link) {//Example of valid link : 2c2b1a7f555bb30601b529d6ea9e06fd
											if(strlen($link) !=32 || strpos($link, " ") !== false) {
												$valid = false;
											}
										}
										if($valid) {
											$this->xmlExtract[$fileName]["references"] = $this->extractReference($config, $fileName, $key, $array_links);
										}
									}
								}
							}
						}
					} else  {
						//TODO/ Treat xml without metadatas? Is it a Hippo extact?
					}
					if(isset($xmlStructure->contents) && !empty($xmlStructure->contents)) {
						foreach ($xmlStructure->contents as $content) {
							foreach ($content as $language => $tags) {
								foreach ($tags as $key => $value) {
									$this->xmlExtract[$fileName][$key."_".$language] = $value->asXML();
								}
							}
						}
					} else  {
						//TODO/ Treat xml without content? Is it a Hippo extact?
					}
				}
			}
		}
		return($this->xmlExtract);
	}
	
	function extractReference($config, $fileName, $key, $array_links) {
		$reference = [];
		print("\nFname : ".$fileName."\n");
		// Find the file owning the CT
		foreach ($array_links as $link) {
			$metaFileNameFound = $this->processDir($config["Root"], $link);
			// Extract reference to other CT.
			if ($metaFileNameFound != ""){
				$fileNameFound = str_replace(".meta.", ".", $metaFileNameFound);
				$reference[$link] = $this->extract($fileNameFound, $config, false);
				print("Reference found : ".$fileNameFound."\n");
			} else {
				//TODO: Throw warning exception if link on other CT.
				//TODO: Hold the link on memory to treat it later. For the case of link on the same CT.
				print("Link NOT found : ".$link."\n");
			}
		}
		return $reference;
	}
	
	function processDir($dir, $link) {
		//Parsing the directory tree
		if(is_dir($dir)) {
			$currentDir = scandir($dir);
			foreach ($currentDir as $key => $value)	{
				if (!in_array($value,array(".", "..", ".DS_Store")))	{
					if (is_dir($dir . "/" . $value)) {
						$this->processDir($dir . "/" . $value, $link);//Recursive
					}
				}
			}
			$metaFileNameFound = "";
			//Search the link in the meta files
			foreach (glob($dir."/*.meta.xml") as $file) {
				$content = file_get_contents($file);
				if (strpos($content, $link) !== false) {
					//TODO: Optimisation: Memorize the link and the file path together For reusablilty.
					return $file;
				}
			}
		}
		return "";
	}
}
