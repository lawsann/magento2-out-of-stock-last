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
    protected $storeManager;

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
     * @param StoreManagerInterface $storeManager
     * @param SalesChannelInterfaceFactory $salesChannelFactory
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     * @param StockStatusFactory $stockStatusFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        StockStateInterface $stockStateInterface,
        StoreManagerInterface $storeManager,
        SalesChannelInterfaceFactory $salesChannelFactory,
        GetStockBySalesChannelInterface $getStockBySalesChannel,
        StockStatusFactory $stockStatusFactory,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->stockStateInterface = $stockStateInterface;
        $this->storeManager = $storeManager;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->getStockBySalesChannel = $getStockBySalesChannel;
        $this->stockStatusFactory = $stockStatusFactory;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Search stock data and associate values to
     * the product attributes
     * 
     * @param CatalogSearchDataProvider $subject
     * @param array $result
     * @param integer $storeId
     * @param array $productsIds
     * @return array
     */
    public function afterGetProductAttributes(
        CatalogSearchDataProvider $subject, 
        array $result,
        $storeId, 
        $productsIds
    ) {
        $website = $this->storeManager->getStore($storeId)->getWebsite();
        $salesChannel = $this->salesChannelFactory->create
        ([
            'data' =>
            [
                'type' => SalesChannelInterface::TYPE_WEBSITE,
                'code' => $website->getCode()
            ]
        ]);
        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        
        $this->logger->debug("Stock ID", [$stockId]);

        /*
        if($stockId == 1) // default, in other words, dont use MSI
        {
        	$this->logger->debug("Product IDs | Website ID", [$productsIds, $website->getId()]);

            $stockStatusResModel = $this->stockStatusFactory->create();
            $stockStatusData = $stockStatusResModel->getProductsStockStatuses($productsIds, $website->getId(), $stockId);
        }
        else
        {
            $stockStatusData = $this->_getProductsStockStatuses($productsIds, $stockId);
        }
        */

        $stockStatusData = $this->_getProductsStockStatuses($productsIds, $stockId);

        $this->logger->debug("Stock Data", [$stockStatusData]);

        foreach($result as $productId => $productData)
        {
            if(isset($stockStatusData[$productId]))
            {
                $result[$productId]["qoosl_is_in_stock"] = intval($stockStatusData[$productId]);
            }
            else
            {
                $result[$productId]["qoosl_is_in_stock"] = 0;
            }
        }

        return $result;
    }

    /**
     * Ajust is_in_stock attribute on index
     * 
     * @param CatalogSearchDataProvider $subject
     * @param array $result
     * @param array $productIndex
     * @param array $productData
     * @return array
     */
    public function afterPrepareProductIndex(
        CatalogSearchDataProvider $subject, 
        array $result,
        $productIndex, 
        $productData
    ) {
        // remove attribute from textual type 
        // to allow use it for sort in elasticsearch
        $typeIndex = array_key_first($result["qoosl_is_in_stock"]);
        $result["qoosl_is_in_stock"] = intval($result["qoosl_is_in_stock"][$typeIndex]);

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
        
        if($stockId == 1)
        {
        	$stockTable = $this->resourceConnection->getTableName("cataloginventory_stock_status");
        	$select = $connection->select()
	            ->from($stockTable, ["product_id", "stock_status"])
	            ->where('product_id IN(?)', $productsIds);
        }
        else
        {
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
        }
        
        return $connection->fetchPairs($select);
    }
}
