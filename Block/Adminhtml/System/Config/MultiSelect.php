<?php

namespace Pace\Pay\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Block config get multiple select
 */
class MultiSelect extends Field
{
	
	/**
	 * Retrieve Element HTML fragment
	 * 
	 * @param AbstractElement $element
	 * @return string
	 */
	protected function _getElementHtml(AbstractElement $element): string
	{
		$script = "<script>require(['jquery', 'mage/multiselect'], function($) {
			'use strict';
			var target = $('#".$element->getId()."');
			console.log(target);
			if (target.length) {
				target.multiselect2({});
				var mselect = target.next();
				if (mselect.length) {
					mselect.find('.block-content').css({height: '200px', overflow: 'scroll'});
					mselect.find('.mselect-edit, .mselect-delete').remove();
				}
			}
		})</script>";

		return parent::_getElementHtml($element) . $script;
	}
}