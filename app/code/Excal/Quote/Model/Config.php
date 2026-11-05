<?php
/**
 * Excal_Quote configuration reader.
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Typed access to Stores > Configuration > Excalibur > Quotations.
 */
class Config
{
    public const XML_ENABLED = 'excal_quote/general/enabled';
    public const XML_MINICART_LINK = 'excal_quote/general/minicart_link';
    public const XML_OWNER_EMAIL = 'excal_quote/general/owner_email';
    public const XML_SENDER_IDENTITY = 'excal_quote/general/sender_identity';
    public const XML_CUSTOMER_TEMPLATE = 'excal_quote/general/customer_template';
    public const XML_OWNER_TEMPLATE = 'excal_quote/general/owner_template';
    public const XML_MAX_PER_HOUR = 'excal_quote/general/max_per_hour';
    public const XML_VAT_RATE = 'excal_quote/pricing/vat_rate';
    public const XML_VALIDITY_DAYS = 'excal_quote/pricing/validity_days';
    public const XML_SUPPLIER_PREFIX = 'excal_quote/supplier/';
    public const XML_LOGO = 'excal_quote/supplier/logo';
    public const LOGO_MEDIA_DIR = 'excal_quote/logo';

    /**
     * Supplier fields printed on the quotation (config field id => printed label).
     */
    public const SUPPLIER_FIELDS = [
        'legal_name' => 'Legal name',
        'trading_name' => 'Trading name',
        'company_number' => 'Company number',
        'vat_number' => 'VAT number',
        'registered_address' => 'Registered address',
        'phone' => 'Phone',
        'email' => 'Email',
        'bank_name' => 'Bank name',
        'bank_account_name' => 'Bank account name',
        'bank_sort_code' => 'Sort code',
        'bank_account_number' => 'Account number',
        'bank_iban' => 'IBAN',
        'bank_bic' => 'BIC / SWIFT',
        'payment_terms' => 'Payment terms',
        'terms_text' => 'Terms and conditions',
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Is the module switched on for the store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Show the extra link in the mini-cart.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isMinicartLinkEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(self::XML_MINICART_LINK, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * VAT percentage applied to goods and delivery.
     *
     * @param int|null $storeId
     * @return float
     */
    public function getVatRate(?int $storeId = null): float
    {
        $value = $this->getString(self::XML_VAT_RATE, $storeId);
        return $value === '' ? 20.0 : max(0.0, (float)$value);
    }

    /**
     * Number of days a quotation remains valid.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getValidityDays(?int $storeId = null): int
    {
        $days = (int)$this->getString(self::XML_VALIDITY_DAYS, $storeId);
        return $days > 0 ? $days : 30;
    }

    /**
     * Max quotations per visitor session per hour (0 = unlimited).
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMaxPerHour(?int $storeId = null): int
    {
        return max(0, (int)$this->getString(self::XML_MAX_PER_HOUR, $storeId));
    }

    /**
     * Owner copy recipient: explicit setting, else supplier email (may be empty).
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOwnerEmail(?int $storeId = null): string
    {
        $email = $this->getString(self::XML_OWNER_EMAIL, $storeId);
        return $email !== '' ? $email : $this->getSupplierValue('email', $storeId);
    }

    /**
     * Sender identity code (general, sales, ...).
     *
     * @param int|null $storeId
     * @return string
     */
    public function getSenderIdentity(?int $storeId = null): string
    {
        $identity = $this->getString(self::XML_SENDER_IDENTITY, $storeId);
        return $identity !== '' ? $identity : 'sales';
    }

    /**
     * Customer email template id.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCustomerTemplate(?int $storeId = null): string
    {
        return $this->getString(self::XML_CUSTOMER_TEMPLATE, $storeId);
    }

    /**
     * Owner email template id.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOwnerTemplate(?int $storeId = null): string
    {
        return $this->getString(self::XML_OWNER_TEMPLATE, $storeId);
    }

    /**
     * Raw supplier value ('' when not configured).
     *
     * @param string $field
     * @param int|null $storeId
     * @return string
     */
    public function getSupplierValue(string $field, ?int $storeId = null): string
    {
        return $this->getString(self::XML_SUPPLIER_PREFIX . $field, $storeId);
    }

    /**
     * Public URL of the uploaded supplier logo, '' if none uploaded.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLogoUrl(?int $storeId = null): string
    {
        $logo = $this->getString(self::XML_LOGO, $storeId);
        if ($logo === '') {
            return '';
        }
        $mediaUrl = $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        return $mediaUrl . self::LOGO_MEDIA_DIR . '/' . ltrim($logo, '/');
    }

    /**
     * Delivery charge ex VAT derived from the Flat Rate carrier configuration.
     *
     * Mirrors Magento\OfflineShipping\Model\Carrier\Flatrate with a per-order handling fee.
     *
     * @param float $totalQty
     * @param int|null $storeId
     * @return float
     */
    public function getDeliveryAmount(float $totalQty, ?int $storeId = null): float
    {
        if (!$this->scopeConfig->isSetFlag('carriers/flatrate/active', ScopeInterface::SCOPE_STORE, $storeId)) {
            return 0.0;
        }
        $price = (float)$this->getString('carriers/flatrate/price', $storeId);
        $type = $this->getString('carriers/flatrate/type', $storeId);
        $cost = $type === 'I' ? $price * $totalQty : $price;

        $fee = (float)$this->getString('carriers/flatrate/handling_fee', $storeId);
        if ($fee > 0) {
            $handlingType = $this->getString('carriers/flatrate/handling_type', $storeId);
            $cost = $handlingType === 'P' ? $cost + $cost * $fee / 100 : $cost + $fee;
        }
        return round(max(0.0, $cost), 2);
    }

    /**
     * Read a store-scope config value as trimmed string.
     *
     * @param string $path
     * @param int|null $storeId
     * @return string
     */
    private function getString(string $path, ?int $storeId): string
    {
        return trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
