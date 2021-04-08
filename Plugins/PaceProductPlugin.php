<?php

namespace Pace\Pay\Plugins;

use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;

/**
 * Class modidfy Product price view
 */
class PaceProductPlugin
{
    /**
     * @var LoggerInterface
     */
    protected $_logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->_logger = $logger;
    }

    public function afterGetProductPrice(
        \Magento\Catalog\Block\Product\ListProduct $subject,
        $result,
        Product $product
    ) {
        $result = $result .
        sprintf('<div class="pace-pay_multi-products-widget-container" data-price="%f"></div>', $product->getFinalPrice());

        return $result;
    }
}
