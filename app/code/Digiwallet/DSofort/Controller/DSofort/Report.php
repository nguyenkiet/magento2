<?php
namespace Digiwallet\DSofort\Controller\DSofort;

use Digiwallet\DSofort\Controller\DSofortBaseAction;

/**
 * Digiwallet DSofort Report Controller
 *
 * @method POST
 */
class Report extends DSofortBaseAction
{
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Backend\Model\Locale\Resolver $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Sales\Model\Order $order
     * @param \Digiwallet\DSofort\Model\DSofort $dsofort
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
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
        \Digiwallet\DSofort\Model\DSofort $dsofort,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) {
        parent::__construct($context, $resourceConnection, $localeResolver, $scopeConfig, $transaction,
            $transportBuilder, $order, $dsofort, $transactionRepository, $transactionBuilder, $invoiceSender);
    }

    /**
     * When a customer return to website from Digiwallet DSofort gateway after a payment is marked as successful.
     * This is an asynchronous call.
     *
     * @return void|string
     */
    public function execute()
    {
        parent::checkDigiwalletResult();
    }
}
