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
            if ( !isset( $params['status'] ) ) {
                throw new \Exception('Unknow Pace webhooks response status');
            }

            if ( 'success' !== $params['status'] ) {
                throw new \Exception('Unsuccessfully handle webhooks callback');
            }
            
            // $order = $this->_orderRepository->get( $params['referenceID'] );
            $order = $this->_order->loadByIncrementId( $params['referenceID'] );
            $this->_logger->info( $order->getId() );

            if ( !$order ) {
                throw new \Exception('Unknow orders');
            }

            $state = $order->getState();
            $status = $order->getStatus();
            $storeId = $order->getStoreId();

            /**
             * applies the scenario on webbhoks update order status
             * @since 1.0.4
             */
            switch ($params['event']) {
                case 'approved':
                    // Only complete an order when it has a new state
                    if ('pending_payment' == $state) {
                        $this->_handleApprove($order);
                    }

                    if ('canceled' == $state) {
                        $isReinstate = $this->_configData->getConfigValue('reinstate_order', $storeId);

                        if ($isReinstate && '1' == $isReinstate) {
                            $this->_handleApprove($order);
                        }
                    }
                    break;
                case 'cancelled':
                    if ('canceled' !== $state) {
                        $this->_handleCancel($order);
                    }
                    break;
                case 'expired':
                    // follows scenario 1: if order already cancelled, then add the comment for order
                    $expiredNote = __('The transaction (Reference ID: %1) has expired. Please try your payment again or contact the admin.', $params['transactionID']);
                    $order->addStatusHistoryComment($expiredNote);
                    
                    if ('pending_payment' == $state) {
                        $this->_handleClose($order);
                    }
                    
                    $this->_orderRepository->save($order);
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