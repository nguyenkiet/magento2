<?php
namespace Digiwallet\DSofort\Model;

use Digiwallet\DCore\DigiwalletCore;
use Digiwallet\DCore\DigiwalletRefund;

class DSofort extends \Magento\Payment\Model\Method\AbstractMethod
{
    const METHOD_CODE = 'dsofort';
    const METHOD_TYPE = 'DEB';
    protected $maxAmount = 5000;
    protected $minAmount = 0.1;

    const COUNTRY_DEUTSCHLAND = '49';
    const COUNTRY_OSTERREICH = '43';
    const COUNTRY_DIE_SCHWEIZ = '41';
    const COUNTRY_BELGIE = '32';
    const COUNTRY_ITALIE = '39';
    const COUNTRY_NETHERLANDS = '31';
    
    /**
     * Payment country code
     *
     * @var string
     */
    private $country_codes  = [
        'nl' => self::COUNTRY_NETHERLANDS,
        'it' => self::COUNTRY_ITALIE,
        'be' => self::COUNTRY_BELGIE,
        'de' => self::COUNTRY_DEUTSCHLAND,
        'ch' => self::COUNTRY_DIE_SCHWEIZ,
        'at' => self::COUNTRY_OSTERREICH
    ];
    /**
     * Payment country list
     *
     * @var string
     */
    private $countries  = [
        self::COUNTRY_DEUTSCHLAND => 'Deutschland',
        self::COUNTRY_OSTERREICH => 'Osterreich',
        self::COUNTRY_DIE_SCHWEIZ => 'Die Schweiz',
        self::COUNTRY_BELGIE => 'Belgie',
        self::COUNTRY_ITALIE => 'Italie',
        self::COUNTRY_NETHERLANDS => 'Netherlands'
    ];
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapturePartial = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefund  = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canVoid  = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseForMultishipping  = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canSaveCc = false;

    /**
     * @var \Magento\Framework\Url
     */
    private $urlBuilder;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Sales\Model\Order
     */
    private $order;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $localeResolver;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resoureConnection;

    /**
     * Payment method type
     *
     * @var string
     */
    private $tpMethod  = self::METHOD_TYPE;

    /**
     * Current request parameter
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Url $urlBuilder
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order $orderFactory
     * @param \Magento\Framework\Locale\Resolver $localeResolver
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \Magento\Framework\App\RequestInterface $requestInterface
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Url $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\Locale\Resolver $localeResolver,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Magento\Framework\App\RequestInterface $requestInterface = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->localeResolver = $localeResolver;
        $this->resoureConnection = $resourceConnection;
        $this->request = $requestInterface;
    }

    /**
     * Start payment
     *
     * @param integer $countryId
     *
     * @return string Bank url
     * @throws \Magento\Checkout\Exception
     */
    public function setupPayment($countryId = false)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getOrder();

        if (!$order->getId()) {
            throw new \Magento\Checkout\Exception(__('Cannot load order #' . $order->getRealOrderId()));
        }

        if ($order->getGrandTotal() < $this->minAmount) {
            throw new \Magento\Checkout\Exception(
                __('Het totaalbedrag is lager dan het minimum van ' . $this->minAmount . ' euro voor ' . DSofort::METHOD_CODE)
            );
        }
        
        if ($order->getGrandTotal() > $this->maxAmount) {
            throw new \Magento\Checkout\Exception(
                __('Het totaalbedrag is hoger dan het maximum van ' . $this->maxAmount . ' euro voor ' . DSofort::METHOD_CODE)
            );
        }

        $orderId = $order->getRealOrderId();
        $language = ($this->localeResolver->getLocale() == 'nl_NL') ? "nl" : "en";
        $testMode = false;//(bool) $this->_scopeConfig->getValue('payment/dsofort/testmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
        $digiCore = new DigiwalletCore(
            $this->tpMethod,
            $this->_scopeConfig->getValue('payment/dsofort/rtlo', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            $language,
            $testMode
        );
        $digiCore->setAmount(round($order->getGrandTotal() * 100));
        $digiCore->setDescription("Order #$orderId");
        if (!$countryId || empty($countryId)) {
            // Check order country ID
            if ($order->getBillingAddress() != null) {
                $billing_country_code = strtolower($order->getBillingAddress()->getCountryId());
                if (isset($this->country_codes[$billing_country_code])) {
                    $countryId = $this->country_codes[$billing_country_code];
                }
            }
        }
        $digiCore->setCountryId($countryId);
        $digiCore->setReturnUrl(
            $this->urlBuilder->getUrl('dsofort/dsofort/return', ['_secure' => true, 'order_id' => $orderId])
        );
        $digiCore->setReportUrl(
            $this->urlBuilder->getUrl('dsofort/dsofort/report', ['_secure' => true, 'order_id' => $orderId, 'ajax' => 1])
        );

        // Get consumer's email
        $consumerEmail = $order->getCustomerEmail();
        if(empty($consumerEmail) && $order->getBillingAddress() != null) {
            $consumerEmail = $order->getBillingAddress()->getEmail();
        }
        if(empty($consumerEmail) && $order->getShippingAddress() != null) {
            $consumerEmail = $order->getShippingAddress()->getEmail();
        }
        $digiCore->setConsumerEmail($consumerEmail);

        $bankUrl = @$digiCore->startPayment();

        if (!$bankUrl) {
            throw new \Exception(__("Digiwallet error: {$digiCore->getErrorMessage()}"));
        }

        $db = $this->resoureConnection->getConnection();
        $tableName   = $this->resoureConnection->getTableName('digiwallet_transaction');
        $db->delete($tableName, ['order_id = ?' => $db->quote($orderId)]);
        $db->query("
            INSERT INTO ".$tableName." SET 
            `order_id`=" . $db->quote($orderId).",
            `method`=" . $db->quote($this->tpMethod) . ",
            `digi_txid`=" . $db->quote($digiCore->getTransactionId()));

        return $bankUrl;
    }

    /**
     * Retrieve payment method type
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMethodType()
    {
        if (empty($this->tpMethod)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot retrieve the payment method type'));
        }
        return $this->tpMethod;
    }
    /**
     * Retrieve available Countries
     *
     * @return array
     */
    public function getCountryList()
    {
        if (empty($this->countries)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We cannot retrieve the payment country list')
            );
        }
        return $this->countries;
    }

    /**
     * Retrieve current order
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
        /*
        $orderId = $this->checkoutSession->getLastOrderId();
        return $this->order->load($orderId);
        */
    }
    /**
     * Check refund availability
     *
     * @return bool
     * @api
     */
    public function canRefund()
    {
        return !empty($this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/apitoken', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
    }
    /**
     * Check partial refund availability for invoice
     *
     * @return bool
     * @api
     */
    public function canRefundPartialPerInvoice()
    {
        return !empty($this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/apitoken', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
    }
    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $api_token = $this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/apitoken', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $refundObj = new DigiwalletRefund(self::METHOD_TYPE, $amount, $api_token, $payment, $this->resoureConnection);
        $refundObj->setLanguage(($this->localeResolver->getLocale() == 'nl_NL') ? "nl" : "en");
        $refundObj->setLayoutCode($this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/rtlo', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $refundObj->setTestMode(false);//(bool) $this->_scopeConfig->getValue('payment/' .  self::METHOD_CODE . '/testmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $refundObj->refund();
    }
}
