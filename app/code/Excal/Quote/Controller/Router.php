<?php
/**
 * Custom router for /quote/view/id/<hash>.
 */
declare(strict_types=1);

namespace Excal\Quote\Controller;

use Excal\Quote\Model\Config;
use Excal\Quote\Model\QuoteLoader;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Url;

/**
 * Maps the short public URL onto quote/view/index with param id.
 *
 * Runs after the standard router (sortOrder 40 in etc/frontend/di.xml), which cannot match
 * "quote/view/id" because there is no View\Id action; the forwarded request is then
 * matched by the standard router on the next loop iteration.
 */
class Router implements RouterInterface
{
    private const PATTERN = '#^quote/view/id/([A-Za-z0-9]{48})/?$#';

    /**
     * @param ActionFactory $actionFactory
     * @param Config $config
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        if ($request->getModuleName()) {
            // Already routed (e.g. our own forward) - avoid a routing loop.
            return null;
        }
        $identifier = trim((string)$request->getPathInfo(), '/');
        if (!preg_match(self::PATTERN, $identifier, $matches) || !$this->config->isEnabled()) {
            return null;
        }

        $request->setModuleName('quote')
            ->setControllerName('view')
            ->setActionName('index')
            ->setParam('id', $matches[1]);
        $request->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->actionFactory->create(Forward::class);
    }
}
