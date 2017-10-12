<?php
namespace Elatebrain\Catalogtranslate\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Google\Cloud\Translate\TranslateClient;

class Translate extends Command
{
    private $_storeManager = null;
    private $_storeEmmulator = null;
    private $_translateClient = null;

    private $_apiKey = null;

    private $_productCollectionFactory = null;
    private $_productLoader = null;

    private $_categoryCollectionFactory = null;
    private $_categoryLoader = null;

    private $_debugMode = false;
    private $_dryMode = false;

    public function __construct(
        \Magento\Framework\App\State $appState,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\ProductRepository $productloader,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\CategoryRepository $categoryloader,
        $name=null
    )
    {
        $appState->setAreaCode('frontend');
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_productLoader = $productloader;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_categoryLoader = $categoryloader;
        parent::__construct($name);
    }

    /*public function __construct(
        $name = null,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->_storeManager = $storeManager;
        return parent::__construct($name);
    }*/

    protected function configure()
    {
        $this->setName('inchoo:hello_world')->setDescription('Prints hello world.');
        $this->addArgument("source_store_view_code",InputArgument::REQUIRED,"Enter Source Store View Code for source language");
        $this->addArgument("target_store_view_code",InputArgument::REQUIRED,"Enter Target Store View Code for target language");
        $this->addArgument("type",InputArgument::REQUIRED,"Enter type as 'product' or 'category' to translate.");
        $this->addArgument("ids",InputArgument::OPTIONAL,"Enter 'product' or 'category' IDs (Comma separated for multiple IDs) to translate specific or enter 'all' for all 'products' or 'categores'. Default will be all.", 'all');
        $this->addArgument("mode",InputArgument::OPTIONAL,"Enter 'debug' to debug translation process. Or Enter 'dry' to translates everything (automatically enables debug also) but do not save anything on the database.", false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectManager = $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create("Elatebrain\Catalogtranslate\Helper\Data");
        $this->_storeManager = $objectManager->create("Magento\Store\Model\StoreManagerInterface");
        $sourceStoreCode = $input->getArgument('source_store_view_code');
        $targetStoreCode = $input->getArgument('target_store_view_code');
        $objectIds = $input->getArgument('ids');

        if($input->getArgument('mode') == "debug"){
            $this->_debugMode = true;
        }

        if($input->getArgument('mode') == "dry"){
            $this->_debugMode = true;
            $this->_dryMode = true;
        }

        $this->_apiKey = $helper->getApiKey();

        if(trim($this->_apiKey) == ""){
            $output->writeln("Please add google api key in system configurations.");
            return;
        }

        $sourceStoreLang = $targetStoreLang = null;

        if(($sourceStoreCode = trim($sourceStoreCode)) != null){
            try{
                $sourceStore = $this->_storeManager->getStore($sourceStoreCode);
                if(($sourceStoreId = $sourceStore->getStoreId()) != null){
                    $resolver = $objectManager->create('Magento\Framework\Locale\Resolver');
                    $resolver->emulate($sourceStore->getStoreId());
                    if($resolver->getLocale() != null){
                        $sourceStoreLang = substr($resolver->getLocale(), 0, 2);
                    }
                    $resolver->revert();
                }
            }
            catch(\Exception $e){
                $output->writeln($e->getMessage()." as source store.");
            }
        }

        if(($targetStoreCode = trim($targetStoreCode)) != null){
            try{
                $targetStore = $this->_storeManager->getStore($targetStoreCode);
                if(($targetStoreId = $targetStore->getStoreId()) != null){
                    $resolver = $objectManager->create('Magento\Framework\Locale\Resolver');
                    $resolver->emulate($targetStore->getStoreId());
                    if($resolver->getLocale() != null){
                        $targetStoreLang = substr($resolver->getLocale(), 0, 2);
                    }
                    $resolver->revert();
                }
            }
            catch(\Exception $e){
                $output->writeln($e->getMessage()." as target store.");
            }
        }

        if($sourceStoreLang != null && $targetStoreLang != null){
            $catalogType = $input->getArgument('type');
            if(($catalogType = trim($catalogType)) === "product"){
                if(trim($helper->getProductAttributes()) == ""){
                    $output->writeln("Please select at least one product attribute in system configurations.");
                    return;
                }

                $output->writeln("You requested to translate '".$catalogType."' from store language '".$sourceStoreLang."' to '".$targetStoreLang."'.");

                $productAttributes = explode(",",$helper->getProductAttributes());
                $productAttributes = array_filter($productAttributes,function($elements){return (trim($elements) != null);});

                $collection = $this->_productCollectionFactory->create();
                if($objectIds != "all"){
                    $objectIds = explode(",",$objectIds);
                    if(is_array($objectIds) && count($objectIds) > 0) {
                        $objectIdsArgs = array();
                        foreach($objectIds as $objectId){
                            if(trim($objectId) != null){
                                $objectIdsArgs[] = trim($objectId);
                            }
                        }
                        $objectIdsArgs = array_unique($objectIdsArgs);
                        $collection->addAttributeToFilter('entity_id',array("in"=>$objectIdsArgs));
                    }
                    else{
                        $output->writeln("Invalid argument value passed for 'ids', You should pass comma separated ids for products or categories Or leave it blank to translate all products and categories.");
                    }
                }

                $productAttrValues = array();
                foreach($collection as $product){
                    $product = $this->_productLoader->getById($product->getId(), false, $sourceStoreId);
                    $output->writeln("Translating SKU '".$product->getSku()."' from store language '".$sourceStoreLang."' to '".$targetStoreLang."'.");
                    foreach($productAttributes as $productAttribute){
                        if(trim($product->getData($productAttribute)) != ""){
                            if(mb_strlen($product->getData($productAttribute)) >= 5000){
                                $output->writeln("'".$productAttribute."' more than 5000 chars long, unsupported by Google Translate, not translated.");
                            }
                            elseif(trim($product->getData($productAttribute)) != null){
                                if($productAttribute == "url_key"){
                                    $productAttrValues[$product->getId()][$productAttribute] = implode("-",$this->translateArray(explode("-",$product->getData($productAttribute)),$targetStoreLang));
                                }
                                else{
                                    $productAttrValues[$product->getId()][$productAttribute] = $product->getData($productAttribute);
                                }
                            }
                        }
                    }
                    $productAttrValues[$product->getId()] = $this->translateArray($productAttrValues[$product->getId()],$targetStoreLang);
                    if($this->_dryMode == false) {
                        $targetproduct = $this->_productLoader->getById($product->getId(), false, $targetStoreId);
                    }
                    foreach($productAttrValues[$product->getId()] as $key => $value){
                        if($this->_debugMode == true){
                            $output->writeln("'".$key."' [".$sourceStoreLang."] ".$product->getData($key)." -> [".$targetStoreLang."] ".$value.".");
                        }
                        if($this->_dryMode == false){
                            $targetproduct->setData($key,$value);
                        }
                    }
                    if($this->_dryMode == false){
                        if($targetproduct->save()){
                            $output->writeln("Done...");
                        }
                    }
                }
            }
            elseif(($catalogType = trim($catalogType)) === "category"){
                if(trim($helper->getCategoryAttributes()) == ""){
                    $output->writeln("Please select at least one category attribute in system configurations.");
                    return;
                }

                $output->writeln("You requested to translate '".$catalogType."' from store language '".$sourceStoreLang."' to '".$targetStoreLang."' language.");

                $categoryAttributes = explode(",",$helper->getCategoryAttributes());
                $categoryAttributes = array_filter($categoryAttributes,function($elements){return (trim($elements) != null);});

                $collection = $this->_categoryCollectionFactory->create()->setStore($sourceStoreId);

                if($objectIds != "all"){
                    $objectIds = explode(",",$objectIds);
                    if(is_array($objectIds) && count($objectIds) > 0) {
                        $objectIdsArgs = array();
                        foreach($objectIds as $objectId){
                            if(trim($objectId) != null){
                                $objectIdsArgs[] = trim($objectId);
                            }
                        }
                        $objectIdsArgs = array_unique($objectIdsArgs);
                        $collection->addAttributeToFilter('entity_id',array("in"=>$objectIdsArgs));
                    }
                    else{
                        $output->writeln("Invalid argument value passed for 'ids', You should pass comma separated ids for products or categories Or leave it blank to translate all products and categories.");
                    }
                }


                $categoryAttrValues = array();
                foreach($collection as $category){
                    $category = $this->_categoryLoader->get($category->getId(), $sourceStoreId);
                    $output->writeln("Translating Category '".$category->getName()."' from store language '".$sourceStoreLang."' to '".$targetStoreLang."'.");
                    foreach($categoryAttributes as $categoryAttribute){
                        if(trim($category->getData($categoryAttribute)) != ""){
                            if(mb_strlen($category->getData($categoryAttribute)) >= 5000){
                                $output->writeln("'".$categoryAttribute."' more than 5000 chars long, unsupported by Google Translate, not translated.");
                            }
                            elseif(trim($category->getData($categoryAttribute)) != null){
                                if($categoryAttribute == "url_key"){
                                    $categoryAttrValues[$category->getId()][$categoryAttribute] = implode("-",$this->translateArray(explode("-",$category->getData($categoryAttribute)),$targetStoreLang));
                                }
                                else{
                                    $categoryAttrValues[$category->getId()][$categoryAttribute] = $category->getData($categoryAttribute);
                                }
                            }
                        }
                    }
                    $categoryAttrValues[$category->getId()] = $this->translateArray($categoryAttrValues[$category->getId()],$targetStoreLang);
                    if($this->_dryMode == false) {
                        $targetcategory = $this->_categoryLoader->get($category->getId(), $targetStoreId);
                    }
                    foreach($categoryAttrValues[$category->getId()] as $key => $value){
                        if($this->_debugMode == true){
                            $output->writeln("'".$key."' [".$sourceStoreLang."] ".$category->getData($key)." -> [".$targetStoreLang."] ".$value.".");
                        }
                        if($this->_dryMode == false){
                            $targetcategory->setData($key,$value);
                        }
                    }
                    if($this->_dryMode == false){
                        if($targetcategory->save($targetcategory)){
                            $output->writeln("Done...");
                        }
                    }
                }
            }
            else{
                $output->writeln("Invalid argument value '".$catalogType."' passed for 'type' argument. Value must be 'product' or 'category' to translate.");
            }
        }
    }

    private function initTranslateClient()
    {
        $this->_translateClient = new TranslateClient(array("key"=>$this->_apiKey));
    }

    private function translateArray($strings, $targetLanguage)
    {
        $this->initTranslateClient();
        $inputArray = array();
        foreach($strings as $key => $string){
            $inputArray[] = $string;
        }
        $results = $this->_translateClient->translateBatch($inputArray, [
            'target' => $targetLanguage,
        ]);
        $outputArray = array();
        foreach($results as $result){
            foreach($strings as $key => $string){
                if(isset($result['input']) && trim($result['input']) == trim($string)){
                    $outputArray[$key] = $result['text'];
                }
            }
        }
        return $outputArray;
    }

    private function translateString($string,$targetLanguage)
    {
        $this->initTranslateClient();
        $result = $this->_translateClient->translate($string, [
            'target' => $targetLanguage,
        ]);
        if(is_array($result) && isset($result['text'])){
            return $result['text'];
        }
        return $string;
    }
}