<?php

namespace Room204\FixingMeasurements\Console\Command;

use Goodwill\AdminTheme\Source\MeasurementValues;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Input\InputArgument;
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
class UpdateMeasurementsFinal extends Command
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
        $this->setName('room204:update-measurements-final')
            ->setDescription('Update Final Missing Measurements')
            ->addArgument('file', InputArgument::REQUIRED, 'File to Import From');
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
        $output->writeln('Begin: Update Final Missing Measurements');

        if (!file_exists($input->getArgument('file'))) {
            throw new LocalizedException(__('Input file does not exist.'));
        }

        $csvArray = $this->_csvProcessor->getData($input->getArgument('file'));

        $output->writeln(gettype($csvArray));
        $output->writeln(print_r($csvArray[0], true));

        $headers = $csvArray[0];

        unset($csvArray[0]);

        $newArray = [];
        foreach ($csvArray as $row) {
            $temp = [];
            foreach ($row as $key => $value) {
                $temp[$headers[$key]] = $value;
            }
            $newArray[] = $temp;
        }

        foreach ($newArray as $newRow) {
//        $newRow = $newArray[12342];
//        $output->writeln(print_r($newRow, true));
            try {
                $product = $this->_productRepository->getById( $newRow['entity_id'] );

                if ($product) {
                    $output->write( $newRow['entity_id'] . ":\t" );
                    $output->write( $newRow['SKU'] . ":\t" );
                    foreach ($this->attributeList as $attr) {
                        $output->write( "{$attr}: {$newRow[$attr]};\t" );
                        $product->setData( $attr, $newRow[$attr] );
                    }
                    $output->writeln( '' );
                    $this->_productRepository->save( $product );
                    unset( $product );
                }

            } catch (\Exception $e) {

            }
        }



        $output->writeln('End: Update Final Missing Measurements');
    }

}