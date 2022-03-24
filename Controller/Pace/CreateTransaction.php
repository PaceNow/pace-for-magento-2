<?php


namespace Pace\Pay\Controller\Pace;

use Zend_Http_Client;

class CreateTransaction extends Transaction
{
    /**
     * Create Pace transaction
     * 
     * @since 1.0.0
     * @return Json
     */
    public function execute()
    {
        $order = $this->_checkoutSession->getLastRealOrder();
        $pacePayload = $this->_getBasePayload();
        $pacePayload['body'] = [
            'items' => $this->getSourceItems($order),
            'country' => $order->getBillingAddress()->getCountryId(),
            'currency' => $order->getOrderCurrencyCode(),
            'expiringAt' => $this->_configData->getExpiredTime(),
            'webhookUrl' => $this->_getBaseUrl() . 'rest/V1/pace/webhooks',
            'referenceId' => $order->getRealOrderId(),
            'amountFloat' => $order->getTotalDue(),
            'redirectUrls' => $this->getPaceRedirectURI($order)
        ];
        $this->getPaceBilling($order, $pacePayload);
        $this->getPaceShipping($order, $pacePayload);
      
        $this->_client->resetParameters();
        try {
            $endpoint = $this->_configData->getApiEndpoint() . '/v1/checkouts';

            $this->_client->setUri( $endpoint );
            $this->_client->setMethod( Zend_Http_Client::POST );
            $this->_client->setHeaders( $pacePayload['headers'] );
            $this->_client->setRawData( json_encode( $pacePayload['body'] ) );
            $response = $this->_client->request();
            $responseJson = json_decode( $response->getBody() );
            $tnxId = $responseJson->{'transactionID'};

            if ( !isset( $tnxId ) ) {
                throw new \Exception('Fail to create Pace transaction');
            }

            $payment = $order->getPayment();
            $payment->setTransactionId( $tnxId );
            $payment->setAdditionalData( $tnxId );
            $this->_paymentRepository->save( $payment );

            $order->addCommentToStatusHistory( __( 'Pace transaction is created (Reference ID: %1)', $tnxId ) );
            $this->_orderRepository->save( $order );

            return $this->_jsonResponse( $responseJson, $response->getStatus() );
        } catch (\Exception $exception) {
            $this->handleCancel( null, true );
            return $this->_jsonResponse( [], 500 );
        }
    }

    /**
     * Prepare source items for transaction
     *
     * @param Magento\Sales\Model\Order $order
     * @since 1.0.3 
     * @return array 
     */
    private function getSourceItems( $order )
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
                'tags' => array_map(
                    [$this, '_getCategoryName'], 
                    $item->getProduct()->getCategoryIds()
                ),
            ];
        }, $orderItems);

        return $items;
    }

    /**
     * Prepare Pace transaction billing
     * 
     * @param Magento\Sales\Model\Order $order
     * @param array $pacePayload 
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
     * @return void
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
     * Prepare Pace redirect urls
     *
     * @param Magento\Sales\Model\Order $order
     * @since 1.0.5
     * @return array
     */
    private function getPaceRedirectURI($order)
    {
        $baseURL = $this->_getBaseUrl();

        return array(
            'success' => $baseURL . 'pace_pay/pace/verifytransaction',
            'failed' => $baseURL . 'pace_pay/pace/verifytransaction'
        );    
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
