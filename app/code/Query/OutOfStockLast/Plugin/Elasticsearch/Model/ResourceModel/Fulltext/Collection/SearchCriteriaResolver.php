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
     * 
     */
    public function afterResolve(
        ElasticsearchResolver $subject, 
        SearchCriteria $result
    ) {
        $result->setSortOrders(array_merge(["is_saleable" => "DESC"], $result->getSortOrders()));

        return $result;
    }
}