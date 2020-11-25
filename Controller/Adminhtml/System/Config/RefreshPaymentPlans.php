<?php

namespace Pace\Pay\Controller\Adminhtml\System\Config;

use Zend_Http_Client;
use Pace\Pay\Helper\ConfigData;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\HTTP\ZendClient;

// payment_plan_min
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
        WriterInterface $configWriter,
        ConfigData $configData
    ) {
        $this->_client = $client;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_configWriter = $configWriter;
        $this->_configData = $configData;
        parent::__construct($context);
    }


    private function _getBasePayload()
    {
        $authToken = base64_encode(
            $this->_configData->getClientId() . ':' .
                $this->_configData->getClientSecret()
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
                "paymentPlan" => $this->_paymentPlan
            ], 200);
        } else {
            return $this->_jsonResponse([
                "success" => FALSE
            ], 500);
        }
    }

    public function refreshPlans()
    {
        $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts/plans';
        $pacePayload = $this->_getBasePayload();

        try {
            $this->_client->setUri($endpoint);
            $this->_client->setMethod(Zend_Http_Client::GET);
            $this->_client->setHeaders($pacePayload['headers']);
            $response = $this->_client->request();
            if ($response->getStatus() < 200 || $response->getStatus() > 299) {
                return [];
            }
            $responseJson = json_decode($response->getBody());

            $paymentPlans = $responseJson->{'list'};
            $paymentPlan = $paymentPlans[0];
            $this->_paymentPlan = $paymentPlan;

            // write to config
            $this->_configWriter->save('payment/pace_pay/payment_plan_id', $paymentPlan->{'id'});
            $this->_configWriter->save('payment/pace_pay/payment_plan_currency', $paymentPlan->{'currencyCode'});
            $this->_configWriter->save('payment/pace_pay/payment_plan_min', $paymentPlan->{'minAmount'}->{'actualValue'});
            $this->_configWriter->save('payment/pace_pay/payment_plan_max', $paymentPlan->{'maxAmount'}->{'actualValue'});

            return self::REFRESH_SUCCESS;
        } catch (\Exception $exception) {
            return self::REFRESH_FAILURE;
        }
    }
}
