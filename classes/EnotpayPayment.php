<?php

class EnotpayPayment extends ObjectModel
{
    public $id_enotpay_payment;
    public $id_order;
    public $order_reference;
    public $amount;
    public $currency;
    public $status;
    public $transaction_id;
    public $payment_url;
    public $payload;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'enotpay_payment',
        'primary' => 'id_enotpay_payment',
        'multilang' => false,
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'required' => true],
            'order_reference' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 32],
            'amount' => ['type' => self::TYPE_FLOAT, 'required' => true],
            'currency' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 10],
            'status' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 32],
            'transaction_id' => ['type' => self::TYPE_STRING, 'size' => 64],
            'payment_url' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'],
            'payload' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'],
            'date_add' => ['type' => self::TYPE_DATE],
            'date_upd' => ['type' => self::TYPE_DATE],
        ],
    ];

    public static function createFromGateway(Order $order, array $requestPayload, array $responsePayload, $transactionId = '')
    {
        $currency = null;
        if ((int) $order->id_currency) {
            $currency = Currency::getCurrencyInstance((int) $order->id_currency);
        }

        $payment = new self();
        $payment->id_order = (int) $order->id;
        $payment->order_reference = (string) $order->reference;
        $payment->amount = (float) $order->total_paid;
        $payment->currency = $currency ? (string) $currency->iso_code : '';
        $payment->status = isset($responsePayload['data']['url']) ? 'created' : 'failed';
        $payment->transaction_id = (string) $transactionId;
        $payment->payment_url = isset($responsePayload['data']['url']) ? (string) $responsePayload['data']['url'] : null;
        $payment->payload = (string) json_encode([
            'request' => $requestPayload,
            'response' => $responsePayload,
        ]);

        return (bool) $payment->add();
    }

    public static function updateFromCallback($orderId, array $payload, $status)
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return false;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_enotpay_payment` FROM `' . _DB_PREFIX_ . 'enotpay_payment` WHERE `id_order` = ' . (int) $orderId
            . ' ORDER BY `id_enotpay_payment` DESC'
        );

        if (empty($rows[0]['id_enotpay_payment'])) {
            return false;
        }

        $payment = new self((int) $rows[0]['id_enotpay_payment']);
        $payment->status = (string) $status;
        if (!empty($payload['transaction_id'])) {
            $payment->transaction_id = (string) $payload['transaction_id'];
        }
        $payment->payload = (string) json_encode([
            'callback' => $payload,
        ]);

        return (bool) $payment->update();
    }

    public static function getLatestPaymentUrl($orderId)
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return '';
        }

        $row = Db::getInstance()->getRow(
            'SELECT `payment_url` FROM `' . _DB_PREFIX_ . 'enotpay_payment` WHERE `id_order` = ' . (int) $orderId
            . ' ORDER BY `id_enotpay_payment` DESC'
        );

        if (empty($row['payment_url'])) {
            return '';
        }

        return (string) $row['payment_url'];
    }
}
