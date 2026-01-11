<?php

class EnotpayCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        if (!$this->module->active) {
            $this->sendResponse(403, 'Module disabled');
        }

        if (!$this->isAuthorized()) {
            $this->sendResponse(403, 'Unauthorized');
        }

        $payload = $this->getPayload();

        if (empty($payload['order_id'])) {
            $this->sendResponse(400, 'Missing order_id');
        }

        $orderId = (int) $payload['order_id'];
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            $this->sendResponse(404, 'Order not found');
        }

        if (!$this->validateAmount($order, $payload)) {
            $this->sendResponse(400, 'Amount mismatch');
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        if ($status === '') {
            $this->sendResponse(400, 'Missing status');
        }

        require_once __DIR__ . '/../../classes/EnotpayPayment.php';

        if (in_array($status, ['success', 'paid', 'completed'], true)) {
            $this->setOrderStatus($order, (int) Configuration::get(Enotpay::CONFIG_OS_SUCCESS));
            $this->addOrderPaymentIfNeeded($order, $payload);
        } elseif (in_array($status, ['fail', 'failed', 'canceled', 'cancelled', 'expired'], true)) {
            $this->setOrderStatus($order, (int) Configuration::get('PS_OS_CANCELED'));
        }

        EnotpayPayment::updateFromCallback($orderId, $payload, $status);

        $this->sendResponse(200, 'OK');
    }

    private function isAuthorized()
    {
        $expectedKey = (string) Configuration::get(Enotpay::CONFIG_API_KEY);
        $providedKey = '';

        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $providedKey = (string) $_SERVER['HTTP_X_API_KEY'];
        } elseif (isset($_SERVER['HTTP_X_APIKEY'])) {
            $providedKey = (string) $_SERVER['HTTP_X_APIKEY'];
        } elseif (Tools::getValue('api_key')) {
            $providedKey = (string) Tools::getValue('api_key');
        }

        if ($expectedKey === '') {
            return true;
        }

        if ($providedKey === '') {
            return true;
        }

        return hash_equals($expectedKey, $providedKey);
    }

    private function getPayload()
    {
        $payload = Tools::getAllValues();

        if (!empty($payload)) {
            return $payload;
        }

        $content = file_get_contents('php://input');
        if ($content) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function validateAmount(Order $order, array $payload)
    {
        if (!isset($payload['amount'])) {
            return true;
        }

        $received = (float) $payload['amount'];
        $expected = (float) $order->total_paid;

        return abs($received - $expected) < 0.01;
    }

    private function setOrderStatus(Order $order, $statusId)
    {
        if (!$statusId || (int) $order->current_state === (int) $statusId) {
            return;
        }

        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->changeIdOrderState((int) $statusId, (int) $order->id);
        $history->add();
    }

    private function addOrderPaymentIfNeeded(Order $order, array $payload)
    {
        $transactionId = (string) ($payload['transaction_id'] ?? '');
        $payments = $order->getOrderPaymentCollection();
        $shouldAdd = true;

        if ($transactionId !== '') {
            foreach ($payments as $payment) {
                if ((string) $payment->transaction_id === $transactionId) {
                    $shouldAdd = false;
                    break;
                }
            }
        } elseif (count($payments) > 0) {
            $shouldAdd = false;
        }

        if (!$shouldAdd) {
            return;
        }

        $order->addOrderPayment((float) $order->total_paid, null, $transactionId ?: null);
    }

    private function sendResponse($code, $message)
    {
        http_response_code((int) $code);
        header('Content-Type: text/plain');
        echo $message;
        exit;
    }
}
