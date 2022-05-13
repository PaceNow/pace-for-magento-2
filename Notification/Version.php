<?php

namespace Pace\Pay\Notification;

use Pace\Pay\Helper\ConfigData;

/**
 * Pace show notify UPDATE VERSION message box
 */
class Version implements \Magento\Framework\Notification\MessageInterface
{

    const MESSAGE_IDENTITY = 'pace_version_notification';

    /**
     * @var Pace\Pay\Helper\ConfigData
     */
    protected $configData;

    public function __construct(
        ConfigData $configData
    ) {
        $this->configData = $configData;
    }

    public function getIdentity()
    {
        // Retrieve unique message identity
        return self::MESSAGE_IDENTITY;
    }

    public function isDisplayed()
    {
        // Return true to show your message, false to hide it
        return $this->checkVersion();
    }

    public function getText()
    {
        // message text
        $html = sprintf("There is a new version of <strong>Pace For Magento 2.0</strong> available. %s.", '<a href="https://developers.pacenow.co/#plugins-magento2" target="_blank">Please update now</a>');
        return $html;
    }

    /**
     * Retrieve system message severity
     * Possible default system message types:
     * - MessageInterface::SEVERITY_CRITICAL
     * - MessageInterface::SEVERITY_MAJOR
     * - MessageInterface::SEVERITY_MINOR
     * - MessageInterface::SEVERITY_NOTICE
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }

    /**
     * checkPaceVersion...
     * 
     * @return boolean
     */
    protected function checkVersion()
    {
        $version = $this->configData->getConfigValue(ConfigData::CONFIG_PACE_SYNC_VERSION);

        if (empty($version)) {
            return;
        }

        $version = json_decode($version)->magento2;
        $moduleVersion = $this->configData->getSetupVersion();

        return @version_compare($version, $moduleVersion) == 1;
    }
}
