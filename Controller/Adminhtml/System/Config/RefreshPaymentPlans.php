<?php

namespace Pace\Pay\Controller\Adminhtml\System\Config;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Pace\Pay\Helper\ConfigData;
use Pace\Pay\Helper\ResponseRespository;
use Pace\Pay\Helper\AdminStoreResolver;

class RefreshPaymentPlans extends Action {
	/**
	 * @var StoreManagerInterface
	 */
	protected $storeManager;

	public function __construct(
		Action\Context $context,
		ConfigData $configData,
		ResponseRespository $response,
		StoreManagerInterface $storeManager,
		MessageManagerInterface $messageManager,
		AdminStoreResolver $adminStoreResolver
	) {
		parent::__construct($context);
		$this->response = $response;
		$this->configData = $configData;
		$this->storeManager = $storeManager;
		$this->messageManager = $messageManager;
		$this->adminStoreResolver = $adminStoreResolver;
	}

	/**
	 * updatePaymentPlans...
	 *
	 * @return Void
	 */
	protected function updatePaymentPlans($storeId, $env, $response) {
		$this->configData->writeToConfig(ConfigData::CONFIG_PAYMENT_PLANS, json_encode($response->list), $storeId, $env);
	}

	/**
	 * execute...
	 *
	 * Refresh payment plans
	 *
	 * @return Void
	 */
	public function execute() {
		$storeId = $this->adminStoreResolver->resolveAdminStoreId();
		$basePayload = $this->configData->getBasePayload($storeId);

		$cURL = \Magento\Framework\App\ObjectManager::getInstance()
			->create(\Magento\Framework\HTTP\Client\Curl::class);
		$cURL->setHeaders($basePayload['headers']);
		$cURL->setOptions([
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
		]);

		try {
			$cURL->get("{$basePayload['apiEndpoint']}/v1/checkouts/plans");
			@$response = json_decode($cURL->getBody());

			if (isset($response->error)) {
				throw new Exception("Pace refresh payment plans failure! Reason: {$response->error->message}");
			}

			$env = $this->configData->getApiEnvironment($storeId);
			$this->updatePaymentPlans($storeId, $env, $response);

			return $this->response->jsonResponse([
				'success' => true,
			], 200);
		} catch (Exception $e) {
			$this->messageManager->addErrorMessage($e->getMessage());

			return $this->response->jsonResponse([
				'success' => true,
			], 200);
		}
	}
}
