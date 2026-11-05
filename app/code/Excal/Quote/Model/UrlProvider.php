<?php
/**
 * Builds public (storefront) quotation URLs from any area.
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Magento\Framework\Url;

/**
 * Storefront URL for the printable quotation.
 */
class UrlProvider
{
    /**
     * @param Url $frontendUrl storefront URL model (works from adminhtml and cron too)
     */
    public function __construct(
        private readonly Url $frontendUrl
    ) {
    }

    /**
     * Public view URL, e.g. https://www.example.com/quote/view/id/<hash>.
     *
     * @param Quote $quote
     * @return string
     */
    public function getViewUrl(Quote $quote): string
    {
        return $this->frontendUrl->getUrl('', [
            '_direct' => 'quote/view/id/' . $quote->getData('hash'),
            '_scope' => (int)$quote->getData('store_id'),
            '_nosid' => true,
        ]);
    }
}
