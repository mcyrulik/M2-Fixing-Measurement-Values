<?php

namespace Room204\FixingMeasurements\Console\Command;

use Goodwill\AdminTheme\Source\MeasurementValues;
use Magento\Framework\App\Filesystem\DirectoryList;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\Filesystem;
use Magento\Catalog\Api\ProductRepositoryInterface;



/**
 * Created by PhpStorm.
 * User: markcyrulik
 * Date: 11/1/16
 * Time: 6:58 PM
 */
class AuditMeasurements extends Command
{

    /**
     * @var Filesystem
     */
    protected $_filesystem;

    protected $attributeList = [
        'gw_waist_measurement',
        'gw_inseam',
        'gw_chest_measurement',
        'gw_length'
    ];

    protected $productCollection;

    protected $_registry;

    protected $_state;

    protected $_logger;

    protected $_productRepository;

    protected $_csvProcessor;

    protected $_measurementValues;

    /**
     * Constructor
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        CollectionFactory $productCollection,
        Registry $registry,
        ProductRepositoryInterface $productRepository,
        \Magento\Framework\File\Csv $csvProcessor,
        MeasurementValues $measurementValues,
        Filesystem $filesystem
    ) {
        $this->productCollection = $productCollection;
        $this->_registry = $registry;
        $this->_state = $state;
        $this->_productRepository = $productRepository;
        $this->_csvProcessor = $csvProcessor;
        $this->_measurementValues = $measurementValues;
        $this->_filesystem = $filesystem;

        parent::__construct();

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test.log');
        $this->_logger = new \Zend\Log\Logger();
        $this->_logger->addWriter($writer);
    }

    /**
     *
     */
    protected function configure()
    {
        $this->setName('room204:audit-measurements')
            ->setDescription('Audit Missing Meausrements');
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
        $output->writeln('Begin: Audit Missing Measurements');

        $productCollection = $this->productCollection
            ->create()
            ->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('gw_waist_measurement')
            ->addAttributeToSelect('gw_inseam')
            ->addAttributeToSelect('gw_chest_measurement')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('gw_length');

        $measurementData = [];
        $measurementData[] = [
            'entity_id',
            'sku',
            'gw_waist_measurement',
            'gw_inseam',
            'gw_chest_measurement',
            'gw_length'
        ];

        /** @var \Magento\Catalog\Model\Product\Interceptor $item */
        foreach ($productCollection as $item) {
            $temp = [
                $item->getData('entity_id'),
                $item->getData('sku'),
                $this->_measurementValues->getOptionText($item->getData('gw_waist_measurement')),
                $this->_measurementValues->getOptionText($item->getData('gw_inseam')),
                $this->_measurementValues->getOptionText($item->getData('gw_chest_measurement')),
                $this->_measurementValues->getOptionText($item->getData('gw_length')),
            ];
            $measurementData[] = $temp;

            $output->writeln(implode("\t", $temp));
        }

        $path = $this->_filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getAbsolutePath("reports");
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filePath = $path."/".'MeasurementValues.csv';

        $this->_csvProcessor->saveData($filePath, $measurementData);

        $output->writeln('End: Audit Missing Measurements');
    }

}