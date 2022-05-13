<?php

namespace Pace\Pay\Model\Adminhtml\Source;

use Magento\Store\Model\StoreManagerInterface;

/**
 * Source Model get product categories
 */
class Categories
{	
	/**
	 * @var StoreManagerInterface
	 */
	protected $storeManager;
	
	public function __construct(
		StoreManagerInterface $storeManager
	)
	{
		$this->storeManager = $storeManager;
	}

	/**
	 * getCategories...
	 * 
	 * @return array
	 */
	public function getCategories()
	{
		$categoryFactory = \Magento\Framework\App\ObjectManager::getInstance()
			->create(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class);
			
		$categories = $categoryFactory->create()
			->addAttributeToSelect('*')
			->setStore($this->storeManager->getStore());

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