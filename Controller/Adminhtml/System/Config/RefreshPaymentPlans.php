<?php

namespace Pace\Pay\Controller\Adminhtml\System\Config;

use Zend_Http_Client;
use Pace\Pay\Helper\ConfigData;
use Pace\Pay\Helper\AdminStoreResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Psr\Log\LoggerInterface;

class RefreshPaymentPlans extends Action
{
    const REFRESH_SUCCESS = 'refresh_success';
    const REFRESH_FAILURE = 'refresh_failure';

    /**
     * @var array
     */
    private $_paymentPlan;

    public function __construct(
        Context $context,
        ZendClient $client,
        JsonFactory $resultJsonFactory,
        ConfigInterface $resourceConfig,
        ConfigData $configData,
        MessageManagerInterface $messageManager,
        StoreRepositoryInterface $storeRepository,
        AdminStoreResolver $adminStoreResolver,
        LoggerInterface $logger
    )
    {
        $this->_client = $client;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_resourceConfig = $resourceConfig;
        $this->_configData = $configData;
        $this->_messageManager = $messageManager;
        $this->_storeRepository = $storeRepository;
        $this->_adminStoreResolver = $adminStoreResolver;
        $this->_logger = $logger;
        parent::__construct($context);
    }

    private function _getBasePayload($clientId, $clientSecret)
    {
        $authToken = base64_encode(
            $clientId . ':' .
            $clientSecret
        );

        $pacePayload = [];
        $pacePayload['headers'] = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $authToken,
        ];

        return $pacePayload;
    }

    protected function _jsonResponse($data, $statusCode = 200)
    {
        $result = $this->_resultJsonFactory->create();
        $result->setData($data);
        $result->setStatusHeader($statusCode);
        $result->setHttpResponseCode($statusCode);
        return $result;
    }

    public function execute()
    {
        $result = $this->refreshPlans();
        if ($result == self::REFRESH_SUCCESS) {
            return $this->_jsonResponse([
                "success" => TRUE,
            ], 200);
        } else {
            return $this->_jsonResponse([
                "success" => TRUE,
            ], 500);
        }
    }

    private function _updatePaymentPlan($storeId, $env, $id, $currency, $min, $max)
    {
        $this->_configData->writeToConfig(ConfigData::CONFIG_PAYMENT_PLAN_ID, $id, $storeId, $env);
        $this->_configData->writeToConfig(ConfigData::CONFIG_PAYMENT_PLAN_CURRENCY, $currency, $storeId, $env);
        $this->_configData->writeToConfig(ConfigData::CONFIG_PAYMENT_PLAN_MIN, $min, $storeId, $env);
        $this->_configData->writeToConfig(ConfigData::CONFIG_PAYMENT_PLAN_MAX, $max, $storeId, $env);
    }

    public function refreshPlans()
    {
        // Refresh payment plans for all stores
        $stores = $this->_storeRepository->getList();
        foreach ($stores as $store) {
            $storeId = $store->getId();
            $endpoint = $this->_configData->getApiEndpoint($storeId) . '/v1/checkouts/plans';
            $clientId = $this->_configData->getClientId($storeId);
            $clientSecret = $this->_configData->getClientSecret($storeId);
            $env = $this->_configData->getApiEnvironment($storeId);

            if (!$clientId && !$clientSecret) {
                $this->_logger
                    ->info('No API credentials found for storeID ' . $storeId);
                $this->_updatePaymentPlan($storeId, $env, null, null, null, null);
                continue;
            }

            $pacePayload = $this->_getBasePayload($clientId, $clientSecret);

            try {
                $this->_client->setUri($endpoint);
                $this->_client->setMethod(Zend_Http_Client::GET);
                $this->_client->setHeaders($pacePayload['headers']);
                $response = $this->_client->request();
                if ($response->getStatus() < 200 || $response->getStatus() > 299) {
                    $this->_updatePaymentPlan($storeId, $env, null, null, null, null);
                    $this->_messageManager->addErrorMessage('Pace Config Error: Invalid API Credentials for storeId: ' . $storeId);
                    continue;
                }
                $responseJson = json_decode($response->getBody());

                $paymentPlans = $responseJson->{'list'};
                $paymentPlan = $paymentPlans[0];

                if (isset($paymentPlan)) {
                    $this->_updatePaymentPlan($storeId, $env, $paymentPlan->{'id'}, $paymentPlan->{'currencyCode'}, $paymentPlan->{'minAmount'}->{'actualValue'}, $paymentPlan->{'maxAmount'}->{'actualValue'});

                    $this->_logger
                        ->info('Pace refresh payment success for storeId ' . $storeId);
                    $this->_logger
                        ->info(json_encode($paymentPlan));
                } else {
                    $this->_updatePaymentPlan($storeId, $env, null, null, null, null);
                    $this->_logger
                        ->info('Pace refresh payment failure for storeId ' . $storeId);
                }
            } catch (\Exception $exception) {
                // $this->_updatePaymentPlan($storeId, $env, null, null, null, null);
                $this->_logger->error('Pace refresh payment failed with exception - ' .
                    $exception);
                $this->_messageManager->addErrorMessage('Something went wrong while refreshing the payment plan.');
            }
        }
        $this->_logger->info('Pace refresh payment plan execution complete');
        return self::REFRESH_SUCCESS;
    }
}
