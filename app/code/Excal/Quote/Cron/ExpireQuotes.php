<?php
/**
 * Nightly expiry of open quotations past their valid-until date.
 */
declare(strict_types=1);

namespace Excal\Quote\Cron;

use Excal\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;

/**
 * Marks open quotations as expired once valid_until is in the past (store local date).
 */
class ExpireQuotes
{
    /**
     * @param QuoteResource $quoteResource
     * @param TimezoneInterface $timezone
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly QuoteResource $quoteResource,
        private readonly TimezoneInterface $timezone,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cron entry point.
     *
     * @return void
     */
    public function execute(): void
    {
        $today = $this->timezone->date()->format('Y-m-d');
        $count = $this->quoteResource->expireBefore($today);
        if ($count) {
            $this->logger->info(sprintf('Excal_Quote: %d quotation(s) marked expired.', $count));
        }
    }
}
