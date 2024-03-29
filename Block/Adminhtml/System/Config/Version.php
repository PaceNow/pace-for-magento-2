<?php

namespace Pace\Pay\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Pace\Pay\Helper\ConfigData;

class Version extends \Magento\Config\Block\System\Config\Form\Field {
	/**
	 * @var ConfigData
	 */
	protected $configData;

	public function __construct(
		\Magento\Backend\Block\Template\Context $context,
		ConfigData $configData
	) {
		parent::__construct($context);
		$this->configData = $configData;
	}

	/**
	 * _getElementHtml...
	 *
	 * get module version for system.xml
	 *
	 * @return string
	 */
	protected function _getElementHtml(AbstractElement $element) {
		return $this->configData->getSetupVersion();
	}
}
