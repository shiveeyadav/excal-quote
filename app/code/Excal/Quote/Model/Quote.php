<?php
/**
 * Official quotation entity.
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Official quotation (table excal_quote).
 */
class Quote extends AbstractModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var string
     */
    protected $_eventPrefix = 'excal_quote';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Quote::class);
    }

    /**
     * All valid status codes.
     *
     * @return string[]
     */
    public static function getStatuses(): array
    {
        return [self::STATUS_OPEN, self::STATUS_CONVERTED, self::STATUS_EXPIRED];
    }
}
