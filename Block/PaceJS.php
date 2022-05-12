<?php

namespace Pace\Pay\Block;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Element\Template;

use Pace\Pay\Helper\ConfigData;

use Exception;

class PaceJS extends Template
{   
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

    /**
     * getBaseWidgetConfig...
     * 
     * @return array
     */
    protected function getBaseWidgetConfig()
    {
        $styles = [
            'fontSize' => $this->configData->getConfigValue('base_font_size', $this->storeId) ?? 0,
            'textPrimaryColor' => $this->configData->getConfigValue('base_text_primary_color', $this->storeId) ?: '',
            'textSecondaryColor' => $this->configData->getConfigValue('base_text_secondary_color', $this->storeId) ?: '',
            
        ];

        return [
            'styles' => $styles,
            'baseActive' => $this->configData->getConfigValue('widgets_active', $this->storeId) == '1',
            'fallbackWidget' => $this->configData->getConfigValue('fallback_widget', $this->storeId) == '1',
        ];
    }

    /**
     * getProductWidgetConfig...
     * 
     * @return array
     */
    protected function getProductWidgetConfig()
    {
        $styles = [
            'fontSize' => $this->configData->getConfigValue('single_product_font_size', $this->storeId) ?? 0,
            'logoTheme' => $this->configData->getConfigValue('single_product_logo_theme', $this->storeId) ?: '',
            'textPrimaryColor' => $this->configData->getConfigValue('single_product_text_primary_color', $this->storeId) ?: '',
            'textSecondaryColor' => $this->configData->getConfigValue('single_product_text_secondary_color', $this->storeId) ?: '',
        ];

        return [
            'isActive' => $this->configData->getConfigValue('single_product_active', $this->storeId) == '1',
            'styles' => $styles,
            'containerStyles' => $this->configData->getConfigValue('single_product_container_style', $this->storeId) ?: ''
        ];
    }

    /**
     * getCatalogWidgetConfig...
     * 
     * @return array
     */
    protected function getCatalogWidgetConfig()
    {
        $styles = [
            'fontSize' => $this->configData->getConfigValue('multi_products_font_size', $this->storeId) ?? 0,
            'logoTheme' => $this->configData->getConfigValue('multi_product_logo_theme', $this->storeId) ?: '',
            'textColor' => $this->configData->getConfigValue('multi_products_text_color', $this->storeId) ?: '',
        ];

        return [
            'isActive' => $this->configData->getConfigValue('multi_products_active', $this->storeId) == '1',
            'styles' => $styles,
        ];
    }

    /**
     * getCheckoutWidgetConfig...
     * 
     * @return array
     */
    protected function getCheckoutWidgetConfig()
    {
        $styles = [
            'fontSize' => $this->configData->getConfigValue('checkout_font_size', $this->storeId) ?? 0,
            'timelineColor' => $this->configData->getConfigValue('checkout_timeline_color', $this->storeId) ?: '',
            'backgroundColor' => $this->configData->getConfigValue('checkout_background_color', $this->storeId) ?: '',
            'foregroundColor' => $this->configData->getConfigValue('checkout_foreground_color', $this->storeId) ?: '',
            'textPrimaryColor' => $this->configData->getConfigValue('checkout_text_primary_color', $this->storeId) ?: '',
            'textSecondaryColor' => $this->configData->getConfigValue('checkout_text_secondary_color', $this->storeId) ?: '',
        ];

        return [
            'isActive' => $this->configData->getConfigValue('checkout_active', $this->storeId) == '1',
            'styles' => $styles,
        ];
    }

    /**
     * getPaceConfig...
     * 
     * @return array
     */
    public function getPaceConfig()
    {
        try {
            $paymentPlans = $this->configData->getPaymentPlan($this->storeId, true);
            $config = [
                'mode' => $this->configData->getApiEnvironment(),
                'isEnable' => !empty($paymentPlans) ? get_object_vars($paymentPlans['paymentPlans']) : [],
                'paymentMode' => $this->configData->getConfigValue('pay_with_pace_mode', $this->storeId),
                'checkoutSetting' => $this->getCheckoutWidgetConfig(),
                'baseWidgetConfig' => $this->getBaseWidgetConfig(),
                'productWidgetConfig' => $this->getProductWidgetConfig(),
                'catalogWidgetConfig' => $this->getCatalogWidgetConfig(),
            ];

            return $config;
        } catch (Exception $e) {
            return [];
        }
    }
}
