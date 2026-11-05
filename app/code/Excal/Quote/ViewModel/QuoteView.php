<?php
/**
 * Data for the printable quotation page.
 */
declare(strict_types=1);

namespace Excal\Quote\ViewModel;

use Excal\Quote\Model\Config;
use Excal\Quote\Model\Quote;
use Excal\Quote\Model\QuoteItem;
use Excal\Quote\Model\QuoteLoader;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Quotation, lines, supplier details (with explicit NOT SET markers) and formatting helpers.
 */
class QuoteView implements ArgumentInterface
{
    /**
     * @param RequestInterface $request
     * @param QuoteLoader $quoteLoader
     * @param Config $config
     * @param PriceCurrencyInterface $priceCurrency
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly QuoteLoader $quoteLoader,
        private readonly Config $config,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Current quotation (validated by the controller).
     *
     * @return Quote|null
     */
    public function getQuote(): ?Quote
    {
        return $this->quoteLoader->getByHash((string)$this->request->getParam('id'));
    }

    /**
     * Lines of the quotation.
     *
     * @param Quote $quote
     * @return QuoteItem[]
     */
    public function getItems(Quote $quote): array
    {
        return $this->quoteLoader->getItems($quote);
    }

    /**
     * Supplier value; '' when not configured (template prints the NOT SET marker).
     *
     * @param Quote $quote
     * @param string $field one of Config::SUPPLIER_FIELDS keys
     * @return string
     */
    public function getSupplier(Quote $quote, string $field): string
    {
        return $this->config->getSupplierValue($field, (int)$quote->getData('store_id'));
    }

    /**
     * Human label for a supplier field (used in NOT SET markers).
     *
     * @param string $field
     * @return string
     */
    public function getSupplierLabel(string $field): string
    {
        return Config::SUPPLIER_FIELDS[$field] ?? $field;
    }

    /**
     * Logo URL or ''.
     *
     * @param Quote $quote
     * @return string
     */
    public function getLogoUrl(Quote $quote): string
    {
        return $this->config->getLogoUrl((int)$quote->getData('store_id'));
    }

    /**
     * Format an amount in the quotation currency.
     *
     * @param Quote $quote
     * @param float|string|null $amount
     * @return string
     */
    public function formatPrice(Quote $quote, $amount): string
    {
        return $this->priceCurrency->format(
            (float)$amount,
            false,
            2,
            (int)$quote->getData('store_id'),
            (string)$quote->getData('currency_code') ?: null
        );
    }

    /**
     * Format a date (Y-m-d or timestamp string) in long form.
     *
     * @param string|null $date
     * @param bool $isUtcTimestamp true for created_at (stored UTC)
     * @return string
     */
    public function formatDate(?string $date, bool $isUtcTimestamp = false): string
    {
        if (!$date) {
            return '';
        }
        if ($isUtcTimestamp) {
            return $this->timezone->formatDateTime(
                $date,
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE
            );
        }
        // Date-only value: format as-is without timezone shifting.
        return $this->timezone->formatDateTime(
            $date,
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE,
            null,
            'UTC'
        );
    }

    /**
     * Quantity without trailing zeros.
     *
     * @param float|string|null $qty
     * @return string
     */
    public function formatQty($qty): string
    {
        return rtrim(rtrim(number_format((float)$qty, 4, '.', ''), '0'), '.');
    }

    /**
     * VAT rate label, e.g. "20".
     *
     * @param Quote $quote
     * @return string
     */
    public function formatRate(Quote $quote): string
    {
        return rtrim(rtrim(number_format((float)$quote->getData('vat_rate'), 2, '.', ''), '0'), '.');
    }

    /**
     * Has the quotation lapsed (status or date).
     *
     * @param Quote $quote
     * @return bool
     */
    public function isExpired(Quote $quote): bool
    {
        if ($quote->getData('status') === Quote::STATUS_EXPIRED) {
            return true;
        }
        $today = $this->timezone->date()->format('Y-m-d');
        return $quote->getData('status') === Quote::STATUS_OPEN
            && (string)$quote->getData('valid_until') < $today;
    }
}
