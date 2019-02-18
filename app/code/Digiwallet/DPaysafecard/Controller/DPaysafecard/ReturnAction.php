<?php
namespace Digiwallet\DPaysafecard\Controller\DPaysafecard;

use Magento\Framework\Controller\ResultFactory;
use Digiwallet\DPaysafecard\Controller\DPaysafeBaseAction;

/**
 * Digiwallet DPaysafecard ReturnAction Controller
 *
 * @method GET
 */
class ReturnAction extends DPaysafeBaseAction
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
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Backend\Model\Locale\Resolver $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Sales\Model\Order $order
     * @param \Digiwallet\DPaysafecard\Model\DPaysafecard $dpaysafecard
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Checkout\Model\Session $checkoutSession
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
        \Digiwallet\DPaysafecard\Model\DPaysafecard $dpaysafecard,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement
    ) {
        parent::__construct($context, $resourceConnection, $localeResolver, $scopeConfig, $transaction,
            $transportBuilder, $order, $dpaysafecard, $transactionRepository, $transactionBuilder, $invoiceSender);
        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        $this->orderManagement = $orderManagement;
    }

    /**
     * When a customer return to website from Digiwallet DPaysafecard gateway.
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
                AND method=" . $db->quote($this->dpaysafecard->getMethodType());
        $result = $db->fetchAll($sql);
        $result = isset($result[0]['paid']) && $result[0]['paid'];
        if (!$result) {
            // Check from Digiwallet API
            $result = parent::checkDigiwalletResult();
        }
        if ($result) {
            $this->_redirect('checkout/onepage/success', ['_secure' => true]);
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
            return $resultRedirect->setPath('checkout/cart');
        }
    }
}
