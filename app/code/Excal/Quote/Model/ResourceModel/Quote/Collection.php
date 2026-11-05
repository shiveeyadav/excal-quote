<?php
/**
 * Official quotation collection.
 */
declare(strict_types=1);

namespace Excal\Quote\Model\ResourceModel\Quote;

use Excal\Quote\Model\Quote;
use Excal\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Collection of official quotations.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Quote::class, QuoteResource::class);
    }
}
