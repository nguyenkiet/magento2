<?php
namespace Digiwallet\DAfterpay\Controller\DAfterpay;

use Magento\Framework\Controller\ResultFactory;
use Digiwallet\DAfterpay\Controller\DAfterpayBaseAction;
use Digiwallet\DAfterpay\Controller\DAfterpayValidationException;

/**
 * Digiwallet Afterpay ReturnAction Controller
 *
 * @method GET
 */
class ReturnAction extends DAfterpayBaseAction
{
    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    private $orderManagement;

    /**
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Backend\Model\Locale\Resolver $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Sales\Model\Order $order
     * @param \Digiwallet\DAfterpay\Model\DAfterpay $dafterpay
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Backend\Model\Locale\Resolver $localeResolver,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Sales\Model\Order $order,
        \Digiwallet\DAfterpay\Model\DAfterpay $dafterpay,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement
    ) {
        parent::__construct($context, $resourceConnection, $localeResolver, $scopeConfig, $transaction,
            $transportBuilder, $order, $dafterpay, $checkoutSession, $transactionRepository, $transactionBuilder, $invoiceSender);
        $this->orderManagement = $orderManagement;
    }

    /**
     * When a customer return to website from Digiwallet Afterpay gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $txId = $this->getRequest()->getParam('trxid', null);
        if (!isset($txId)) {
            $txId = $this->getRequest()->getParam('invoiceID', null);
        }
        if (! isset($txId)) {
            $this->checkoutSession->restoreQuote();
            return $resultRedirect->setPath('checkout/cart');
        }
        
        $orderId = (int) $this->getRequest()->get('order_id');
        $db = $this->resoureConnection->getConnection();
        $tableName = $this->resoureConnection->getTableName('digiwallet_transaction');
        
        $sql = "SELECT `paid` FROM " . $tableName . " 
                WHERE `order_id` = " . $db->quote($orderId) . "
                AND `digi_txid` = " . $db->quote($txId) . "
                AND method=" . $db->quote($this->dafterpay->getMethodType());
        $result = $db->fetchAll($sql);
        
        if (isset($result[0]['paid']) && $result[0]['paid']) {
            $this->_redirect('checkout/onepage/success', [
                '_secure' => true
            ]);
        } else {
            if (parent::checkDigiwalletResult($txId, $orderId)) {
                $this->_redirect('checkout/onepage/success', [
                    '_secure' => true,
                    'paid' => "1"
                ]);
            } else {
                if (! empty($this->enrichment_url)) {
                    // Redirect to filling more information
                    $this->_redirect($this->enrichment_url);
                } elseif (! empty($this->reject_messages)) {
                    // Show error message (Reject message)
                    $errors = new DAfterpayValidationException(json_decode($this->reject_messages, true));
                    foreach ($errors->getErrorItems() as $message) {
                        $this->messageManager->addExceptionMessage(new \Exception(), __((is_array($message)) ? implode(", ", $message) : $message));
                    }
                }
                try{
                    $orderIdentityId = $this->checkoutSession->getLastRealOrder()->getId();
                    if(!empty($orderIdentityId)) {
                        $this->orderManagement->cancel($orderIdentityId);
                    }
                } catch (\Exception $exception) {
                    // Do nothing
                }
                $this->checkoutSession->restoreQuote();
                $this->_redirect('checkout/cart', [
                    '_secure' => true
                ]);
            }
        }
    }
}
