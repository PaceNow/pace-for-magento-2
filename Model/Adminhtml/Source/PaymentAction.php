<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use Magento\Payment\Model\MethodInterface;
use \Magento\Framework\Data\OptionSourceInterface;

/**
 * Class PaymentAction
 */
class PaymentAction implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'initialize',
                'label' => __('Initialize')
            ]
        ];
    }
}
