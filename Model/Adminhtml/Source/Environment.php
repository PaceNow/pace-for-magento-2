<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use \Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    const PLAYGROUND = 'playground';
    const PRODUCTION = 'production';
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::PLAYGROUND,
                'label' => __('Playground')
            ],
            [
                'value' => self::PRODUCTION,
                'label' => __('Production')
            ]
        ];
    }
}
