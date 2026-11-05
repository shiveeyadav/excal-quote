<?php
/**
 * Official quotation item resource model.
 */
declare(strict_types=1);

namespace Excal\Quote\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for excal_quote_item.
 */
class QuoteItem extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('excal_quote_item', 'item_id');
    }
}
