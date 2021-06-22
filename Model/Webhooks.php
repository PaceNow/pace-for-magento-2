<?php 

namespace Pace\Pay\Model;

use Magento\Sales\Model\Order;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Payment\Repository;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\DB\Transaction as DBTransaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;

use Psr\Log\LoggerInterface;

use Pace\Pay\Helper\ConfigData;
use Pace\Pay\Api\WebhookManagementInterface;

use Zend_Http_Client;

/**
 * Class process Pace webhooks callback
 */
class Webhooks implements WebhookManagementInterface
{	
	/**
     * @var ZendClient
     */
    protected $_client;

	/**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var Builder
     */
    protected $_builder;

	/**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    protected $_request;

    /**
     * @var ConfigData;
     */
    protected $_configData;

    /**
     * @var DBTransaction
     */
    protected $_dbTransaction;

    /**
     * @var InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var InvoiceRepository
     */
    protected $_invoiceRepository;

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var PaymentRepository
     */
    protected $_repository;

    /**
     * @var TransactionBuilder
     */
    protected $_transactionBuilder;

    /**
     * @var TransactionRepository
     */
    protected $_transactionRepository;

	public function __construct(
		Builder $builder,
		Request $request, 
		ZendClient $client,
		ConfigData $configData,
		Repository $repository,
		DBTransaction $dbTransaction,
		InvoiceService $invoiceService,
		LoggerInterface $logger,
		InvoiceRepository $invoiceRepository,
		OrderRepositoryInterface $orderRepository,
		TransactionRepository $transactionRepository
	)
	{
		$this->_client = $client;
		$this->_logger = $logger;
		$this->_builder = $builder;
		$this->_request = $request;
		$this->_repository = $repository;
		$this->_configData = $configData;
		$this->_dbTransaction = $dbTransaction;
		$this->_invoiceService = $invoiceService;
		$this->_orderRepository = $orderRepository;
		$this->_invoiceRepository = $invoiceRepository;
		$this->_transactionRepository = $transactionRepository;
	}

	public function _handle()
	{	
		$params = $this->_request->getBodyParams();

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

    		switch ($params['event']) {
    			case 'approved':
    				$this->_handleApprove($order);
    				break;
    			case 'cancelled':
    				// $this->_handleCancel($order);
    				break;
    			case 'expired':
    				
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

	/**
	 * Processing approved transaction
	 * 
	 * @param OrderInterface $order
     * @param array $params
	 * @since 1.0.3
	 */
	public function _handleApprove($order)
	{
		$payment = $order->getPayment();
        $payment->setIsTransactionClosed(true);

        $storeId = $order->getStoreId();
        $transactionId = $payment->getAdditionalData();

        $endpoint = $this->_configData->getApiEndpoint($storeId) . '/v1/checkouts/' . $transactionId;
        $pacePayload = $this->_configData->getBasePayload($storeId);
        $paceTransaction = null;
        try {
            $this->_client->resetParameters();
            $this->_client->setUri($endpoint);
            $this->_client->setMethod(Zend_Http_Client::GET);
            $this->_client->setHeaders($pacePayload['headers']);
            $response = $this->_client->request();

            if ($response->getStatus() < 200 || $response->getStatus() > 299) {
                throw new \Exception('Unknown Pace transaction statuses');
            }

            $paceTransaction = json_decode($response->getBody());
        } catch (\Exception $exception) {
            $this->_logger->info($e->getMessage());
        }

        $order->setStatus(Order::STATE_PROCESSING);
        $order->addStatusHistoryComment(__('Pace payment is completed (Reference ID: %1)', $transactionId));
        $payment->setLastTransId($transactionId);
        $payment->setTransactionId($transactionId);

        if (!is_null($paceTransaction)) {
            $additionalPaymentInformation = [Transaction::RAW_DETAILS => json_encode($paceTransaction)];
            $payment->setAdditionalInformation($additionalPaymentInformation);
            $transaction = $this->_builder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($transactionId)
                ->setAdditionalInformation($additionalPaymentInformation)
                ->setFailSafe(true)
                ->build(Transaction::TYPE_CAPTURE);
        }
        
        $payment->setParentTransactionId(null);

        $this->_repository->save($payment);
        $this->_orderRepository->save($order);
        $this->_transactionRepository->save($transaction);

        if ($this->_configData->getIsAutomaticallyGenerateInvoice()) {
            $this->_invoiceOrder($order, $transactionId);
        }

        $this->_orderRepository->save($order);
	}

	/**
     * @param Order $order
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
            } catch (\Exception $exception) {
                $order->addCommentToStatusHistory(
                    __('Failed to generate invoice automatically')
                );
            }

            $this->_orderRepository->save($order);
        }
    }
}