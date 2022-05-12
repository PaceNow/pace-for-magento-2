<?php

namespace Pace\Pay\Helper;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;

use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

use Pace\Pay\Model\Ui\ConfigProvider;
use Pace\Pay\Model\Adminhtml\Source\Environment;

use Exception;

const CONFIG_PREFIX = 'payment/pace_pay/';

class ConfigData extends AbstractHelper
{
    const CONFIG_ACTIVE = "active";
    const CONFIG_ENVIRONMENT = "environment";
    const CONFIG_PAYMENT_PLANS = "payment_plans";
    const CONFIG_PACE_SYNC_VERSION = 'pace_sync_version';
    const CONFIG_PAYMENT_PLAN_ID = "payment_plan_id";
    const CONFIG_GENERATE_INVOICE = "generate_invoice"; // TODO: remove
    const CONFIG_CLIENT_ID = 'client_id';
    const CONFIG_CLIENT_SECRET = 'client_secret';
    const CONFIG_SORT_ORDER = "sort_order";
    const CONFIG_BLACK_LISTED = "widget_blacklisted";

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var ReadFactory
     */
    protected $readFactory;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

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
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        $this->_configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->_moduleList = $moduleList;
        $this->readFactory = $readFactory;
        $this->productMetadata = $productMetadata;
        $this->_componentRegistrar = $componentRegistrar;
    }

    /**
     * encrypt...
     * 
     * @return string(Hash)
     */
    public function encrypt($message)
    {
        return $this->encryptor->encrypt($message);
    }

    /**
     * decrypt...
     * 
     * @return string
     */
    public function decrypt($hash)
    {
        return $this->encryptor->decrypt($hash);
    }

    /**
     * getConfigValue...
     * 
     * @return mixed
     */
    public function getConfigValue($key, $storeId = null, $env = null)
    {
        // If its env specific, get the env prefix.
        $key = !empty($env) 
            ? "{$env}_{$key}"
            : $key;
        $storeId = $storeId ?? $this->storeManager->getStore()->getId();
        $data = $this->scopeConfig->getValue(CONFIG_PREFIX . $key, ScopeInterface::SCOPE_STORE, $storeId);
        
        return $data;
    }

    /**
     * getApiEnvironment...
     * 
     * @return string
     */
    public function getApiEnvironment($storeId = null)
    {
        return $this->getConfigValue(self::CONFIG_ENVIRONMENT, $storeId);
    }

    /**
     * getApiEndpoint...
     * 
     * @return string
     */
    public function getApiEndpoint($env = '')
    {
        if (empty($env)) {
            return '';
        }

        return 'playground' == $env
            ? 'https://api-playground.pacenow.co'
            : 'https://api.pacenow.co';
    }

    /**
     * isMethodAvailable...
     * 
     * @return bool
     */
    public function isMethodAvailable($storeId = null, $getDetails = false)
    {
        $env = $this->getApiEnvironment($storeId);
        $paymentPlans = $this->getConfigValue(self::CONFIG_PAYMENT_PLANS, $storeId, $env);

        if (!empty($paymentPlans)) {
            throw new Exception("Payment plans is not found!");
        }

        $invalidPaymentPlans = [];
        foreach (json_decode($paymentPlans) as $p) {
            $invalidPaymentPlans[$p->currencyCode][] = $p;  
        }

        $storeCurrency = $this->storeManager->getStore($storeId)->getCurrentCurrencyCode();

        if (!in_array($storeCurrency, array_keys($invalidPaymentPlans))) {
            throw new Exception("Pace doesn't support the client currency!");
        }

        $finalPlan = null;
        $paymentPlanByCurrency = $invalidPaymentPlans[$storeCurrency];
        $storeCountry = $this->scopeConfig->getValue($key = 'general/country/default', ScopeInterface::SCOPE_STORE, $storeId);

        foreach ($paymentPlanByCurrency as $plan) {
            if (0 == strcmp($storeCountry, $plan->country)) {
                $finalPlan = $plan;
                break;    
            }
        }
        
        if (empty($finalPlan)) {
            throw new Exception("Empty available plan!");
        }

        $finalPlan->isAvailable = true;

        if ($getDetails) {
            return $finalPlan;
        }

        return true;
    }

    public function getModuleVersion()
    {
        $unknownVersion = __('Unknown version');
        try {
            $path = $this->_componentRegistrar->getPath(
                ComponentRegistrar::MODULE,
                'Pace_Pay'
            );
            $directoryRead = $this->readFactory->create($path);
            $composerJsonData = $directoryRead->readFile('composer.json');
            $data = json_decode($composerJsonData);

            return !empty($data->version) ? $data->version : $this->getSetupVersion();
        } catch (Exception $exception) {
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

    /**
     * isEnabled...
     * ConfigProvider
     * 
     * @return bool
     */
    public function isEnable()
    {
        $storeId = $this->storeManager->getStore()->getId();;
        
        return ((bool)$this->getConfigValue('active', $storeId) && $this->isMethodAvailable($storeId));
    }

    /**
     * getAPIAuthenticate...
     * 
     * @return string
     */
    protected function getAPIAuthenticate($storeId = null, $env = '')
    {
        $clientId = $this->encryptor->decrypt($this->getConfigValue(self::CONFIG_CLIENT_ID, $storeId, $env));
        $clientSecret = $this->encryptor->decrypt($this->getConfigValue(self::CONFIG_CLIENT_SECRET, $storeId, $env));

        return 'Basic ' . base64_encode("{$clientId}:{$clientSecret}");
    }

    /**
     * getBasePayload...
     * 
     * Get Pace Http headers
     *
     * @return array
     */
    public function getBasePayload($storeId = null)
    {   
        $env = $this->getApiEnvironment($storeId);
        $version = sprintf('%s, %s, %s', ConfigProvider::PLUGIN_NAME, $this->getSetupVersion(), $this->productMetadata->getVersion());
        $payload = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->getAPIAuthenticate($storeId, $env),
                'X-Pace-PlatformVersion' => $version,
            ],
            'apiEndpoint' => $this->getApiEndpoint($env)
        ];

        return $payload;
    }

    /**
     * getPaymentPlan...
     * 
     * @return array
     */
    public function getPaymentPlan($storeId = null, $getDetails = false)
    {
        return [
            'paymentPlans' => $this->isMethodAvailable($storeId, $getDetails),
        ];
    }

    /**
     * convertPricebyCountry...
     * 
     * @return Number
     */
    public function convertPricebyCountry($basePrice, $country)
    {
        /**
         * @see https://docs.adyen.com/development-resources/currency-codes
         */
        $listIgnoreCurrencies = ['CVE', 'DJF', 'GNF', 'IDR', 'JPY', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array($country, $listIgnoreCurrencies)) {
            return $basePrice;
        }

        $convert = strval(floatval($basePrice * 100));
        $unit = intval($convert);

        return $unit;
    }
}
