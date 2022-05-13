<?php

namespace Pace\Pay\Controller\Pace;

use Pace\Pay\Model\Ui\ConfigProvider;
use Pace\Pay\Helper\ConfigData;

use Magento\Checkout\Model\Session;

use Magento\Framework\DB\Transaction as DBTransaction;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Controller\Result\JsonFactory;

use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\Service\InvoiceService;

use Magento\Store\Model\StoreManagerInterface;

abstract class Transaction extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\ActionInterface 
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var ConfigData;
     */
    protected $configData;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var TransactionBuilder
     */
    protected $transactionBuilder;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var InvoiceSender
     */
    protected $_invoiceSender;

    /**
     * @var DBTransaction
     */
    protected $dbTransaction;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param Session $checkoutSession
     * @param ProductMetadataInterface $productMetadata
     * @param ConfigData $configData
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param TransactionBuilder $transactionBuilder
     * @param MessageManagerInterface $messageManager
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param DBTransaction $dbTransaction
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        ConfigData $configData,
        JsonFactory $resultJsonFactory,
        DBTransaction $dbTransaction,
        InvoiceSender $invoiceSender,
        InvoiceService $invoiceService,
        TransactionBuilder $transactionBuilder,
        StoreManagerInterface $storeManager,
        MessageManagerInterface $messageManager,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagement
    )
    {
        parent::__construct($context);
        $this->configData = $configData;
        $this->storeManager = $storeManager;
        $this->_invoiceSender = $invoiceSender;
        $this->dbTransaction = $dbTransaction;
        $this->invoiceService = $invoiceService;
        $this->messageManager = $messageManager;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->orderManagement = $orderManagement;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->transactionBuilder = $transactionBuilder;
    }

    public abstract function execute();

    /**
     * getBaseUrl...
     * 
     * @return string
     */
    protected function getBaseUrl($path = '')
    {
        $path = $path ?: \Magento\Framework\UrlInterface::URL_TYPE_WEB;
        
        return $this->storeManager->getStore()->getBaseUrl($path);
    }

    /**
     * getTransactionDetail...
     * 
     * @return Object
     */
    protected function getTransactionDetail($order)
    {
        $tnxId = $order->getPayment()->getLastTransId();
        $getBasePayload = $this->configData->getBasePayload($order->getStoreId());

        $cURL = \Magento\Framework\App\ObjectManager::getInstance()
                ->create(\Magento\Framework\HTTP\Client\Curl::class);
                
        $cURL->setHeaders($getBasePayload['headers']);
        $cURL->setOptions([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $cURL->get("{$getBasePayload['apiEndpoint']}/v1/checkouts/{$tnxId}");

        return $cURL->getBody();
    }

    /**
     * resultFactory...
     * 
     * @return Json
     */
    protected function resultFactory($data, $statusCode = 200)
    {
        $result = $this->resultJsonFactory->create();
        $result->setData($data);
        $result->setStatusHeader($statusCode);
        $result->setHttpResponseCode($statusCode);

        return $result;
    }

    /**
     * doAssignTransactionToOrder...
     * 
     * @return Void
     */
    public function doAssignTransactionToOrder($transaction, $order)
    {   
        $tnxId = $transaction->transactionID;
        $order->getPayment()->setLastTransId($tnxId);
        $order->addCommentToStatusHistory("Pace transaction is created (Reference ID: {$tnxId})");
        $this->orderRepository->save($order);
    }

    /**
     * doCloseOrder...
     * 
     * @return Void
     */
    protected function doCloseOrder($order)
    {
        $state = $this->configData->getConfigValue('pace_expired', $order->getStoreId()) ?? Order::STATE_CLOSED;
        $status = $order->getConfig()->getStateDefaultStatus($state);
        $comment = "Pace payment has expired (Reference ID: {$order->getPayment()->getLastTransId()})";

        $order->setState($state);
        $order->addStatusToHistory($status, $comment, false);
        $this->orderRepository->save($order);
    }

    /**
     * doCancelOrder...
     *
     * @return Void
     */
    public function doCancelOrder($order)
    {
        if ($order->canCancel()) {
            $state = $this->configData->getConfigValue('pace_canceled', $order->getStoreId()) ?? Order::STATE_CANCELED;
            $status = $order->getConfig()->getStateDefaultStatus($state);

            $tnxId = $order->getPayment()->getLastTransId();
            $comment = $tnxId
                ? "Pace payment canceled (Reference ID: {$tnxId})"
                : "Failed to create Pace's transaction";
            $order->setState($state);
            $order->addStatusToHistory($status, $comment, $isCustomerNotified = false);
            
            $this->orderRepository->save($order);
            $this->orderManagement->cancel($order->getId());
            $this->messageManager->addMessage(
                $this->messageManager->createMessage('notice', 'PACENOTICE')->setText($comment)
            );
            // clear cart
            $this->checkoutSession->restoreQuote();
        }
    }

    /**
     * doCompleteOrder...
     * 
     * @return Void
     */
    public function doCompleteOrder($order)
    {
        $this->createTransactionAttachedOrder($order);

        if ($this->configData->getConfigValue('generate_invoice', $order->getStoreId())) {
            // create invoice assigned to order
            $this->createInvoiceAttachedOrder($order);
        }

        $state = $this->configData->getConfigValue('pace_approved', $order->getStoreId()) ?? Order::STATE_PROCESSING;

        if (
            'pending_payment' == $order->getState() || 
            ($state != $order->getState() && $this->configData->getConfigValue('reinstate_order', $order->getStoreId()))
        ) {
            $this->applyApprovedStateOrders($order, $state);
            $order->addStatusHistoryComment("Pace payment is completed (Reference ID: {$order->getPayment()->getLastTransId()})");
        }

        $this->orderRepository->save($order);
    }

    /**
     * applyApprovedStateOrders...
     * 
     * Update orders depends on Product type (giftcard or others one)
     * 
     * @return Void
     */
    protected function applyApprovedStateOrders($order, $state)
    {
        $giftcardOnly = function($order) {
            $lineItems = $order->getAllItems();
            foreach ($lineItems as $item) {
                if ('giftcard' != $item->getProductType()) {
                    return false;
                    break;
                }
            }

            return true;
        };

        $finalState = $giftcardOnly ? Order::STATE_COMPLETE : $state;
        $order->setState($finalState)->setStatus(
            $order->getConfig()->getStateDefaultStatus($finalState)
        );

        $this->orderRepository->save($order);
    }

    /**
     * createTransactionAttachedOrder...
     * 
     * @return Void
     */
    protected function createTransactionAttachedOrder($order)
    {
        @$response = $this->getTransactionDetail($order);
        $responseJson = json_decode($response);

        if (!isset($responseJson->error)) {
            $tnxId = $responseJson->transactionID;
            $payment = $order->getPayment();
            $payment->setLastTransId($tnxId);
            $payment->setTransactionId($tnxId);
            $payment->setAdditionalInformation([
                PaymentTransaction::RAW_DETAILS => $response
            ]);

            // create prepare transaction
            $transaction = $this->transactionBuilder
                ->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($tnxId)
                ->setAdditionalInformation([
                    PaymentTransaction::RAW_DETAILS => $response
                ])
                ->setFailSafe(true)
                ->build(PaymentTransaction::TYPE_CAPTURE);
            $transaction->save();

            $payment->setParentTransactionId(null);
            $payment->save();
        }
    }
}
