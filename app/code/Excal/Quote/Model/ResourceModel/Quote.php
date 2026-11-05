<?php
/**
 * Official quotation resource model.
 */
declare(strict_types=1);

namespace Excal\Quote\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for excal_quote.
 */
class Quote extends AbstractDb
{
    public const TABLE = 'excal_quote';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'entity_id');
    }

    /**
     * Set status for a list of quotation ids.
     *
     * @param int[] $ids
     * @param string $status
     * @return int affected rows
     */
    public function updateStatus(array $ids, string $status): int
    {
        if (!$ids) {
            return 0;
        }
        return (int)$this->getConnection()->update(
            $this->getMainTable(),
            ['status' => $status],
            ['entity_id IN (?)' => array_map('intval', $ids)]
        );
    }

    /**
     * Mark open quotations whose valid-until date is before $today as expired.
     *
     * @param string $today Y-m-d
     * @return int affected rows
     */
    public function expireBefore(string $today): int
    {
        return (int)$this->getConnection()->update(
            $this->getMainTable(),
            ['status' => \Excal\Quote\Model\Quote::STATUS_EXPIRED],
            ['status = ?' => \Excal\Quote\Model\Quote::STATUS_OPEN, 'valid_until < ?' => $today]
        );
    }
}
