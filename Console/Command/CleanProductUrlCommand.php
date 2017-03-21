<?php
/**
 *  @category Magento2_Module
 *  @project Magento 2
 *  @author   Matteo Manfrin <matteo@hevelop.com>
 *  @copyright Copyright (c) 2017 Hevelop  (https://hevelop.com)
 */

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

class CleanProductUrlCommand extends Command
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
     * @var State
     */
    protected $state;

    public function __construct(
        State $state,
        Collection $collection,
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        UrlPersistInterface $urlPersist
    )
    {
        $this->state = $state;
        $this->collection = $collection;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('iazel:cleanurl')
            ->setDescription('Clean url for given products')
            ->addArgument(
                'pids',
                InputArgument::IS_ARRAY,
                'Products to clean'
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
     * remove all products not visible individually from url_rewrite
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
            ->addAttributeToFilter('visibility', 1);
        $list = $this->collection->load();
        foreach ($list as $product) {

            try {
                $this->urlPersist->deleteByData([
                    UrlRewrite::ENTITY_ID => $product->getId(),
                    UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                    UrlRewrite::REDIRECT_TYPE => 0
                ]);
                $out->writeln('<info>Deleted Url keys for product: ' . $product->getId() . '</info>');
            } catch (\Exception $e) {
                $out->writeln('<error>Duplicated url for ' . $product->getId() . '</error>');
            }
        }
    }
}
