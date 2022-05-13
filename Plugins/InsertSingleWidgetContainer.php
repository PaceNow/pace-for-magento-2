<?php

namespace Pace\Pay\Plugins;

use Magento\Catalog\Model\Product;

use Pace\Pay\Helper\ConfigData;

class InsertSingleWidgetContainer
{
    /**
     * @var Pace\Pay\Helper\ConfigData
     */
    protected $config;

    public function __construct(
        ConfigData $config
    ) {
        $this->config = $config;
    }

    /**
     * isProductInBlacklisted...
     * 
     * @return bool
     */
    protected function isProductInBlacklisted($product)
    {
        $categories = $product->getCategoryIds();
        $blacklisted = $this->config->getConfigValue(ConfigData::CONFIG_BLACK_LISTED);

        if (empty($categories) || empty($blacklisted)) {
            return 0;
        }

        return count(array_intersect($categories, explode(',', $blacklisted)));
    }

    /**
     * afterGetProductPrice...
     * 
     * @return string
     */
    public function afterGetProductPrice(
        \Magento\Catalog\Block\Product\ListProduct $subject,
        $result,
        Product $product
    ) {
        if ($this->isProductInBlacklisted($product)) {
            return $result;
        }

        $result .= "<div class=\"pace-pay_multi-products-widget-container\" data-price=\"{$product->getFinalPrice()}\"></div>";
        
        return $result;
    }
}
