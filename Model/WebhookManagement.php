<?php
namespace Pace\Pay\Model;

use Exception;
use Pace\Pay\Model\Transaction;
use Psr\Log\LoggerInterface;

class WebhookManagement implements \Pace\Pay\Api\WebhookManagementInterface {

	public function __construct(
		Transaction $transaction,
		LoggerInterface $logger
	) {
		$this->logger = $logger;
		$this->transaction = $transaction;
	}

	/**
	 * doWebhookCallbacks...
	 *
	 * @param string @code
	 * @return json
	 */
	public function doWebhookCallbacks($code) {
		try {
			// decrypt
			$code = $this->transaction->configData->decrypt($code);
			$order = !empty($code)
			? $this->transaction->order->loadByIncrementId($code)
			: '';

			if (empty($order)) {
				throw new Exception('Security error or decapitated Order!');
			}

			$payload = file_get_contents("php://input");
			@$this->webhookFactory($order, $payload);

			return json_encode(['status' => true]);
		} catch (Exception $e) {
			$this->logger->info($e->getMessage());
			return json_encode([
				'status' => false,
				'message' => $e->getMessage()]
			);
		}
	}

	/**
	 * webhookFactory...
	 */
	protected function webhookFactory($order, $payload = []) {
		$payload = $payload ? json_decode($payload) : '';

		if (empty($payload)) {
			throw new Exception('Empty callback parameters!');
		}

		switch ($payload->event) {
		case 'approved':
			$this->transaction->doCompleteOrder($order);
			break;
		case 'cancelled':
			$this->transaction->doCancelOrder($order);
			break;
		case 'expired':
			$this->transaction->doCloseOrder($order);
			break;
		default:
			throw new Exception('Missing event payload!');
			break;
		}
	}
}