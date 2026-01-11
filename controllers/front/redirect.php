<?php

class EnotpayRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        if (!$this->module->active) {
            Tools::redirect('index.php?controller=order');
        }

        if (!Configuration::get(Enotpay::CONFIG_SHOP_ID) || !Configuration::get(Enotpay::CONFIG_API_KEY)) {
            Tools::redirect('index.php?controller=order');
        }

        $cart = $this->context->cart;
        if (!$cart->id) {
            Tools::redirect('index.php?controller=order');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order');
        }

        $currency = new Currency($cart->id_currency);
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $waitingStatus = (int) Configuration::get(Enotpay::CONFIG_OS_WAITING);

        $this->module->validateOrder(
            (int) $cart->id,
            $waitingStatus,
            $total,
            $this->module->displayName,
            null,
            [],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $orderId = (int) $this->module->currentOrder;
        $order = new Order($orderId);

        $payload = [
            'order_id' => (string) $orderId,
            'amount' => $total,
            'currency' => $currency->iso_code,
            'shop_id' => Configuration::get(Enotpay::CONFIG_SHOP_ID),
            'comment' => $this->module->l('Order #') . $order->reference,
            'success_url' => $this->context->link->getModuleLink(
                $this->module->name,
                'validation',
                ['order_id' => $orderId],
                true
            ),
            'fail_url' => $this->context->link->getModuleLink(
                $this->module->name,
                'validation',
                ['order_id' => $orderId, 'status' => 'fail'],
                true
            ),
        ];

        require_once __DIR__ . '/../../classes/EnotpayPayment.php';

        $client = $this->module->getApiClient();
        $response = $client->request('invoice/create', 'post', $payload);

        EnotpayPayment::createFromGateway(
            $order,
            $payload,
            $response,
            (string) ($response['data']['id'] ?? '')
        );

        if (isset($response['data']['id'])) {
            $order->addOrderPayment($total, null, (string) $response['data']['id']);
        }

        if (!isset($response['data']['url'])) {
            $this->errors[] = $this->module->l('Failed to initialize payment. Please contact the store administrator.');
            Tools::redirect($this->context->link->getPageLink('order', true, null, 'step=3'));
        }

        Tools::redirect($response['data']['url']);
    }
}
