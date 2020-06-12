<?php

/**
 */
class Apps_Payfast_Block_Success extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payfast/success.phtml');
    }

    /**
     * Get continue shopping url
     */
    public function getContinueShoppingUrl()
    {
        return Mage::getUrl('*/*/cancel', array('_nosid' => true));
    }

}
