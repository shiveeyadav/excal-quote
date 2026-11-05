<?php
/**
 * Creates an official quotation from the shopping cart.
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Excal\Quote\Model\ResourceModel\Quote as QuoteResource;
use Excal\Quote\Model\ResourceModel\QuoteItem as QuoteItemResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote as Cart;

/**
 * Snapshot cart lines and totals into excal_quote / excal_quote_item.
 */
class QuoteCreator
{
    /**
     * @param Config $config
     * @param QuoteFactory $quoteFactory
     * @param QuoteItemFactory $quoteItemFactory
     * @param QuoteResource $quoteResource
     * @param QuoteItemResource $quoteItemResource
     * @param Random $random
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly Config $config,
        private readonly QuoteFactory $quoteFactory,
        private readonly QuoteItemFactory $quoteItemFactory,
        private readonly QuoteResource $quoteResource,
        private readonly QuoteItemResource $quoteItemResource,
        private readonly Random $random,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Create and persist the quotation.
     *
     * @param Cart $cart
     * @param array<string, string> $contact validated form data
     * @param int|null $customerId
     * @return Quote
     * @throws LocalizedException
     */
    public function create(Cart $cart, array $contact, ?int $customerId): Quote
    {
        $storeId = (int)$cart->getStoreId();
        $lines = [];
        $subtotal = 0.0;
        $totalQty = 0.0;
        $sort = 0;

        foreach ($cart->getAllVisibleItems() as $cartItem) {
            $qty = (float)$cartItem->getQty();
            if ($qty <= 0) {
                continue;
            }
            // base_price is the unit price excluding tax (set by the tax total collector).
            $unit = round((float)$cartItem->getBasePrice(), 4);
            $lineTotal = round($unit * $qty, 2);
            $subtotal += $lineTotal;
            $totalQty += $qty;
            $lines[] = [
                'product_id' => $cartItem->getProductId() ? (int)$cartItem->getProductId() : null,
                'sku' => mb_substr((string)$cartItem->getSku(), 0, 64),
                'name' => mb_substr((string)$cartItem->getName(), 0, 255),
                'qty' => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
                'sort_order' => $sort++,
            ];
        }
        if (!$lines) {
            throw new LocalizedException(__('Your basket is empty.'));
        }

        $subtotal = round($subtotal, 2);
        $delivery = $this->config->getDeliveryAmount($totalQty, $storeId);
        $vatRate = $this->config->getVatRate($storeId);
        $vat = round(($subtotal + $delivery) * $vatRate / 100, 2);
        $now = $this->timezone->date();
        $validUntil = (clone $now)->modify('+' . $this->config->getValidityDays($storeId) . ' days');

        $quote = $this->quoteFactory->create();
        $quote->addData([
            'hash' => $this->random->getRandomString(
                QuoteLoader::HASH_LENGTH,
                Random::CHARS_LOWERS . Random::CHARS_UPPERS . Random::CHARS_DIGITS
            ),
            'status' => Quote::STATUS_OPEN,
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'source_cart_id' => $cart->getId() ? (int)$cart->getId() : null,
            'currency_code' => (string)($cart->getBaseCurrencyCode() ?: 'GBP'),
            'subtotal' => $subtotal,
            'delivery_amount' => $delivery,
            'vat_rate' => $vatRate,
            'vat_amount' => $vat,
            'grand_total' => round($subtotal + $delivery + $vat, 2),
            'valid_until' => $validUntil->format('Y-m-d'),
        ]);
        $quote->addData(array_intersect_key($contact, array_flip([
            'organisation', 'department', 'contact_name', 'email', 'phone',
            'billing_address', 'delivery_address', 'customer_reference', 'notes',
        ])));

        $connection = $this->quoteResource->getConnection();
        $connection->beginTransaction();
        try {
            $this->quoteResource->save($quote);
            $quote->setData('quote_number', sprintf('EXQ-%s-%06d', $now->format('Y'), (int)$quote->getId()));
            $this->quoteResource->save($quote);
            foreach ($lines as $line) {
                $item = $this->quoteItemFactory->create();
                $item->setData($line);
                $item->setData('quote_id', (int)$quote->getId());
                $this->quoteItemResource->save($item);
            }
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new LocalizedException(__('The quotation could not be saved. Please try again.'), $e);
        }
        return $quote;
    }
}
