<?php

namespace Pace\Pay\Controller\Pace;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\OrderInterface;
use Zend_Http_Client;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;

class VerifyTransaction extends Transaction
{
    const ERROR_REDIRECT_URL = '/checkout/cart';
    const SUCCESS_REDIRECT_URL = '/checkout/onepage/success';
    const VERIFY_SUCCESS = 'verify_success';
    const VERIFY_FAILED = 'verify_failed';
    const VERIFY_UNKNOWN = 'verify_unknown';

    public function execute()
    {
        $order = $this->_checkoutSession->getLastRealOrder();
        $verifyResult = $this->verifyAndInvoiceOrder($order);
        $resultRedirect = $this->_resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if ($verifyResult == self::VERIFY_SUCCESS) {
            return $resultRedirect->setUrl(self::SUCCESS_REDIRECT_URL);
        } else if ($verifyResult == self::VERIFY_FAILED) {
            $this->_handleError();
            return $resultRedirect->setUrl(self::ERROR_REDIRECT_URL);
        } else {
            return $resultRedirect->setUrl(self::SUCCESS_REDIRECT_URL);
        }
    }

    /**
     * @param OrderInterface $order
     * @return string
     */
    public function verifyAndInvoiceOrder($order)
    {
        $payment = $order->getPayment();
        $transactionId = $payment->getAdditionalData();

        if ($transactionId == null || $transactionId == '') {
            return self::VERIFY_FAILED;
        }

        $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts/' . $transactionId;
        $pacePayload = $this->_getBasePayload();

        $this->_client->resetParameters();
        $paceTransaction = null;
        try {
            $this->_client->setUri($endpoint);
            $this->_client->setMethod(Zend_Http_Client::GET);
            $this->_client->setHeaders($pacePayload['headers']);
            $response = $this->_client->request();
            if ($response->getStatus() < 200 || $response->getStatus() > 299) {
                return self::VERIFY_UNKNOWN;
            }
            $paceTransaction = json_decode($response->getBody());
        } catch (\Exception $exception) {
            return self::VERIFY_UNKNOWN;
        }

        $payment->setIsTransactionClosed(true);
        $paceTransactionStatus = $paceTransaction->{'status'};

        if ($paceTransactionStatus == 'approved') {
            $order->setStatus(Order::STATE_PROCESSING);

//            $formattedPrice = $order->getBaseCurrency()->formatTxt(
//                $order->getGrandTotal()
//            );
            $message = __('Pace payment is completed (Reference ID: %1)', $transactionId);
            $order->addStatusHistoryComment($message);

            $payment->setLastTransId($transactionId);
            $payment->setTransactionId($transactionId);
            $additionalPaymentInformation = [PaymentTransaction::RAW_DETAILS => json_encode($paceTransaction)];
            $payment->setAdditionalInformation($additionalPaymentInformation);
            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($transactionId)
                ->setAdditionalInformation($additionalPaymentInformation)
                ->setFailSafe(true)
                ->build(PaymentTransaction::TYPE_CAPTURE);
            $payment->setParentTransactionId(null);

            $this->_paymentRepository->save($payment);
            $this->_orderRepository->save($order);
            $this->_transactionRepository->save($transaction);

            if ($this->_configData->getIsAutomaticallyGenerateInvoice()) {
                $this->_invoiceOrder($order, $transactionId);
            }

            $result = self::VERIFY_SUCCESS;
        } else {
            $result = self::VERIFY_FAILED;
        }
        $this->_orderRepository->save($order);

        return $result;
    }

    /**
     * @param Order $order
     */
    public function cancelTransaction($order)
    {
        $payment = $order->getPayment();
        $transactionId = $payment->getAdditionalData();

        if ($transactionId == null || $transactionId == '') {
            return;
        }

        $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts/' . $transactionId . '/cancel';
        $pacePayload = $this->_getBasePayload();

        $this->_client->resetParameters();
        try {
            $this->_client->setUri($endpoint);
            $this->_client->setMethod(Zend_Http_Client::POST);
            $this->_client->setHeaders($pacePayload['headers']);
            $this->_client->request();
        } catch (\Exception $exception) {
            return;
        }
    }

    /**
     * @param Order $order
     * @param string $transactionId
     */
    private function _invoiceOrder($order, $transactionId)
    {
        if ($order->canInvoice()) {
            try {
                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->setTransactionId($transactionId);
                $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $this->_invoiceRepository->save($invoice);
                $dbTransactionSave = $this->_dbTransaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $dbTransactionSave->save();
                $order->addCommentToStatusHistory(
                    __('Notified customer about invoice creation #%1', $invoice->getId())
                )->setIsCustomerNotified(true);
                $this->_orderRepository->save($order);
            } catch (\Exception $exception) {
                $order->addCommentToStatusHistory(
                    __('Failed to generate invoice automatically')
                );
                $this->_orderRepository->save($order);
            }
        }
    }
}
