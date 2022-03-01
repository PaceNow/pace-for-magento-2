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
        $this->_config = $configData;
        $this->_registry = $registry;
        parent::__construct($context);
    }

    public function getProductPrice()
    {
        $this->_product = $this->_registry->registry('current_product');

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
        
        return $categories;
    }

    // No styles rendered if price does not fall within range and no fallback is set too.
    public function getSingleProductContainerStyle()
    {
        $fallbackWidget = $this->_config->getConfigValue(ConfigData::CONFIG_FALLBACK_WIDGET);
        $paymentPlan = $this->_config->getPaymentPlan();

        if (!$paymentPlan || !isset($paymentPlan['paymentPlans'])) {
            return "display: none;";
        }

        $paymentPlan = $paymentPlan['paymentPlans'];
        $minAmount = $paymentPlan->minAmount->actualValue;
        $maxAmount = $paymentPlan->maxAmount->actualValue;
        $productPrice = $this->getProductPrice();
        $style = $this->_config->getConfigValue(ConfigData::CONFIG_SINGLE_PRODUCT_CONTAINER_STYLE);
        if (($productPrice < $minAmount || $productPrice > $maxAmount) && !$fallbackWidget) {
            $style = "display: none;";
        } else if (!isset($style)) {
            $style = "";
        }

        return $style;
    }
}
