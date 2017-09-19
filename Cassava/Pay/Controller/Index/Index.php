<?php
/**
 * Copyright © 2013-2017 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Cassava\Pay\Controller\Index;

use Magento\Framework\UrlInterface;
use Cassava\Pay\Controller\Index\CassavaPayCurl;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Cassava\Pay\Model\Payment
     */
    protected $_paymentPlugin;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var
     */
    protected $_redirect;

    /**
     * @var
     */
    protected $_orderId;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $_orderManagement;

    /**
     * @var UrlInterface
     */
    protected $_url;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Cassava\Pay\Model\Payment $paymentPlugin,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $session,
        \Magento\Sales\Model\Order $order,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement
    )
    {
        $this->_paymentPlugin = $paymentPlugin;
        $this->_scopeConfig = $scopeConfig;
        $this->_session = $session;
        $this->_order = $order;
        $this->_storeManager = $storeManager;
        $this->_orderManagement = $orderManagement;
        $this->messageManager = $context->getMessageManager();
        $this->_url = $context->getUrl();
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Psr\Log\LoggerInterface::class)->debug($_POST);
        }

        //check if isset success from cassava gateway
        $success = filter_input(INPUT_GET, 'success');
        $cancel = filter_input(INPUT_GET, 'cancel');

        if (isset($success) && !empty($success)) {

            $orderId = $success;
            $transactionToken = filter_input(INPUT_GET, 'TransactionToken');

            $this->verifyTokenResponse($transactionToken, $orderId);
        } //check if isset cancel from cassava gateway
        elseif (isset($cancel) && !empty($cancel)) {
            $orderId = $cancel;
            $errorMessage = _('Payment canceled by customer');
            $this->restoreOrderToCart($errorMessage, $orderId);
        } else {
            /** @var \Magento\Checkout\Model\Session $session */
            $orderId = $this->_session->getLastRealOrderId();

            if (!isset($orderId) || !$orderId) {

                $message = 'Invalid order ID, please try again later';
                /** @var  \Magento\Framework\Message\ManagerInterface $messageManager */
                $this->messageManager->addError($message);
                return $this->_redirect('checkout/cart');
            }


            $comment = 'Payment has not been processed yet';

            $this->setCommentToOrder($orderId, $comment);


            /** @var  \Magento\Sales\Api\OrderManagementInterface $orderManagement */
            $this->_orderManagement->hold($orderId); //cancel the order

            $this->_orderId = $orderId;

            $billingDetails = $this->getBillingDetailsByOrderId($orderId);
            $configDetails = $this->getPaymentConfig();


            /** Set new cassavaPayCurl object */
            $cassavaPayCurl = new CassavaPayCurl($billingDetails, $configDetails);
            $response = $cassavaPayCurl->cassavaPayTokenResult();

            $this->checkCassavaResponse($response);
        }

    }

    public function setCommentToOrder($orderId, $comment)
    {
        $order = $this->_order->load($orderId);
        $order->addStatusHistoryComment($comment);
        $order->save();
    }

    public function verifyTokenResponse($transactionToken, $orderId)
    {
        if (!isset($transactionToken)) {
            $errorMessage = _('Transaction Token error, please contact support center');
            $this->restoreOrderToCart($errorMessage, $orderId);
        }

        /** get verify token response from cassava */
        $response = $this->verifyToken($transactionToken);
        if ($response) {

            if ($response->Result[0] == '000') {

                $this->_orderManagement->unHold($orderId);
                $comment = 'Payment has been processed successfully';
                $this->setCommentToOrder($orderId, $comment);
                return $this->_redirect('checkout/onepage/success');
            } else {

                $errorCode = $response->Result[0];
                $errorDesc = $response->ResultExplanation[0];
                $errorMessage = _('Payment Failed: ' . $errorCode . ', ' . $errorDesc);
                $this->restoreOrderToCart($errorMessage, $orderId);
            }
        }
    }

    /**
     * Verify paymnet token from Cassava
     */
    public function verifyToken($transactionToken)
    {
        $configDetails = $this->getPaymentConfig();

        $inputXml = '<?xml version="1.0" encoding="utf-8"?>
					<API3G>
					  <CompanyToken>' . $configDetails['company_token'] . '</CompanyToken>
					  <Request>verifyToken</Request>
					  <TransactionToken>' . $transactionToken . '</TransactionToken>
					</API3G>';

        $url = $configDetails['gateway_url'];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $inputXml);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response !== FALSE) {
            /** convert the XML result into array */
            $xml = simplexml_load_string($response);
            return $xml;
        }
        return false;
    }

    /**
     * Check Cassava pay response for the first request
     */
    public function checkCassavaResponse($response)
    {
        if ($response === FALSE) {
            $errorMessage = _('Payment error: Unable to connect to the payment gateway, please try again later');
            $this->restoreOrderToCart($errorMessage, $this->_orderId);
        } else {
            $this->getDataResponse($response);
        }
    }

    /**
     * Get and check first data response
     */
    public function getDataResponse($response)
    {
        $url = trim($response, '"');
        header("Location: $url", true, 301);
        exit();
        //return $this->_redirect($url);
    }

    /**
     * Restore quote and cancel the order
     *
     */
    public function restoreOrderToCart($errorMessage, $orderId)
    {

        /** @var \Magento\Sales\Api\OrderManagementInterface $orderManagement */
        $this->_orderManagement->unHold($orderId);

        $this->_orderManagement->cancel($orderId);

        /** add msg to cancel */
        $this->setCommentToOrder($orderId, $errorMessage);

        /** @var \Magento\Checkout\Model\Session $session */
        $this->_session->restoreQuote();

        /** show error message on checkout/cart */
        $this->messageManager->addError($errorMessage);

        /** and redirect to chechout /cart*/
        return $this->_redirect('checkout/cart');
    }

    /**
     * Get Billing Details By Order Id
     * @return array $param
     */
    public function getBillingDetailsByOrderId($orderId)
    {
        /** @var Magento\Sales\Model\Order $order */
        $order_information = $this->_order->loadByIncrementId($orderId);
        $billingDetails = $order_information->getBillingAddress();
        $ordered_items = $order_information->getAllItems();

        /** New products array */
        $productsArr = [];

        foreach ($ordered_items as $key => $item) {
            /** Product name */
            $productsArr[$key] = $item->getName();
        }

        $param = [
            'order_id' => $orderId,
            'amount' => number_format($order_information->getGrandTotal(), 2, '.', ''),
            'currency' => $this->_storeManager->getStore()->getCurrentCurrency()->getCode(),
            'first_name' => $billingDetails->getFirstName(),
            'last_name' => $billingDetails->getLastname(),
            'email' => $billingDetails->getEmail(),
            'phone' => $billingDetails->getTelephone(),
            'address' => $billingDetails->getStreetLine(1),
            'city' => $billingDetails->getCity(),
            'zipcode' => $billingDetails->getPostcode(),
            'country' => $billingDetails->getCountryId(),
            'redirectURL' => $this->_url->getUrl('cassava/index/index?success=' . $orderId),
            'backURL' => $this->_url->getUrl('cassava/index/index?cancel=' . $orderId),
            'products' => $productsArr
        ];

        return $param;
    }

    /**
     * Get configuration values (Store -> Sales -> Payment Method ->CassavaPayModule)
     * @return array $paramArr
     */
    public function getPaymentConfig()
    {
        /** get types of configuration */
        $param = $this->configArr();
        /** create new array */
        $paramArr = [];

        foreach ($param as $single_param) {
            /** get config values */
            $paramArr[$single_param] = $this->_scopeConfig->getValue('payment/cassava_pay/' . $single_param, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }

        return $paramArr;
    }

    /**
     * Set Configuration Array
     * @return array $param
     */
    public function configArr()
    {
        $param = ['active', 'company_token', 'gateway_url', 'return_url'];
        return $param;
    }
}