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
        // clear old notice messages
        $this->clearNotices();

        $order = $this->_checkoutSession->getLastRealOrder();
        $verifyResult = $this->verifyAndInvoiceOrder( $order );
        $resultRedirect = $this->_resultFactory->create( ResultFactory::TYPE_REDIRECT );

        switch ( $verifyResult ) {

            case self::VERIFY_SUCCESS:
                $this->_handleApprove( $order );
                return $resultRedirect->setUrl( self::SUCCESS_REDIRECT_URL );

                break;
            case self::VERIFY_FAILED:
                $this->handleCancel();
                return $resultRedirect->setUrl( self::ERROR_REDIRECT_URL );

                break;
            case self::VERIFY_UNKNOWN:
                return $resultRedirect->setUrl( self::ERROR_REDIRECT_URL );

                break;
            default:
                // code...
                break;
        }
    }

    /**
     * Verify Pace transaction, and create invoice if payment successfuly
     * 
     * @param OrderInterface $order
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

    /**
     * Clear old notices message
     *
     * @since 1.0.5
     * @return @void
     */
    private function clearNotices()
    {
        $notices = $this->_messageManager->getMessages( true );
    }
}
