<?php 

namespace Pace\Pay\Model;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DB\Transaction as DBTransaction;
use Magento\Framework\Webapi\Rest\Request as WebapiRequest;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Repository as PaymentRepository;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use Magento\Catalog\Model\CategoryRepository;

use Psr\Log\LoggerInterface;

use Pace\Pay\Helper\ConfigData;
use Pace\Pay\Controller\Pace\Transaction;
use Pace\Pay\Gateway\Http\PayJsonConverter;
use Pace\Pay\Api\WebhookManagementInterface;

/**
 * Class process Pace webhooks callback
 */
class Webhooks extends Transaction implements WebhookManagementInterface
{   
    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    protected $_webApiRequest;

    public function __construct(
        WebapiRequest $webApiRequest,
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
        PaymentRepository $paymentRepository,
        DBTransaction $dbTransaction,
        ModuleListInterface $moduleList,
        LoggerInterface $logger,
        OrderManagementInterface $orderManagement
    )
    {
        parent::__construct(
            $resultJsonFactory,
            $checkoutSession,
            $client,
            $jsonConverter,
            $configData,
            $quoteRepository,
            $request,
            $orderRepository,
            $resultFactory,
            $categoryRepository,
            $order,
            $storeManager,
            $transactionBuilder,
            $transactionRepository,
            $messageManager,
            $invoiceService,
            $invoiceSender,
            $invoiceRepository,
            $paymentRepository,
            $dbTransaction,
            $moduleList,
            $logger,
            $orderManagement
        );

        $this->_webApiRequest = $webApiRequest;
    }

    public function execute()
    {
        return 1;
    }

    public function _handle()
    {   
        $params = $this->_webApiRequest->getBodyParams();
        try {
            if (!isset($params['status'])) {
                throw new \Exception('Unknow Pace webhooks response status');
            }

            if ('success' !== $params['status']) {
                throw new \Exception('Unsuccessfully handle webhooks callback');
            }

            $order = $this->_orderRepository->get($params['referenceID']);
            
            if (!$order) {
                throw new \Exception('Unknow orders');
            }

            $orderStatus = $order->getStatus();
            switch ($params['event']) {
                case 'approved':
                    if ($this->_configData->getApprovedStatus() !== $orderStatus) {
                        $this->_handleApprove($order);    
                    }
                    break;
                case 'cancelled':
                    if ($this->_configData->getCancelStatus() !== $orderStatus) {
                        $this->_handleCancel($order);    
                    }
                    break;
                case 'expired':
                    if ($this->_configData->getExpiredStatus() !== $orderStatus) {
                        $this->_handleClose($order);    
                    }
                    break;
                default:
                    // code...
                    break;
            }

            return 1;
        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());
        }
    }
}