<?php

namespace Pace\Pay\Plugins;

use Magento\Framework\View\Asset\Minification;

/**
 * Exclude some js file from Magento minification
 */
class ExcludeFilesFromMinification {
	public function aroundGetExcludes(Minification $subject, callable $proceed, $contentType) {
		$result = $proceed($contentType);

		if ($contentType != 'js') {
			return $result;
		}

		$result[] = "https://pay.pacenow.co/pace-pay";
		$result[] = "https://pay-playground.pacenow.co/pace-pay";

		return $result;
	}
}
