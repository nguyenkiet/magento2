<?php
namespace Digiwallet\DAfterpay\Model;

class DAfterpayConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{

    /**
     *
     * @var string
     */
    private $methodCode = \Digiwallet\DAfterpay\Model\DAfterpay::METHOD_CODE;

    /**
     *
     * @var \Digiwallet\DAfterpay\Model\DAfterpay
     */
    private $method;

    /**
     *
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    /**
     *
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     *
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     *            @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Escaper $escaper,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->escaper = $escaper;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->urlBuilder = $urlBuilder;
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                'dafterpay' => [
                    'redirectUrl' => $this->urlBuilder->getUrl('dafterpay/dafterpay/redirect', [
                        '_secure' => true
                    ])
                ]
            ]
        ] : [];
    }
}
