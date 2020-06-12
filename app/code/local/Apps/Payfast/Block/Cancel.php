<?php


class Apps_Payfast_Block_Cancel extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payfast/cancel.phtml');
    }

    /**
     * Get continue shopping url
     */
    public function getContinueShoppingUrl()
    {
        return Mage::getUrl('*/*/cancel', array('_nosid' => true));
    }
}