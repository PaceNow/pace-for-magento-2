<?php

namespace Pace\Pay\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Pace\Pay\Helper\AdminStoreResolver;
use Pace\Pay\Helper\ConfigData;
use Psr\Log\LoggerInterface;
use Zend_Http_Client;

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
    ) {
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
                "success" => true,
            ], 200);
        } else {
            return $this->_jsonResponse([
                "success" => true,
            ], 500);
        }
    }

    public function _updatePaymentPlan($storeId, $env, $plans)
    {
        if (!$plans) {
            return;
        }

        if (is_object($plans) || is_array($plans)) {
            $plans = json_encode($plans);
        }

        $this->_configData->writeToConfig(
            $key = ConfigData::CONFIG_PAYMENT_PLANS,
            $value = $plans,
            $storeId,
            $env
        );
    }

    public function refreshPlans()
    {
        // Refresh payment plans for all stores
        $stores = $this->_storeRepository->getList();
        foreach ($stores as $store) {
            $storeId = $store->getId();
            
            $clientId = $this->_configData->getClientId($storeId);
            $clientSecret = $this->_configData->getClientSecret($storeId);
            $env = $this->_configData->getApiEnvironment($storeId);

            if (!$clientId && !$clientSecret) {
                $this->_logger->info('No API credentials found for storeID ' . $storeId);
                $this->_updatePaymentPlan($storeId, $env, null);
                continue;
            }

            $pacePayload = $this->_configData->getBasePayload( $storeId );

            try {
                $endpoint = $this->_configData->getApiEndpoint($storeId) . '/v1/checkouts/plans';
                $this->_client->setUri($endpoint);
                $this->_client->setMethod(Zend_Http_Client::GET);
                $this->_client->setHeaders($pacePayload['headers']);
                $response = $this->_client->request();

                if ($response->getStatus() < 200 || $response->getStatus() > 299) {
                    $this->_updatePaymentPlan($storeId, $env, null);
                    $this->_messageManager->addErrorMessage('Pace Config Error: Invalid API Credentials for storeId: ' . $storeId);
                    continue;
                }

                $responseJson = json_decode($response->getBody());
                $paymentPlans = $responseJson->{'list'};

                if (isset($paymentPlans)) {
                    $this->_updatePaymentPlan($storeId, $env, $paymentPlans);

                    $this->_logger->info('Pace refresh payment success for storeId ' . $storeId);
                    $this->_logger->info(json_encode($paymentPlans));
                } else {
                    $this->_updatePaymentPlan($storeId, $env, null);
                    $this->_logger->info('Pace refresh payment failure for storeId ' . $storeId);
                }
            } catch (\Exception $exception) {
                // $this->_updatePaymentPlan($storeId, $env, null, null, null, null);
                $this->_logger->error('Pace refresh payment failed with exception - ' . $exception);
                $this->_messageManager->addErrorMessage('Something went wrong while refreshing the payment plan.');
            }
        }
        
        $this->_logger->info('Pace refresh payment plan execution complete');
        return self::REFRESH_SUCCESS;
    }
}
