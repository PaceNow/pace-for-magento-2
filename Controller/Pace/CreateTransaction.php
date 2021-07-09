<?php


namespace Pace\Pay\Controller\Pace;

use Zend_Http_Client;

class CreateTransaction extends Transaction
{
    public function execute()
    {
        $order = $this->_checkoutSession->getLastRealOrder();
        $pacePayload = $this->_getBasePayload();
        $redirectUrls = [
            'success' => $this->_getBaseUrl() . 'pace_pay/pace/verifytransaction',
            'failed' => $this->_getBaseUrl() . 'pace_pay/pace/verifytransaction'    
        ];
        $pacePayload['body'] = [
            'items' => $this->getSourceitems($order),
            'country' => $order->getBillingAddress()->getCountryId(),
            'currency' => $order->getOrderCurrencyCode(),
            'webhookUrl' => $this->_getBaseUrl() . 'rest/V1/pace/webhooks',
            'referenceId' => $order->getId(),
            'amountFloat' => $order->getTotalDue(),
            'redirectUrls' => $redirectUrls
        ];
        $this->getPaceBilling($order, $pacePayload);
        $this->getPaceShipping($order, $pacePayload);
      
        $this->_client->resetParameters();
        try {
            $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts';

            $this->_client->setUri($endpoint);
            $this->_client->setMethod(Zend_Http_Client::POST);
            $this->_client->setHeaders($pacePayload['headers']);
            $this->_client->setRawData(json_encode($pacePayload['body']));
            $response = $this->_client->request();
            $responseJson = json_decode($response->getBody());
            $paceTransactionId = $responseJson->{'transactionID'};

            if ($paceTransactionId == null || $paceTransactionId == '') {
                throw new \Exception('Fail to create Pace transaction');
            }

            $payment = $order->getPayment();
            $payment->setTransactionId($paceTransactionId);
            $payment->setAdditionalData($paceTransactionId);
            $order->addCommentToStatusHistory(
                __('Pace transaction is created (Reference ID: %1)', $paceTransactionId)
            );

            $this->_orderRepository->save($order);
            $this->_paymentRepository->save($payment);

            return $this->_jsonResponse($responseJson, $response->getStatus());
        } catch (\Exception $exception) {
            $this->_handleCancel(null, true);
            return $this->_jsonResponse([], 500);
        }
    }

    /**
     * Prepare source items for transaction
     *
     * @param Magento\Sales\Model\Order $order
     * @since 1.0.3 
     * @return array 
     */
    private function getSourceitems($order)
    {
        $orderItems = $order->getAllVisibleItems();

        if (!$orderItems) {
            return array();
        }

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

        return $items;
    }

    /**
     * Prepare Pace transaction billing
     * 
     * @param Magento\Sales\Model\Order $order
     * @param arrat $pacePayload 
     * @since 0.0.26
     */
    private function getPaceBilling($order, &$pacePayload)
    {
        $getBillingAddress = $order->getBillingAddress();

        if (!$getBillingAddress) {
            return;
        }

        $billingDetails = $getBillingAddress->getData();

        if (empty($billingDetails)) {
            return;
        }

        $pacePayload['body']['billingAddress'] = [
            'firstName' => $billingDetails['firstname'],
            'lastName' => $billingDetails['lastname'],
            'addr1' => $billingDetails['street'],
            'addr2' => '',
            'city' => $billingDetails['city'],
            'state' => $billingDetails['region'],
            'region' => $billingDetails['region'],
            'postalCode' => $billingDetails['postcode'],
            'countryIsoCode' => $billingDetails['country_id'],
            'phone' => $billingDetails['telephone'],
            'email' => $billingDetails['email'],
        ];
    }

    /**
     * Prepare Pace transaction shipping
     *
     * @param Magento\Sales\Model\Order $order
     * @param array $pacePayload 
     * @since 0.0.26
     */
    private function getPaceShipping($order, &$pacePayload)
    {   
        $getShippingAddress = $order->getShippingAddress();

        if (!$getShippingAddress) {
            return;
        }

        $shippingDetails = $getShippingAddress->getData();

        if (empty($shippingDetails)) {
            return;
        }

        $pacePayload['body']['shippingAddress'] = [
            'firstName' => $shippingDetails['firstname'],
            'lastName' => $shippingDetails['lastname'],
            'addr1' => $shippingDetails['street'],
            'addr2' => '',
            'city' => $shippingDetails['city'],
            'state' => $shippingDetails['region'],
            'region' => $shippingDetails['region'],
            'postalCode' => $shippingDetails['postcode'],
            'countryIsoCode' => $shippingDetails['country_id'],
            'phone' => $shippingDetails['telephone'],
            'email' => $shippingDetails['email'],
        ];
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
