<?php

namespace Pace\Pay\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Pace\Pay\Helper\ConfigData;

class SingleProductWidget extends Template {
	/**
	 * @var ConfigData
	 */
	protected $config;

	public function __construct(
		Context $context,
		Registry $registry,
		ConfigData $configData
	) {
		parent::__construct($context);
		$this->config = $configData;
		$this->product = $registry->registry('current_product');
	}

	/**
	 * getProductPrice...
	 *
	 * @return float
	 */
	public function getProductPrice() {
		return $this->product->getFinalPrice();
	}

	/**
	 * isBlacklisted...
	 *
	 * Check whether the product category is on the blacklist
	 *
	 * @return boolean
	 */
	public function isBlacklisted() {
		$categories = $this->product->getCategoryIds();
		$blacklisted = $this->config->getConfigValue(ConfigData::CONFIG_BLACK_LISTED);

		if (empty($categories) || empty($blacklisted)) {
			return 0;
		}

		return count(array_intersect($categories, explode(',', $blacklisted)));
	}
}
