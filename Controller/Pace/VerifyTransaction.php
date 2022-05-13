<?php

namespace Pace\Pay\Controller\Pace;

use Pace\Pay\Model\Ui\ConfigProvider;

use Exception;

class VerifyTransaction extends Transaction
{
    const ERROR_REDIRECT_URL = '/checkout/cart';
    const SUCCESS_REDIRECT_URL = '/checkout/onepage/success';

    const VERIFY_SUCCESS = 'verify_success';
    const VERIFY_UNKNOWN = 'verify_unknown';
    const VERIFY_FAILED = 'verify_failed';

    /**
     * transactionResultFactory...
     * 
     * @return string
     */
    protected function transactionResultFactory($order, $transaction)
    {
        $result = function($transaction) {
            if (isset($transaction->error)) {
                return self::VERIFY_UNKNOWN;
            }

            $status = $transaction->status;

            if ('approved' == $status) {
                return self::VERIFY_SUCCESS;
            } elseif ('cancelled' == $status) {
                return self::VERIFY_FAILED;
            }
        };

        $redirect = function($result, $order) {
            switch ($result) {
                case self::VERIFY_UNKNOWN:
                    return self::ERROR_REDIRECT_URL;
                    break;
                case self::VERIFY_SUCCESS:
                    $this->doCompleteOrder($order);
                    return self::SUCCESS_REDIRECT_URL;
                    break;
                case self::VERIFY_FAILED:
                    $this->doCancelOrder($order);
                    return self::ERROR_REDIRECT_URL;
                    break;
            }
        };

        return $redirect($result($transaction), $order);
    }

    /**
     * execute...
     * 
     * Verify transaction to update Order
     * 
     * @return Json
     */
    public function execute()
    {
        $redirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        try {
            if (!$this->checkoutSession->getLastRealOrderId()) {
                throw new Exception('Checkout session expired!');
            }

            $order = $this->checkoutSession->getLastRealOrder();
            
            if (ConfigProvider::CODE != $order->getPayment()->getMethodInstance()->getCode()) {
                throw new Exception('The last order not paid with Pace!');
            }

            $tnxId = $order->getPayment()->getLastTransId();

            if (!$tnxId) {
                throw new Exception('Empty transaction ID!');
            }

            @$response = json_decode($this->getTransactionDetail($order));
            // Factory: transaction statuses
            $verifyResult = $this->transactionResultFactory($order, $response);

            // $this->_eventManager->dispatch('pace_pay_verifytransaction_before_redirect'); TODO: remove

            return $redirect->setUrl($verifyResult);
        } catch (Exception $e) {
            die($e->getMessage());
            return $redirect->setUrl(self::ERROR_REDIRECT_URL);
        }
    }
}
