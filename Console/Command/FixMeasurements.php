<?php

namespace Room204\FixingMeasurements\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;


/**
 * Created by PhpStorm.
 * User: markcyrulik
 * Date: 11/1/16
 * Time: 6:58 PM
 */
class FixMeasurements extends Command
{

    protected $productCollection;

    protected $_registry;

    protected $_state;

    protected $_categoryRepository;

    protected $_productRepository;

    /**
     * Constructor
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        CollectionFactory $productCollection,
        CategoryRepositoryInterface $categoryRepository,
        Registry $registry,
        ProductRepositoryInterface $productRepository
    ) {
        $this->productCollection = $productCollection;
        $this->_registry = $registry;
        $this->_state = $state;
        $this->_categoryRepository = $categoryRepository;
        $this->_productRepository = $productRepository;
        parent::__construct();
    }

    /**
     *
     */
    protected function configure()
    {
        $this->setName('room204:fix-measurements')
            ->setDescription('Fixing Missing Meausrements');
        parent::configure();;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $spacer = "        ";
        $this->_registry->register('isSecureArea', true);
        $this->_state->setAreaCode('adminhtml');

        $output->writeln('Begin: Fixing Missing Meausrements');
        $counter = 1;

    }

}