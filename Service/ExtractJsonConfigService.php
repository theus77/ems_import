<?php

namespace EmsImportBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
require 'vendor/autoload.php';

class ExtractJsonConfigService
{
	private $doctrine;
	private $logger;
	private $config;
	
	public function __construct(Registry $doctrine, Logger $logger)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
	}
	
	function getConfig($contentTypeName) {
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
	   	/** @var \AppBundle\Repository\ContentTypeRepository $contentTypeRepository */
    	$contentTypeRepository = $em->getRepository('AppBundle:ContentType');
    	/** @var \AppBundle\Entity\ContentType $contentTypeTo */
    	$contentTypeTo = $contentTypeRepository->findOneBy(array("name" => $contentTypeName, 'deleted' => false));
    	if(!$contentTypeTo) {
    		//TODO: Throw exception
    		//$this->output->writeln("<error>Content type ".$this->contentTypeName." not found</error>");
    		exit;
    	} else {
   			$this->config["CT"] = $contentTypeTo->getExtra();
    		$this->processChildren($contentTypeTo->getFieldType(), "");//Recursion on each fields
    	}
    	return($this->config);
	}

	protected function processChildren($fieldType, $parentsName)
	{
   		$children = $fieldType->getChildren();
   		foreach ($children as $child) {
   			$treePath = $parentsName."/".$child->getName();
   			$this->processChildren($child, $treePath);
   			$options = $child->getOptions();
   			if(isset($options["extraOptions"]) && $options["extraOptions"]["extra"] != "") {
  				$this->config[$treePath] = $options["extraOptions"];
   			}
			// Detect if the field is a link to an other CT.
			if($child->getType() == "AppBundle\Form\DataField\DataLinkFieldType") {
				$displayOptions = $child->getDisplayOptions();
				if(isset($displayOptions["type"]) && !empty($displayOptions["type"]))
				$this->config[$treePath]["linksTo"] = $displayOptions["type"];
			}
   		}
	}
}
