<?php

namespace Pace\Pay\Plugins;

use Magento\Catalog\Model\Product;

use Psr\Log\LoggerInterface;

use Pace\Pay\Helper\ConfigData;

/**
 * Class modidfy Product price view
 */
class PaceProductPlugin
{
    /**
     * var $_config
     * Pace\Pay\Helper\ConfigData
     */
    protected $_config;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    public function __construct(
        ConfigData $config,
        LoggerInterface $logger
    ) {
        $this->_config = $config;
        $this->_logger = $logger;
    }

    protected function isProductInBlacklisted($product)
    {
        $categories = $product->getCategoryIds();
        $blacklisted = $this->_config->getConfigValue(ConfigData::CONFIG_BLACK_LISTED);

        if (empty($categories) || empty($blacklisted)) {
            return 0;
        }

        return count(array_intersect($categories, explode(',', $blacklisted)));
    }

    public function afterGetProductPrice(
        \Magento\Catalog\Block\Product\ListProduct $subject,
        $result,
        Product $product
    ) {
        if ($this->isProductInBlacklisted($product)) {
            return $result;
        }

        $result = $result .
        sprintf('<div class="pace-pay_multi-products-widget-container" data-price="%f"></div>', $product->getFinalPrice());

        return $result;
    }
}
