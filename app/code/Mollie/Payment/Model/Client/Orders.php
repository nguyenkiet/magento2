<?php
/**
 * Copyright © 2018 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mollie\Payment\Model\Client;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Mollie\Payment\Helper\General as MollieHelper;
use Mollie\Payment\Model\OrderLines;

/**
 * Class Orders
 *
 * @package Mollie\Payment\Model\Client
 */
class Orders extends AbstractModel
{

    const CHECKOUT_TYPE = 'order';

    /**
     * @var MollieHelper
     */
    private $mollieHelper;
    /**
     * @var OrderLines
     */
    private $orderLines;
    /**
     * @var OrderRepository
     */
    private $orderRepository;
    /**
     * @var InvoiceRepository
     */
    private $invoiceRepository;
    /**
     * @var OrderSender
     */
    private $orderSender;
    /**
     * @var InvoiceSender
     */
    private $invoiceSender;
    /**
     * @var InvoiceService
     */
    private $invoiceService;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var Registry
     */
    private $registry;

    /**
     * Orders constructor.
     *
     * @param OrderLines        $orderLines
     * @param OrderSender       $orderSender
     * @param InvoiceSender     $invoiceSender
     * @param InvoiceService    $invoiceService
     * @param OrderRepository   $orderRepository
     * @param InvoiceRepository $invoiceRepository
     * @param CheckoutSession   $checkoutSession
     * @param ManagerInterface  $messageManager
     * @param Registry          $registry
     * @param MollieHelper      $mollieHelper
     */
    public function __construct(
        OrderLines $orderLines,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        InvoiceService $invoiceService,
        OrderRepository $orderRepository,
        InvoiceRepository $invoiceRepository,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        Registry $registry,
        MollieHelper $mollieHelper
    ) {
        $this->orderLines = $orderLines;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceService = $invoiceService;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->registry = $registry;
        $this->mollieHelper = $mollieHelper;
    }

    /**
     * @param Order                       $order
     * @param \Mollie\Api\MollieApiClient $mollieApi
     *
     * @return string
     * @throws LocalizedException
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startTransaction(Order $order, $mollieApi)
    {
        $storeId = $order->getStoreId();
        $orderId = $order->getEntityId();
        $additionalData = $order->getPayment()->getAdditionalInformation();

        $transactionId = $order->getMollieTransactionId();
        if (!empty($transactionId)) {
            $mollieOrder = $mollieApi->orders->get($transactionId);
            return $mollieOrder->getCheckoutUrl();
        }

        $paymentToken = $this->mollieHelper->getPaymentToken();
        $method = $this->mollieHelper->getMethodCode($order);
        $orderData = [
            'amount'              => $this->mollieHelper->getOrderAmountByOrder($order),
            'orderNumber'         => $order->getIncrementId(),
            'billingAddress'      => $this->getAddressLine($order->getBillingAddress()),
            'consumerDateOfBirth' => null,
            'lines'               => $this->orderLines->getOrderLines($order),
            'redirectUrl'         => $this->mollieHelper->getRedirectUrl($orderId, $paymentToken),
            'webhookUrl'          => $this->mollieHelper->getWebhookUrl(),
            'locale'              => $this->mollieHelper->getLocaleCode($storeId, self::CHECKOUT_TYPE),
            'method'              => $method,
            'metadata'            => [
                'order_id'      => $orderId,
                'store_id'      => $order->getStoreId(),
                'payment_token' => $paymentToken
            ],
        ];

        if (!$order->getIsVirtual() && $order->hasData('shipping_address_id')) {
            $orderData['shippingAddress'] = $this->getAddressLine($order->getShippingAddress());
        }

        if (isset($additionalData['selected_issuer'])) {
            $orderData['payment']['issuer'] = $additionalData['selected_issuer'];
        }

        if ($method == 'banktransfer') {
            $orderData['payment']['dueDate'] = $this->mollieHelper->getBanktransferDueDate($storeId);
        }

        if (isset($additionalData['limited_methods'])) {
            $orderData['method'] = $additionalData['limited_methods'];
        }

        $this->mollieHelper->addTolog('request', $orderData);
        $mollieOrder = $mollieApi->orders->create($orderData);
        $this->processResponse($order, $mollieOrder);

        return $mollieOrder->getCheckoutUrl();
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     *
     * @return array
     */
    public function getAddressLine($address)
    {
        return [
            'organizationName' => $address->getCompany(),
            'title'            => $address->getPrefix(),
            'givenName'        => $address->getFirstname(),
            'familyName'       => $address->getLastname(),
            'email'            => $address->getEmail(),
            'streetAndNumber'  => rtrim(implode(' ', $address->getStreet()), ' '),
            'postalCode'       => $address->getPostcode(),
            'city'             => $address->getCity(),
            'region'           => $address->getRegion(),
            'country'          => $address->getCountryId(),
        ];
    }

    /**
     * @param Order $order
     * @param       $mollieOrder
     *
     * @throws LocalizedException
     */
    public function processResponse(Order $order, $mollieOrder)
    {
        $this->mollieHelper->addTolog('response', $mollieOrder);
        $order->getPayment()->setAdditionalInformation('checkout_url', $mollieOrder->getCheckoutUrl());
        $order->getPayment()->setAdditionalInformation('checkout_type', self::CHECKOUT_TYPE);
        $order->getPayment()->setAdditionalInformation('payment_status', $mollieOrder->status);
        if (isset($mollieOrder->expiresAt)) {
            $order->getPayment()->setAdditionalInformation('expires_at', $mollieOrder->expiresAt);
        }

        $this->orderLines->linkOrderLines($mollieOrder->lines, $order);

        $status = $this->mollieHelper->getStatusPending($order->getStoreId());

        $msg = __('Customer redirected to Mollie');
        if ($order->getPayment()->getMethodInstance()->getCode() == 'mollie_methods_paymentlink') {
            $msg = __('Created Mollie Checkout Url');
        }

        $order->addStatusToHistory($status, $msg, false);
        $order->setMollieTransactionId($mollieOrder->id);
        $this->orderRepository->save($order);
    }

    /**
     * @param Order                       $order
     * @param \Mollie\Api\MollieApiClient $mollieApi
     * @param string                      $type
     * @param null                        $paymentToken
     *
     * @return array
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws LocalizedException
     */
    public function processTransaction(Order $order, $mollieApi, $type = 'webhook', $paymentToken = null)
    {
        $orderId = $order->getId();
        $storeId = $order->getStoreId();
        $transactionId = $order->getMollieTransactionId();
        $mollieOrder = $mollieApi->orders->get($transactionId, ["embed" => "payments"]);
        $this->mollieHelper->addTolog($type, $mollieOrder);
        $status = $mollieOrder->status;

        $this->orderLines->updateOrderLinesByWebhook($mollieOrder->lines, $mollieOrder->isPaid());

        /**
         * Check if last payment was canceled, failed or expired and redirect customer to cart for retry.
         */
        $lastPayment = isset($mollieOrder->_embedded->payments) ? end($mollieOrder->_embedded->payments) : null;
        $lastPaymentStatus = isset($lastPayment) ? $lastPayment->status : null;
        if ($lastPaymentStatus == 'canceled' || $lastPaymentStatus == 'failed' || $lastPaymentStatus == 'expired') {
            $method = $order->getPayment()->getMethodInstance()->getTitle();
            $order->getPayment()->setAdditionalInformation('payment_status', $lastPaymentStatus);
            $this->orderRepository->save($order);
            $this->mollieHelper->registerCancellation($order, $status);
            $msg = ['success' => false, 'status' => $lastPaymentStatus, 'order_id' => $orderId, 'type' => $type, 'method' => $method];
            $this->mollieHelper->addTolog('success', $msg);
            return $msg;
        }

        $refunded = $mollieOrder->amountRefunded !== null ? true : false;
        $order->getPayment()->setAdditionalInformation('payment_status', $status);
        $this->orderRepository->save($order);

        if (($mollieOrder->isPaid() || $mollieOrder->isAuthorized()) && !$refunded) {
            $amount = $mollieOrder->amount->value;
            $currency = $mollieOrder->amount->currency;
            $orderAmount = $this->mollieHelper->getOrderAmountByOrder($order);

            if ($currency != $orderAmount['currency']) {
                $msg = ['success' => false, 'status' => 'paid', 'order_id' => $orderId, 'type' => $type];
                $this->mollieHelper->addTolog('error', __('Currency does not match.'));
                return $msg;
            }

            $payment = $order->getPayment();

            if (!$payment->getIsTransactionClosed() && $type == 'webhook') {
                if ($order->isCanceled()) {
                    $order = $this->mollieHelper->uncancelOrder($order);
                }

                if (abs($amount - $orderAmount['value']) < 0.01) {
                    $payment->setTransactionId($transactionId);
                    $payment->setCurrencyCode($order->getBaseCurrencyCode());

                    if ($mollieOrder->isPaid()) {
                        $payment->setIsTransactionClosed(true);
                        $payment->registerCaptureNotification($order->getBaseGrandTotal(), true);
                    }

                    if ($mollieOrder->isAuthorized()) {
                        $payment->setIsTransactionClosed(false);
                        $payment->registerAuthorizationNotification($order->getBaseGrandTotal(), true);

                        /**
                         * Create pending invoice, as order has not been paid.
                         */
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
                        $invoice->setTransactionId($transactionId);
                        $invoice->register();

                        $this->invoiceRepository->save($invoice);
                    }

                    $order->setState(Order::STATE_PROCESSING);
                    $this->orderRepository->save($order);

                    if ($mollieOrder->amountCaptured !== null) {
                        if ($mollieOrder->amount->currency != $mollieOrder->amountCaptured->currency) {
                            $message = __(
                                'Mollie: Order Amount %1, Captures Amount %2',
                                $mollieOrder->amount->currency . ' ' . $mollieOrder->amount->value,
                                $mollieOrder->amountCaptured->currency . ' ' . $mollieOrder->amountCaptured->value
                            );
                            $order->addStatusHistoryComment($message);
                            $this->orderRepository->save($order);
                        }
                    }
                }

                /** @var Order\Invoice $invoice */
                $invoice = $payment->getCreatedInvoice();
                $sendInvoice = $this->mollieHelper->sendInvoice($storeId);

                if (!$order->getEmailSent()) {
                    $this->orderSender->send($order);
                    $message = __('New order email sent');
                    $order->addStatusHistoryComment($message)->setIsCustomerNotified(true);
                    $this->orderRepository->save($order);
                }

                if ($invoice && !$invoice->getEmailSent() && $sendInvoice) {
                    $this->invoiceSender->send($invoice);
                    $message = __('Notified customer about invoice #%1', $invoice->getIncrementId());
                    $order->addStatusHistoryComment($message)->setIsCustomerNotified(true);
                    $this->orderRepository->save($order);
                }

                if (!$order->getIsVirtual()) {
                    $defaultStatusProcessing = $this->mollieHelper->getStatusProcessing($storeId);
                    if ($defaultStatusProcessing && ($defaultStatusProcessing != $order->getStatus())) {
                        $order->setStatus($defaultStatusProcessing);
                        $this->orderRepository->save($order);
                    }
                }
            }

            $msg = ['success' => true, 'status' => $status, 'order_id' => $orderId, 'type' => $type];
            $this->mollieHelper->addTolog('success', $msg);
            $this->checkCheckoutSession($order, $paymentToken, $mollieOrder, $type);
            return $msg;
        }

        if ($refunded) {
            $msg = ['success' => true, 'status' => 'refunded', 'order_id' => $orderId, 'type' => $type];
            $this->mollieHelper->addTolog('success', $msg);
            return $msg;
        }

        if ($mollieOrder->isCreated()) {
            if ($mollieOrder->method == 'banktransfer' && !$order->getEmailSent()) {
                $this->orderSender->send($order);
                $message = __('New order email sent');
                if (!$statusPending = $this->mollieHelper->getStatusPendingBanktransfer($storeId)) {
                    $statusPending = $order->getStatus();
                }

                $order->setState(Order::STATE_PENDING_PAYMENT);
                $order->addStatusToHistory($statusPending, $message, true);
                $this->orderRepository->save($order);
            }
            $msg = ['success' => true, 'status' => $status, 'order_id' => $orderId, 'type' => $type];
            $this->mollieHelper->addTolog('success', $msg);
            $this->checkCheckoutSession($order, $paymentToken, $mollieOrder, $type);
            return $msg;
        }

        if ($mollieOrder->isCanceled() || $mollieOrder->isExpired()) {
            if ($type == 'webhook') {
                $this->mollieHelper->registerCancellation($order, $status);
            }
            $msg = ['success' => false, 'status' => $status, 'order_id' => $orderId, 'type' => $type];
            $this->mollieHelper->addTolog('success', $msg);
            return $msg;
        }

        if ($mollieOrder->isCompleted()) {
            $msg = ['success' => true, 'status' => $status, 'order_id' => $orderId, 'type' => $type];
            $this->mollieHelper->addTolog('success', $msg);
            return $msg;
        }

        $msg = ['success' => false, 'status' => $status, 'order_id' => $orderId, 'type' => $type];
        $this->mollieHelper->addTolog('success', $msg);
        return $msg;
    }

    /**
     * @param Order $order
     * @param       $paymentToken
     * @param       $paymentData
     * @param       $type
     */
    public function checkCheckoutSession(Order $order, $paymentToken, $paymentData, $type)
    {
        if ($type == 'webhook') {
            return;
        }
        if ($this->checkoutSession->getLastOrderId() != $order->getId()) {
            if ($paymentToken && isset($paymentData->metadata->payment_token)) {
                if ($paymentToken == $paymentData->metadata->payment_token) {
                    $this->checkoutSession->setLastQuoteId($order->getQuoteId())
                        ->setLastSuccessQuoteId($order->getQuoteId())
                        ->setLastOrderId($order->getId())
                        ->setLastRealOrderId($order->getIncrementId());
                }
            }
        }
    }

    /**
     * @param Order $order
     *
     * @return $this
     * @throws LocalizedException
     */
    public function cancelOrder(Order $order)
    {
        $transactionId = $order->getMollieTransactionId();
        if (empty($transactionId)) {
            $msg = ['error' => true, 'msg' => __('Transaction ID not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        $apiKey = $this->mollieHelper->getApiKey($order->getStoreId());
        if (empty($apiKey)) {
            $msg = ['error' => true, 'msg' => __('Api key not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        try {
            $mollieApi = $this->loadMollieApi($apiKey);
            $mollieApi->orders->cancel($transactionId);
        } catch (\Exception $e) {
            $this->mollieHelper->addTolog('error', $e->getMessage());
            throw new LocalizedException(
                __('Mollie: %1', $e->getMessage())
            );
        }

        return $this;
    }

    /**
     * @param $apiKey
     *
     * @return \Mollie\Api\MollieApiClient
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws LocalizedException
     */
    public function loadMollieApi($apiKey)
    {
        if (class_exists('Mollie\Api\MollieApiClient')) {
            $mollieApiClient = new \Mollie\Api\MollieApiClient();
            $mollieApiClient->setApiKey($apiKey);
            $mollieApiClient->addVersionString('Magento/' . $this->mollieHelper->getMagentoVersion());
            $mollieApiClient->addVersionString('MollieMagento2/' . $this->mollieHelper->getExtensionVersion());
            return $mollieApiClient;
        } else {
            throw new LocalizedException(__('Class Mollie\Api\MollieApiClient does not exist'));
        }
    }

    /**
     * @param Order\Shipment $shipment
     * @param Order          $order
     *
     * @return $this
     * @throws LocalizedException
     */
    public function createShipment(Order\Shipment $shipment, Order $order)
    {
        $shipAll = false;
        $orderId = $order->getId();

        $transactionId = $order->getMollieTransactionId();
        if (empty($transactionId)) {
            $msg = ['error' => true, 'msg' => __('Transaction ID not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        $mollieShipmentId = $shipment->getMollieShipmentId();
        if ($mollieShipmentId !== null) {
            $msg = ['error' => true, 'msg' => __('Shipment already pushed to Mollie')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        $apiKey = $this->mollieHelper->getApiKey($order->getStoreId());
        if (empty($apiKey)) {
            $msg = ['error' => true, 'msg' => __('Api key not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        /**
         * If products ordered qty equals shipping qty,
         * complete order can be shipped incl. shipping & discount itemLines.
         */
        if ((int)$order->getTotalQtyOrdered() == (int)$shipment->getTotalQty()) {
            $shipAll = true;
        }

        /**
         * If shipping qty equals open physical products count,
         * all remaining lines can be shipped, incl. shipping & discount itemLines.
         */
        $openForShipmentQty = $this->orderLines->getOpenForShipmentQty($orderId);
        if ((int)$shipment->getTotalQty() == (int)$openForShipmentQty) {
            $shipAll = true;
        }

        try {
            $mollieApi = $this->loadMollieApi($apiKey);
            $mollieOrder = $mollieApi->orders->get($transactionId);
            if ($shipAll) {
                $mollieShipment = $mollieOrder->shipAll();
            } else {
                $orderLines = $this->orderLines->getShipmentOrderLines($shipment);
                $mollieShipment = $mollieOrder->createShipment($orderLines);
            }
            $mollieShipmentId = isset($mollieShipment) ? $mollieShipment->id : 0;
            $shipment->setMollieShipmentId($mollieShipmentId);

            /**
             * Check if Transactions needs to be captures (eg. Klarna methods)
             */
            $payment = $order->getPayment();
            if (!$payment->getIsTransactionClosed()) {
                $payment->registerCaptureNotification($order->getBaseGrandTotal(), true);
                $this->orderRepository->save($order);

                /** @var Order\Invoice $invoice */
                $invoice = $payment->getCreatedInvoice();
                $sendInvoice = $this->mollieHelper->sendInvoice($order->getStoreId());
                if ($invoice && !$invoice->getEmailSent() && $sendInvoice) {
                    $this->invoiceSender->send($invoice);
                    $message = __('Notified customer about invoice #%1', $invoice->getIncrementId());
                    $order->addStatusHistoryComment($message)->setIsCustomerNotified(true);
                    $this->orderRepository->save($order);
                }
            }
        } catch (\Exception $e) {
            $this->mollieHelper->addTolog('error', $e->getMessage());
            throw new LocalizedException(
                __('Mollie API: %1', $e->getMessage())
            );
        }

        return $this;
    }

    /**
     * @param Order\Shipment       $shipment
     * @param Order\Shipment\Track $track
     * @param Order                $order
     *
     * @return Orders
     * @throws LocalizedException
     */
    public function updateShipmentTrack($shipment, $track, $order)
    {
        $transactionId = $order->getMollieTransactionId();
        if (empty($transactionId)) {
            $msg = ['error' => true, 'msg' => __('Transaction ID not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        $shipmentId = $shipment->getMollieShipmentId();
        if (empty($shipmentId)) {
            $msg = ['error' => true, 'msg' => __('Shipment ID not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        $apiKey = $this->mollieHelper->getApiKey($order->getStoreId());
        if (empty($apiKey)) {
            $msg = ['error' => true, 'msg' => __('Api key not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        try {
            $mollieApi = $this->loadMollieApi($apiKey);
            $mollieOrder = $mollieApi->orders->get($transactionId);
            if ($mollieShipment = $mollieOrder->getShipment($shipmentId)) {
                $this->mollieHelper->addTolog(
                    'tracking',
                    sprintf('Added %s shipping for %s', $track->getTitle(), $transactionId)
                );
                $mollieShipment->tracking = [
                    'carrier' => $track->getTitle(),
                    'code'    => $track->getTrackNumber()
                ];
                $mollieShipment->update();
            }
        } catch (\Exception $e) {
            $this->mollieHelper->addTolog('error', $e->getMessage());
        }

        return $this;
    }

    /**
     * @param Order\Creditmemo $creditmemo
     * @param Order            $order
     *
     * @return $this
     * @throws LocalizedException
     */
    public function createOrderRefund(Order\Creditmemo $creditmemo, Order $order)
    {
        $storeId = $order->getStoreId();
        $orderId = $order->getId();

        /**
         * Skip the creation of an online refund if an offline refund is used + add notice msg.
         * Registry set at the Mollie\Payment\Model\Mollie::refund and is set once an online refund is used.
         */
        if (!$this->registry->registry('online_refund')) {
            $this->messageManager->addNoticeMessage(__(
                    'An offline refund has been created, please make sure to also create this 
                    refund on mollie.com/dashboard or use the online refund option.'
                )
            );
            return $this;
        }

        $methodCode = $this->mollieHelper->getMethodCode($order);
        if (!$order->hasShipments() && ($methodCode == 'klarnapaylater' || $methodCode == 'klarnasliceit')) {
            $msg = __('Order can only be refunded after Klara has been captured (after shipment)');
            throw new LocalizedException($msg);
        }

        $transactionId = $order->getMollieTransactionId();
        if (empty($transactionId)) {
            $msg = ['error' => true, 'msg' => __('Transaction ID not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        $apiKey = $this->mollieHelper->getApiKey($storeId);
        if (empty($apiKey)) {
            $msg = ['error' => true, 'msg' => __('Api key not found')];
            $this->mollieHelper->addTolog('error', $msg);
            return $this;
        }

        /**
         * Check for creditmemo adjusment fee's, positive and negative.
         * Throw exception if these are set, as this is not supportef by the orders api.
         */
        if ($creditmemo->getAdjustmentPositive() > 0 || $creditmemo->getAdjustmentNegative() > 0) {
            $msg = __('Creating an online refund with adjustment fee\'s is not supported by Mollie');
            $this->mollieHelper->addTolog('error', $msg);
            throw new LocalizedException($msg);
        }

        /**
         * Check if Shipping Fee needs to be refunded.
         * Throws exception if Shipping Amount of credit does not match Shipping Fee of paid orderLine.
         */
        $addShippingToRefund = null;
        $shippingCostsLine = $this->orderLines->getShippingFeeItemLineOrder($orderId);
        if ($shippingCostsLine->getId() && $shippingCostsLine->getQtyRefunded() == 0) {
            if ($creditmemo->getShippingAmount() > 0) {
                $addShippingToRefund = true;
                if (abs($creditmemo->getShippingInclTax() - $shippingCostsLine->getTotalAmount()) > 0.01) {
                    $msg = __('Can not create online refund, as shipping costs do not match');
                    $this->mollieHelper->addTolog('error', $msg);
                    throw new LocalizedException($msg);
                }
            }
        }

        try {
            $mollieApi = $this->loadMollieApi($apiKey);
            $mollieOrder = $mollieApi->orders->get($transactionId);
            if ($order->getState() == Order::STATE_CLOSED) {
                $mollieOrder->refundAll();
            } else {
                $orderLines = $this->orderLines->getCreditmemoOrderLines($creditmemo, $addShippingToRefund);
                $mollieOrder->refund($orderLines);
            }
        } catch (\Exception $e) {
            $this->mollieHelper->addTolog('error', $e->getMessage());
            throw new LocalizedException(
                __('Mollie API: %1', $e->getMessage())
            );
        }

        return $this;
    }
}
