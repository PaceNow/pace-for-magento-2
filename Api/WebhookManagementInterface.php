<?php
namespace Pace\Pay\Api;

interface WebhookManagementInterface {
	/**
	 * doWebhookCallbacks...
	 * 
	 * @param string $code
	 * @return json
	 */
	public function doWebhookCallbacks($code);
}