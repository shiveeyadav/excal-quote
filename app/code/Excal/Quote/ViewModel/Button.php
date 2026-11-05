<?php
/**
 * Cart / mini-cart "Get an official quotation" link.
 */
declare(strict_types=1);

namespace Excal\Quote\ViewModel;

use Excal\Quote\Model\Config;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Provides the quotation request URL and visibility flags.
 */
class Button implements ArgumentInterface
{
    /**
     * @param Config $config
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Show the cart page button.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Show the mini-cart link.
     *
     * @return bool
     */
    public function isMinicartLinkEnabled(): bool
    {
        return $this->config->isMinicartLinkEnabled();
    }

    /**
     * Form URL.
     *
     * @return string
     */
    public function getRequestUrl(): string
    {
        return $this->urlBuilder->getUrl('quote/request');
    }
}
