<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UrlRewrite\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\EventManager;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\UrlRewrite\Controller\Adminhtml\Url\Rewrite;

/**
 * UrlRewrite model class
 *
 * @method int getEntityId()
 * @method string getEntityType()
 * @method int getRedirectType()
 * @method int getStoreId()
 * @method int getIsAutogenerated()
 * @method string getTargetPath()
 * @method UrlRewrite setEntityId(int $value)
 * @method UrlRewrite setEntityType(string $value)
 * @method UrlRewrite setRequestPath($value)
 * @method UrlRewrite setTargetPath($value)
 * @method UrlRewrite setRedirectType($value)
 * @method UrlRewrite setStoreId($value)
 * @method UrlRewrite setDescription($value)
 */
class UrlRewrite extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @var Json
     */
    private $serializer;

    /**
     * UrlRewrite constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     * @param Json $serializer
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        Json $serializer = null
    ) {
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Initialize corresponding resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Magento\UrlRewrite\Model\ResourceModel\UrlRewrite::class);
        $this->_collectionName = \Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection::class;
    }

    /**
     * Get metadata
     *
     * @return array
     * @api
     */
    public function getMetadata()
    {
        $metadata = $this->getData(\Magento\UrlRewrite\Service\V1\Data\UrlRewrite::METADATA);
        return !empty($metadata) ? $this->serializer->unserialize($metadata) : [];
    }

    /**
     * Overwrite Metadata in the object.
     *
     * @param array|string $metadata
     * @return $this
     */
    public function setMetadata($metadata)
    {
        if (is_array($metadata)) {
            $metadata = $this->serializer->serialize($metadata);
        }
        return $this->setData(\Magento\UrlRewrite\Service\V1\Data\UrlRewrite::METADATA, $metadata);
    }

    private function opt1() {
        $map = [
            Rewrite::ENTITY_TYPE_PRODUCT => Product::CACHE_TAG,
            Rewrite::ENTITY_TYPE_CATEGORY => Category::CACHE_TAG,
            Rewrite::ENTITY_TYPE_CMS_PAGE => Page::CACHE_TAG
        ];

        if ($this->getEntityType() !== Rewrite::ENTITY_TYPE_CUSTOM) {
            $cacheKey = $map[$this->getEntityType()];

            $cacheContext = ObjectManager::getInstance()->get(CacheContext::class);
            $eventManager = ObjectManager::getInstance()->get(EventManager::class);

            $cacheContext->registerEntities($cacheKey, [$this->getEntityId()]);
            $eventManager->dispatch('clean_cache_by_tags', ['object' => $cacheContext]);
        }
    }

    private function opt2() {
        $map = [
            Rewrite::ENTITY_TYPE_PRODUCT => function ($prodId) {
                /** @var ProductRepositoryInterface $productRepository */
                $productRepository = ObjectManager::getInstance()->get(ProductRepositoryInterface::class);
                return $productRepository->getById($prodId);
            },
            Rewrite::ENTITY_TYPE_CATEGORY => function ($catId) {
                /** @var CategoryRepositoryInterface $productRepository */
                $categoryRepository = ObjectManager::getInstance()->get(CategoryRepositoryInterface::class);
                return $categoryRepository->get($catId);
            },
            Rewrite::ENTITY_TYPE_CMS_PAGE => function ($cmsId) {
                /** @var PageRepositoryInterface $productRepository */
                $pageRepository = ObjectManager::getInstance()->get(PageRepositoryInterface::class);
                return $pageRepository->getById($cmsId);
            },
            Rewrite::ENTITY_TYPE_CUSTOM => false
        ];

        $getter = $map[$this->getEntityType()];

        if ($getter) {
            $entity = $getter($this->getEntityId());

            $entityManager = ObjectManager::getInstance()->get(EventManager::class);
            $entityManager->dispatch('clean_cache_by_tags', ['object' => $entity]);
        }
    }

    public function afterSave()
    {
        $this->opt1();
        return parent::afterSave(); // TODO: Change the autogenerated stub
    }
}
