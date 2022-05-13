<?php
namespace Pace\Pay\Model;

use Pace\Pay\Api\WebhookManagementInterface;

class WebhookManagement implements WebhookManagementInterface
{
	/**
	 * doWebhookCallbacks...
	 * 
	 * @return void
	 */
	public function doWebhookCallbacks($code)
	{
		print_r($code);
	}
}