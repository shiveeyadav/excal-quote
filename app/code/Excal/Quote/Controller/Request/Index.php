<?php
/**
 * Quotation request form (GET /quote/request).
 */
declare(strict_types=1);

namespace Excal\Quote\Controller\Request;

use Excal\Quote\Model\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Shows the "official quotation" form for the current basket.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param Config $config
     * @param CheckoutSession $checkoutSession
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        if (!(int)$this->checkoutSession->getQuote()->getItemsCount()) {
            $this->messageManager->addNoticeMessage(
                __('Add the products you need to your basket, then request an official quotation.')
            );
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Request an official quotation'));
        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');
        return $page;
    }
}
