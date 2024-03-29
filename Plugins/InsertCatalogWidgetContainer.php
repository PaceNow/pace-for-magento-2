<?php

namespace Pace\Pay\Plugins;

use Magento\Catalog\Model\Product;
use Pace\Pay\Helper\ConfigData;

class InsertCatalogWidgetContainer {
	/**
	 * @var Pace\Pay\Helper\ConfigData
	 */
	protected $config;

	public function __construct(
		ConfigData $config,
		\Magento\Framework\Pricing\Helper\Data $pricingHelper
	) {
		$this->config = $config;
		$this->pricingHelper = $pricingHelper;
	}

	/**
	 * isProductInBlacklisted...
	 *
	 * @return bool
	 */
	protected function isProductInBlacklisted($product) {
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

		$result .= "<div class=\"pace-pay_multi-products-widget-container\" data-price=\"{$this->pricingHelper->currency($product->getFinalPrice(), false, false)}\"></div>";

		return $result;
	}
}
