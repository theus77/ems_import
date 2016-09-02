<?php
namespace EmsImportBundle\Command;

use AppBundle\Entity\ContentType;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
//use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
require 'vendor/autoload.php';

class CopyOfImportCommand extends ContainerAwareCommand {
	
	/** @var \Elasticsearch\Client $client */
	private $client;
	private $doctrine;
	private $logger;
	private $container;
	private $elasticIndex;
	private $contentTypeInstitution;
	private $contentTypeName;
	private $contentTypeType;
	private $contentTypeJson;
	private $output;
	private $paths = ["coming" => __DIR__."/../../../web/migrate/coming_to_belgium/themas/",
				   "leaving" => __DIR__."/../../../web/migrate/leaving_belgium/themas/",
				   "coming_info" => __DIR__."/../../../web/migrate/coming_to_belgium/themas/",
				   "leaving_info" => __DIR__."/../../../web/migrate/leaving_belgium/themas/",
				   "convention" => __DIR__."/../../../web/migrate/coming_to_belgium/themas/",
				   "country" => __DIR__."/../../../web/migrate/criteria/",
				   "nationality" => __DIR__."/../../../web/migrate/criteria/",
				   "coming_status" => __DIR__."/../../../web/migrate/criteria/",
				   "coming_subject" => __DIR__."/../../../web/migrate/criteria/",
				   "leaving_status" => __DIR__."/../../../web/migrate/criteria/",
				   "leaving_subject" => __DIR__."/../../../web/migrate/criteria/"
	];
	private $params;
	
	public function __construct(Registry $doctrine, Logger $logger, Client $client)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
		$this->client = $client;
		parent::__construct();
	}
	
	protected function configure()
	{
		$this->setName('import:parse')
            ->setDescription('Parse a xml repository to extract content into CT coming or leaving')
            ->addArgument(
                'contentTypeType',
                InputArgument::REQUIRED,
                'coming or leaving or coming_info or leaving_info or convention or country or nationality or coming_status or coming_subject or leaving_status or leaving_subject'
            )
            ->addArgument(
                'contentTypeExactName',
                InputArgument::REQUIRED,
                'Content type name to import into'
            )
            ->addArgument(
                'institutionName',
                InputArgument::REQUIRED,
                'ContentType name of the institution'
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
		$this->client = ClientBuilder::create()           // Instantiate a new ClientBuilder
//			->setHosts(array("http://es1.smals.scloud.be:80"))      // Set the hosts
			->setHosts(array("http://127.0.0.1:9200"))      // Set the hosts
//			->setHosts(array("http://10.4.162.129:9200"))      // Set the hosts to port-mdk
			->build();              // Build the client object
    	$this->contentTypeType = $input->getArgument('contentTypeType');
		$this->contentTypeName = $input->getArgument('contentTypeExactName');
    	$this->contentTypeInstitution = $input->getArgument('institutionName');
    	$this->elasticIndex = $input->getArgument('elasticsearchIndex');
    	$this->output = $output;
    	
    	/** @var EntityManager $em */
    	$em = $this->doctrine->getManager();
    	/** @var \AppBundle\Repository\ContentTypeRepository $contentTypeRepository */
    	$contentTypeRepository = $em->getRepository('AppBundle:ContentType');
    	/** @var \AppBundle\Entity\ContentType $contentTypeTo */
    	$contentTypeTo = $contentTypeRepository->findOneBy(array("name" => $this->contentTypeName, 'deleted' => false));
    	if(!$contentTypeTo) {
    		$this->output->writeln("<error>Content type ".$this->contentTypeName." not found</error>");
    		exit;
    	} else {
    		//TEST to access the "extra" fields of the CT and the "extraOptions" of eache fields. It Works!
    		$this->output->writeln("Content type Json : ".$contentTypeTo->getExtra());
    		$this->processChildren($contentTypeTo->getFieldType());//Recursion on each fields
    	}
    	 
//		$this->processDir($this->paths[$input->getArgument('contentTypeType')], $input->getArgument('contentTypeType'));
		$this->output->writeln("\nDone!");
	}

	protected function processDir($sourceDir, $site) {
		//Parsing the directory tree
		if(is_dir($sourceDir)) {
			$currentDir = scandir($sourceDir);
			foreach ($currentDir as $key => $value)	{
				$this->params = [];
				if (!in_array($value,array(".", "..", ".DS_Store")))	{
					if (is_dir($sourceDir . "/" . $value)) {
						$this->params["label"] = $value;
						$this->params["label_fr"] = $value;
						$this->params["label_nl"] = $value;
						$this->params["label_de"] = $value;
						$this->params["label_en"] = $value;
						$this->params["color"] = "#".sprintf("%02X%02X%02X", mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
						if(!$this->client->exists([
								"index" => $this->elasticIndex,
								"type" => $this->contentTypeInstitution,
								"id" => $value,
						])) {
							$this->client->index([
									"index" => $this->elasticIndex,
									"type" => $this->contentTypeInstitution,
									"id" => $value,
									"body" => $this->params
							]);
						}
						$this->processDir($sourceDir . "/" . $value, $site);//Recursive
					}	else {
						if((strpos($site, "coming_") === 0 || strpos($site, "country") || strpos($site, "nationality")) && 
								strpos($value, "c2b") === false && $site != "coming_info" && $site != "leaving_info") {
							continue;
						}
						if(strpos($site, "leaving_") === 0 && strpos($value, "LB") === false && $site != "coming_info" && $site != "leaving_info") {
							continue;
						}
						if(strpos($value, ".xml") !== false ) {
//							$this->output->writeln("\n".$sourceDir . "/" . $value."\n");
							//Open xml file
							if($xml = file_get_contents($sourceDir . "/" . $value)){
								if($xmlStructure = simplexml_load_string($xml)){
									if($site == "coming" || $site == "leaving" ||
									   $site == "coming_info" || $site == "leaving_info" || $site == "convention") {
									   	$id = substr($value, 0, -4);
										$this->params["name"] = ucfirst(strtolower(str_replace("_", " ", $id))); 
										$sourceDirArray = explode("/",$sourceDir);
										$this->params["institutions"][] = "institution:smals";
										$this->params["institutions"][] = "institution:".end($sourceDirArray);
										
										if($site == "coming" || $site == "leaving" ) {
											$this->params["color"] = "#".sprintf("%02X%02X%02X", mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
											if(isset($xmlStructure->metadatas) && $xmlStructure->metadatas == "") {//Skips info pages
												continue;
											}
											if(isset($xmlStructure->metadatas->external_links) && $xmlStructure->metadatas->external_links != "") {
												//TODO
												//$this->params["external_links"] = strip_tags($xmlStructure->metadatas->external_links->asXML());//Example : <external_links>f5547dff555bb3090083ce25436d89f5,9ecfafbb555bb309005d50c4c7982a65,bbc3ed6e555bb30600ad89ec9ec1e77e</external_links>
												$this->params["external_links"] = [];
											} else {
												$this->params["external_links"] = [];
											}
											if(isset($xmlStructure->metadatas->hangs) && $xmlStructure->metadatas->hangs != "") {
												//$this->params["hangs"] = strip_tags($xmlStructure->metadatas->hangs->asXML());
											} else {
												$this->params["hangs"] = [];
											}
											if(isset($xmlStructure->contents->fr->summary->html->body) && $xmlStructure->contents->fr->summary->html->body != "") {
											$this->params["summary_fr"] = $xmlStructure->contents->fr->summary->html->body->asXML();
											} else {
												$this->params["summary_fr"] = "";
											}
											if(isset($xmlStructure->contents->nl->summary->html->body) && $xmlStructure->contents->nl->summary->html->body != "") {
												$this->params["summary_nl"] = $xmlStructure->contents->nl->summary->html->body->asXML();
											} else {
												$this->params["summary_nl"] = "";
											}
											if(isset($xmlStructure->contents->de->summary->html->body) && $xmlStructure->contents->de->summary->html->body != "") {
												$this->params["summary_de"] = $xmlStructure->contents->de->summary->html->body->asXML();
											} else {
												$this->params["summary_de"] = "";
											}
											$criteriaResult = $this->getCriteria($sourceDir . "/" . $value);
											$this->params["criteria"] = $criteriaResult['criteria'];
										}
										if($site == "coming_info" || $site == "leaving_info" || $site == "convention" ) {
											if(isset($xmlStructure->metadatas) && $xmlStructure->metadatas != "") {
												continue;
											}
											if(($site == "coming_info" || $site == "leaving_info" ) && strpos($id, "_Convention_") !== false) {//Skip if it is a convention
												continue;
											}
											if($site == "convention" && strpos($id, "_Convention_") === false) {//Skip if it is an info page
												continue;
											}
											if(isset($xmlStructure->contents->fr->hang) && $xmlStructure->contents->fr->hang != "") {
												$this->params["hang_fr"] = [strip_tags($xmlStructure->contents->fr->hangs->asXML())];
											} else {
												$this->params["hang_fr"] = "";
											}
											if(isset($xmlStructure->contents->fr->hang) && $xmlStructure->contents->nl->hang != "") {
												$this->params["hang_nl"] = [strip_tags($xmlStructure->contents->nl->hangs->asXML())];
											} else {
												$this->params["hang_nl"] = "";
											}
											if(isset($xmlStructure->contents->en->hang) && $xmlStructure->contents->en->hang != "") {
												$this->params["hang_en"] = [strip_tags($xmlStructure->contents->en->hangs->asXML())];
											} else {
												$this->params["hang_en"] = "";
											}
											if(isset($xmlStructure->contents->de->hang) && $xmlStructure->contents->de->hang != "") {
												$this->params["hang_de"] = [strip_tags($xmlStructure->contents->de->hangs->asXML())];
											} else {
												$this->params["hang_de"] = "";
											}
										}
										if(isset($xmlStructure->contents->fr->title) && $xmlStructure->contents->fr->title != "") {
											$this->params["title_fr"] = strip_tags($xmlStructure->contents->fr->title->asXML());
										} else {
											$this->params["title_fr"] = "";
										}
										if(isset($xmlStructure->contents->nl->title) && $xmlStructure->contents->nl->title != "") {
											$this->params["title_nl"] = strip_tags($xmlStructure->contents->nl->title->asXML());
										} else {
											$this->params["title_nl"] = "";
										}
										if(isset($xmlStructure->contents->de->title) && $xmlStructure->contents->de->title != "") {
											$this->params["title_de"] = strip_tags($xmlStructure->contents->de->title->asXML());
										} else {
											$this->params["title_de"] = "";
										}
										if(isset($xmlStructure->contents->en->title) && $xmlStructure->contents->en->title != "") {
											$this->params["title_en"] = strip_tags($xmlStructure->contents->en->title->asXML());
										} else {
											$this->params["title_en"] = "";
										}
										if(isset($xmlStructure->contents->fr->introduction->html->body) && $xmlStructure->contents->fr->introduction->html->body != "") {
											$this->params["introduction_fr"] = $xmlStructure->contents->fr->introduction->html->body->asXML();
										} else {
											$this->params["introduction_fr"] = "";
										}
										if(isset($xmlStructure->contents->nl->introduction->html->body) && $xmlStructure->contents->nl->introduction->html->body != "") {
											$this->params["introduction_nl"] = $xmlStructure->contents->nl->introduction->html->body->asXML();
										} else {
											$this->params["introduction_nl"] = "";
										}
										if(isset($xmlStructure->contents->de->introduction->html->body) && $xmlStructure->contents->de->introduction->html->body != "") {
											$this->params["introduction_de"] = $xmlStructure->contents->de->introduction->html->body->asXML();
										} else {
											$this->params["introduction_de"] = "";
										}
										if(isset($xmlStructure->contents->en->introduction->html->body) && $xmlStructure->contents->en->introduction->html->body != "") {
											$this->params["introduction_en"] = $xmlStructure->contents->en->introduction->html->body->asXML();
										} else {
											$this->params["introduction_en"] = "";
										}
										if(isset($xmlStructure->contents->fr->description->html->body) && $xmlStructure->contents->fr->description->html->body != "") {
											$this->params["body_fr"] = $xmlStructure->contents->fr->description->html->body->asXML();
										} else {
											$this->params["body_fr"] = "";
										}
										if(isset($xmlStructure->contents->nl->description->html->body) && $xmlStructure->contents->nl->description->html->body != "") {
											$this->params["body_nl"] = $xmlStructure->contents->nl->description->html->body->asXML();
										} else {
											$this->params["body_nl"] = "";
										}
										if(isset($xmlStructure->contents->de->description->html->body) && $xmlStructure->contents->de->description->html->body != "") {
											$this->params["body_de"] = $xmlStructure->contents->de->description->html->body->asXML();
										} else {
											$this->params["body_de"] = "";
										}
										if(isset($xmlStructure->contents->en->description->html->body) && $xmlStructure->contents->en->description->html->body != "") {
											$this->params["body_en"] = $xmlStructure->contents->en->description->html->body->asXML();
										} else {
											$this->params["body_en"] = "";
										}
										switch($site){
											case "coming";
												if(isset($xmlStructure->metadatas->internal_links_C2B) && $xmlStructure->metadatas->internal_links_C2B != "") {
													$this->params["internal_links_c2b"] = strip_tags($xmlStructure->metadatas->internal_links_C2B->asXML());
												} else {
													$this->params["internal_links_c2b"] = [];
												}
												if(isset($xmlStructure->contents->fr->external_links_wb) && $xmlStructure->contents->fr->external_links_wb != "") {
													$this->params["external_links_wb_fr"] = strip_tags($xmlStructure->contents->fr->external_links_wb->asXML());
												} else {
													$this->params["external_links_wb_fr"] = [];
												}
												if(isset($xmlStructure->contents->nl->external_links_wb) && $xmlStructure->contents->nl->external_links_wb != "") {
													$this->params["external_links_wb_nl"] = strip_tags($xmlStructure->contents->nl->external_links_wb->asXML());
												} else {
													$this->params["external_links_wb_nl"] = [];
												}
												if(isset($xmlStructure->contents->de->external_links_wb) && $xmlStructure->contents->de->external_links_wb != "") {
													$this->params["external_links_wb_de"] = strip_tags($xmlStructure->contents->de->external_links_wb->asXML());
												} else {
													$this->params["external_links_wb_de"] = [];
												}
												if(isset($xmlStructure->contents->en->external_links_wb) && $xmlStructure->contents->en->external_links_wb != "") {
													$this->params["external_links_wb_en"] = strip_tags($xmlStructure->contents->en->external_links_wb->asXML());
												} else {
													$this->params["external_links_wb_en"] = [];
												}
												if(isset($xmlStructure->contents->en->summary->html->body) && $xmlStructure->contents->en->summary->html->body != "") {
													$this->params["summary_en"] = $xmlStructure->contents->en->summary->html->body->asXML();
												} else {
													$this->params["summary_en"] = "";
												}
												$this->params["subject"] = "coming_subject:".$criteriaResult['subject'];
												break;
											case "leaving":
												if(isset($xmlStructure->metadatas->internal_links_LB) && $xmlStructure->metadatas->internal_links_LB != "") {
													$this->params["internal_links_lb"] = strip_tags($xmlStructure->metadatas->internal_links_LB->asXML());
												} else {
													$this->params["internal_links_lb"] = [];
												}
												if(isset($xmlStructure->metadatas->internal_links_citizen) && $xmlStructure->metadatas->internal_links_citizen != "") {
													$this->params["internal_links_citizen"] = strip_tags($xmlStructure->metadatas->internal_links_citizen->asXML());
												} else {
													$this->params["internal_links_citizen"] = [];
												}
												$this->params["subject"] = "leaving_subject:".$criteriaResult['subject'];
											break;
											case "coming_info";
											case "leaving_info";
											case "convention";
											break;
										}
										$this->output->write(".");
									   		$this->client->index([
												"index" => $this->elasticIndex,
												"id" => $id,
												"type" => $this->contentTypeName,
												"body" => $this->params
										]);
									}//End coming||leaving||coming_info||leaving_info||convention
									else {
										if(strpos($this->contentTypeType, "country") !== false || 
										   strpos($this->contentTypeType, "nationality") !== false) {//Country + Nationality
											if(strpos($this->contentTypeType, "country") !== false) {
												$xmlGroup = $xmlStructure->destination;
											} else {
												$xmlGroup = $xmlStructure->nationality;
											}
											foreach ($xmlGroup->children() as $group) {
												if($group->getName() == "country") {
													$this->params = [];
													$this->params['group'] = "";
													$this->importCountry($group);
												} else {
													$groups2BTreated = [];
													$first_attr = $group->attributes();
													if($first_attr == "group_1") {
														foreach($group as $subGroup){
															if($subGroup->getName() == "country") {
																$this->params = [];
																$this->params['group'] = "";
																$this->importCountry($subGroup);
															} else {
																$id = $this->getID($subGroup);
																$groups2BTreated[$id] = $subGroup;
															}
														}
														$id = $this->getID($group);
														$groups2BTreated[$id] = $group;
													} else {
														$id = $this->getID($group);
														$groups2BTreated[$id] = $group;
													}
													foreach ($groups2BTreated as $groupName => $group2BTreated) {
														if($group2BTreated->getName() == "country") {
															$this->params = [];
															$this->params['group'] = "";
															$this->importCountry($group2BTreated);
														} else {
															$this->params = [];
															$this->params['group'] = $groupName;
															foreach ($group2BTreated->children() as $groupChild){
																$this->importCountry($groupChild);
															}
														}
													}
												}
											}//End Country||Nationality
										} else {//Status + Subject
											dump("begin");
											if(strpos($this->contentTypeType, "status") !== false) {
												$xmlGroup = $xmlStructure->status;
											} else {
												$xmlGroup = $xmlStructure->subject;
											}
											foreach ($xmlGroup->children() as $groupChild){
												foreach ($groupChild->attributes() as $groupChildAttrName => $groupChildAttrvalue) {
													if($groupChildAttrName == "id") {
														$this->params["key"] = strtolower(str_replace(["\"", " "], ["", "_"],substr($groupChildAttrvalue->asXML(), 4)));
													} else {
														$this->params["label_".$groupChildAttrName] = str_replace("\"", "",substr($groupChildAttrvalue->asXML(), 4));
													}
												}
												$this->output->write(".");
												$this->client->index([
														"index" => $this->elasticIndex,
														"id" => $this->params["key"],
														"type" => $this->contentTypeName,
														"body" => $this->params
												]);
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
	protected  function importCountry($country) {
		foreach ($country->attributes() as $countryAttrName => $countryAttrvalue) {
			if($countryAttrName != "id") {
				$this->params["label_".$countryAttrName] = str_replace("\"", "",substr($countryAttrvalue->asXML(), 4));
			} else {
				$this->params["key"] = strtolower(str_replace(["\"", " "], ["", "_"],substr($countryAttrvalue->asXML(), 4)));
			}
		}
		$this->output->write(".");
		$this->client->index([
				"index" => $this->elasticIndex,
				"id" => $this->params["key"],
				"type" => $this->contentTypeName,
				"body" => $this->params
		]);
		
	}
	
	protected function getId($group) {
		foreach ($group->attributes() as $attrName => $attrvalue) {
			if ($attrName == "id") {
				return str_replace("\"", "",substr($attrvalue->asXML(), 4));
			}
		}
		return "";
	}
	protected function 	getCriteria($fileName) {
		
		$criteria = [];
		$criteriaFileName = str_replace(["themas", ".xml"],["criteria", "_criteria.xml"], $fileName);
		$subject = "";
//		dump($criteriaFileName);
		if(is_file($criteriaFileName)) {
			if($xmlCriteriaFile = file_get_contents($criteriaFileName)){
				if($xmlCriteriaStructure = simplexml_load_string($xmlCriteriaFile)){
					foreach($xmlCriteriaStructure->attributes() as $attributeName => $attributeValue) {
						if($attributeName == "subject") {
							$subject = strtolower(str_replace(["subject=\"", "\"", " "], ["", "", "_"],trim($attributeValue->asXML())));
							continue;
						}
					}
					if($subject == ""){
						dump("\nsubject not found in: ".$criteriaFileName."\n");
					}
					foreach ($xmlCriteriaStructure->children() as $combili) {
						$destination = strip_tags($combili->destination->asXML());
						$nationalities = explode(";", strip_tags($combili->nationality->asXML()));
						array_walk($nationalities, function(&$item, $key){$item = "nationality:".$item;});
						$status = strip_tags($combili->status->asXML());
						$criteria[] = ["country" => ["country:".$destination], 
									   "nationalities" => $nationalities, 
									   "status" => [$this->contentTypeType."_status:".$status]
									  ];
					}
				}
			}
		}
		return ["criteria" => $criteria, "subject" => $subject];
	}
	protected function addNationality(&$item, $key)
	{
		$item = "nationality:".$item;
	}
	
	protected function processChildren($fieldType)
	{
   		$children = $fieldType->getChildren();
   		foreach ($children as $child) {
   			$this->processChildren($child);
   			$options = $child->getOptions();
   			if(isset($options["extraOptions"]) && $options["extraOptions"]["extra"] != "") {
   				$this->output->writeln($child->getName());
   				$this->output->writeln($options["extraOptions"]);
   			}
   		}
	}
}