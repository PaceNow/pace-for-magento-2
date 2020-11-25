<?php

namespace Pace\Pay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
// use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Pace\Pay\Model\Adminhtml\Source\Environment;
use Magento\Store\Model\StoreManagerInterface;

const CONFIG_PREFIX = 'payment/pace_pay/';

function parseWidgetFontSize($fontSizeString)
{
    if (isset($fontSizeString) && strpos($fontSizeString, ",") && strpos($fontSizeString, "|")) {
        $fontSizeArray = explode('|', $fontSizeString);
        for ($i = 0; $i < count($fontSizeArray); $i++) {
            $fontSizeArray[$i] = explode(',', $fontSizeArray[$i]);
        }

        return $fontSizeArray;
    }

    return $fontSizeString;
}

class ConfigData extends AbstractHelper
{
    const CONFIG_ACTIVE = "active";
    const CONFIG_TITLE = "title";
    const CONFIG_DEBUG = "debug";
    const CONFIG_ENVIRONMENT = "environment";
    const CONFIG_PAYMENT_PLAN_ID = "payment_plan_id";
    const CONFIG_PAYMENT_PLAN_CURRENCY = "payment_plan_currency";
    const CONFIG_PAYMENT_PLAN_MIN = "payment_plan_min";
    const CONFIG_PAYMENT_PLAN_MAX = "payment_plan_max";
    const CONFIG_FALLBACK_WIDGET = "fallback_widget";
    const CONFIG_PAY_WITH_PACE_MODE = "pay_with_pace_mode";
    const CONFIG_GENERATE_INVOICE = "generate_invoice";
    const CONFIG_PLAYGROUND_CLIENT_ID = "playground_client_id";
    const CONFIG_PLAYGROUND_CLIENT_SECRET = "playground_client_secret";
    const CONFIG_PRODUCTION_CLIENT_ID = "production_client_id";
    const CONFIG_PRODUCTION_CLIENT_SECRET = "production_client_secret";
    const CONFIG_SORT_ORDER = "sort_order";
    const CONFIG_WIDGETS_ACTIVE = "widgets_active";
    const CONFIG_SINGLE_PRODUCT_ACTIVE = "single_product_active";
    const CONFIG_SINGLE_PRODUCT_CONTAINER_STYLE = 'single_product_container_style';
    const CONFIG_SINGLE_PRODUCT_LOGO_THEME = "single_product_logo_theme";
    const CONFIG_SINGLE_PRODUCT_TEXT_PRIMARY_COLOR = "single_product_text_primary_color";
    const CONFIG_SINGLE_PRODUCT_TEXT_SECONDARY_COLOR = "single_product_text_secondary_color";
    const CONFIG_SINGLE_PRODUCT_FONT_SIZE = "single_product_font_size";
    const CONFIG_MULTI_PRODUCTS_ACTIVE = "multi_products_active";
    const CONFIG_MULTI_PRODUCTS_LOGO_THEME = "multi_product_logo_theme";
    const CONFIG_MULTI_PRODUCTS_TEXT_COLOR = "multi_products_text_color";
    const CONFIG_MULTI_PRODUCTS_FONT_SIZE = "multi_products_font_size";
    const CONFIG_CHECKOUT_TEXT_PRIMARY_COLOR = "checkout_text_primary_color";
    const CONFIG_CHECKOUT_TEXT_SECONDARY_COLOR = "checkout_text_secondary_color";
    const CONFIG_CHECKOUT_TIMELINE_COLOR = "checkout_timeline_color";
    const CONFIG_CHECKOUT_BACKGROUND_COLOR = "checkout_background_color";
    const CONFIG_CHECKOUT_FOREGROUND_COLOR = "checkout_foreground_color";
    const CONFIG_CHECKOUT_FONT_SIZE = "checkout_font_size";
    const CONFIG_BASE_FONT_FAMILY = "base_font_family";
    const CONFIG_BASE_TEXT_PRIMARY_COLOR = "base_text_primary_color";
    const CONFIG_BASE_TEXT_SECONDARY_COLOR = "base_text_secondary_color";

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     * 
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->_storeManager = $storeManager;
    }


    public function getConfigValue($key, $storeId = NULL)
    {
        try {
            return $this->scopeConfig->getValue(CONFIG_PREFIX . $key, ScopeInterface::SCOPE_STORE, $storeId);
        } catch (\Exception $e) {
            return NULL;
        }
    }

    private function _getEnvPrefix($apiEnvironment)
    {
        if ($apiEnvironment === Environment::PRODUCTION) {
            return 'production_';
        } else {
            return 'playground_';
        }
    }

    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            CONFIG_PREFIX . 'enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getApiEnvironment($storeId = NULL)
    {
        return $this->getConfigValue(self::CONFIG_ENVIRONMENT, $storeId);
    }

    public function getApiEndpoint($storeId = NULL)
    {
        $env = $this->getApiEnvironment($storeId);
        if ($env == 'playground') {
            return 'https://api-playground.pacenow.co';
        } else if ($env == 'production') {
            return 'https://api.pacenow.co';
        } else {
            return '';
        }
    }

    public function getClientId($storeId = NULL)
    {
        $apiEnvironment = $this->getApiEnvironment($storeId);
        $configPrefix = $this->_getEnvPrefix($apiEnvironment);
        $clientId = $this->getConfigValue($configPrefix . 'client_id', $storeId);
        $clientId = $this->encryptor->decrypt($clientId);

        return $clientId;
    }


    public function getClientSecret($storeId = NULL)
    {
        $apiEnvironment = $this->getApiEnvironment($storeId);
        $configPrefix = $this->_getEnvPrefix($apiEnvironment);
        $clientSecret = $this->getConfigValue($configPrefix . 'client_secret', $storeId);
        $clientSecret = $this->encryptor->decrypt($clientSecret);

        return $clientSecret;
    }

    public function getBaseWidgetConfig()
    {
        $styles = [
            "textPrimaryColor" => $this->getConfigValue(self::CONFIG_BASE_TEXT_PRIMARY_COLOR),
            "textSecondaryColor" => $this->getConfigValue(self::CONFIG_BASE_TEXT_SECONDARY_COLOR),
            "fontFamily" => parseWidgetFontSize($this->getConfigValue(self::CONFIG_BASE_FONT_FAMILY)),
        ];

        $styles = array_filter($styles, function ($value) {
            return isset($value);
        });

        return [
            "isActive" => $this->getConfigValue(self::CONFIG_ACTIVE) == '1',
            "fallbackWidget" => $this->getConfigValue(self::CONFIG_FALLBACK_WIDGET) == "1",
            "styles" => $styles,
        ];
    }

    public function getIsAutomaticallyGenerateInvoice()
    {
        return $this->getConfigValue(self::CONFIG_GENERATE_INVOICE) == '1';
    }

    public function getSingleProductWidgetConfig()
    {
        $styles = [
            "logoTheme" => $this->getConfigValue(self::CONFIG_SINGLE_PRODUCT_LOGO_THEME),
            "textPrimaryColor" => $this->getConfigValue(self::CONFIG_SINGLE_PRODUCT_TEXT_PRIMARY_COLOR),
            "textSecondaryColor" => $this->getConfigValue(self::CONFIG_SINGLE_PRODUCT_TEXT_SECONDARY_COLOR),
            "fontSize" => parseWidgetFontSize($this->getConfigValue(self::CONFIG_SINGLE_PRODUCT_FONT_SIZE))
        ];

        $styles = array_filter($styles, function ($value) {
            return isset($value);
        });

        return [
            "isActive" => $this->getConfigValue(self::CONFIG_SINGLE_PRODUCT_ACTIVE) == '1',
            "styles" => $styles,
        ];
    }

    public function getMultiProductsWidgetConfig()
    {
        $styles = [
            "logoTheme" => $this->getConfigValue(self::CONFIG_MULTI_PRODUCTS_LOGO_THEME),
            "textColor" => $this->getConfigValue(self::CONFIG_MULTI_PRODUCTS_TEXT_COLOR),
            "fontSize" => parseWidgetFontSize($this->getConfigValue(self::CONFIG_MULTI_PRODUCTS_FONT_SIZE))
        ];

        $styles = array_filter($styles, function ($value) {
            return isset($value);
        });

        return [
            "isActive" => $this->getConfigValue(self::CONFIG_MULTI_PRODUCTS_ACTIVE) == '1',
            "styles" => $styles,
        ];
    }

    public function getCheckoutWidgetConfig()
    {
        $styles = [
            "textPrimaryColor" => $this->getConfigValue(self::CONFIG_CHECKOUT_TEXT_PRIMARY_COLOR),
            "textSecondaryColor" => $this->getConfigValue(self::CONFIG_CHECKOUT_TEXT_SECONDARY_COLOR),
            "timelineColor" => $this->getConfigValue(self::CONFIG_CHECKOUT_TIMELINE_COLOR),
            "backgroundColor" => $this->getConfigValue(self::CONFIG_CHECKOUT_BACKGROUND_COLOR),
            "foregroundColor" => $this->getConfigValue(self::CONFIG_CHECKOUT_FOREGROUND_COLOR),
            "fontSize" => parseWidgetFontSize($this->getConfigValue(self::CONFIG_CHECKOUT_FONT_SIZE))
        ];

        $styles = array_filter($styles, function ($value) {
            return isset($value);
        });

        return [
            "styles" => $styles,
        ];
    }

    public function getEnvironmentPrefix($storeId = NULL)
    {
        $env = $this->getApiEnvironment($storeId);
        $prefix = $env . '_';

        return $prefix;
    }

    public function getIsCurrencySupported($storeId = NULL)
    {
        $prefix = $this->getEnvironmentPrefix($storeId);
        $storeCurrency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        $paymentPlanCurrency = $this->getConfigValue($prefix . SELF::CONFIG_PAYMENT_PLAN_CURRENCY, $storeId);

        return $paymentPlanCurrency == $storeCurrency;
    }

    public function getPaymentPlan($storeId = NULL)
    {
        $prefix = $this->getEnvironmentPrefix($storeId);
        return [
            "isCurrencySupported" => $this->getIsCurrencySupported($storeId),
            "currency" => $this->getConfigValue($prefix . SELF::CONFIG_PAYMENT_PLAN_CURRENCY, $storeId),
            "minAmount" => $this->getConfigValue($prefix . SELF::CONFIG_PAYMENT_PLAN_MIN, $storeId),
            "maxAmount" => $this->getConfigValue($prefix . SELF::CONFIG_PAYMENT_PLAN_MAX, $storeId),
        ];
    }
}
