<?php
/**

 */


class Apps_Payfast_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payfast/form.phtml');
    }

    protected function _getConfig()
    {
        return Mage::getSingleton('payfast/config');
    }
}