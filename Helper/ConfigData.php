<?php

namespace Pace\Pay\Helper;

use DateTime;
use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Pace\Pay\Model\Adminhtml\Source\Environment;
use Pace\Pay\Model\Ui\ConfigProvider;

const CONFIG_PREFIX = 'payment/pace_pay/';

class ConfigData extends AbstractHelper {
	const CONFIG_ACTIVE = "active";
	const CONFIG_ENVIRONMENT = "environment";
	const CONFIG_PAYMENT_PLANS = "payment_plans";
	const CONFIG_PACE_SYNC_VERSION = 'pace_sync_version';
	const CONFIG_PAYMENT_PLAN_ID = "payment_plan_id";
	const CONFIG_CLIENT_ID = 'client_id';
	const CONFIG_CLIENT_SECRET = 'client_secret';
	const CONFIG_SORT_ORDER = "sort_order";
	const CONFIG_BLACK_LISTED = "widget_blacklisted";

	// Hashing
	const KEY = 'PM';
	const ALGO = 'DH0EjlYKmEgoJzSR';
	const METHOD = 'AES-256-CBC';

	/**
	 * @var EncryptorInterface
	 */
	protected $encryptor;

	/**
	 * @var ProductMetadataInterface
	 */
	protected $productMetadata;

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
		ProductMetadataInterface $productMetadata
	) {
		parent::__construct($context);
		$this->encryptor = $encryptor;
		$this->storeManager = $storeManager;
		$this->configWriter = $configWriter;
		$this->cacheTypeList = $cacheTypeList;
		$this->moduleList = $moduleList;
		$this->productMetadata = $productMetadata;
	}

	/**
	 * encrypt...
	 *
	 * @return string(Hash)
	 */
	public function encrypt($message) {
		$key = hash('sha256', self::KEY);
		$secret = substr(hash('sha256', self::ALGO), 0, 16);
		$encryptString = openssl_encrypt($message, self::METHOD, $key, 0, $secret);

		return base64_encode($encryptString);
	}

	/**
	 * decrypt...
	 *
	 * @return string
	 */
	public function decrypt($hash) {
		$key = hash('sha256', self::KEY);
		$secret = substr(hash('sha256', self::ALGO), 0, 16);
		$decryptString = base64_decode($hash);

		return openssl_decrypt($decryptString, self::METHOD, $key, 0, $secret);
	}

	/**
	 * getConfigValue...
	 *
	 * @return mixed
	 */
	public function getConfigValue($key, $storeId = null, $env = null) {
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
	public function getApiEnvironment($storeId = null) {
		return $this->getConfigValue(self::CONFIG_ENVIRONMENT, $storeId);
	}

	/**
	 * getApiEndpoint...
	 *
	 * @return string
	 */
	public function getApiEndpoint($env = '') {
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
	public function isMethodAvailable($storeId = null, $getDetails = false) {
		$env = $this->getApiEnvironment($storeId);
		$paymentPlans = $this->getConfigValue(self::CONFIG_PAYMENT_PLANS, $storeId, $env);

		if (empty($paymentPlans)) {
			throw new Exception("Payment plans is not found!");
		}

		$availablePlans = [];
		foreach (json_decode($paymentPlans) as $plan) {
			$planCurrency = $plan->currencyCode;
			$availablePlans[$planCurrency][] = $plan;
		}

		$storeCurrency = $this->storeManager->getStore($storeId)->getCurrentCurrencyCode();

		if (!in_array($storeCurrency, array_keys($availablePlans))) {
			throw new Exception("Store currency not matched.");
		}

		$finalPlan = null;
		$planByCurrency = $availablePlans[$storeCurrency];

		// Sort list plans by end date
		usort($planByCurrency, function ($i, $o) {
			$now = new DateTime();
			return strtotime($o->endedAt ?? $now->format('Y-m-d H:i:s')) - strtotime($i->endedAt ?? $now->format('Y-m-d H:i:s'));
		});

		$storeCountry = $this->scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_STORE, $storeId);

		foreach ($planByCurrency as $plan) {
			if (0 == strcmp($storeCountry, $plan->country)) {
				$finalPlan = $plan;
				break;
			}
		}

		if (empty($finalPlan)) {
			throw new Exception("Nulled payment plans.");
		}

		$finalPlan->isAvailable = true;

		if ($getDetails) {
			return $finalPlan;
		}

		return true;
	}

	public function getSetupVersion() {
		$moduleInfo = $this->moduleList->getOne('Pace_Pay');

		return $moduleInfo['setup_version'];
	}

	private function _getEnvPrefix($apiEnvironment) {
		if ($apiEnvironment === Environment::PRODUCTION) {
			return 'production_';
		} else if ($apiEnvironment === Environment::PLAYGROUND) {
			return 'playground_';
		} else {
			return '';
		}
	}

	public function writeToConfig($key, $value, $storeId = null, $env = null) {
		// If its env specific, get the env prefix.
		if (isset($env)) {
			$key = $this->_getEnvPrefix($env) . $key;
		}

		if (isset($value)) {
			$this->configWriter->save(CONFIG_PREFIX . $key, $value, ScopeInterface::SCOPE_STORES, $storeId);
		} else {
			$this->configWriter->delete(CONFIG_PREFIX . $key, ScopeInterface::SCOPE_STORES, $storeId);
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
	public function isEnable() {
		$storeId = $this->storeManager->getStore()->getId();

		return ((bool) $this->getConfigValue('active', $storeId) && $this->isMethodAvailable($storeId));
	}

	/**
	 * getAPIAuthenticate...
	 *
	 * @return string
	 */
	protected function getAPIAuthenticate($storeId = null, $env = '') {
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
	public function getBasePayload($storeId = null) {
		$env = $this->getApiEnvironment($storeId);
		$setupVersion = $this->getSetupVersion();
		$version = sprintf('%s, %s, %s', ConfigProvider::PLUGIN_NAME, $setupVersion, $this->productMetadata->getVersion());
		$payload = [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => $this->getAPIAuthenticate($storeId, $env),
				'X-Pace-PlatformVersion' => $version,
				'X-Pace-PluginsName' => ConfigProvider::PLUGIN_NAME,
				'X-Pace-PluginsVersion' => $setupVersion,
			],
			'apiEndpoint' => $this->getApiEndpoint($env),
		];

		return $payload;
	}

	/**
	 * getPaymentPlan...
	 *
	 * @return array
	 */
	public function getPaymentPlan($storeId = null, $getDetails = false) {
		return [
			'paymentPlans' => $this->isMethodAvailable($storeId, $getDetails),
		];
	}

	/**
	 * convertPricebyCountry...
	 *
	 * @return Number
	 */
	public function convertPricebyCountry($basePrice, $country) {
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

	/**
	 * getMagentoVersion...
	 *
	 * @return String
	 */
	public function getMagentoVersion() {
		return $this->productMetadata->getVersion();
	}
}
