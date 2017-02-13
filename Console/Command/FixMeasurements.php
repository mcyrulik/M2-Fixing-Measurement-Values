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

    protected $_resourceConnection;

    /**
     * Constructor
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        CollectionFactory $productCollection,
        Registry $registry,
        ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->productCollection = $productCollection;
        $this->_registry = $registry;
        $this->_state = $state;
        $this->_productRepository = $productRepository;
        $this->_resourceConnection = $resourceConnection;
        parent::__construct();

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/FixMeasurements.log');
        $this->_logger = new \Zend\Log\Logger();
        $this->_logger->addWriter($writer);
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
        $this->_logger->info('Begin: Fixing Missing Meausrements');

        $connection = $this->_resourceConnection->getConnection();
        $eventTable = $this->_resourceConnection->getTableName('magento_logging_event');
        $eventChangesTable = $this->_resourceConnection->getTableName('magento_logging_event_changes');
        $productTable = $this->_resourceConnection->getTableName('catalog_product_entity');

        $query = "SELECT
                    *
                FROM
                    {$eventChangesTable}
                    INNER JOIN {$eventTable} ON {$eventChangesTable}.event_id={$eventTable}.log_id
                WHERE
                    {$eventTable}.event_code = 'catalog_products'
                ORDER BY
                    log_id ASC";

        $result = $connection->fetchAll($query);

        $transformArray = [];

        // Getting just the information that we care about. Feel free to adjust the attributeList property to the attributes you need.
        foreach ($result as $eventChange) {
            $resultData = unserialize($eventChange['result_data']);

            $temp = [];

            foreach ($this->attributeList as $attr) {
                $temp[$attr] = $resultData[$attr];
            }

            $transformArray[$eventChange['source_id']][] = $temp;
        }

        // Now that we have our slimmed down list, let's slim it down further.
        foreach ($transformArray as $productId => $data) {
            // limiting our list to this range of products.
            if ($productId < 36 || $productId > 20692) {
                $transformArray[$productId]['new_data'] = null;
                continue;
            }

            if (count( $data ) == 1) {
                /** if there is only one set of data, then it should not be updated - it had to have been entered
                 * by a user. We will use NULL to skip thee later.
                 */
                $transformArray[$productId]['new_data'] = null;
            } else {
                $temp = [];

                foreach ($data as $historyData) {
                    foreach ($this->attributeList as $attr) {
                        if ($historyData[$attr] && $historyData[$attr] > 0) {
                            $temp[$attr] = $historyData[$attr];
                        }
                    }
                }
                $transformArray[$productId]['new_data'] = $temp;
            }

        }

        // $transformArray should now have an index new_data that should be the data we need to process if it is not null.
        $counter = 1;
        foreach ($transformArray as $productId => $data) {
            $output->write(str_pad($counter, 6, "0", STR_PAD_LEFT)." => ");
            $textLog = str_pad($counter, 6, "0", STR_PAD_LEFT)." => ";

            if ($productId && $connection->fetchOne("SELECT 1 FROM catalog_product_entity where entity_id={$productId}")) {
                $product = $this->_productRepository->getById($productId);

                if ($data['new_data'] != null) {
                    if ($product) {
                        $output->write($product->getSku().' Processing Product: '.' => ');
                        $textLog .= $product->getSku().' Processing Product: '.' => ';

                        foreach ($data['new_data'] as $attribute => $attrData) {
                            $output->write($attribute.': '.$attrData.", ");
                            $textLog .= $attribute.': '.$attrData.", ";
                            $product->setData($attribute, $attrData);
                        }

                        try {
                            $this->_productRepository->save( $product );
                        } catch (\Exception $e) {
                            $output->write($e->getMessage());
                            $textLog .= $e->getMessage();
                        }

                    } else {
                        $output->write('Product Not Found: '.$productId);
                        $textLog .= 'Product Not Found: '.$productId;
                    }
                    $counter++;
                } else {
                    $output->write($product->getSku().' No Data to Update: ');
                    $textLog .= $product->getSku().' No Data to Update: ';
                    $counter++;
                }
            } else {
                $product = null;
                $output->writeln('Product Not Found: '.$productId);
                $this->_logger->info('Product Not Found: '.$productId);
                continue;
            }

            $output->writeln(' Done.');
            $this->_logger->info($textLog.' Done.');
        }

        $output->writeln('Ending: Fixing Missing Meausrements');
        $this->_logger->info('Ending: Fixing Missing Meausrements');

    }

}