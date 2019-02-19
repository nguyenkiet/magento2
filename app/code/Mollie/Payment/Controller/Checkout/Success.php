<?php
/**
 * Copyright © 2018 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mollie\Payment\Controller\Checkout;

use Mollie\Payment\Model\Mollie as MollieModel;
use Mollie\Payment\Helper\General as MollieHelper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;

/**
 * Class Success
 *
 * @package Mollie\Payment\Controller\Checkout
 */
class Success extends Action
{

    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;
    /**
     * @var MollieModel
     */
    protected $mollieModel;
    /**
     * @var MollieHelper
     */
    protected $mollieHelper;

    /**
     * Success constructor.
     *
     * @param Context       $context
     * @param Session       $checkoutSession
     * @param PaymentHelper $paymentHelper
     * @param MollieModel   $mollieModel
     * @param MollieHelper  $mollieHelper
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        PaymentHelper $paymentHelper,
        MollieModel $mollieModel,
        MollieHelper $mollieHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->paymentHelper = $paymentHelper;
        $this->mollieModel = $mollieModel;
        $this->mollieHelper = $mollieHelper;
        parent::__construct($context);
    }

    /**
     * Return from mollie after payment
     */
    public function execute()
    {
        if (!$orderId = $this->getRequest()->getParam('order_id')) {
            $this->mollieHelper->addTolog('error', __('Invalid return, missing order id.'));
            $this->messageManager->addNoticeMessage(__('Invalid return from Mollie.'));
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            $paymentToken = $this->getRequest()->getParam('payment_token', null);
            $status = $this->mollieModel->processTransaction($orderId, 'success', $paymentToken);
        } catch (\Exception $e) {
            $this->mollieHelper->addTolog('error', $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('There was an error checking the transaction status.'));
            $this->_redirect('checkout/cart');
            return;
        }

        if (!empty($status['success'])) {
            try {
                $this->checkoutSession->start();
                $this->_redirect('checkout/onepage/success?utm_nooverride=1');
            } catch (\Exception $e) {
                $this->mollieHelper->addTolog('error', $e->getMessage());
                $this->messageManager->addErrorMessage(__('Something went wrong.'));
                $this->_redirect('checkout/cart');
            }
        } else {
            $this->checkoutSession->restoreQuote();
            if (isset($status['status']) && ($status['status'] == 'canceled')) {
                $this->messageManager->addNoticeMessage(__('Payment canceled, please try again.'));
            } elseif (isset($status['status']) && ($status['status'] == 'failed') && isset($status['method'])) {
                $this->messageManager->addErrorMessage(__('Payment of type %1 has been rejected. Decision is based on order and outcome of risk assessment.', $status['method']));
            } else {
                $this->messageManager->addErrorMessage(__('Something went wrong.'));
            }

            $this->_redirect('checkout/cart');
        }
    }
}
