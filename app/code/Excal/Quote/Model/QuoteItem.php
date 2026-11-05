<?php
/**
 * Official quotation line.
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Official quotation line (table excal_quote_item).
 */
class QuoteItem extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\QuoteItem::class);
    }
}
