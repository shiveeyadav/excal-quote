<?php
/**
 * Sends quotation emails (customer link + owner copy).
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Magento\Framework\App\Area;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Email notifications. Never throws: failures are logged and the quotation stays saved.
 */
class Mailer
{
    /**
     * @param Config $config
     * @param QuoteLoader $quoteLoader
     * @param UrlProvider $urlProvider
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param PriceCurrencyInterface $priceCurrency
     * @param TimezoneInterface $timezone
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly QuoteLoader $quoteLoader,
        private readonly UrlProvider $urlProvider,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send customer email and owner copy.
     *
     * @param Quote $quote
     * @return bool true if the customer email was sent
     */
    public function sendAll(Quote $quote): bool
    {
        $storeId = (int)$quote->getData('store_id');
        $vars = $this->buildVars($quote);
        $customerSent = $this->send(
            $this->config->getCustomerTemplate($storeId),
            (string)$quote->getData('email'),
            (string)$quote->getData('contact_name'),
            $vars,
            $storeId,
            null
        );
        $ownerEmail = $this->config->getOwnerEmail($storeId);
        if ($ownerEmail === '') {
            $this->logger->warning(sprintf(
                'Excal_Quote: no owner email configured, copy of %s not sent.',
                (string)$quote->getData('quote_number')
            ));
        } else {
            $this->send(
                $this->config->getOwnerTemplate($storeId),
                $ownerEmail,
                '',
                $vars,
                $storeId,
                (string)$quote->getData('email')
            );
        }
        return $customerSent;
    }

    /**
     * Send one templated email.
     *
     * @param string $templateId
     * @param string $to
     * @param string $toName
     * @param array $vars
     * @param int $storeId
     * @param string|null $replyTo
     * @return bool
     */
    private function send(
        string $templateId,
        string $to,
        string $toName,
        array $vars,
        int $storeId,
        ?string $replyTo
    ): bool {
        if ($templateId === '' || $to === '') {
            $this->logger->error('Excal_Quote: missing email template or recipient.');
            return false;
        }
        $this->inlineTranslation->suspend();
        try {
            $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope($this->config->getSenderIdentity($storeId), $storeId)
                ->addTo($to, $toName);
            if ($replyTo) {
                $this->transportBuilder->setReplyTo($replyTo);
            }
            $this->transportBuilder->getTransport()->sendMessage();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Excal_Quote: email for %s to %s failed: %s',
                (string)$vars['quote_data']['quote_number'],
                $to,
                $e->getMessage()
            ));
            return false;
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * Template variables (arrays/scalars only, compatible with strict template mode).
     *
     * @param Quote $quote
     * @return array
     */
    private function buildVars(Quote $quote): array
    {
        $storeId = (int)$quote->getData('store_id');
        $currency = (string)$quote->getData('currency_code');
        $rows = '';
        foreach ($this->quoteLoader->getItems($quote) as $item) {
            $rows .= sprintf(
                '<tr><td style="padding:4px;border-bottom:1px solid #ddd">%s</td>'
                . '<td style="padding:4px;border-bottom:1px solid #ddd">%s</td>'
                . '<td style="padding:4px;border-bottom:1px solid #ddd;text-align:right">%s</td>'
                . '<td style="padding:4px;border-bottom:1px solid #ddd;text-align:right">%s</td>'
                . '<td style="padding:4px;border-bottom:1px solid #ddd;text-align:right">%s</td></tr>',
                $this->escaper->escapeHtml((string)$item->getData('sku')),
                $this->escaper->escapeHtml((string)$item->getData('name')),
                $this->escaper->escapeHtml((string)(float)$item->getData('qty')),
                $this->escaper->escapeHtml($this->money((float)$item->getData('unit_price'), $storeId, $currency)),
                $this->escaper->escapeHtml($this->money((float)$item->getData('line_total'), $storeId, $currency))
            );
        }

        return [
            'view_url' => $this->urlProvider->getViewUrl($quote),
            'items_html' => $rows,
            'quote_data' => [
                'quote_number' => (string)$quote->getData('quote_number'),
                'organisation' => (string)$quote->getData('organisation'),
                'department' => (string)$quote->getData('department'),
                'contact_name' => (string)$quote->getData('contact_name'),
                'email' => (string)$quote->getData('email'),
                'phone' => (string)$quote->getData('phone'),
                'billing_address' => (string)$quote->getData('billing_address'),
                'delivery_address' => (string)$quote->getData('delivery_address'),
                'customer_reference' => (string)$quote->getData('customer_reference'),
                'notes' => (string)$quote->getData('notes'),
                'subtotal' => $this->money((float)$quote->getData('subtotal'), $storeId, $currency),
                'delivery' => $this->money((float)$quote->getData('delivery_amount'), $storeId, $currency),
                'vat_rate' => rtrim(rtrim(number_format((float)$quote->getData('vat_rate'), 2, '.', ''), '0'), '.'),
                'vat' => $this->money((float)$quote->getData('vat_amount'), $storeId, $currency),
                'total' => $this->money((float)$quote->getData('grand_total'), $storeId, $currency),
                'valid_until' => $this->timezone->formatDateTime(
                    (string)$quote->getData('valid_until'),
                    \IntlDateFormatter::LONG,
                    \IntlDateFormatter::NONE,
                    null,
                    'UTC'
                ),
            ],
        ];
    }

    /**
     * Format money without HTML container.
     *
     * @param float $amount
     * @param int $storeId
     * @param string $currency
     * @return string
     */
    private function money(float $amount, int $storeId, string $currency): string
    {
        return $this->priceCurrency->format($amount, false, 2, $storeId, $currency ?: null);
    }
}
