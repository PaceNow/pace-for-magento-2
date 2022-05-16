<?php
namespace Pace\Pay\Api;

interface WebhookManagementInterface {
	/**
	 * doWebhookCallbacks...
	 * 
	 * @param string $code
	 * @return void
	 */
	public function doWebhookCallbacks($code);
}