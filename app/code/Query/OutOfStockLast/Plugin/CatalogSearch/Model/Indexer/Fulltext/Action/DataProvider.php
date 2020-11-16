<?php

namespace Query\OutOfStockLast\Plugin\CatalogSearch\Model\Indexer\Fulltext\Action;

use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\StatusFactory as StockStatusFactory;
use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider as CatalogSearchDataProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class DataProvider
{
    /**
     * StockStateInterface
     */
    private $stockStateInterface;

    /**
     * @var SalesChannelInterfaceFactory
     */
    private $salesChannelFactory;

    /**
     * @var GetStockBySalesChannelInterface
     */
    private $getStockBySalesChannel;

    /**
     * @var StockStatusFactory
     */
    private $stockStatusFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * LoggerInterface
     */
    private $logger;


    /**
     * @param StockStateInterface $stockStateInterface
     * @param StoreManagerInterface $storeManagerInterface
     * @param SalesChannelInterfaceFactory $salesChannelFactory
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     * @param StockStatusFactory $stockStatusFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        StockStateInterface $stockStateInterface,
        StoreManagerInterface $storeManagerInterface,
        SalesChannelInterfaceFactory $salesChannelFactory,
        GetStockBySalesChannelInterface $getStockBySalesChannel,
        StockStatusFactory $stockStatusFactory,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->stockStateInterface = $stockStateInterface;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->getStockBySalesChannel = $getStockBySalesChannel;
        $this->stockStatusFactory = $stockStatusFactory;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * 
     */
    public function afterGetProductAttributes(
        CatalogSearchDataProvider $subject, 
        array $result,
        $storeId, 
        $productsIds
    ) {
        $website = $this->storeManagerInterface->getStore($storeId)->getWebsite();
        $salesChannel = $this->salesChannelFactory->create
        ([
            'data' =>
            [
                'type' => SalesChannelInterface::TYPE_WEBSITE,
                'code' => $website->getCode()
            ]
        ]);

        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        $stockStatusResModel = $this->stockStatusFactory->create();
        
        if($stockId == 1) // default, in other words, dont use MSI
        {
            $stockStatusData = $stockStatusResModel->getProductsStockStatuses($productsIds, $website->getId(), $stockId);
        }
        else
        {
            $stockStatusData = $this->_getProductsStockStatuses($productsIds, $stockId);
        }

        foreach($result as $productId => $productData)
        {
            if(isset($stockStatusData[$productId]))
            {
                $result[$productId]["is_saleable"] = intval($stockStatusData[$productId]);
            }
            else
            {
                $result[$productId]["is_saleable"] = 0;
            }
        }

        return $result;
    }

    /**
     * 
     */
    public function afterPrepareProductIndex(
        CatalogSearchDataProvider $subject, 
        array $result,
        $productIndex, 
        $productData
    ) {
        // remove o atributo do tipo textual, 
        // para que ela possa ser ordenavelc
        $typeIndex = array_key_first($result["is_saleable"]);
        $result["is_saleable"] = intval($result["is_saleable"][$typeIndex]);

        return $result;
    }

    /**
     * Retrieve product status
     * Return array as key product_id, value - stock status
     *
     * @param string[] $productsIds
     * @param int $stockId
     * @return array
     */
    private function _getProductsStockStatuses($productsIds, $stockId)
    {
        $connection = $this->resourceConnection->getConnection();
        $entityTable = $this->resourceConnection->getTableName("catalog_product_entity");
        $stockTable = $this->resourceConnection->getTableName("inventory_stock_{$stockId}");

        $select = $connection->select()
            ->from($entityTable, ["entity_id"])
            ->joinLeft
            (
                ["stock_status" => $stockTable],
                "catalog_product_entity.sku = stock_status.sku",
                ["is_salable"]
            )
            ->where('entity_id IN(?)', $productsIds);
        
        return $connection->fetchPairs($select);
    }
}