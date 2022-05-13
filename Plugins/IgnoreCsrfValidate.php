<?php

namespace Pace\Pay\Plugins;

use Pace\Pay\Model\Ui\ConfigProvider;

/**
 * Ignore Csrf validate when Pace callbacks
 */
class IgnoreCsrfValidate
{
	/**
     * @param \Magento\Framework\App\Request\CsrfValidator $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\App\ActionInterface $action
     */
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        $request,
        $action
    ) {
        if ($request->getModuleName() == ConfigProvider::MODULE_NAME) {
            return; // Skip CSRF check
        }

        $proceed($request, $action); // Proceed Magento 2 core functionalities
    }
}