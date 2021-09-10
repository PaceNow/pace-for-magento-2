<?php

namespace Pace\Pay\Controller\Pace;

use Magento\Catalog\Model\CategoryRepository;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\HTTP\ZendClient;
use Pace\Pay\Gateway\Http\PayJsonConverter;
use Pace\Pay\Helper\ConfigData;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\App\Request\Http;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Repository as PaymentRepository;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Framework\DB\Transaction as DBTransaction;
use Magento\Framework\Module\ModuleListInterface;
use Pace\Pay\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderManagementInterface;

use Zend_Http_Client;

abstract class Transaction implements ActionInterface
{
    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var ZendClient
     */
    protected $_client;

    /**
     * @var PayJsonConverter
     */
    protected $_jsonConverter;

    /**
     * @var ConfigData;
     */
    protected $_configData;

    /**
     * @var QuoteRepository
     */
    protected $_quoteRepository;

    /**
     * @var Http
     */
    protected $_request;

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var ResultFactory
     */
    protected $_resultFactory;

    /**
     * @var CategoryRepository
     */
    protected $_categoryRepository;

    /**
     * @var Order
     */
    protected $_order;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var TransactionBuilder
     */
    protected $_transactionBuilder;

    /**
     * @var TransactionRepository
     */
    protected $_transactionRepository;

    /**
     * @var MessageManagerInterface
     */
    protected $_messageManager;

    /**
     * @var InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var InvoiceSender
     */
    protected $_invoiceSender;

    /**
     * @var InvoiceRepository
     */
    protected $_invoiceRepository;

    /**
     * @var PaymentRepository
     */
    protected $_paymentRepository;

    /**
     * @var DBTransaction
     */
    protected $_dbTransaction;

    /**
     * @var ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var OrderManagementInterface
     */
    protected $_orderManagement;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param Session $checkoutSession
     * @param ProductMetadataInterface $productMetadata
     * @param ZendClient $client
     * @param PayJsonConverter $jsonConverter
     * @param ConfigData $configData
     * @param QuoteRepository $quoteRepository
     * @param Http $request
     * @param OrderRepositoryInterface $orderRepository
     * @param ResultFactory $resultFactory
     * @param CategoryRepository $categoryRepository
     * @param Order $order
     * @param StoreManagerInterface $storeManager
     * @param TransactionBuilder $transactionBuilder
     * @param TransactionRepository $transactionRepository
     * @param MessageManagerInterface $messageManager
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param InvoiceRepository $invoiceRepository
     * @param PaymentRepository $_paymentRepository
     * @param DBTransaction $dbTransaction
     * @param ModuleListInterface $moduleList
     * @param LoggerInterface $logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Session $checkoutSession,
        ZendClient $client,
        PayJsonConverter $jsonConverter,
        ConfigData $configData,
        QuoteRepository $quoteRepository,
        Http $request,
        OrderRepositoryInterface $orderRepository,
        ResultFactory $resultFactory,
        CategoryRepository $categoryRepository,
        Order $order,
        StoreManagerInterface $storeManager,
        TransactionBuilder $transactionBuilder,
        TransactionRepository $transactionRepository,
        MessageManagerInterface $messageManager,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        InvoiceRepository $invoiceRepository,
        PaymentRepository $_paymentRepository,
        DBTransaction $dbTransaction,
        ModuleListInterface $moduleList,
        LoggerInterface $logger,
        OrderManagementInterface $orderManagement
    )
    {
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->_client = $client;
        $this->_jsonConverter = $jsonConverter;
        $this->_configData = $configData;
        $this->_quoteRepository = $quoteRepository;
        $this->_request = $request;
        $this->_orderRepository = $orderRepository;
        $this->_resultFactory = $resultFactory;
        $this->_categoryRepository = $categoryRepository;
        $this->_order = $order;
        $this->_storeManager = $storeManager;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_transactionRepository = $transactionRepository;
        $this->_messageManager = $messageManager;
        $this->_invoiceService = $invoiceService;
        $this->_invoiceSender = $invoiceSender;
        $this->_invoiceRepository = $invoiceRepository;
        $this->_paymentRepository = $_paymentRepository;
        $this->_dbTransaction = $dbTransaction;
        $this->_moduleList = $moduleList;
        $this->_logger = $logger;
        $this->_orderManagement = $orderManagement;
    }

    public abstract function execute();

    /**
     * @param mixed $data
     * @param int $statusCode
     * @return Json
     */
    protected function _jsonResponse($data, $statusCode = 200)
    {
        $result = $this->_resultJsonFactory->create();
        $result->setData($data);
        $result->setStatusHeader($statusCode);
        $result->setHttpResponseCode($statusCode);
        return $result;
    }

    /**
     * @return array
     */
    protected function _getBasePayload($store = null)
    {
        $pacePayload = $this->_configData->getBasePayload($store);

        return $pacePayload;
    }

    /**
     * @return string
     */
    protected function _getBaseUrl()
    {
        try {
            return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        } catch (\Exception $exception) {
            return '';
        }
    }

    /**
     * Create invoice during the complete orders
     * 
     * @param Magento\Sales\Model\Order $order
     * @param string $transactionId
     */
    protected function _invoiceOrder($order, $transactionId)
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

    protected function _handleClose($order)
    {
        $order = !is_null($order) ? 
            $order : $this->_checkoutSession->getLastRealOrder();
        $paceStatuses = $this->_configData->getExpiredStatus();
        $order->setState($paceStatuses['state'])->setStatus($paceStatuses['status']);
        $order->addCommentToStatusHistory('Pace transaction has been expired');
        $this->_orderRepository->save($order);
    }

    protected function _handleCancel($order = null, $isError = false)
    {
        $order = !is_null($order) ? 
            $order : $this->_checkoutSession->getLastRealOrder();

        $paceStatuses = $this->_configData->getCancelStatus();
        $order->setState($paceStatuses['state'])->setStatus($paceStatuses['status']);
        $order->addCommentToStatusHistory(__('Order with Pace canceled.'));
        $this->_orderRepository->save($order);
        $this->_orderManagement->cancel($order->getId());
        $this->_checkoutSession->restoreQuote();
        
        if ($isError) {
            $this->_messageManager->addErrorMessage('Could not checkout with Pace. Please try again.');
        } else {
            $this->_messageManager->addNoticeMessage('Your order was cancelled.');
        }
    }

    protected function _handleApprove($order, $isReinstate = false)
    {
        $payment = $order->getPayment();
        $payment->setIsTransactionClosed(true);

        // create magento orders transaction
        if ( !$isReinstate ) {
            $this->createTransactionAttachedOrder( $order, $payment );
        }
        
        $transactionId = $payment->getAdditionalData();
        
        if ( $this->_configData->getIsAutomaticallyGenerateInvoice() ) {
            $this->_invoiceOrder( $order, $transactionId );
        }

        // get approved statuses by merchant setting
        $this->_applyApprovedStateOrders( $order );

        $order->addStatusHistoryComment( __( 'Pace payment is completed (Reference ID: %1)', $transactionId ) );

        $this->_orderRepository->save( $order );
    }

    /**
     * Update orders depends on Product type (giftcard or others one)
     * 
     * @param  Magento\Sales\Model\Order $order
     * 
     * @since 1.0.4
     */
    protected function _applyApprovedStateOrders($order)
    {
        if ( !empty( $order->getAllItems() ) ) {
            
            $onlyHasGiftCard = false;

            $items = $order->getAllItems();
            foreach ( $items as $item ) {
                // check if each item in orders has 'giftcard' type
                if ( 'giftcard' == $item->getProductType() ) {
                    $onlyHasGiftCard = true;
                }else{
                    $onlyHasGiftCard = false;
                    break;                
                }
            }

            // complete orders if contains only giftcard product
            if ( $onlyHasGiftCard ) {
                $order->setState( Order::STATE_COMPLETE )->setStatus( Order::STATE_COMPLETE );
            } else {
                // change state & status based on setting
                $approvedStatus = $this->_configData->getApprovedStatus();

                if ( $approvedStatus ) {
                    $order->setState( $approvedStatus['state'] )->setStatus( $approvedStatus['status'] );
                }
            }

            $this->_orderRepository->save( $order );
        }
    }

    protected function createTransactionAttachedOrder($order, $payment)
    {
        $storeId = $order->getStoreId();
        $transactionId = $payment->getAdditionalData();

        if ($transactionId) {
            $endpoint = $this->_configData->getApiEndpoint($storeId) . '/v1/checkouts/' . $transactionId;
            $headers = $this->_configData->getBasePayload($storeId)['headers'];

            try {
                $this->_client->resetParameters();
                $this->_client->setUri($endpoint);
                $this->_client->setMethod(Zend_Http_Client::GET);
                $this->_client->setHeaders($headers);
                $response = $this->_client->request();

                if ($response->getStatus() < 200 || $response->getStatus() > 299) {
                    throw new \Exception('Unknown Pace transaction statuses');
                }

                $rawData = $response->getBody();
                $additionalPaymentInformation = array( PaymentTransaction::RAW_DETAILS => $rawData );
                // update order payment information
                $payment->setLastTransId( $transactionId );
                $payment->setTransactionId( $transactionId );
                $payment->setAdditionalInformation( $additionalPaymentInformation );
                $newOrdersTransaction = $this->_transactionBuilder
                    ->setPayment( $payment )
                    ->setOrder( $order )
                    ->setTransactionId( $transactionId )
                    ->setAdditionalInformation( $additionalPaymentInformation )
                    ->setFailSafe( true )
                    ->build( PaymentTransaction::TYPE_CAPTURE );

                $payment->setParentTransactionId( null );

                $this->_paymentRepository->save( $payment );
                $this->_transactionRepository->save( $newOrdersTransaction );
            } catch (\Exception $e) {
                $this->_logger->info($e->getMessage());
            }
        }
    }
}
