<?php
namespace Pace\Pay\Model;

use Pace\Pay\Api\WebhookManagementInterface;

class WebhookManagement implements WebhookManagementInterface
{
	/**
	 * doWebhookCallbacks...
	 * 
	 * @api
	 * @param string @code
	 * @return void
	 */
	public function doWebhookCallbacks($code)
	{
		print_r($code);
	}
}