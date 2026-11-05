<?php
/**
 * Official quotation item collection.
 */
declare(strict_types=1);

namespace Excal\Quote\Model\ResourceModel\QuoteItem;

use Excal\Quote\Model\QuoteItem;
use Excal\Quote\Model\ResourceModel\QuoteItem as QuoteItemResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Collection of official quotation items.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'item_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(QuoteItem::class, QuoteItemResource::class);
    }
}
