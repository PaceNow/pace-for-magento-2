<?php

namespace Pace\Pay\Model;

class TrackingStatus extends \Magento\Framework\Model\AbstractModel {

	protected function _construct() {
		$this->_init('Pace\Pay\Model\ResourceModel\TrackingStatus');
	}
}
