<?php

namespace Pace\Pay\Block;

use Magento\Framework\View\Element\Template;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\ObjectManager;
use Pace\Pay\Helper\ConfigData;

class SingleProductWidget extends Template
{
    protected $product;
    protected $currency;

    public function __construct(
        Template\Context $context,
        Product $product,
        ConfigData $config
    ) {
        parent::__construct($context);
        $this->product = $product;
        $this->config = $config;
    }

    public function getProductPrice()
    {
        $objectManager = ObjectManager::getInstance();
        $registry = $objectManager->get('\Magento\Framework\Registry');
        $currentProduct = $registry->registry('current_product');

        return $currentProduct->getFinalPrice();
    }

    public function getSingleProductContainerStyle()
    {
        $style = $this->config->getConfigValue(ConfigData::CONFIG_SINGLE_PRODUCT_CONTAINER_STYLE);
        if (!isset($style)) {
            $style = "";
        }

        return $style;
    }
}
