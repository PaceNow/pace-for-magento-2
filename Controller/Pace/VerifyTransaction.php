<?php

namespace Pace\Pay\Controller\Pace;

use Magento\Framework\Controller\ResultFactory;

use Zend_Http_Client;

class VerifyTransaction extends Transaction
{
    const ERROR_REDIRECT_URL = '/checkout/cart';
    const SUCCESS_REDIRECT_URL = '/checkout/onepage/success';
    const VERIFY_SUCCESS = 'verify_success';
    const VERIFY_UNKNOWN = 'verify_unknown';
    const VERIFY_FAILED = 'verify_failed';

    public function execute()
    {
        $resultRedirect = $this->_resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $payload = $this->_request->getParams();

            if (!isset($payload['merchantReferenceId']) || empty($payload['merchantReferenceId'])) {
                throw new \Exception('Verify transaction: missing merchantReferenceId.');
            }

            $referenceId = (int) $payload['merchantReferenceId'];
            $order = $this->_order->loadByIncrementId($referenceId);
            
            if (empty($order)) {
                throw new \Exception('Verify transaction: unknow order.');
            }
            
            $verifyResult = $this->verifyAndInvoiceOrder($order);
            switch ($verifyResult) {
                case self::VERIFY_SUCCESS:
                    $this->_handleApprove($order);
                    $url = self::SUCCESS_REDIRECT_URL;
                    break;
                case self::VERIFY_FAILED:
                    $this->handleCancel($order);
                    $url = self::ERROR_REDIRECT_URL;
                    break;
                case self::VERIFY_UNKNOWN:
                    $url = self::ERROR_REDIRECT_URL;
                    break;
                default:
                    $url = '/';
                    break;
            }

            $this->_eventManager->dispatch('pace_pay_verifytransaction_before_redirect');

            return $resultRedirect->setUrl($url);
        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());
            return $resultRedirect->setUrl(self::ERROR_REDIRECT_URL);
        }
    }

    /**
     * Verify Pace transaction, and create invoice if payment successfuly
     * 
     * @param Order $order
     * @return string
     */
    public function verifyAndInvoiceOrder($order)
    {
        $payment = $order->getPayment();

        if ( is_null( $payment ) ) {
            return self::VERIFY_UNKNOWN;
        }

        $transactionId = $payment->getAdditionalData();

        if ( is_null( $transactionId ) || empty( $transactionId ) ) {
            return self::VERIFY_FAILED;
        }

        $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts/' . $transactionId;
        $pacePayload = $this->_getBasePayload();

        $this->_client->resetParameters();
        $paceTransaction = null;

        try {
            $this->_client->setUri( $endpoint );
            $this->_client->setMethod( Zend_Http_Client::GET );
            $this->_client->setHeaders( $pacePayload['headers'] );
            $response = $this->_client->request();

            if ( $response->getStatus() < 200 || $response->getStatus() > 299 ) {
                throw new \Exception('Failed to get Pace transactions');
            }

            $transaction = json_decode( $response->getBody() );
            $statuses = $transaction->{'status'};

            if ( 'approved' == $statuses ) {
                return self::VERIFY_SUCCESS;
            }

            return self::VERIFY_FAILED;
        } catch (\Exception $exception) {
            return self::VERIFY_UNKNOWN;
        }
    }

    /**
     * Make a APIs request to cancel Pace transaction
     *
     * @since 1.0.3
     * @param  Magento\Sales\Model\Order $order
     * @return boolean
     */
    public function cancelTransaction( $order )
    {
        $payment = $order->getPayment();
        $tnxId = $payment->getAdditionalData();

        if ( empty( $tnxId ) ) {
            return;
        }

        $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts/' . $tnxId . '/cancel';
        $pacePayload = $this->_getBasePayload();

        $this->_client->resetParameters();
        try {
            $this->_client->setUri( $endpoint );
            $this->_client->setMethod( Zend_Http_Client::POST );
            $this->_client->setHeaders( $pacePayload['headers'] );
            $this->_client->request();
        } catch (\Exception $exception) {
            return;
        }
    }
}
