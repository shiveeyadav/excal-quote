<?php
/**
 * "View / print" link column for the admin grid.
 */
declare(strict_types=1);

namespace Excal\Quote\Ui\Component\Listing\Column;

use Magento\Framework\Url;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Links each row to its public printable page (storefront URL).
 */
class Actions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Url $frontendUrl
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Url $frontendUrl,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (empty($item['hash'])) {
                continue;
            }
            $item[$name]['view'] = [
                'href' => $this->frontendUrl->getUrl('', [
                    '_direct' => 'quote/view/id/' . $item['hash'],
                    '_scope' => (int)($item['store_id'] ?? 0) ?: null,
                    '_nosid' => true,
                ]),
                'label' => __('View / print'),
                'target' => '_blank',
            ];
        }
        return $dataSource;
    }
}
