<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Pace\Pay\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Config as OrderConfig;

/**
 * Order configuration model - Pace custom
 *
 * @api
 * @since 100.0.2
 */
class Config extends OrderConfig
{
	/**
	 * getStateByStatus...
	 *
	 * @return Void
	 */
	public function getStateByStatus($status) {
		foreach ($this->_getCollection() as $item) {
			if ($item->getData('status') == $status) {
				return $item->getData('state');
			}
		}

		return null;
	}
}
