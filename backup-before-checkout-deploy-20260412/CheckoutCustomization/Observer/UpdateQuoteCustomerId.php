<?php
namespace Mab\CheckoutCustomization\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\QuoteFactory;
use Mab\License\Helper\Data as LicenseHelper;
use Psr\Log\LoggerInterface;

class UpdateQuoteCustomerId implements ObserverInterface
{
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var LicenseHelper
     */
    protected $licenseHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        QuoteFactory $quoteFactory,
        LicenseHelper $licenseHelper,
        LoggerInterface $logger
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->licenseHelper = $licenseHelper;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            if (!$this->licenseHelper->isLicenseActive()) {
                $this->logger->info('Mab_CheckoutCustomization license is not active, skipping quote customer ID update');
                return;
            }

            $order = $observer->getEvent()->getOrder();
            if (!$order) {
                // Try alternative way to get order
                $order = $observer->getOrder();
                if (!$order) {
                    $this->logger->warning('No order found in observer event');
                    return;
                }
            }
            
            $quoteId = $order->getQuoteId();
            $customerId = $order->getCustomerId();
            
            // Log the event for debugging purposes
            $this->logger->info('UpdateQuoteCustomerId observer triggered', [
                'order_id' => $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'quote_id' => $quoteId,
                'order_customer_id' => $customerId
            ]);
            
            if ($quoteId) {
                $quote = $this->quoteFactory->create()->load($quoteId);
                
                // Log quote details for debugging
                $this->logger->info('Quote details', [
                    'quote_id' => $quote->getId(),
                    'quote_customer_id' => $quote->getCustomerId(),
                    'customer_is_guest' => $quote->getData('customer_is_guest')
                ]);
                
                // Check if we need to update the quote
                // Always update if:
                // 1. Quote customer_id is NULL but order has customer_id (guest to registered conversion)
                // 2. Quote customer_id doesn't match order customer_id
                $quoteCustomerId = $quote->getCustomerId();
                $needsUpdate = false;
                
                if ($quote->getId()) {
                    // More robust comparison using strict comparison
                    if ($customerId !== null && ($quoteCustomerId === null || $quoteCustomerId !== $customerId)) {
                        $needsUpdate = true;
                    } elseif ($customerId === null && $quoteCustomerId !== null) {
                        $needsUpdate = true;
                    }
                    // Additional check for guest orders that should have customer_is_guest = 1
                    elseif ($customerId !== null && $quote->getData('customer_is_guest') == 1) {
                        $needsUpdate = true;
                    }
                    // Additional check for cases where customer_id exists on both but customer_is_guest flag is wrong
                    elseif ($customerId !== null && $quoteCustomerId !== null && $customerId === $quoteCustomerId && $quote->getData('customer_is_guest') == 1) {
                        $needsUpdate = true;
                    }
                }
                
                if ($needsUpdate) {
                    $this->logger->info('Updating quote customer ID', [
                        'quote_id' => $quoteId,
                        'old_customer_id' => $quoteCustomerId,
                        'new_customer_id' => $customerId
                    ]);
                    
                    try {
                        $quote->setCustomerId($customerId);
                        // Also set customer_is_guest to 0 if we have a customer_id, otherwise 1
                        $quote->setData('customer_is_guest', $customerId ? 0 : 1);
                        if ($customerId && $order->getCustomerGroupId()) {
                            $quote->setCustomerGroupId($order->getCustomerGroupId());
                        }
                        $quote->getResource()->save($quote);
                        
                        $this->logger->info('Quote customer ID updated successfully', [
                            'quote_id' => $quoteId,
                            'customer_id' => $customerId
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to update quote customer ID', [
                            'quote_id' => $quoteId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        // Try alternative save method
                        try {
                            $resource = $quote->getResource();
                            $connection = $resource->getConnection();
                            $connection->update(
                                $resource->getMainTable(),
                                [
                                    'customer_id' => $customerId,
                                    'customer_is_guest' => $customerId ? 0 : 1,
                                    'customer_group_id' => $customerId && $order->getCustomerGroupId() ? $order->getCustomerGroupId() : null
                                ],
                                ['entity_id = ?' => $quoteId]
                            );
                            $this->logger->info('Quote customer ID updated successfully using direct SQL', [
                                'quote_id' => $quoteId,
                                'customer_id' => $customerId
                            ]);
                        } catch (\Exception $e2) {
                            $this->logger->error('Failed to update quote customer ID using direct SQL as well', [
                                'quote_id' => $quoteId,
                                'error' => $e2->getMessage(),
                                'trace' => $e2->getTraceAsString()
                            ]);
                        }
                    }
                } else {
                    $this->logger->info('Quote customer ID is already correct or no update needed', [
                        'quote_id' => $quoteId,
                        'quote_customer_id' => $quoteCustomerId,
                        'order_customer_id' => $customerId,
                        'customer_is_guest' => $quote->getData('customer_is_guest')
                    ]);
                }
            } else {
                $this->logger->warning('Missing quote ID', [
                    'quote_id' => $quoteId,
                    'customer_id' => $customerId
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception in UpdateQuoteCustomerId observer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}