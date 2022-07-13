<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use \Magento\Framework\Data\OptionSourceInterface;

class VoucherTagCountries implements OptionSourceInterface {
	/**
	 * @return array
	 */
	public function toOptionArray(): array
	{
		return [
			[
				'value' => 'HK$60',
				'label' => 'Hong Kong HK$60',
			],
			[
				'value' => 'RM25',
				'label' => 'Malaysia RM25',
			],
            [
				'value' => 'NT$200',
				'label' => 'Taiwan NT$200',
			],
            [
				'value' => 'S$10',
				'label' => 'Singapore S$10',
			],
            [
				'value' => '¥1,500',
				'label' => 'Japan ¥1,500',
			],
            [
				'value' => '฿150',
				'label' => 'Thailand ฿150',
			],
		];
	}
}
