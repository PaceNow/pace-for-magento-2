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
use \Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Repository as PaymentRepository;
use Magento\Framework\DB\Transaction as DBTransaction;
use Magento\Framework\Module\ModuleListInterface;
use Pace\Pay\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderManagementInterface;

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
     * @var ProductMetadataInterface
     */
    protected $_metaDataInterface;

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
        ProductMetadataInterface $productMetadata,
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
        $this->_metaDataInterface = $productMetadata;
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
        $magentoVersion = $this->_metaDataInterface->getVersion();
        $pluginVersion = $this->_moduleList->getOne(ConfigProvider::MODULE_NAME)['setup_version'];
        $platformVersionString = ConfigProvider::PLUGIN_NAME . ',' . $pluginVersion . ',' . $magentoVersion;

        $authToken = base64_encode(
            $this->_configData->getClientId($store) . ':' .
            $this->_configData->getClientSecret($store)
        );

        $pacePayload = [];
        $pacePayload['headers'] = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $authToken,
            'X-Pace-PlatformVersion' => $platformVersionString,
        ];

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

    protected function _handleCancel($isError = false)
    {
        $order = $this->_checkoutSession->getLastRealOrder();
        $order->setStatus(Order::STATE_CANCELED);
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
}
