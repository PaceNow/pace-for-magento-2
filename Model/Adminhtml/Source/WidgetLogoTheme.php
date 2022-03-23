<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use \Magento\Framework\Data\OptionSourceInterface;

class WidgetLogoTheme implements OptionSourceInterface
{
    const LIGHT = 'light';
    const DARK = 'dark';
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::LIGHT,
                'label' => __('Light')
            ],
            [
                'value' => self::DARK,
                'label' => __('Dark')
            ]
        ];
    }
}
