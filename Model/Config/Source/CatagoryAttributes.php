<?php

namespace Elatebrain\Catalogtranslate\Model\Config\Source;

class CatagoryAttributes implements \Magento\Framework\Option\ArrayInterface
{
    /** @var \Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory  */
    protected $_collectionFactory;

    /**
     * CategoryAttribute constructor.
     * @param \Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory $collectionFactory
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory $collectionFactory
    )
    {
        $this->_collectionFactory = $collectionFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $attributes   = $this->_collectionFactory->create();
        $attributes->addFieldToFilter("backend_type", array("in" => array("varchar", "text", "textarea")));
        $attributes->addFieldToFilter("frontend_input", array("in" => array("text", "textarea")));
        $attributes->addFieldToFilter("attribute_code", array("nin" => array("all_children", "children", "custom_layout_update", "path_in_store", "url_path")));
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
