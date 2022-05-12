<?php
namespace Pace\Pay\Controller\Pace;

use Exception;

/**
 * Handle webhook callback from Pace
 */
class WebhookCallback extends Transaction
{
	/**
	 * webhookFactory...
	 * 
	 * @return Void
	 */
	protected function webhookFactory($order, $payload)
	{
		switch ($payload['event']) {
			case 'approved':
				$this->doCompleteOrder($order);
				break;
			case 'cancelled':
				$this->doCancelOrder($order);
				break;
			case 'expired':
				$this->doCloseOrder($order);
				break;
			default:
				throw new Exception('Missing event payload!');
				break;
		}
	}

	/**
	 * execute...
	 * 
	 * @return Void
	 */
	public function execute()
	{
		try {
			$payload = $this->request->getParams();
			$orderId = isset($payload['securityCode'])
				? $this->configData->decrypt($payload['securityCode'])
				: '';

			$order = !empty($orderId)
				? $this->orderRepository->get($orderId)
				: '';

			if (empty($order) || !$order instanceof \Magento\Sales\Model\Order\Interceptor) {
				throw new Exception('Security error or decapitated Order!');
			}

			@$this->webhookFactory($order, $payload);
		} catch (Exception $e) {
			$rawData = json_encode($payload);
			$message = "Webhook callback processing \nPayload: {$rawData} \nError: {$e->getMessage()}";
			$this->logger->info($message);
			die($message);
		}
	}
}