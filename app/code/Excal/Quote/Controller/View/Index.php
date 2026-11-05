<?php
/**
 * Public printable quotation (GET /quote/view/id/<hash>).
 */
declare(strict_types=1);

namespace Excal\Quote\Controller\View;

use Excal\Quote\Model\Config;
use Excal\Quote\Model\QuoteLoader;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Renders the quotation identified by its unguessable hash.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param Config $config
     * @param QuoteLoader $quoteLoader
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly QuoteLoader $quoteLoader,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $quote = $this->quoteLoader->getByHash((string)$this->request->getParam('id'));
        if (!$this->config->isEnabled() || $quote === null) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Quotation %1', $quote->getData('quote_number')));
        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');
        $page->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $page->setHeader('Referrer-Policy', 'no-referrer', true);
        return $page;
    }
}
