<?php
/**
 * Example of valid json for Content type (to put in the textarea "Extra" of the content type):
 * 	{
 *		"JsonOptionFile": "C:\\PHPDEV\\prj\\ElasticMS\\var\\Migration\\json\\coming.json",
 *		"ImportSourceType": "xml",
 *		"CallbackFunction": [
 *			"ComingImport",
 *			"customExecute"
 *		],
 *		"Location": "C:\\PHPDEV\\prj\\ElasticMS\\var\\Migration\\coming_to_belgium\\themas",
 *		"Root": "C:\\PHPDEV\\prj\\ElasticMS\\var\\Migration",
 *		"Mode": "Erase",
 *		"Strip": "true"
 *	}
 *
 * Example of valid json for field (to put in the textarea "Extra" in the tab "Extra" of the options of a field):
 * 
 *	{
 *  	"JsonOptionFile": "C:\PHPDEV\prj\ElasticMS\var\Migration\json\coming_name.json",
 *	 	"CallbackFunction": "ComingNameImport",
 *  	"Default": "Name",
 *		"Mode": "Erase",
 *	}
 * 
 *  CallbackFunctions (Must be classes in fact :-) must be placed in EmsImportBundle\Service\CallbackFunctions (examples available)
 *  
 *  Example of command:
 *  php bin\console import:parse coming chco_import -v
 * 
 */
namespace EmsImportBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EmsImportBundle\Service\CreateElasticEntryService;
use EmsImportBundle\Service\CallbackFunctionService;
use EmsImportBundle\Service\ExtractJsonConfigService;
use EmsImportBundle\Service\ParsingTreeService;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
//use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
require 'vendor/autoload.php';

class ImportCommand extends ContainerAwareCommand {
	
	/** @var \Elasticsearch\Client $client */
	private $client;
	private $doctrine;
	private $logger;
	private $elasticIndex;
	private $contentTypeName;
	private $output;
	private $config;
	private $configExtractor;
	private $treeParser;
	private $entries;
	private $callbackFunctionLoader;
	private $callbackFunctionReport;
	private $elasticEntriesCreator;
	private $elasticEntriesCreatorReport;
	
	public function __construct(Registry $doctrine, 
								Logger $logger, 
								Client $client, 
								ExtractJsonConfigService $configExtractor,
								ParsingTreeService $parsingTreeService,
								CallbackFunctionService $callbackFunctionLoader,
								CreateElasticEntryService $elasticEntriesCreator)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
		$this->client = $client;
		$this->configExtractor = $configExtractor;
		$this->treeParser = $parsingTreeService;
		$this->callbackFunctionLoader = $callbackFunctionLoader;
		$this->elasticEntriesCreator = $elasticEntriesCreator;
		parent::__construct();
	}
	
	protected function configure()
	{
		$this->setName('import:parse')
            ->setDescription('Parse a sopurce repository to extract content into CT')
            ->addArgument(
                'contentTypeExactName',
                InputArgument::REQUIRED,
                'Content type name to import into'
            )
            ->addArgument(
                'elasticsearchIndex',
                InputArgument::REQUIRED,
                'Elasticsearch index where to find the ContentType to import into'
            )
        ;
	}
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->contentTypeName = $input->getArgument('contentTypeExactName');
    	$this->elasticIndex = $input->getArgument('elasticsearchIndex');
    	$this->output = $output;
    	//TODO Validate input provided.
    	
   		// Load Config
   		$this->config = $this->configExtractor->getConfig($this->contentTypeName);
		$this->output->writeln("\nConfig:");
		print_r($this->config);
		//TODO: Decision based on Config
   		if(isset($this->config["CT"]) && !empty($this->config["CT"])) {
			$config = json_decode($this->config["CT"], true);
   			if(isset($config["JsonOptionFile"]) && 
   					!empty($config["JsonOptionFile"])) {
   				//TODO/ Load the json file			
   			}
   			
   			// Parsing the Tree
   		   	if(isset($config["Location"]) && 
   					!empty($config["Location"])) {
		   		// Getting Entries
				$this->output->writeln("\nEntries:");
   				$this->entries = $this->treeParser->processDir($config["Location"], $config);
//				print_r($this->entries);
   			}
   			
   		}
   		// CallbackFunctions (Must be classes in fact :-), placed in EmsImportBundle\Service\CallbackFunctions (examples available)
		$this->output->writeln("\nCallbackFunction:");
		$this->callbackFunctionReport = $this->callbackFunctionLoader->execute($this->config, $this->entries);
		
   		// Export into Elastic
		$this->output->writeln("\nImport Elastic:");
		$this->elasticEntriesCreatorReport = $this->elasticEntriesCreator->execute($this->contentTypeName, 
																				   $this->elasticIndex,
																				   $this->config,
																				   $this->entries);
		
		$this->output->writeln("\nDone!");
	}
}