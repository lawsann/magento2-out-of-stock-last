<?php

namespace Query\OutOfStockLast\Plugin\Elasticsearch\Model\ResourceModel\Fulltext\Collection;

use Magento\Elasticsearch\Model\ResourceModel\Fulltext\Collection\SearchCriteriaResolver as ElasticsearchResolver;
use Magento\Framework\Api\Search\SearchCriteria;
use Psr\Log\LoggerInterface;

class SearchCriteriaResolver
{
    /**
     * LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Inserts is_in_stock on top of
     * sort order attributes
     *
     * @param ElasticsearchResolver $subject
     * @param SearchCriteria $result
     * @return array
     */
    public function afterResolve(
        ElasticsearchResolver $subject, 
        SearchCriteria $result
    ) {
        $result->setSortOrders(array_merge(["qoosl_is_in_stock" => "DESC"], $result->getSortOrders()));

        return $result;
    }
}
