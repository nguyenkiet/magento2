<?php
namespace Digiwallet\DIdeal\Controller\DIdeal;

use Magento\Framework\Controller\ResultFactory;
use Digiwallet\DIdeal\Controller\DIdealBaseAction;

/**
 * Digiwallet DIdeal ReturnAction Controller
 *
 * @method GET
 */
class ReturnAction extends DIdealBaseAction
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

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
     * @param \Digiwallet\DIdeal\Model\DIdeal $dideal
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
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
        \Digiwallet\DIdeal\Model\DIdeal $dideal,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) {
            parent::__construct($context, $resourceConnection, $localeResolver, $scopeConfig, $transaction,
                $transportBuilder, $order, $dideal, $transactionRepository, $transactionBuilder, $invoiceSender);
            $this->checkoutSession = $checkoutSession;
            $this->context = $context;
    }

    /**
     * When a customer return to website from Digiwallet DIdeal gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $txId = $this->getRequest()->getParam('trxid', null);
        if (!isset($txId)) {
            $this->checkoutSession->restoreQuote();
            return $resultRedirect->setPath('checkout/cart');
        }
        
        $orderId = (int) $this->getRequest()->get('order_id');
        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT `paid` FROM ".$tableName." 
                WHERE `order_id` = " . $db->quote($orderId) . "
                AND `digi_txid` = " . $db->quote($txId) . "
                AND method=" . $db->quote($this->dideal->getMethodType());
        $result = $db->fetchAll($sql);
        $result = isset($result[0]['paid']) && $result[0]['paid'];
        if (!$result) {
            // Check from Digiwallet API
            $result = parent::checkDigiwalletResult();
        }
        if ($result) {
            $this->_redirect('checkout/onepage/success', ['_secure' => true]);
        } else {
            $this->checkoutSession->restoreQuote();
            if(!empty($this->errorMessage)) {
                $this->context->getMessageManager()->addErrorMessage($this->errorMessage);
            }
            return $resultRedirect->setPath('checkout/cart');
        }
    }
}
