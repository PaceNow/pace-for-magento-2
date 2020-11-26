<?php

namespace Pace\Pay\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\ObjectManager;
use Pace\Pay\Helper\ConfigData;

class SingleProductWidget extends Template
{
    public function __construct(
        Template\Context $context,
        ConfigData $configData
    ) {
        parent::__construct($context);
        $this->_config = $configData;
    }

    public function getProductPrice()
    {
        $objectManager = ObjectManager::getInstance();
        $registry = $objectManager->get('\Magento\Framework\Registry');
        $currentProduct = $registry->registry('current_product');

        return $currentProduct->getFinalPrice();
    }

    // No styles rendered if price does not fall within range and no fallback is set too.
    public function getSingleProductContainerStyle()
    {
        $fallbackWidget = $this->_config->getConfigValue(ConfigData::CONFIG_FALLBACK_WIDGET);
        $paymentPlan = $this->_config->getPaymentPlan();

        if (!isset($paymentPlan)) {
            return "display: none;";
        }

        $minAmount = $paymentPlan['minAmount'];
        $maxAmount = $paymentPlan['maxAmount'];
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
