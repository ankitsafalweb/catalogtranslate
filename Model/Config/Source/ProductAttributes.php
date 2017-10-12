<?php

namespace Elatebrain\Catalogtranslate\Model\Config\Source;

class ProductAttributes implements \Magento\Framework\Option\ArrayInterface
{
    /** @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory  */
    protected $_collectionFactory;

    /**
     * ProductAttribute constructor.
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $collectionFactory
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $collectionFactory
    )
    {
        $this->_collectionFactory = $collectionFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $attributes   = $this->_collectionFactory->create()->addVisibleFilter();
        $attributes->addFieldToFilter("backend_type", array("in" => array("varchar", "text", "textarea")));
        $attributes->addFieldToFilter("frontend_input", array("in" => array("text", "textarea")));
        $attributes->addFieldToFilter("attribute_code", array("nin" => array("custom_layout_update", "recurring_profile")));
        $attributes->setOrder("attribute_code", "ASC");
        $arrAttribute = [
            [
                'label' => __('-- Please select --'),
                'value' => '',
            ],
        ];

        foreach ($attributes as $attribute) {
            $arrAttribute[] = [
                'label' => $attribute->getFrontendLabel(),
                'value' => $attribute->getAttributeCode()
            ];
        }

        return $arrAttribute;
    }
}
