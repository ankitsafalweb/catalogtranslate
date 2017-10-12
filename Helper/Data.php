<?php

namespace Elatebrain\Catalogtranslate\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @type array
     */
    protected $_data = [];

    /**
     * @type \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @type \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager
    )
    {
        $this->objectManager = $objectManager;
        $this->storeManager  = $storeManager;
        $this->scopeConfig = $context->getScopeConfig();

        parent::__construct($context);
    }

    public function getApiKey()
    {
        return $this->scopeConfig->getValue(
            "catalogtranslate/general/api_key"
        );
    }

    public function getProductAttributes()
    {
        return $this->scopeConfig->getValue(
            "catalogtranslate/general/product_attributes"
        );
    }

    public function getCategoryAttributes()
    {
        return $this->scopeConfig->getValue(
            "catalogtranslate/general/category_attributes"
        );
    }
}