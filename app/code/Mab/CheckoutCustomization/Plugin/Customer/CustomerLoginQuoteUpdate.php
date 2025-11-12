<?php
/**
 * Plugin to ensure quote customer data is properly updated when customer logs in
 */
namespace Mab\CheckoutCustomization\Plugin\Customer;

use Magento\Customer\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Psr\Log\LoggerInterface;

class CustomerLoginQuoteUpdate
{
    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CustomerLoginQuoteUpdate constructor.
     * @param QuoteRepository $quoteRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        QuoteRepository $quoteRepository,
        LoggerInterface $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
    }

    /**
     * After customer login, ensure any existing guest quote is properly updated
     *
     * @param Session $subject
     * @param Session $result
     * @return Session
     */
    public function afterSetCustomerDataAsLoggedIn(Session $subject, Session $result)
    {
        try {
            $customer = $subject->getCustomer();
            if ($customer && $customer->getId()) {
                // Get the customer's active quote
                $quote = $this->quoteRepository->getActiveForCustomer($customer->getId());
                
                if ($quote && $quote->getId()) {
                    // Check if this was previously a guest quote
                    // More robust comparison using strict comparison
                    if ($quote->getCustomerId() !== $customer->getId() || $quote->getData('customer_is_guest') == 1) {
                        $this->logger->info('Updating guest quote after customer login', [
                            'quote_id' => $quote->getId(),
                            'old_customer_id' => $quote->getCustomerId(),
                            'new_customer_id' => $customer->getId(),
                            'customer_is_guest' => $quote->getData('customer_is_guest')
                        ]);
                        
                        // Update the quote with correct customer data
                        $quote->setCustomerId($customer->getId());
                        $quote->setData('customer_is_guest', 0);
                        $quote->setCustomerGroupId($customer->getGroupId());
                        
                        $this->quoteRepository->save($quote);
                        
                        $this->logger->info('Guest quote updated successfully after customer login', [
                            'quote_id' => $quote->getId(),
                            'customer_id' => $customer->getId()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error updating quote after customer login', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }
}