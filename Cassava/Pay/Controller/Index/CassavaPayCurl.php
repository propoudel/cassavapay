<?php
/**
 * Copyright © 2013-2017 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Cassava\Pay\Controller\Index;


class CassavaPayCurl
{
    /**
     * @var
     */
    protected $billingDetails;

    /**
     * @var
     */
    protected $configDetails;

    /**
     * CassavaPayCurl constructor.
     * @param $billingDetails
     * @param $configDetails
     */
    public function __construct($billingDetails, $configDetails)
    {
        $this->billingDetails = $billingDetails;
        $this->configDetails = $configDetails;
    }

    /**
     * Get token result
     * @return mixed
     */
    public function cassavaPayTokenResult()
    {
        $response = $this->create_send_json_request();
        return $response;
    }

    /**
     * Check billing para, and create accordinatly
     * @param $billingDetails
     * @param $configDetails
     * @return array
     */
    public function checkBillingDetails($billingDetails, $configDetails)
    {
        $param  = [
            'order_id'      => $billingDetails["order_id"],
            'amount' 		=> (isset($billingDetails["amount"]))?  $billingDetails["amount"]  : "",
            'first_name' 	=> (isset($billingDetails["first_name"]))? $billingDetails["first_name"] : "",
            'last_name' 	=> (isset($billingDetails["last_name"]))? $billingDetails["last_name"] : "",
            'phone' 		=> (isset($billingDetails["phone"]))?  $billingDetails["phone"] : "",
            'email' 		=> (isset($billingDetails["email"]))? $billingDetails["email"]  : "",
            'address' 		=> (isset($billingDetails["address"]))? $billingDetails["address"] : "",
            'city' 		    => (isset($billingDetails["city"]))? $billingDetails["city"] : "",
            'zipcode' 		=> (isset($billingDetails["zipcode"]))? $billingDetails["zipcode"] : "",
            'country' 		=> (isset($billingDetails["country"]))? $billingDetails["country"] : "",
        ];

        return $param;
    }

    /**
     * Create and send first json request
     * @return mixed
     */
    public function create_send_json_request()
    {
        $billingDetails = $this->billingDetails;
        $configDetails = $this->configDetails;

        $param = $this->checkBillingDetails($billingDetails, $configDetails);

        $products = $this->generateProducts($billingDetails);

        $array_data = [
            'first_name' => $param['first_name'],
            'last_name' => $param['last_name'],
            'order_number' => $billingDetails["order_id"],
            'house_number' => '477',
            'street' => $param["address"],
            'city' => $param["city"],
            'country' => $param["country"],
            'currency' => 'USD',
            'email_address' => $param["email"],
            'mobile_number' => $param["phone"],
            'redirect_url' => $billingDetails["redirectURL"],
            'account_number' => $billingDetails["order_id"],
            'payment_channel' => 'CARD',
            'dealer_name' => $param['first_name'],
            'vendor_id' => '5d3a2502-104c-43d7-973a-ca624eb7c821',
            'amount' => $param['amount'],
            'order_details' => $products,
        ];

        $response = $this->createCURL(json_encode($array_data));

        return $response;
    }

    /**
     * @param $billingDetails
     * @return array
     */
    public function generateProducts($billingDetails)
    {
        $productsArr = $billingDetails['products'];
        $products = [];

        foreach ($productsArr as $key => $item) {

            $serviceDesc = preg_replace('/&/', 'and', $item);

            $products[] = [
                'item_name' => $serviceDesc,
                'unit_count' => 1
            ];
        }
        return $products;
    }

    /**
     * Generate Curl and return response
     *
     * @param $input
     * @return mixed
     */
    public function createCURL($input)
    {
        $configDetails = $this->configDetails;

        $url = $configDetails['gateway_url'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

}