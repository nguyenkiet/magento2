<?php
namespace Digiwallet\DCreditcard\Model;

class DCreditcardConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{

    /**
     *
     * @var string
     */
    private $methodCode = \Digiwallet\DCreditcard\Model\DCreditcard::METHOD_CODE;

    /**
     *
     * @var \Digiwallet\DCreditcard\Model\DCreditcard
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
                'dcreditcard' => [
                    'redirectUrl' => $this->urlBuilder->getUrl('dcreditcard/dcreditcard/redirect', [
                        '_secure' => true
                    ])
                ]
            ]
        ] : [];
    }
}
