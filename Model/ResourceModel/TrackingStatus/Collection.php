<?php

namespace Pace\Pay\Model\ResourceModel\TrackingStatus;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Pace\Pay\Model\TrackingStatus', 'Pace\Pay\Model\ResourceModel\TrackingStatus');
    }
}
