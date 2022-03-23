<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use Magento\Store\Model\StoreManagerInterface;

use Magento\Framework\App\ObjectManager;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Source Model get product categories
 */
class Categories
{	
	/**
	 * var $_storeManager;
	 * Magento\Store\Model\StoreManagerInterface
	 */
	protected $_storeManager;
	
	public function __construct(
		StoreManagerInterface $storeManager
	)
	{
		$this->_storeManager = $storeManager;
	}

	public function getCategories()
	{
		$objectManager = ObjectManager::getInstance();
		$categoryFactory = $objectManager->create(CollectionFactory::class);

		$categories = $categoryFactory->create()
			->addAttributeToSelect('*')
			->setStore($this->_storeManager->getStore());

		return $categories;
	}

	public function toOptionArray(): array
	{
		$cate = [];
		$categories = $this->getCategories();

		if (!empty($categories)) {
			foreach ($categories as $c) {
				$cate[] = ['value' => $c->getId(), 'label' => $c->getName()];
			}
		}

		return $cate;
	}
}