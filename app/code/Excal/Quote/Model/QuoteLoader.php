<?php
/**
 * Loads quotations by public hash.
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Excal\Quote\Model\ResourceModel\Quote as QuoteResource;
use Excal\Quote\Model\ResourceModel\QuoteItem\CollectionFactory as ItemCollectionFactory;

/**
 * Hash lookup with per-request cache.
 */
class QuoteLoader
{
    public const HASH_LENGTH = 48;

    /**
     * @var array<string, Quote|null>
     */
    private array $cache = [];

    /**
     * @param QuoteFactory $quoteFactory
     * @param QuoteResource $quoteResource
     * @param ItemCollectionFactory $itemCollectionFactory
     */
    public function __construct(
        private readonly QuoteFactory $quoteFactory,
        private readonly QuoteResource $quoteResource,
        private readonly ItemCollectionFactory $itemCollectionFactory
    ) {
    }

    /**
     * Is the string a syntactically valid public hash.
     *
     * @param string $hash
     * @return bool
     */
    public function isValidHash(string $hash): bool
    {
        return strlen($hash) === self::HASH_LENGTH && ctype_alnum($hash);
    }

    /**
     * Load quotation by hash, null if not found.
     *
     * @param string $hash
     * @return Quote|null
     */
    public function getByHash(string $hash): ?Quote
    {
        if (!$this->isValidHash($hash)) {
            return null;
        }
        if (!array_key_exists($hash, $this->cache)) {
            $quote = $this->quoteFactory->create();
            $this->quoteResource->load($quote, $hash, 'hash');
            // Constant-time confirmation of the stored hash (defence in depth against collation quirks).
            $this->cache[$hash] = $quote->getId() && hash_equals((string)$quote->getData('hash'), $hash)
                ? $quote
                : null;
        }
        return $this->cache[$hash];
    }

    /**
     * Quotation lines in print order.
     *
     * @param Quote $quote
     * @return QuoteItem[]
     */
    public function getItems(Quote $quote): array
    {
        $collection = $this->itemCollectionFactory->create();
        $collection->addFieldToFilter('quote_id', (int)$quote->getId());
        $collection->setOrder('sort_order', 'ASC');
        $collection->setOrder('item_id', 'ASC');
        return array_values($collection->getItems());
    }
}
