<?php
/**
 * Copyright © 2018 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mollie\Payment\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class TransactionType
 *
 * @package Mollie\Payment\Model\Adminhtml\Source
 */
class Method implements ArrayInterface
{

    /**
     * Options array
     *
     * @var array
     */
    public $options = null;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $this->options = [
                [
                    'value' => '',
                    'label' => __('Payments API')
                ],
                [
                    'value' => 'order',
                    'label' => __('Orders API')
                ]
            ];
        }
        return $this->options;
    }
}
