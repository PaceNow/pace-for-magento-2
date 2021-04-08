<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use \Magento\Framework\Data\OptionSourceInterface;

class CronIntervalTime implements OptionSourceInterface
{
    const FIVE_MINUTE = '*/5 * * * *';
    const FIFTEEN_MINUTE = '*/15 * * * *';
    const THIRTY_MINUTE = '*/30 * * * *';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {

        return [
            [
                'value' => self::FIVE_MINUTE,
                'label' => __('Every 5 minutes')
            ],
            [
                'value' => self::FIFTEEN_MINUTE,
                'label' => __('Every 15 minutes')
            ],
            [
                'value' => self::THIRTY_MINUTE,
                'label' => __('Every 30 minutes')
            ]
        ];
    }
}
