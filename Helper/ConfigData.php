<?php

namespace Pace\Pay\Helper;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Sales\Model\Order;

use Pace\Pay\Model\Ui\ConfigProvider;
use Pace\Pay\Model\Adminhtml\Source\Environment;

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
    const CONFIG_PAYMENT_PLANS = "payment_plans";
    const CONFIG_PACE_SYNC_VERSION = 'pace_sync_version';
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
    const CONFIG_CHECKOUT_ACTIVE = "checkout_active";
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
     * @var ReadFactory
     */
    protected $_readFactory;

    /**
     * @var DeploymentConfig
     */
    protected $_deploymentconfig;

    /**
     * @var ProductMetadataInterface
     */
    protected $_metaDataInterface;

    /**
     * @var ComponentRegistrarInterface
     */
    protected $_componentRegistrar;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $configWriter
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        ModuleListInterface $moduleList,
        ReadFactory $readFactory,
        ComponentRegistrarInterface $componentRegistrar,
        DeploymentConfig $deploymentConfig,
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->_storeManager = $storeManager;
        $this->_configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->_moduleList = $moduleList;
        $this->_readFactory = $readFactory;
        $this->_metaDataInterface = $productMetadata;
        $this->_componentRegistrar = $componentRegistrar;
        $this->_deploymentconfig = $deploymentConfig;
    }

    protected function isMethodAvailable(
        $storeId = null
    ) {
        $env = $this->getApiEnvironment($storeId);
        try {
            // retrieve plans from database
            $paymentPlans = $this->getConfigValue(SELF::CONFIG_PAYMENT_PLANS, $storeId, $env);

            if (!$paymentPlans) {
                throw new \Exception("Plans is not found");
            }

            // get list available currencies from payment plans
            $listAvailableCurrencies = [];
            foreach (json_decode($paymentPlans) as $plan) {
                $listAvailableCurrencies[$plan->currencyCode] = $plan;
            }

            $storeCurrency = $this->_storeManager->getStore($storeId)->getCurrentCurrencyCode();

            if (!in_array($storeCurrency, array_keys($listAvailableCurrencies))) {
                throw new \Exception("Pace doesn't support the client currency");
            }

            $getPacePlanFollowCurrency = $listAvailableCurrencies[$storeCurrency];
            $storeCountry = $this->scopeConfig->getValue($key = 'general/country/default', ScopeInterface::SCOPE_STORE, $storeId);

            if ($getPacePlanFollowCurrency->country !== $storeCountry) {
                throw new \Exception("Pace doesn't support the client country");
            }

            $getPacePlanFollowCurrency->isAvailable = true;

            return $getPacePlanFollowCurrency;
        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());
            return;
        }
    }

    public function getModuleVersion()
    {
        $unknownVersion = __('Unknown version');
        try {
            $path = $this->_componentRegistrar->getPath(
                ComponentRegistrar::MODULE,
                'Pace_Pay'
            );
            $directoryRead = $this->_readFactory->create($path);
            $composerJsonData = $directoryRead->readFile('composer.json');
            $data = json_decode($composerJsonData);

            return !empty($data->version) ? $data->version : $unknownVersion;
        } catch (\Exception $exception) {
            return $unknownVersion;
        }
    }

    public function getSetupVersion()
    {
        $moduleInfo = $this->_moduleList->getOne('Pace_Pay');
        return $moduleInfo['setup_version'];
    }

    private function _getEnvPrefix($apiEnvironment)
    {
        if ($apiEnvironment === Environment::PRODUCTION) {
            return 'production_';
        } else if ($apiEnvironment === Environment::PLAYGROUND) {
            return 'playground_';
        } else {
            return '';
        }
    }

    public function getConfigValue($key, $storeId = null, $env = null)
    {
        // If its env specific, get the env prefix.
        if (isset($env)) {
            $key = $this->_getEnvPrefix($env) . $key;
        }
        try {
            return $this->scopeConfig->getValue(CONFIG_PREFIX . $key, ScopeInterface::SCOPE_STORE, $storeId);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function writeToConfig($key, $value, $storeId = null, $env = null)
    {
        // If its env specific, get the env prefix.
        if (isset($env)) {
            $key = $this->_getEnvPrefix($env) . $key;
        }

        if (isset($value)) {
            $this->_configWriter->save(CONFIG_PREFIX . $key, $value, ScopeInterface::SCOPE_STORES, $storeId);
        } else {
            $this->_configWriter->delete(CONFIG_PREFIX . $key, ScopeInterface::SCOPE_STORES, $storeId);
        }

        $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        $this->cacheTypeList->cleanType(\Magento\PageCache\Model\Cache\Type::TYPE_IDENTIFIER);
    }

    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            CONFIG_PREFIX . 'enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getApiEnvironment($storeId = null)
    {
        return $this->getConfigValue(self::CONFIG_ENVIRONMENT, $storeId);
    }

    public function getApiEndpoint($storeId = null)
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

    public function getClientId($storeId = null)
    {
        $apiEnvironment = $this->getApiEnvironment($storeId);
        $configPrefix = $this->_getEnvPrefix($apiEnvironment);
        $clientId = $this->getConfigValue($configPrefix . 'client_id', $storeId);
        $clientId = $this->encryptor->decrypt($clientId);

        return $clientId;
    }

    public function getClientSecret($storeId = null)
    {
        $apiEnvironment = $this->getApiEnvironment($storeId);
        $configPrefix = $this->_getEnvPrefix($apiEnvironment);
        $clientSecret = $this->getConfigValue($configPrefix . 'client_secret', $storeId);
        $clientSecret = $this->encryptor->decrypt($clientSecret);

        return $clientSecret;
    }

    /**
     * Get Pace Http headers
     *
     * @since 1.0.3
     * @param  int $store store Id
     * @return array
     */
    public function getBasePayload($store = null)
    {
        $magentoVersion = $this->_metaDataInterface->getVersion();
        $pluginVersion = $this->_moduleList->getOne(ConfigProvider::MODULE_NAME)['setup_version'];
        $platformVersionString = ConfigProvider::PLUGIN_NAME . ', ' . $pluginVersion . ', ' . $magentoVersion;

        $authToken = base64_encode(
            $this->getClientId($store) . ':' .
            $this->getClientSecret($store)
        );

        $pacePayload = [];
        $pacePayload['headers'] = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $authToken,
            'X-Pace-PlatformVersion' => $platformVersionString,
        ];

        return $pacePayload;
    }

    /**
     * Get Pace approved statuses
     * 
     * @since 1.0.3
     * @return string
     */
    public function getApprovedStatus()
    {
        $statuses = $this->getConfigValue('pace_approved') ?? Order::STATE_PROCESSING;

        return $statuses;
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
            "baseActive" => $this->getConfigValue(self::CONFIG_WIDGETS_ACTIVE) == '1',
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
            "fontSize" => parseWidgetFontSize($this->getConfigValue(self::CONFIG_SINGLE_PRODUCT_FONT_SIZE)),
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
            "fontSize" => parseWidgetFontSize($this->getConfigValue(self::CONFIG_MULTI_PRODUCTS_FONT_SIZE)),
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
            "fontSize" => parseWidgetFontSize($this->getConfigValue(self::CONFIG_CHECKOUT_FONT_SIZE)),
        ];

        $styles = array_filter($styles, function ($value) {
            return isset($value);
        });

        return [
            "isActive" => $this->getConfigValue(self::CONFIG_CHECKOUT_ACTIVE),
            "styles" => $styles,
        ];
    }

    public function getIsCurrencySupported($storeId = null)
    {
        $env = $this->getApiEnvironment($storeId);
        $storeCurrency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        $paymentPlanCurrency = $this->getConfigValue(SELF::CONFIG_PAYMENT_PLAN_CURRENCY, $storeId, $env);

        return $paymentPlanCurrency == $storeCurrency;
    }

    public function getPaymentPlan($storeId = null)
    {
        // $env = $this->getApiEnvironment($storeId);
        return [
            "paymentPlans" => $this->isMethodAvailable($storeId),
        ];
    }
}
