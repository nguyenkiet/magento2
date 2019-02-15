<?php
namespace Digiwallet\DAfterpay\Controller\DAfterpay;

use Magento\Framework\Controller\ResultFactory;
use Digiwallet\DAfterpay\Controller\DAfterpayValidationException;

/**
 * Digiwallet Afterpay Redirect Controller
 *
 * @method GET
 */
class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     *
     * @var \Digiwallet\DAfterpay\Model\DAfterpay
     */
    private $dafterpay;

    /**
     *
     * @var \Magento\Framework\View\Result\PageFactory
     */
    private $resultPageFactory;

    /**
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Digiwallet\DAfterpay\Model\DAfterpay $Afterpay
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     *            @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Digiwallet\DAfterpay\Model\DAfterpay $dafterpay,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->dafterpay = $dafterpay;
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * When a customer has ordered and redirect to Digiwallet Afterpay gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        
        try {
            // Show payment page
            $this->dafterpay->setupPayment();
            if (! empty($this->dafterpay->getRedirectUrl())) {
                // Redirect to host page
                return $this->_redirect($this->dafterpay->getRedirectUrl());
            } elseif (! empty($this->dafterpay->getRejectedMessage())) {
                // Show reject message
                $this->messageManager->addExceptionMessage(new \Exception(), __("The order has been rejected with the reason: " . $this->dafterpay->getRejectedMessage()));
            } else {
                // Order captured. Transfer to return Url to check the payment status
                $this->_redirect($this->dafterpay->getReturnUrl() . '&trxid=' . $this->dafterpay->getTransactionId());
            }
        } catch (DAfterpayValidationException $e) {
            foreach ($e->getErrorItems() as $key => $value) {
                $this->messageManager->addExceptionMessage(new \Exception(), __((is_array($value)) ? implode(", ", $value) : $value));
            }
            $this->logger->critical($e);
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __($e->getMessage()));
            $this->logger->critical($e);
        }
        $this->checkoutSession->restoreQuote();
        return $resultRedirect->setPath('checkout/cart');
    }
}
