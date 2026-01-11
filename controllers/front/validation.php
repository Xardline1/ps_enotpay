<?php

class EnotpayValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $orderId = (int) Tools::getValue('order_id');
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            Tools::redirect('index.php');
        }

        $this->context->smarty->assign([
            'order_reference' => $order->reference,
            'status' => Tools::getValue('status', ''),
        ]);

        $this->setTemplate('module:enotpay/views/templates/front/validation.tpl');
    }
}
