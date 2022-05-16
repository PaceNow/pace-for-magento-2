<?php
namespace Pace\Pay\Controller\Pace;

use Pace\Pay\Model\Transaction;
use Pace\Pay\Helper\AdminStoreResolver;
use Pace\Pay\Helper\ResponseRespository;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action;

use Psr\Log\LoggerInterface;

use Exception;

class CreateTransaction extends Action\Action implements ActionInterface
{
    public function __construct(
        Session $session,
        Action\Context $context, 
        Transaction $transaction, 
        LoggerInterface $logger,
        AdminStoreResolver $adminResolver,
        ResponseRespository $response
    )
    {
        parent::__construct($context);
        $this->logger = $logger;
        $this->session = $session;
        $this->response = $response;
        $this->transaction = $transaction;
        $this->adminResolver = $adminResolver;
    }

    /**
     * getSource...
     * 
     * return list items as source for transaction
     * 
     * @return array
     */
    protected function getSource($order)
    {
        $mediaPath = $this->adminResolver->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
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
                'unitPrice' => $this->transaction->convertPricebyCountry($product->getPrice(), $order->getOrderCurrencyCode()),
                'productUrl' => $product->getProduct()->getProductUrl(),
            ];
        }, $lines);

        return $lineItems;
    }

    /**
     * getTransactionResource...
     * 
     * @return array
     */
    protected function getTransactionResource($order)
    {
        $storeId = $order->getStoreId();
        $verifyUrl ="{$this->adminResolver->getBaseUrl()}pace_pay/pace/verifytransaction";
        $getBillingAddress = $order->getBillingAddress()->getData();
        $getShippingAddress = $order->getShippingAddress()->getData();

        return [
            'items' => $this->getSource($order),
            'currency' => $order->getOrderCurrencyCode(),
            'expiringAt' => $this->transaction->getExpiredTime($storeId),
            'webhookUrl' => $this->transaction->getWebhookUrl($this->adminResolver->getBaseUrl(), $order),
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
            if (!$this->session->getLastRealOrderId()) {
                return $this->response->jsonResponse(['message' => 'Checkout session expired!'], 404);
            }

            $order = $this->session->getLastRealOrder();
            $getBasePayload = $this->transaction->getBasePayload($order->getStoreId());
            $transactionResource = $this->getTransactionResource($order);

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
                throw new Exception($response->error->message);
            }
            
            $this->transaction->doAssignTransactionToOrder($response, $order);
            return $this->response->jsonResponse($response);
        } catch (Exception $e) {
            $this->logger->info("Create Pace transaction failed. Reason: {$e->getMessage()}");
            $this->transaction->doCancelOrder($order);
            return $this->response->jsonResponse(['message' => $e->getMessage()]);
        }
    }
}
