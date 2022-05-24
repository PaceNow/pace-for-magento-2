<?php
// TODO: remove
namespace Pace\Pay\Gateway\Http;

use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\ConverterInterface;

/**
 * Class HtmlFormConverter
 * @package Magento\Payment\Gateway\Http\Converter
 * @api
 * @since 100.0.2
 */
class PayJsonConverter implements ConverterInterface
{
    /**
     * Converts gateway response to ENV structure
     *
     * @param string $response
     * @return array
     * @throws ConverterException
     */
    public function convert($response)
    {
        $convertedResponse = json_decode($response);

        if ($convertedResponse == null && json_last_error() !== JSON_ERROR_NONE) {
            throw new ConverterException(
                __('The gateway response format was incorrect. Verify the format and try again.')
            );
        }

        return $convertedResponse;
    }
}
