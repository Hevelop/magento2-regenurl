<?php

namespace Iazel\RegenProductUrl\Console\Command;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class RegenerateProductUrlCommand extends Command
{
    /**
     * @var ProductUrlRewriteGenerator
     */
    protected $productUrlRewriteGenerator;

    /**
     * @var UrlPersistInterface
     */
    protected $urlPersist;

    /**
     * @var ProductRepositoryInterface
     */
    protected $collection;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var State
     */
    protected $state;

    public function __construct(
        State $state,
        Collection $collection,
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        UrlPersistInterface $urlPersist,
        StoreManagerInterface $storeManager
    )
    {
        $this->state = $state;
        $this->collection = $collection;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
        $this->storeManager = $storeManager;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('iazel:regenurl')
            ->setDescription('Regenerate url for given products')
            ->addArgument(
                'pids',
                InputArgument::IS_ARRAY,
                'Products to regenerate'
            )
            ->addOption(
                'store', 's',
                InputOption::VALUE_REQUIRED,
                'Use the specific Store View',
                Store::DEFAULT_STORE_ID
            );
        return parent::configure();
    }


    /**
     * @param InputInterface $inp
     * @param OutputInterface $out
     * @return int|null|void
     */
    public function execute(InputInterface $inp, OutputInterface $out)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $store_id = $inp->getOption('store');
        $this->collection->addStoreFilter($store_id)->setStoreId($store_id);

        $pids = $inp->getArgument('pids');
        if (!empty($pids)) {
            $this->collection->addIdFilter($pids);
        }

        $this->collection->addAttributeToSelect(['url_path', 'url_key'])
            ->addAttributeToFilter('visibility', ['in' => [2, 3, 4]]);
        $list = $this->collection->load();

        $stores = $this->storeManager->getStores();
        $storeIds = [];
        foreach ($stores as $store) {
            $storeIds[] = $store->getId();
        }
        foreach ($list as $product) {

            if ($store_id === Store::DEFAULT_STORE_ID) {
                //Delete all url rewrite for default store
                $this->urlPersist->deleteByData([
                    UrlRewrite::ENTITY_ID => $product->getId(),
                    UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                    UrlRewrite::REDIRECT_TYPE => 0
                ]);
            } else {
                //Delete all url rewrite for single store
                $this->urlPersist->deleteByData([
                    UrlRewrite::ENTITY_ID => $product->getId(),
                    UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                    UrlRewrite::REDIRECT_TYPE => 0,
                    UrlRewrite::STORE_ID => $store_id,
                ]);
            }
            try {
                if ($store_id === Store::DEFAULT_STORE_ID) {
                    foreach ($storeIds as $storeId) {
                        $product->setStoreId($storeId);
                        $this->urlPersist->replace(
                            $this->productUrlRewriteGenerator->generate($product)
                        );
                    }
                } else {
                    $product->setStoreId($store_id);
                    $this->urlPersist->replace(
                        $this->productUrlRewriteGenerator->generate($product)
                    );
                }

                $out->writeln('<info>Regenerated url keys for product ' . $product->getId() . '</info>');
            } catch (\Exception $e) {
                $out->writeln('<error>Duplicated url for ' . $product->getId() . '</error>');
            }
        }
    }
}
