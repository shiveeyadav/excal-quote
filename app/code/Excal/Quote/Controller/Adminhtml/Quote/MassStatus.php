<?php
/**
 * Admin mass action: change quotation status.
 */
declare(strict_types=1);

namespace Excal\Quote\Controller\Adminhtml\Quote;

use Excal\Quote\Model\Quote;
use Excal\Quote\Model\ResourceModel\Quote as QuoteResource;
use Excal\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Sets open / converted / expired on the selected quotations.
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Excal_Quote::quotes';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param QuoteResource $quoteResource
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly QuoteResource $quoteResource
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');
        $status = (string)$this->getRequest()->getParam('status');
        if (!in_array($status, Quote::getStatuses(), true)) {
            $this->messageManager->addErrorMessage(__('Unknown status.'));
            return $redirect;
        }
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = $this->quoteResource->updateStatus(array_map('intval', $collection->getAllIds()), $status);
            $this->messageManager->addSuccessMessage(__('%1 quotation(s) updated.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}
