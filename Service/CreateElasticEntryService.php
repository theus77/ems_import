<?php

namespace EmsImportBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Monolog\Logger;

class CreateElasticEntryService
{
	/** @var \Elasticsearch\Client $client */
	private $client;
	private $doctrine;
	private $logger;

	public function __construct(Registry $doctrine, 
								Logger $logger, 
								Client $client)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
		$this->client = $client;
//		$this->client = ClientBuilder::create()           // Instantiate a new ClientBuilder
//			->setHosts(array("http://es1.smals.scloud.be:80"))      // Set the hosts
//			->setHosts(array("http://127.0.0.1:9200"))      // Set the hosts
//			->setHosts(array("http://10.4.162.129:9200"))      // Set the hosts to port-mdk
//			->build();              // Build the client object
	}
	
	public function execute($ct,$elasticIndex, $config, $entries) {
		print("\nExecuting CreateElasticEntryService\n");
//		print_r($entries);
		//TODO: Validate the index
		foreach ($entries as $id => $entry) {
	   		$this->client->index([
				"index" => $elasticIndex,
				"id" => $id,
				"type" => $ct,
				"body" => $entry
			]);

		}
	}
}
