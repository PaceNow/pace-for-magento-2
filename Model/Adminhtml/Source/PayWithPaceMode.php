<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use \Magento\Framework\Data\OptionSourceInterface;

class PayWithPaceMode implements OptionSourceInterface {
	const REDIRECT = 'redirect';
	const POPUP = 'popup';
	/**
	 * @return array
	 */
	public function toOptionArray(): array
	{
		return [
			[
				'value' => self::REDIRECT,
				'label' => __('Redirect'),
			],
			[
				'value' => self::POPUP,
				'label' => __('Popup'),
			],
		];
	}
}
