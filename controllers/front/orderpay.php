<?php

class EnotpayOrderpayModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        if (!$this->module->active) {
            Tools::redirect('index.php?controller=history');
        }

        $orderId = (int) Tools::getValue('order_id');
        if ($orderId <= 0) {
            Tools::redirect('index.php?controller=history');
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order) || (string) $order->module !== $this->module->name) {
            Tools::redirect('index.php?controller=history');
        }

        $secureKey = (string) Tools::getValue('secure_key');
        if ($secureKey === '' || $secureKey !== (string) $order->secure_key) {
            Tools::redirect('index.php?controller=history');
        }

        $currentState = (int) $order->current_state;
        if (!in_array($currentState, [14, 17], true)) {
            Tools::redirect('index.php?controller=order-detail&id_order=' . (int) $order->id);
        }

        $paymentUrl = $this->module->createPaymentUrlForOrder($order);
        if ($paymentUrl === '') {
            $this->errors[] = $this->module->l('Failed to initialize payment. Please contact the store administrator.');
            Tools::redirect('index.php?controller=order-detail&id_order=' . (int) $order->id);
        }

        Tools::redirect($paymentUrl);
    }
}
