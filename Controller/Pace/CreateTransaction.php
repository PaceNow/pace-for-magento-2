<?php
namespace Pace\Pay\Controller\Pace;

use Exception;

class CreateTransaction extends Transaction
{
    /**
     * getSource...
     * 
     * return list items as source for transaction
     * 
     * @return array
     */
    protected function getSource($order)
    {
        $mediaPath = $this->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $categoryFactory = \Magento\Framework\App\ObjectManager::getInstance()
            ->create(\Magento\Catalog\Model\CategoryRepository::class);

        $lines = $order->getAllVisibleItems();
        $lineItems = array_map(function($product) 
            use (
                $order,
                $mediaPath, 
                $categoryFactory
            ) {
            $image = $product->getProduct()->getImage();
            $image = $image ? "{$mediaPath}catalog/product{$image}" : '';

            $categories = $product->getProduct()->getCategoryIds();
            $tags = $categories
                ? array_map(function($cate) 
                    use (
                        $categoryFactory
                    ) {
                    return $categoryFactory->get($cate)->getName();
                }, $categories)
                : [];

            return [
                'name' => $product->getName(),
                'tags' => $tags,
                'brand' => '',
                'itemId' => $product->getId(),
                'itemType' => $product->getProductType(),
                'imageUrl' => $image,
                'quantity' => (int) $product->getQtyOrdered(),
                'unitPrice' => $this->configData->convertPricebyCountry($product->getPrice(), $order->getOrderCurrencyCode()),
                'productUrl' => $product->getProduct()->getProductUrl(),
            ];
        }, $lines);

        return $lineItems;
    }

    /**
     * getExpiredTime...
     * 
     * @return string
     */
    protected function getExpiredTime($storeId = null)
    {
        $expiredTime = $this->configData->getConfigValue('expired_time', $storeId);

        if (empty($expiredTime)) {
            return '';
        }

        $now = new \DateTime();
        $expiredTime = $now->modify(sprintf('+%s minutes', $expiredTime));
            
        return $expiredTime->format('Y-m-d H:i:s');
    }

    /**
     * getWebhookUrl...
     * 
     * @return string
     */
    protected function getWebhookUrl($order)
    {   
        $securityCode = $this->configData->encrypt($order->getRealOrderId());

        return "{$this->getBaseUrl()}pace_pay/pace/webhookcallback?securityCode={$securityCode}";
    }

    /**
     * getTransactionResource...
     * 
     * @return array
     */
    protected function getTransactionResource($order)
    {
        $storeId = $order->getStoreId();
        $verifyUrl = "{$this->getBaseUrl()}pace_pay/pace/verifytransaction";
        $getBillingAddress = $order->getBillingAddress()->getData();
        $getShippingAddress = $order->getShippingAddress()->getData();

        return [
            'items' => $this->getSource($order),
            'currency' => $order->getOrderCurrencyCode(),
            'expiringAt' => $this->getExpiredTime($storeId),
            'webhookUrl' => $this->getWebhookUrl($order),
            'referenceID' => $order->getRealOrderId(),
            'amountFloat' => $order->getTotalDue(),
            'redirectUrls' => [
                'success' => $verifyUrl,
                'failed' => $verifyUrl
            ],
            'billingAddress' => [
                'city' => $getBillingAddress['city'],
                'addr1' => $getBillingAddress['street'],
                'addr2' => '',
                'email' => $getBillingAddress['email'],
                'state' => $getBillingAddress['region'],
                'phone' => $getBillingAddress['telephone'],
                'region' => $getBillingAddress['region'],
                'lastName' => $getBillingAddress['lastname'],
                'firstName' => $getBillingAddress['firstname'],
                'postalCode' => $getBillingAddress['postcode'],
                'countryIsoCode' => $getBillingAddress['country_id'],
            ],
            'shippingAddress' => [
                'city' => $getShippingAddress['city'],
                'addr1' => $getShippingAddress['street'],
                'addr2' => '',
                'email' => $getShippingAddress['email'],
                'state' => $getShippingAddress['region'],
                'phone' => $getShippingAddress['telephone'],
                'region' => $getShippingAddress['region'],
                'lastName' => $getShippingAddress['lastname'],
                'firstName' => $getShippingAddress['firstname'],
                'postalCode' => $getShippingAddress['postcode'],
                'countryIsoCode' => $getShippingAddress['country_id'],
            ]
        ];
    }

    /**
     * execute...
     * 
     * create a new transction
     * 
     * @return mixed
     */
    public function execute()
    {
        try {
            if (!$this->checkoutSession->getLastRealOrderId()) {
                return $this->resultFactory(['message' => 'Checkout session expired!'], 404);
            }

            $order = $this->checkoutSession->getLastRealOrder();
            $transactionResource = $this->getTransactionResource($order);
            
            $getBasePayload = $this->configData->getBasePayload($order->getStoreId());

            $cURL = \Magento\Framework\App\ObjectManager::getInstance()
                ->create(\Magento\Framework\HTTP\Client\Curl::class);
                
            $cURL->setHeaders($getBasePayload['headers']);
            $cURL->setOptions([
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            $cURL->post("{$getBasePayload['apiEndpoint']}/v1/checkouts", json_encode($transactionResource));
            @$response = json_decode($cURL->getBody());
            
            if (isset($response->error)) {
                throw new Exception("Create Pace transaction failed. Reason: {$response->error->message}");
            }
            
            $this->doAssignTransactionToOrder($response, $order);
            return $this->resultFactory($response, 200);
        } catch (Exception $e) {
            $this->doCancelOrder($order);
            return $this->resultFactory(['message' => $e->getMessage()]);
        }
    }
}
