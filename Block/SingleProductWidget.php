<?php

namespace Pace\Pay\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

use Pace\Pay\Helper\ConfigData;

class SingleProductWidget extends Template
{
    /**
     * var $_config
     * Pace\Pay\Helper\ConfigData
     */
    protected $_config;

    /**
     * var $_objectManager
     * Magento\Framework\App\ObjectManager
     */
    protected $_registry;

    /**
     * var $_product;
     */
    protected $_product;

    public function __construct(
        Context $context,
        Registry $registry,
        ConfigData $configData
    ) {
        parent::__construct($context);

        $this->_config = $configData;
        $this->_registry = $registry;
        $this->_product = $this->_registry->registry('current_product');
    }

    public function getProductPrice()
    {
        return $this->_product->getFinalPrice();
    }


    /**
     * Check whether the product category is on the blacklist
     * 
     * @since 1.0.7
     */
    public function isBlacklisted()
    {
        $categories = $this->_product->getCategoryIds();
        $blacklisted = $this->_config->getConfigValue(ConfigData::CONFIG_BLACK_LISTED);

        if (empty($categories) || empty($blacklisted)) {
            return 0;
        }

        return count(array_intersect($categories, explode(',', $blacklisted)));
    }
}
