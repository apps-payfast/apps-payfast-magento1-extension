<?php
/**

 */

class Apps_Payfast_Block_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payfast/info.phtml');
    }

    public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }

    public function toPdf()
    {
        $this->setTemplate('payfast/pdf/info.phtml');
        return $this->toHtml();
    }
}