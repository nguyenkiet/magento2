<?php
namespace Digiwallet\DCreditcard\Controller\DCreditcard;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Digiwallet DCreditcard Redirect Controller
 *
 * @method GET
 */
class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Digiwallet\DCreditcard\Model\DCreditcard
     */
    private $dcreditcard;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Digiwallet\DCreditcard\Model\DCreditcard $dcreditcard
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Digiwallet\DCreditcard\Model\DCreditcard $dcreditcard,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->dcreditcard = $dcreditcard;
    }

    /**
     * When a customer has ordered and redirect to Digiwallet DCreditcard gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $dcreditcardUrl = $this->dcreditcard->setupPayment();
            $this->_redirect($dcreditcardUrl);
            return;
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __($e->getMessage()));
            $this->logger->critical($e);
        }
        $this->checkoutSession->restoreQuote();
        return $resultRedirect->setPath('checkout/cart');
    }
}
