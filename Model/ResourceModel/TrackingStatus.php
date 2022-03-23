<?php

namespace Pace\Pay\Model\ResourceModel;

class TrackingStatus extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    protected function _construct()
    {
        $this->_init('track_order_status', 'entity_id');
    }
}
