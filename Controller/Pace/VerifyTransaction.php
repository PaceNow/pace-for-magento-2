<?php

namespace Pace\Pay\Controller\Pace;

use Pace\Pay\Model\Transaction;
use Pace\Pay\Model\Ui\ConfigProvider;
use Pace\Pay\Helper\ResponseRespository;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action;
use Magento\Framework\App\ActionInterface;

use Exception;

class VerifyTransaction extends Action\Action implements ActionInterface
{
    const ERROR_REDIRECT_URL = '/checkout/cart';
    const SUCCESS_REDIRECT_URL = '/checkout/onepage/success';

    const VERIFY_SUCCESS = 'verify_success';
    const VERIFY_UNKNOWN = 'verify_unknown';
    const VERIFY_FAILED = 'verify_failed';

    public function __construct(
        Session $session,
        Action\Context $context,
        Transaction $transaction,
        ResponseRespository $response
    )
    {
        parent::__construct($context);
        $this->session = $session;
        $this->response = $response;
        $this->transaction = $transaction;
    }

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
                    $this->transaction->doCompleteOrder($order);
                    return self::SUCCESS_REDIRECT_URL;
                    break;
                case self::VERIFY_FAILED:
                    $this->transaction->doCancelOrder($order);
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
        try {
            if (!$this->session->getLastRealOrderId()) {
                throw new Exception('Checkout session expired!');
            }

            $order = $this->session->getLastRealOrder();
            
            if (ConfigProvider::CODE != $order->getPayment()->getMethodInstance()->getCode()) {
                throw new Exception('The last order not paid with Pace!');
            }

            $tnxId = $order->getPayment()->getLastTransId();

            if (!$tnxId) {
                throw new Exception('Empty transaction ID!');
            }

            @$response = json_decode($this->transaction->getTransactionDetail($order));
            // Factory: transaction statuses
            $verifyResult = $this->transactionResultFactory($order, $response);

            return $this->response->redirectResponse($verifyResult)
        } catch (Exception $e) {
            return $this->response->redirectResponse(self::ERROR_REDIRECT_URL)
        }
    }
}
