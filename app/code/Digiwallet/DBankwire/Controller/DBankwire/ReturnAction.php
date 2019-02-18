<?php
namespace Digiwallet\DBankwire\Controller\DBankwire;

use Magento\Framework\Controller\ResultFactory;
use Digiwallet\DBankwire\Controller\DBankwireBaseAction;

/**
 * Digiwallet DBankwire ReturnAction Controller
 *
 * @method GET
 */
class ReturnAction extends DBankwireBaseAction
{
    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var \Magento\Framework\App\Action\Context
     */
    private $context;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Backend\Model\Locale\Resolver $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Sales\Model\Order $order
     * @param \Digiwallet\DBankwire\Model\DBankwire $dbankwire
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
        \Magento\Checkout\Model\Session $checkoutSession,
        \Digiwallet\DBankwire\Model\DBankwire $dbankwire,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement
    ) {
            parent::__construct($context, $resourceConnection, $localeResolver, $scopeConfig, $transaction,
                $transportBuilder, $order, $dbankwire, $checkoutSession, $transactionRepository, $transactionBuilder, $invoiceSender);
            $this->context = $context;
            $this->orderManagement = $orderManagement;
    }

    /**
     * When a customer return to website from Digiwallet DBankwire gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $txId = $this->getRequest()->getParam('trxid', null);
        if (!isset($txId)) {
            return $resultRedirect->setPath('checkout/cart');
        }
        
        $orderId = (int) $this->getRequest()->get('order_id');
        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT `paid` FROM ".$tableName." 
                WHERE `order_id` = " . $db->quote($orderId) . "
                AND `digi_txid` = " . $db->quote($txId) . "
                AND method=" . $db->quote($this->dbankwire->getMethodType());
        $result = $db->fetchAll($sql);
        
        if (isset($result[0]['paid']) && $result[0]['paid']) {
            $this->_redirect('checkout/onepage/success', ['_secure' => true]);
        } else {
            if (parent::checkDigiwalletResult($txId, $orderId)) {
                $this->_redirect('checkout/onepage/success', ['_secure' => true, 'paid' => "1"]);
            } else {
                $orderIdentityId = $this->checkoutSession->getLastRealOrder()->getId();
                if(!empty($this->errorMessage)) {
                    $this->context->getMessageManager()->addErrorMessage($this->errorMessage);
                    $this->checkoutSession->getLastRealOrder()->addStatusHistoryComment($this->errorMessage);
                    $this->checkoutSession->getLastRealOrder()->save();
                }
                if(!empty($orderIdentityId)) {
                    $this->orderManagement->cancel($orderIdentityId);
                }
                // Restore latest Cart data
                $this->checkoutSession->restoreQuote();
                $this->_redirect('checkout/cart', ['_secure' => true]);
            }
        }
    }
}
