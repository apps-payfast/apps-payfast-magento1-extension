<?php

/**

 */
class Apps_Payfast_Model_Webcheckout extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'payfast_webcheckout';    
    protected $_defaultLocale = 'en';
    protected $_liveUrl = 'https://ipguat.apps.net.pk/Ecommerce/api/Transaction/PostTransaction';
    protected $_liveAccessTokenApi = "https://ipguat.apps.net.pk/Ecommerce/api/Transaction/GetAccessToken?MERCHANT_ID=%s&SECURED_KEY=%s";
    protected $_formBlockType = 'payfast/form';
    protected $_infoBlockType = 'payfast/info';
    protected $_order;

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder() {
        if (!$this->_order) {
            $this->_order = $this->getInfoInstance()->getOrder();
        }
        return $this->_order;
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('payfast/processing/redirect');
    }

    /**
     * Return payment method type string
     *
     * @return string
     */
    public function getPaymentMethodType() {
        return $this->_paymentMethod;
    }

    public function getUrl() {
        return $this->_liveUrl;
    }

    /**
     * prepare params array to send it to gateway page via POST
     *
     * @return array
     */
    public function getFormFields() {
        
        $merchant_id = $this->getConfigData('merchant_id');
        $secured_key = $this->getConfigData('secured_key');
        $merchant_name = $this->getConfigData('merchant_name');
        
        /**
         * Get token first
         */
        $accessToken = $this->getAccessToken($merchant_id, $secured_key);

        $price = number_format($this->getOrder()->getGrandTotal(), 2, '.', '');
        $currency = $this->getOrder()->getOrderCurrencyCode();

        
        $billing = $this->getOrder()->getBillingAddress();

        $basket_id = $this->getOrder()->getRealOrderId();
        $signature = md5($merchant_id . ":" . $secured_key . ":" . $accessToken . ":" . $basket_id);
        
        $params = array(
            'MERCHANT_ID' => $merchant_id,
            'MERCHANT_NAME' => $merchant_name,
            'TOKEN' => $accessToken,
            'PROCCODE' => 00,
            'TXNAMT' => $price,
            'CURRENCY_CODE' => $currency,
            'CUSTOMER_MOBILE_NO' => $billing->getTelephone(),
            'CUSTOMER_EMAIL_ADDRESS' =>  $this->getOrder()->getCustomerEmail(),
            'SIGNATURE' => $signature,
            'VERSION' => 'MAGENTO1.9-APPS-PAYMENT-1.0',
            'TXNDESC' => 'Products purchased from ' . $merchant_name,
            'SUCCESS_URL' => urlencode(Mage::getUrl('payfast/processing/response?')),
            'FAILURE_URL' => urlencode(Mage::getUrl('payfast/processing/response?')),
            'BASKET_ID' => $basket_id,
            'ORDER_DATE' => date('Y-m-d H:i:s', time()),
            'CHECKOUT_URL' => urlencode(Mage::getUrl('payfast/processing/response?')),
        );
   
        return $params;
    }

    protected function _debug($debugData) {
        if (method_exists($this, 'getDebugFlag')) {
            return parent::_debug($debugData);
        }

        if ($this->getConfigData('debug')) {
            Mage::log($debugData, null, 'payment_' . $this->getCode() . '.log', true);
        }
    }

    /**
     * 
     * @param string $merchant_id
     * @param string $secured_key
     * @return string
     */
    protected function getAccessToken($merchant_id, $secured_key) {
        
        /**
         * Fetch Token From Payfast Payment Gateway
         * 
         */
        $token_url = sprintf($this->_liveAccessTokenApi, $merchant_id, $secured_key);
        
        $response = $this->curl_request($token_url);
        $response_decode = json_decode($response);

        if (isset($response_decode->ACCESS_TOKEN)) {
            return $response_decode->ACCESS_TOKEN;
        }
        return;
    }

    /**
     * 
     * @param string $url
     * @return mixed
     */
    protected function curl_request($url) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_USERAGENT,'Magento 1 APPS PayFast Plugin');
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

}
