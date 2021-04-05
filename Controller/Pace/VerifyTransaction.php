<?php

namespace Pace\Pay\Controller\Pace;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Zend_Http_Client;

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
            $this->_handleCancel();
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

    public function getAllOrder()
    {
        $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts/' . 'list';
        $pacePayload = $this->_getBasePayload();
        $params = [
            "from" => date('Y-m-d', strtotime("-1 weeks")),
            "to" => date('Y-m-d'),
        ];

        $this->_client->resetParameters();
        try {
            $this->_client->setUri($endpoint);

            $this->_client->setHeaders($pacePayload['headers']);
            $this->_client->setRawData(json_encode($params));
            $response = $this->_client->request("POST");
            if ($response->getStatus() < 200 || $response->getStatus() > 299) {
                return self::VERIFY_UNKNOWN;
            }

            $pace_transaction = json_decode($response->getBody(), true);
            $orders = [];
            if (!!$pace_transaction['items']) {

                foreach ($pace_transaction['items'] as $key => $transaction) {
                    usort($transaction, function ($a, $b) {
                        return filter_var($a['transactionID'], FILTER_SANITIZE_NUMBER_INT) - filter_var($b['transactionID'], FILTER_SANITIZE_NUMBER_INT) > 0;
                    });

                    foreach ($transaction as $value) {
                        $orders[$value['referenceID']] = $value;
                    }
                }
            }

            return $orders;
        } catch (\Exception $exception) {
            return;
        }
    }

    public function checkOrderManuallyUpdate($order, $pace)
    {

        if ($pace['status'] == "pending_confirmation" && ($order->getState() == "canceled" || $order->getState() == 'closed')) {

            $this->cancelTransaction($order);
            return false;
        }

        if ($order->getState() == 'pending_payment') {
            return true;
        }

        if ($order->getState() != 'canceled') {
            return false;
        }

        $this->_objectManager = ObjectManager::getInstance();
        $trackStatus = $this->_objectManager->create('Pace\Pay\Model\TrackingStatus');
        return !$trackStatus->getCollection()->addFieldToFilter('order_id', ['eq' => (int) $order->getIncrementId()])->getSize();
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
