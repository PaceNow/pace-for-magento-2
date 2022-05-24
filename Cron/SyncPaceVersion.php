<?php
namespace Pace\Pay\Cron;

use Exception;
use Pace\Pay\Helper\AdminStoreResolver;
use Pace\Pay\Helper\ConfigData;
use Psr\Log\LoggerInterface;

/**
 * Class Sync Pace version
 */
class SyncPaceVersion {
	public function __construct(
		ConfigData $configData,
		LoggerInterface $logger,
		AdminStoreResolver $storeResolver
	) {
		$this->logger = $logger;
		$this->configData = $configData;
		$this->storeResolver = $storeResolver;
	}

	/**
	 * class instance when cron run
	 *
	 * @return void
	 */
	public function execute() {
		try {
			$basePayload = $this->configData->getBasePayload($this->storeResolver->resolveAdminStoreId());
			$cURL = \Magento\Framework\App\ObjectManager::getInstance()
				->create(\Magento\Framework\HTTP\Client\Curl::class);
			$cURL->setHeaders($basePayload['headers']);
			$cURL->setOptions([
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 30,
			]);

			$endpoint = 'https://developers.pacenow.co/version.json';
			$cURL->get($endpoint);
			@$response = $cURL->getBody();

			if (empty($response)) {
				throw new Exception("Get Pace plugins version failed!");
			}

			$this->configData->writeToConfig(ConfigData::CONFIG_PACE_SYNC_VERSION, $response);
		} catch (Exception $e) {
			$this->logger->info($e->getMessage());
		}
	}
}
