<?php

namespace Pace\Pay\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;

use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Controller\Result\JsonFactory;

use Magento\Store\Model\StoreManagerInterface;

use Pace\Pay\Helper\ConfigData;

use Exception;

class RefreshPaymentPlans extends Action
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        Action\Context $context,
        ConfigData $configData,
        JsonFactory $resultJsonFactory,
        StoreManagerInterface $storeManager,
        MessageManagerInterface $messageManager
    ) {
        parent::__construct($context);
        $this->configData = $configData;
        $this->storeManager = $storeManager;
        $this->messageManager = $messageManager;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    protected function response($data, $statusCode = 200)
    {
        $result = $this->resultJsonFactory->create();
        $result->setData($data);
        $result->setStatusHeader($statusCode);
        $result->setHttpResponseCode($statusCode);

        return $result;
    }

    /**
     * updatePaymentPlans...
     * 
     * @return Void
     */
    protected function updatePaymentPlans($storeId, $env, $response)
    {
        $this->configData->writeToConfig(ConfigData::CONFIG_PAYMENT_PLANS, json_encode($response->list), $storeId, $env);
    }

    /**
     * execute...
     * 
     * Refresh payment plans
     * 
     * @return Void
     */
    public function execute()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $basePayload = $this->configData->getBasePayload($storeId);

        $cURL = \Magento\Framework\App\ObjectManager::getInstance()
            ->create(\Magento\Framework\HTTP\Client\Curl::class);
        $cURL->setHeaders($basePayload['headers']);
        $cURL->setOptions([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        try {
            $cURL->get("{$basePayload['apiEndpoint']}/v1/checkouts/plans");
            @$response = json_decode($cURL->getBody());

            if (isset($response->error)) {
                throw new Exception("Pace refresh payment plans failure! Reason: {$response->error->message}");
            }

            $env = $this->configData->getApiEnvironment($storeId);
            $this->updatePaymentPlans($storeId, $env, $response);

            return $this->response([
                'success' => true
            ], 200);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            
            return $this->response([
                'success' => true
            ], 200);
        }
    }
}
