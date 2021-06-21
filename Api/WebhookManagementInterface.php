<?php

namespace Pace\Pay\Api;

/**
  * summary
  */
interface WebhookManagementInterface
{	
	/**
	 * Pace webhooks contract
	 * @param  object $value 
	 * @return json        
	 */
    public function _handle();
} 