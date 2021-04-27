<?php

namespace Pace\Pay\Block;

use Magento\Framework\View\Element\Template;

/**
 * Include Pace SDK
 */
class PaceSDK extends Template
{

    public function __construct(
        Template\Context $context
    ) {
        parent::__construct($context);
    }
}
