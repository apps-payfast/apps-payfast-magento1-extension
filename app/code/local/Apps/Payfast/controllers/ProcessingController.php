<?php

/**

 */
class Apps_Payfast_ProcessingController extends Mage_Core_Controller_Front_Action {

    protected $_successBlockType = 'payfast/success';
    protected $_failureBlockType = 'payfast/failure';
    protected $_cancelBlockType = 'payfast/cancel';
    protected $_order = NULL;
    protected $_paymentInst = NULL;

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * when customer selects Payfast payment method
     */
    public function redirectAction() {
        try {
            $session = $this->_getCheckout();

            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());
            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }

            if ($order->getPayment() && $order->getPayment()->getTransactionId()) {
                Mage::throwException('Order was already processed');
            }

            if ($order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $order->setState(
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $this->_getPendingPaymentStatus(), Mage::helper('payfast')->__('Redirect to PayFast WebCheckout.')
                )->save();
            }

            if ($session->getQuoteId() && $session->getLastSuccessQuoteId()) {
                $session->setPayfastQuoteId($session->getQuoteId());
                $session->setPayfastRealOrderId($session->getLastRealOrderId());
                $session->getQuote()->setIsActive(false)->save();
                $session->clear();
            }


            $this->loadLayout();
            $this->renderLayout();

            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_debug('Error: ' . $e->getMessage());
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Payfast returns POST variables to this action
     */
    public function responseAction() {

        try {
            $request = $this->_checkReturnedPost();
            if ($request['err_code'] == '00' || $request['err_code'] == '000') {
                $this->_processSale($request);
            } elseif ($request['err_code'] == '529') {
                $this->_processCancel($request);

                $this->getResponse()->setBody(
                        $this->getLayout()
                                ->createBlock($this->_cancelBlockType)
                                ->setOrder($this->_order)
                                ->toHtml()
                );
            } else {
                $this->_processCancel($request);

                $this->getResponse()->setBody(
                        $this->getLayout()
                                ->createBlock($this->_cancelBlockType)
                                ->setOrder($this->_order)
                                ->toHtml()
                );
            }
        } catch (Mage_Core_Exception $e) {
            $this->_debug('Payfast response error: ' . $e->getMessage());
            $this->getResponse()->setBody(
                    $this->getLayout()
                            ->createBlock($this->_failureBlockType)
                            ->setOrder($this->_order)
                            ->toHtml()
            );
        }
    }

    /**
     * Payfast return action
     */
    public function successAction() {

        $session = $this->_getCheckout();
        $request = $this->getRequest()->getQuery();
        $order_id = $request['order_id'];
        $session->addSuccess(Mage::helper('payfast')->__('Your payment has been successfull. Order ID: ' . $order_id));
        $this->_redirect('customer/account');
    }

    /**
     * Payfast return action
     */
    public function cancelAction() {
        // set quote to active
        $session = $this->_getCheckout();
        if ($quoteId = $session->getPayfastQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                $session->setQuoteId($quoteId);
            }
        }
        $session->addError(Mage::helper('payfast')->__('Your payment was not successfull.'));
        $this->_redirect('checkout/cart');
    }

    protected function _checkReturnedPost() {

        $request = '';

        if ($this->getRequest()->isPost()) {
            $request = $this->getRequest()->getPost();
        } else {
            $request = $this->getRequest()->getQuery();
        }

        $helper = Mage::helper('core/http');

        if (method_exists($helper, 'getRemoteAddr')) {
            $remoteAddr = $helper->getRemoteAddr();
        } else {
            $requestServer = $this->getRequest()->getServer();
            $remoteAddr = $requestServer['REMOTE_ADDR'];
        }
        if (!preg_match('/\.payfast\.net.pk/', gethostbyaddr($remoteAddr))) {
            //Mage::throwException('Domain can\'t be validated.');
        }


        if (empty($request['basket_id'])) {
            Mage::throwException('Missing or invalid order ID');
        }


        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($request['basket_id']);
        if (!$this->_order->getId()) {
            Mage::throwException('Order not found');
        }

        $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();

        if ($request['Response_Key']) {
            $validate = $this->_valdiateResposeKey($request);
            if (!$validate) {
                Mage::throwException('Critical: Mismatched Response');
            }
        }

        return $request;
    }

    /**
     * Process success response
     */
    protected function _processSale($request) {

        $additionalData[] = 'RDV Message Key: ' . $request['Rdv_Message_Key'];
        $additionalData[] = 'Status Code: ' . $request['err_code'];
        $additionalData[] = 'Status Message: ' . $request['err_msg'];
        $additionalData[] = 'Response Key: ' . $request['Response_Key'];

        $additionalDataString = serialize($additionalData);

        // save transaction information
        $this->_order->getPayment()
                ->setAdditionalData($additionalDataString)
                ->setTransactionId($request['transaction_id'])
                ->setLastTransId($request['transaction_id']);

        $this->_order->addStatusToHistory(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, 'Successful Payment via PayFast');
        $this->_order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, 'Successful Payment via PayFast');

        $this->_order->sendNewOrderEmail();
        $this->_order->setEmailSent(true);
        $this->_order->save();

        $order_id = $this->_order->getId();
        $order_real_id = $this->_order->getRealOrderId();

        $session = $this->_getCheckout();
        $session->addSuccess(Mage::helper('payfast')->__('Your payment has been successfull. Order ID: ' . $order_real_id));
        $this->_redirect('checkout/cart');
    }

    /**
     * Process success response
     */
    protected function _processCancel($request) {

        $additionalData[] = 'RDV Message Key: ' . $request['Rdv_Message_Key'];
        $additionalData[] = 'Status Code: ' . $request['err_code'];
        $additionalData[] = 'Status Message: ' . $request['err_msg'];
        $additionalData[] = 'Response Key: ' . $request['Response_Key'];

        $additionalDataString = serialize($additionalData);

        // save transaction information
        $this->_order->getPayment()
                ->setAdditionalData($additionalDataString)
                ->setTransactionId($request['transaction_id'])
                ->setLastTransId($request['transaction_id']);

        $this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), 'Unsuccessful Payment via PayFast');
        $this->_order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $this->_paymentInst->getConfigData('order_status'), 'Unsuccessful Payment via PayFast');
        $this->_order->save();

        $order_id = $this->_order->getId();
        $order_real_id = $this->_order->getRealOrderId();

        $session = $this->_getCheckout();
    }

    protected function _getPendingPaymentStatus() {
        return Mage::helper('payfast')->getPendingPaymentStatus();
    }

    /**
     * Log debug data to file
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData) {
        if (Mage::getStoreConfigFlag('payment/payfast/debug')) {
            Mage::log($debugData, null, 'payment_payfast.log', true);
        }
    }

    private function _valdiateResposeKey($request) {

        $response_key = $request['Response_Key'];
        $price = number_format($this->_order->getGrandTotal(), 2, '.', '');

        if (intval($price) == $price) {
            $price = intval($price);
        }

        $concatenated = $this->_paymentInst->getConfigData('merchant_id')
                . $request['basket_id']
                . $this->_paymentInst->getConfigData('secret_word')
                . $price
                . $request['err_code'];

        $response_hash = strtoupper(md5($concatenated));
        if ($response_hash == $response_key) {
            return true;
        }

        return false;
    }

}
