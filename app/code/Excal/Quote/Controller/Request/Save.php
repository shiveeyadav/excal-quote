<?php
/**
 * Quotation request submit (POST /quote/request/save).
 */
declare(strict_types=1);

namespace Excal\Quote\Controller\Request;

use Excal\Quote\Model\Config;
use Excal\Quote\Model\FormValidator;
use Excal\Quote\Model\Mailer;
use Excal\Quote\Model\QuoteCreator;
use Excal\Quote\Model\RequestThrottle;
use Excal\Quote\Model\UrlProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Validates the form, snapshots the basket into a quotation, emails it and shows it.
 */
class Save implements HttpPostActionInterface
{
    public const PERSIST_KEY = 'excal_quote_request';
    private const SESSION_TIMES_KEY = 'excal_quote_request_times';
    private const HONEYPOT_FIELD = 'website';

    /**
     * @param RequestInterface $request
     * @param Config $config
     * @param FormKeyValidator $formKeyValidator
     * @param FormValidator $formValidator
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param QuoteCreator $quoteCreator
     * @param Mailer $mailer
     * @param UrlProvider $urlProvider
     * @param DataPersistorInterface $dataPersistor
     * @param ForwardFactory $forwardFactory
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     * @param RequestThrottle $throttle
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly FormValidator $formValidator,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly QuoteCreator $quoteCreator,
        private readonly Mailer $mailer,
        private readonly UrlProvider $urlProvider,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly ForwardFactory $forwardFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger,
        private readonly RequestThrottle $throttle
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
        $back = $this->redirectFactory->create()->setPath('quote/request');

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Your session has expired. Please submit the form again.'));
            return $back;
        }

        $post = (array)$this->request->getPostValue();
        if (trim((string)($post[self::HONEYPOT_FIELD] ?? '')) !== '') {
            // Bot: pretend nothing happened, do not save or email.
            $this->logger->info('Excal_Quote: honeypot triggered, request discarded.');
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        [$data, $errors] = $this->formValidator->validate($post);
        if ($errors) {
            foreach ($errors as $error) {
                $this->messageManager->addErrorMessage($error);
            }
            $this->dataPersistor->set(self::PERSIST_KEY, $data);
            return $back;
        }

        if (!$this->checkRateLimit()
            || !$this->throttle->isAllowed((string)($data['email'] ?? ''), $this->config->getMaxPerHour())
        ) {
            $this->messageManager->addErrorMessage(
                __('Too many quotation requests. Please try again later or contact us.')
            );
            $this->dataPersistor->set(self::PERSIST_KEY, $data);
            return $back;
        }

        $cart = $this->checkoutSession->getQuote();
        if (!(int)$cart->getItemsCount()) {
            $this->messageManager->addErrorMessage(__('Your basket is empty.'));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        try {
            $cart->collectTotals();
            $customerId = $this->customerSession->isLoggedIn()
                ? (int)$this->customerSession->getCustomerId()
                : null;
            $quote = $this->quoteCreator->create($cart, $data, $customerId);
        } catch (LocalizedException $e) {
            $this->logger->error('Excal_Quote: save failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set(self::PERSIST_KEY, $data);
            return $back;
        }

        $this->dataPersistor->clear(self::PERSIST_KEY);
        $this->recordRequest();
        $this->throttle->record((string)$quote->getData('email'));

        if ($this->mailer->sendAll($quote)) {
            $this->messageManager->addSuccessMessage(__(
                'Quotation %1 has been created and emailed to %2.',
                $quote->getData('quote_number'),
                $quote->getData('email')
            ));
        } else {
            $this->messageManager->addWarningMessage(__(
                'Quotation %1 has been created, but we could not email it. Please save or print this page.',
                $quote->getData('quote_number')
            ));
        }

        return $this->redirectFactory->create()->setUrl($this->urlProvider->getViewUrl($quote));
    }

    /**
     * Simple per-session throttle.
     *
     * @return bool
     */
    private function checkRateLimit(): bool
    {
        $max = $this->config->getMaxPerHour();
        if ($max === 0) {
            return true;
        }
        return count($this->getRecentTimes()) < $max;
    }

    /**
     * Remember this successful request for the throttle.
     *
     * @return void
     */
    private function recordRequest(): void
    {
        $times = $this->getRecentTimes();
        $times[] = time();
        $this->customerSession->setData(self::SESSION_TIMES_KEY, $times);
    }

    /**
     * Request timestamps within the last hour.
     *
     * @return int[]
     */
    private function getRecentTimes(): array
    {
        $cutoff = time() - 3600;
        $times = (array)$this->customerSession->getData(self::SESSION_TIMES_KEY);
        return array_values(array_filter($times, static fn ($t) => (int)$t > $cutoff));
    }
}
