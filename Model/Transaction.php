<?php
namespace Pace\Pay\Model;

use DateTime;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\DB\Transaction as DBTransaction;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\Service\InvoiceService;
use Pace\Pay\Helper\ConfigData;

/**
 * Transaction resource model
 */
class Transaction {
	/**
	 * @var Session
	 */
	public $session;

	/**
	 * @var ConfigData
	 */
	public $configData;

	/**
	 * @var OrderRepositoryInterface
	 */
	public $orderRepository;

	function __construct(
		Session $session,
		ConfigData $configData,
		DBTransaction $dbTransaction,
		InvoiceService $invoiceService,
		ManagerInterface $messageManager,
		TransactionBuilder $transactionBuilder,
		OrderRepositoryInterface $orderRepository
	) {
		$this->session = $session;
		$this->configData = $configData;
		$this->dbTransaction = $dbTransaction;
		$this->invoiceService = $invoiceService;
		$this->messageManager = $messageManager;
		$this->orderRepository = $orderRepository;
		$this->transactionBuilder = $transactionBuilder;
	}

	/**
	 * convertPricebyCountry...
	 *
	 * @return Number
	 */
	public function convertPricebyCountry($basePrice, $country) {
		return $this->configData->convertPricebyCountry($basePrice, $country);
	}

	/**
	 * getWebhookUrl...
	 *
	 * @return string
	 */
	public function getWebhookUrl($baseUrl, $order) {
		$securityCode = $this->configData->encrypt($order->getRealOrderId());

		return "{$baseUrl}rest/V1/pace/webhookcallback/{$securityCode}";
	}

	/**
	 * getExpiredTime...
	 *
	 * @return string
	 */
	public function getExpiredTime($storeId = null) {
		$expiredTime = $this->configData->getConfigValue('expired_time', $storeId);

		if (empty($expiredTime)) {
			return '';
		}

		$now = new DateTime();
		$expiredTime = $now->modify(sprintf('+%s minutes', $expiredTime));

		return $expiredTime->format('Y-m-d H:i:s');
	}

	/**
	 * getBasePayload...
	 *
	 * @return array
	 */
	public function getBasePayload($storeId = null) {
		return $this->configData->getBasePayload($storeId);
	}

	/**
	 * getTransactionDetail...
	 *
	 * @return Object
	 */
	public function getTransactionDetail($order) {
		$tnxId = $order->getPayment()->getLastTransId();
		$getBasePayload = $this->configData->getBasePayload($order->getStoreId());

		$cURL = \Magento\Framework\App\ObjectManager::getInstance()
			->create(\Magento\Framework\HTTP\Client\Curl::class);

		$cURL->setHeaders($getBasePayload['headers']);
		$cURL->setOptions([
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
		]);
		$cURL->get("{$getBasePayload['apiEndpoint']}/v1/checkouts/{$tnxId}");

		return $cURL->getBody();
	}

	/**
	 * doAssignTransactionToOrder...
	 *
	 * @return Void
	 */
	public function doAssignTransactionToOrder($transaction, $order) {
		$tnxId = $transaction->transactionID;
		$order->getPayment()->setLastTransId($tnxId);
		$order->addCommentToStatusHistory("Pace transaction is created (Reference ID: {$tnxId})");
		$order->save();
	}

	/**
	 * createTransactionAttachedOrder...
	 *
	 * @return Void
	 */
	protected function createTransactionAttachedOrder($order) {
		@$response = $this->getTransactionDetail($order);
		$responseJson = json_decode($response);

		if (!isset($responseJson->error)) {
			$tnxId = $responseJson->transactionID;
			$payment = $order->getPayment();
			$payment->setLastTransId($tnxId);
			$payment->setTransactionId($tnxId);
			$payment->setAdditionalInformation([
				PaymentTransaction::RAW_DETAILS => $response,
			]);

			// create prepare transaction
			$transaction = $this->transactionBuilder
				->setPayment($payment)
				->setOrder($order)
				->setTransactionId($tnxId)
				->setAdditionalInformation([
					PaymentTransaction::RAW_DETAILS => $response,
				])
				->setFailSafe(true)
				->build(PaymentTransaction::TYPE_CAPTURE);
			$transaction->save();

			$payment->setParentTransactionId(null);
			$payment->save();
		}
	}

	/**
	 * createInvoiceAttachedOrder...
	 *
	 * Create invoice during the complete orders
	 *
	 * @return Void
	 */
	protected function createInvoiceAttachedOrder($order) {
		if ($order->canInvoice()) {
			try {
				$invoice = $this->invoiceService->prepareInvoice($order);
				$invoice->setTransactionId($order->getPayment()->getLastTransId());
				$invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
				$invoice->register();
				$invoice->save();

				$dbTransactionSave = $this->dbTransaction
					->addObject($invoice)
					->addObject($invoice->getOrder());
				$dbTransactionSave->save();

				$order->addCommentToStatusHistory(__('Notified customer about invoice creation #%1', $invoice->getId()))
					->setIsCustomerNotified(true);
			} catch (Exception $exception) {
				$order->addCommentToStatusHistory(__('Failed to generate invoice automatically'));
			}

			$this->orderRepository->save($order);
		}
	}

	/**
	 * applyApprovedStateOrders...
	 *
	 * Update orders depends on Product type (giftcard or others one)
	 *
	 * @return Void
	 */
	protected function applyApprovedStateOrders($order, $state) {
		$giftcardOnly = function ($order) {
			$lineItems = $order->getAllItems();
			foreach ($lineItems as $item) {
				if ('giftcard' != $item->getProductType()) {
					return false;
					break;
				}
			}

			return true;
		};

		$finalState = $giftcardOnly($order) ? Order::STATE_COMPLETE : $state;
		$order->setState($finalState)->setStatus(
			$order->getConfig()->getStateDefaultStatus($finalState)
		);

		$order->save();
	}

	/**
	 * doCloseOrder...
	 *
	 * @return Void
	 */
	public function doCloseOrder($order) {
		$state = $this->configData->getConfigValue('pace_expired', $order->getStoreId()) ?? Order::STATE_CLOSED;
		$status = $order->getConfig()->getStateDefaultStatus($state);
		$comment = "Pace payment has expired (Reference ID: {$order->getPayment()->getLastTransId()})";

		$order->setState($state);
		$order->addStatusToHistory($status, $comment, false);
		$order->save();
	}

	/**
	 * doCancelOrder...
	 *
	 * @return Void
	 */
	public function doCancelOrder($order) {
		if ($order->canCancel()) {
			$state = $this->configData->getConfigValue('pace_canceled', $order->getStoreId()) ?? Order::STATE_CANCELED;
			$status = $order->getConfig()->getStateDefaultStatus($state);

			$tnxId = $order->getPayment()->getLastTransId();
			$comment = $tnxId
			? "Pace payment canceled (Reference ID: {$tnxId})"
			: "Failed to create Pace's transaction";
			$order->setState($state);
			$order->addStatusToHistory($status, $comment, $isCustomerNotified = false);
			$order->cancel();
			$order->save();

			$this->messageManager->addMessage(
				$this->messageManager->createMessage('notice', 'PACENOTICE')->setText($comment)
			);

			$this->session->restoreQuote();
		}
	}

	/**
	 * doCompleteOrder...
	 *
	 * @return Void
	 */
	public function doCompleteOrder($order) {
		@$this->createTransactionAttachedOrder($order);

		if ($this->configData->getConfigValue('generate_invoice', $order->getStoreId())) {
			// create invoice assigned to order
			@$this->createInvoiceAttachedOrder($order);
		}

		$state = $this->configData->getConfigValue('pace_approved', $order->getStoreId()) ?? Order::STATE_PROCESSING;

		if (
			'pending_payment' == $order->getState() ||
			($state != $order->getState() && $this->configData->getConfigValue('reinstate_order', $order->getStoreId()))
		) {
			@$this->applyApprovedStateOrders($order, $state);
			$order->addStatusHistoryComment("Pace payment is completed (Reference ID: {$order->getPayment()->getLastTransId()})");
		}

		$this->orderRepository->save($order);
	}
}