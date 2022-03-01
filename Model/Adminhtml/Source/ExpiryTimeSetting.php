<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use \Magento\Framework\Data\OptionSourceInterface;

class ExpiryTimeSetting implements OptionSourceInterface 
{	
	/**
	 * Return expiry time options
	 *
	 * @since 1.0.4
	 * @return array
	 */
	public function toOptionArray(): array
	{
		return [
			[
				'value' => 0,
				'label' => __( 'No' )
			],
			[
				'value' => 10080,
				'label' => __( '1 week' ),
				'default' => true
			],
			[
				'value' => 10,
				'label' => __( '10 mins' )
			],
			[
				'value' => 15,
				'label' => __( '15 mins' )
			],
			[
				'value' => 20,
				'label' => __( '20 mins' )
			],
			[
				'value' => 30,
				'label' => __( '30 mins' )
			],
			[
				'value' => 60,
				'label' => __( '60 mins' )
			],
		];
	}
}