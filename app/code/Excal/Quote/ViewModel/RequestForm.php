<?php
/**
 * Data for the quotation request form.
 */
declare(strict_types=1);

namespace Excal\Quote\ViewModel;

use Excal\Quote\Controller\Request\Save;
use Excal\Quote\Model\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Prefill values (persisted post data, else logged-in customer data) and basket summary.
 */
class RequestForm implements ArgumentInterface
{
    /**
     * @var array<string,string>|null
     */
    private ?array $values = null;

    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param DataPersistorInterface $dataPersistor
     * @param PriceCurrencyInterface $priceCurrency
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * POST target.
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->urlBuilder->getUrl('quote/request/save', ['_secure' => true]);
    }

    /**
     * Basket URL.
     *
     * @return string
     */
    public function getCartUrl(): string
    {
        return $this->urlBuilder->getUrl('checkout/cart');
    }

    /**
     * Prefill value for a field.
     *
     * @param string $field
     * @return string
     */
    public function getValue(string $field): string
    {
        if ($this->values === null) {
            $this->values = $this->loadValues();
        }
        return $this->values[$field] ?? '';
    }

    /**
     * Basket lines for the summary table.
     *
     * @return array<int, array{sku:string,name:string,qty:float}>
     */
    public function getCartLines(): array
    {
        $lines = [];
        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $lines[] = [
                'sku' => (string)$item->getSku(),
                'name' => (string)$item->getName(),
                'qty' => (float)$item->getQty(),
            ];
        }
        return $lines;
    }

    /**
     * Configured VAT rate for the note under the form.
     *
     * @return string
     */
    public function getVatRateLabel(): string
    {
        $rate = $this->config->getVatRate();
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
    }

    /**
     * Validity in days.
     *
     * @return int
     */
    public function getValidityDays(): int
    {
        return $this->config->getValidityDays();
    }

    /**
     * Format quantity without trailing zeros.
     *
     * @param float $qty
     * @return string
     */
    public function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }

    /**
     * Build prefill values.
     *
     * @return array<string,string>
     */
    private function loadValues(): array
    {
        $persisted = $this->dataPersistor->get(Save::PERSIST_KEY);
        if (is_array($persisted)) {
            $this->dataPersistor->clear(Save::PERSIST_KEY);
            return array_map('strval', array_filter($persisted, 'is_scalar'));
        }
        if (!$this->customerSession->isLoggedIn()) {
            return [];
        }
        $customer = $this->customerSession->getCustomer();
        $values = [
            'contact_name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
            'email' => (string)$customer->getEmail(),
        ];
        $billing = $customer->getDefaultBillingAddress();
        $shipping = $customer->getDefaultShippingAddress();
        if ($billing instanceof Address) {
            $values['organisation'] = (string)$billing->getCompany();
            $values['phone'] = (string)$billing->getTelephone();
            $values['billing_address'] = $this->formatAddress($billing);
        }
        if ($shipping instanceof Address) {
            $values['delivery_address'] = $this->formatAddress($shipping);
            $values['organisation'] = ($values['organisation'] ?? '') ?: (string)$shipping->getCompany();
            $values['phone'] = ($values['phone'] ?? '') ?: (string)$shipping->getTelephone();
        }
        return $values;
    }

    /**
     * Plain multi-line address.
     *
     * @param Address $address
     * @return string
     */
    private function formatAddress(Address $address): string
    {
        $lines = array_merge(
            [(string)$address->getCompany()],
            (array)$address->getStreet(),
            [(string)$address->getCity(), (string)$address->getRegion(), (string)$address->getPostcode()],
            [(string)$address->getCountryModel()->getName()]
        );
        return implode("\n", array_filter(array_map('trim', array_map('strval', $lines)), 'strlen'));
    }
}
