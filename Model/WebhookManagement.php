<?php
namespace Pace\Pay\Model;

use Pace\Pay\Model\Transaction;
use Pace\Pay\Helper\ConfigData;

use Psr\Log\LoggerInterface;

use Exception;

class WebhookManagement implements \Pace\Pay\Api\WebhookManagementInterface
{

	public function __construct(
		ConfigData $configData,
		Transaction $transaction,
		LoggerInterface $logger
	)
	{
		$this->logger = $logger;
		$this->config = $configData;
		$this->transaction = $transaction;
	}

	/**
	 * doWebhookCallbacks...
	 * 
	 * @param string @code
	 * @return void
	 */
	public function doWebhookCallbacks($code)
	{
		try {
			// decrypt
			$code = $this->config->decrypt($code);
			$order = !empty($code)
				? $this->transaction->orderRepository->get($code)
				: '';
			
			if (empty($order) || !$order instanceof \Magento\Sales\Model\Order\Interceptor) {
				throw new Exception('Security error or decapitated Order!');
			}

			$payload = file_get_contents("php://input");
			@$this->webhookFactory($order, $payload);
		} catch (Exception $e) {
			$this->logger->info();
		}
	}

	/**
	 * webhookFactory...
	 */
	protected function webhookFactory($order, $payload = [])
	{
		switch ($payload['event']) {
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