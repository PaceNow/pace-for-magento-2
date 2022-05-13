<?php
namespace Pace\Pay\Api;

interface WebhookManagementInterface {
	/**
	 * @return void
	 */
	public function doWebhookCallbacks($code);
}