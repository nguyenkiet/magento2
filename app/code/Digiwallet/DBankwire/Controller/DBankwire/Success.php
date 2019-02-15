<?php
namespace Digiwallet\DBankwire\Controller\DBankwire;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Digiwallet DBankwire Redirect Controller
 *
 * @method GET
 */
class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resoureConnection;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Digiwallet\DBankwire\Model\DBankwire
     */
    private $dbankwire;
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    private $resultPageFactory;
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Digiwallet\DBankwire\Model\DBankwire $DBankwire
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Digiwallet\DBankwire\Model\DBankwire $dbankwire,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resoureConnection = $resourceConnection;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->dbankwire = $dbankwire;
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * When a customer has ordered and redirect to Digiwallet DBankwire gateway.
     *
     * @return void|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $trans_id = $this->getRequest()->getParam('trxid');
        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $sql = "SELECT * FROM ".$tableName."
                WHERE `digi_txid` = " . $db->quote($trans_id) . "
                AND method=" . $db->quote($this->dbankwire->getMethodType());
        $result = $db->fetchAll($sql);
        if (!count($result) || ((!empty($result[0]['paid'])) ? true : false)) {
            /* @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('checkout/cart');
        }
        // Show result page
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Your order has been placed'));
        return $resultPage;
    }
}
