<?php


namespace Pace\Pay\Controller\Pace;

use Zend_Http_Client;

class CreateTransaction extends Transaction
{
    public function execute()
    {
        $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts';
        $order = $this->_checkoutSession->getLastRealOrder();
        $payment = $order->getPayment();
        $pacePayload = $this->_getBasePayload();
        $orderItems = $order->getAllVisibleItems();
        $items = array_map(function ($item) {
            return [
                'itemId' => $item->getItemId(),
                'itemType' => $item->getProductType(),
                'reference' => $item->getSku(),
                'name' => $item->getName(),
                'quantity' => $item->getQuantityOrdered(),
                'unitPriceCents' => $item->getPrice(),
                'productUrl' => $item->getProduct()->getProductUrl(),
                'brand' => '',
                'tags' => array_map(array($this, '_getCategoryName'), $item->getProduct()
                    ->getCategoryIds()),
            ];
        }, $orderItems);
        $redirectUrls = [
            'success' => $this->_getBaseUrl() . 'pace_pay/pace/verifytransaction',
            'failed' => $this->_getBaseUrl() . 'pace_pay/pace/verifytransaction'
        ];
        $pacePayload['body'] = [
            'referenceId' => $order->getRealOrderId(),
            'amountFloat' => $order->getTotalDue(),
            'currency' => $order->getOrderCurrencyCode(),
            'country' => $order->getBillingAddress()->getCountryId(),
            'items' => $items,
            'redirectUrls' => $redirectUrls
        ];

        $this->_client->resetParameters();
        try {
            $this->_client->setUri($endpoint);
            $this->_client->setMethod(Zend_Http_Client::POST);
            $this->_client->setHeaders($pacePayload['headers']);
            $this->_client->setRawData(json_encode($pacePayload['body']));
            $response = $this->_client->request();
            $responseJson = json_decode($response->getBody());
            $paceTransactionId = $responseJson->{'transactionID'};

            if ($paceTransactionId == null || $paceTransactionId == '') {
                $this->_handleCancel(true);
                return $this->_jsonResponse([], 500);
            }

            $payment->setTransactionId($paceTransactionId);
            $payment->setAdditionalData($paceTransactionId);
            $order->addCommentToStatusHistory(
                __('Pace transaction is created (Reference ID: %1)', $paceTransactionId)
            );

            $this->_orderRepository->save($order);
            $this->_paymentRepository->save($payment);

            return $this->_jsonResponse($responseJson, $response->getStatus());
        } catch (\Exception $exception) {
            $this->_handleCancel(true);
            return $this->_jsonResponse([], 500);
        }
    }

    /**
     * @param $categoryId
     * @return string|null
     */
    private function _getCategoryName($categoryId)
    {
        try {
            $category = $this->_categoryRepository->get($categoryId);
            return $category->getName();
        } catch (\Exception $exception) {
            return null;
        }
    }
}
