<?php
/**
 * Quotation status options.
 */
declare(strict_types=1);

namespace Excal\Quote\Model\Source;

use Excal\Quote\Model\Quote;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options for the status column/filter.
 */
class Status implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Quote::STATUS_OPEN, 'label' => __('Open')],
            ['value' => Quote::STATUS_CONVERTED, 'label' => __('Converted')],
            ['value' => Quote::STATUS_EXPIRED, 'label' => __('Expired')],
        ];
    }
}
