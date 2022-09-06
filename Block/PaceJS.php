<?php

namespace Pace\Pay\Block;

use Exception;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;
use Pace\Pay\Helper\ConfigData;

class PaceJS extends Template {
	/**
	 * @var int
	 */
	protected $storeId;

	public function __construct(
		Template\Context $context,
		ConfigData $configData,
		StoreManagerInterface $storeManager
	) {
		parent::__construct($context);

		$this->storeId = $storeManager->getStore()->getId();
		$this->configData = $configData;
	}

	protected function getConfig($key) {
		return $this->configData->getConfigValue($key);
	}

	/**
	 * getBaseWidgetConfig...
	 *
	 * @return array
	 */
	protected function getBaseWidgetConfig() {
		$styles = [];

		return [
			'styles' => $styles,
			'fallbackWidget' => $this->getConfig('fallback_widget') == '1',
		];
	}

	/**
	 * getProductWidgetConfig...
	 *
	 * @return array
	 */
	protected function getProductWidgetConfig() {
		$styles = [
			'fontSize' => $this->getConfig('single_product_font_size') ?? 0,
			'logoTheme' => $this->getConfig('single_product_logo_theme') ?: '',
			'textPrimaryColor' => $this->getConfig('single_product_text_primary_color') ?: '',
			'textSecondaryColor' => $this->getConfig('single_product_text_secondary_color') ?: '',
		];

		return [
			'styles' => $styles,
			'isActive' => $this->getConfig('single_product_active') == '1',
			'containerStyles' => $this->getConfig('single_product_container_style') ?: '',
		];
	}

	/**
	 * getCatalogWidgetConfig...
	 *
	 * @return array
	 */
	protected function getCatalogWidgetConfig() {
		$styles = [
			'fontSize' => $this->getConfig('multi_products_font_size') ?? 0,
			'logoTheme' => $this->getConfig('multi_products_logo_theme') ?: '',
			'textColor' => $this->getConfig('multi_products_text_color') ?: '',
		];

		return [
			'styles' => $styles,
			'isActive' => $this->getConfig('multi_products_active') == '1',
		];
	}

	/**
	 * getCheckoutWidgetConfig...
	 *
	 * @return array
	 */
	protected function getCheckoutWidgetConfig() {
		$styles = [
			'fontSize' => $this->getConfig('checkout_font_size') ?? 0,
			'timelineColor' => $this->getConfig('checkout_timeline_color') ?: '',
			'backgroundColor' => $this->getConfig('checkout_background_color') ?: '',
			'foregroundColor' => $this->getConfig('checkout_foreground_color') ?: '',
			'textPrimaryColor' => $this->getConfig('checkout_text_primary_color') ?: '',
			'textSecondaryColor' => $this->getConfig('checkout_text_secondary_color') ?: '',
		];

		return [
			'styles' => $styles,
			'isActive' => $this->getConfig('checkout_active') == '1',
		];
	}
	
	/**
	 * getVoucherTagConfig
	 *
	 * @return array
	 */
	protected function getVoucherTagConfig() {
		$styles = [
			'style' => $this->getConfig('voucher_tag_style') ?: '',
			'backgroundColor' => $this->getConfig('voucher_tag_background_color') ?: '',
		];

		return [
			'enable' => $this->getConfig('voucher_tag_enable') == '1',
			'country' => $this->getConfig('voucher_tag_country') ?: '',
			'styles' => $styles,
		];
	}

	/**
	 * getPaceConfig...
	 *
	 * @return array
	 */
	public function getPaceConfig() {
		try {
			$paymentPlans = $this->configData->getPaymentPlan($this->storeId, true);
			$config = [
				'mode' => $this->configData->getApiEnvironment(),
				'isEnable' => !empty($paymentPlans) ? get_object_vars($paymentPlans['paymentPlans']) : [],
				'paymentMode' => $this->getConfig('pay_with_pace_mode'),
				'checkoutSetting' => $this->getCheckoutWidgetConfig(),
				'baseWidgetConfig' => $this->getBaseWidgetConfig(),
				'productWidgetConfig' => $this->getProductWidgetConfig(),
				'catalogWidgetConfig' => $this->getCatalogWidgetConfig(),
				'voucherTagConfig' => $this->getVoucherTagConfig(),
			];

			return $config;
		} catch (Exception $e) {
			return [];
		}
	}
}
