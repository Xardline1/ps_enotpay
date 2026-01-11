<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Enotpay extends PaymentModule
{
    public const CONFIG_SHOP_ID = 'ENOTPAY_SHOP_ID';
    public const CONFIG_API_KEY = 'ENOTPAY_API_KEY';
    public const CONFIG_API_URL = 'ENOTPAY_API_URL';
    public const CONFIG_TITLE = 'ENOTPAY_TITLE';
    public const CONFIG_DESCRIPTION = 'ENOTPAY_DESCRIPTION';
    public const CONFIG_OS_WAITING = 'ENOTPAY_OS_WAITING';
    public const CONFIG_OS_SUCCESS = 'ENOTPAY_OS_SUCCESS';

    public function __construct()
    {
        $this->name = 'enotpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.3';
        $this->author = 'Xardline, Enotpay';
        $this->controllers = ['redirect', 'validation', 'callback'];
        $this->is_eu_compatible = 1;

        parent::__construct();

        $this->displayName = $this->l('Enotpay');
        $this->description = $this->l('Accept payments via Enotpay.');
        $this->ps_versions_compliancy = ['min' => '8.2.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->installOrderState()
            && $this->installTabs()
            && $this->installSql()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->setDefaultConfig();
    }

    public function uninstall()
    {
        $waitingId = (int) Configuration::get(self::CONFIG_OS_WAITING);
    
        $ok = parent::uninstall()
            && $this->uninstallTabs()
            && $this->uninstallSql()
            && Configuration::deleteByName(self::CONFIG_SHOP_ID)
            && Configuration::deleteByName(self::CONFIG_API_KEY)
            && Configuration::deleteByName(self::CONFIG_API_URL)
            && Configuration::deleteByName(self::CONFIG_TITLE)
            && Configuration::deleteByName(self::CONFIG_DESCRIPTION)
            && Configuration::deleteByName(self::CONFIG_OS_WAITING)
            && Configuration::deleteByName(self::CONFIG_OS_SUCCESS);

        if ($waitingId > 0) {
            $state = new OrderState($waitingId);
            if (Validate::isLoadedObject($state)) {
                $state->deleted = 1;
                $state->save();
            }
        }
    
        return $ok;
    }


    public function getContent()
    {
        if (Tools::isSubmit('submitEnotpay')) {
            $this->postProcess();
        }

        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'payments_link' => $this->context->link->getAdminLink('AdminEnotpayPayments'),
        ]);

        return $this->fetch('module:enotpay/views/templates/admin/configure.tpl')
            . $this->renderForm();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->isConfigured()) {
            return [];
        }

        $this->context->smarty->assign([
            'payment_description' => Configuration::get(self::CONFIG_DESCRIPTION),
        ]);

        $option = new PaymentOption();
        $option->setCallToActionText($this->getTitle())
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', [], true))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/enotpay-logo.png'))
            ->setAdditionalInformation($this->fetch('module:enotpay/views/templates/front/payment_info.tpl'));

        return [$option];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active || empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        $state = new OrderState((int) $order->current_state, (int) $this->context->language->id);

        $this->context->smarty->assign([
            'order_reference' => $order->reference,
            'order_state' => $state->name,
        ]);

        return $this->fetch('module:enotpay/views/templates/front/payment_return.tpl');
    }

    public function hookDisplayOrderDetail($params)
    {
        if (!$this->active || empty($params['order'])) {
            return '';
        }

        $order = $params['order'];
        if (!($order instanceof Order)) {
            $orderId = (int) $params['order'];
            $order = new Order($orderId);
        }

        if (!Validate::isLoadedObject($order)) {
            return '';
        }

        if ((string) $order->module !== $this->name) {
            return '';
        }

        $currentState = (int) $order->current_state;
        if (!in_array($currentState, [14, 17], true)) {
            return '';
        }

        require_once __DIR__ . '/classes/EnotpayPayment.php';

        $paymentUrl = EnotpayPayment::getLatestPaymentUrl((int) $order->id);
        if ($paymentUrl === '') {
            $paymentUrl = $this->context->link->getModuleLink(
                $this->name,
                'orderpay',
                [
                    'order_id' => $order->id,
                    'secure_key' => $order->secure_key,
                ],
                true
            );
        }

        $this->context->smarty->assign([
            'payment_url' => $paymentUrl,
        ]);

        return $this->fetch('module:enotpay/views/templates/hook/order_pay_button.tpl');
    }

    public function getApiClient()
    {
        require_once __DIR__ . '/classes/EnotpayApiClient.php';

        return new EnotpayApiClient(
            (string) Configuration::get(self::CONFIG_API_URL),
            (string) Configuration::get(self::CONFIG_API_KEY)
        );
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (!isset($this->context->controller)) {
            return;
        }

        $controller = (string) $this->context->controller->controller_name;
        $configureModule = Tools::getValue('configure') === $this->name;

        if ($controller === 'AdminEnotpayPayments' || ($controller === 'AdminModules' && $configureModule)) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin/enotpay_admin.css');
        }
    }

    private function setDefaultConfig()
    {
        $successState = (int) Configuration::get('PS_OS_PAYMENT');

        return Configuration::updateValue(self::CONFIG_API_URL, 'https://api.mivion.com/')
            && Configuration::updateValue(self::CONFIG_TITLE, $this->l('Pay by card or instant methods'))
            && Configuration::updateValue(self::CONFIG_DESCRIPTION, $this->l('You will be redirected to Enotpay to complete payment.'))
            && Configuration::updateValue(self::CONFIG_OS_SUCCESS, $successState);
    }

    public function createPaymentUrlForOrder(Order $order)
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $currency = new Currency((int) $order->id_currency);
        $payload = [
            'order_id' => (string) $order->id,
            'amount' => (float) $order->total_paid,
            'currency' => (string) $currency->iso_code,
            'shop_id' => Configuration::get(self::CONFIG_SHOP_ID),
            'comment' => $this->l('Order #') . $order->reference,
            'success_url' => $this->context->link->getModuleLink(
                $this->name,
                'validation',
                ['order_id' => $order->id],
                true
            ),
            'fail_url' => $this->context->link->getModuleLink(
                $this->name,
                'validation',
                ['order_id' => $order->id, 'status' => 'fail'],
                true
            ),
        ];

        $client = $this->getApiClient();
        $response = $client->request('invoice/create', 'post', $payload);

        EnotpayPayment::createFromGateway(
            $order,
            $payload,
            $response,
            (string) ($response['data']['id'] ?? '')
        );

        if (empty($response['data']['url'])) {
            return '';
        }

        return (string) $response['data']['url'];
    }

    private function postProcess()
    {
        $shopId = trim((string) Tools::getValue(self::CONFIG_SHOP_ID));
        $apiKey = trim((string) Tools::getValue(self::CONFIG_API_KEY));
        $apiUrl = trim((string) Tools::getValue(self::CONFIG_API_URL));
        $title = trim((string) Tools::getValue(self::CONFIG_TITLE));
        $description = trim((string) Tools::getValue(self::CONFIG_DESCRIPTION));
        $successState = (int) Tools::getValue(self::CONFIG_OS_SUCCESS);

        if ($shopId === '' || $apiKey === '') {
            $this->context->controller->errors[] = $this->l('Shop ID and API key are required.');
            return;
        }

        if ($apiUrl === '') {
            $apiUrl = 'https://api.mivion.com/';
        }

        if (!preg_match('#^https?://#i', $apiUrl)) {
            $apiUrl = 'https://' . $apiUrl;
        }
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            $this->context->controller->errors[] = $this->l('API URL is invalid.');
            return;
        }

        if ($title === '') {
            $title = (string) Configuration::get(self::CONFIG_TITLE);
        }
        if ($description === '') {
            $description = (string) Configuration::get(self::CONFIG_DESCRIPTION);
        }

        Configuration::updateValue(self::CONFIG_SHOP_ID, $shopId);
        Configuration::updateValue(self::CONFIG_API_KEY, $apiKey);
        Configuration::updateValue(self::CONFIG_API_URL, rtrim($apiUrl, '/') . '/');
        Configuration::updateValue(self::CONFIG_TITLE, $title);
        Configuration::updateValue(self::CONFIG_DESCRIPTION, $description);
        Configuration::updateValue(self::CONFIG_OS_SUCCESS, $successState);

        $this->context->controller->confirmations[] = $this->l('Settings updated.');
    }

    private function renderForm()
    {
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Enotpay settings'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Shop ID'),
                        'name' => self::CONFIG_SHOP_ID,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('API key'),
                        'name' => self::CONFIG_API_KEY,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('API URL'),
                        'name' => self::CONFIG_API_URL,
                        'desc' => $this->l('Base API endpoint, for example https://api.mivion.com/'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Payment title'),
                        'name' => self::CONFIG_TITLE,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Payment description'),
                        'name' => self::CONFIG_DESCRIPTION,
                        'autoload_rte' => false,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Success order status'),
                        'name' => self::CONFIG_OS_SUCCESS,
                        'options' => [
                            'query' => $this->getOrderStateOptions(),
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEnotpay';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value = [
            self::CONFIG_SHOP_ID => Configuration::get(self::CONFIG_SHOP_ID),
            self::CONFIG_API_KEY => Configuration::get(self::CONFIG_API_KEY),
            self::CONFIG_API_URL => Configuration::get(self::CONFIG_API_URL),
            self::CONFIG_TITLE => $this->getTitle(),
            self::CONFIG_DESCRIPTION => Configuration::get(self::CONFIG_DESCRIPTION),
            self::CONFIG_OS_SUCCESS => Configuration::get(self::CONFIG_OS_SUCCESS),
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    private function getOrderStateOptions()
    {
        $states = OrderState::getOrderStates($this->context->language->id);

        return $states ?: [];
    }

    private function installOrderState()
    {
        if ((int) Configuration::get(self::CONFIG_OS_WAITING)) {
            return true;
        }

        $orderState = new OrderState();
        $orderState->color = '#4169E1';
        $orderState->send_email = false;
        $orderState->logable = false;
        $orderState->invoice = false;
        $orderState->unremovable = true;
        $orderState->paid = false;
        $orderState->module_name = $this->name;

        foreach (Language::getLanguages(false) as $language) {
            $orderState->name[$language['id_lang']] = $this->l('Awaiting Enotpay payment');
        }

        if (!$orderState->add()) {
            return false;
        }

        Configuration::updateValue(self::CONFIG_OS_WAITING, (int) $orderState->id);

        return true;
    }

    private function isConfigured()
    {
        return (bool) Configuration::get(self::CONFIG_SHOP_ID)
            && (bool) Configuration::get(self::CONFIG_API_KEY);
    }

    private function getTitle()
    {
        $title = Configuration::get(self::CONFIG_TITLE);

        return $title ?: $this->displayName;
    }

    private function installSql()
    {
        $sql = sprintf(
            "CREATE TABLE IF NOT EXISTS `%senotpay_payment` (
                `id_enotpay_payment` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_order` INT UNSIGNED NOT NULL,
                `order_reference` VARCHAR(32) NOT NULL,
                `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `currency` VARCHAR(10) NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'created',
                `transaction_id` VARCHAR(64) NULL,
                `payment_url` TEXT NULL,
                `payload` LONGTEXT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_enotpay_payment`),
                KEY `idx_enotpay_payment_order` (`id_order`),
                KEY `idx_enotpay_payment_transaction` (`transaction_id`)
            ) ENGINE=%s DEFAULT CHARSET=utf8mb4;",
            _DB_PREFIX_,
            _MYSQL_ENGINE_
        );

        return (bool) Db::getInstance()->execute($sql);
    }

    private function uninstallSql()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'enotpay_payment`';

        return Db::getInstance()->execute($sql);
    }

    private function installTabs()
    {
        if ((int) Tab::getIdFromClassName('AdminEnotpayPayments')) {
            return true;
        }

        $parentId = (int) Tab::getIdFromClassName('AdminParentPayment');
        if ($parentId <= 0) {
            $parentId = (int) Tab::getIdFromClassName('AdminParentOrders');
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminEnotpayPayments';
        $tab->name = [];
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[$language['id_lang']] = $this->l('Enotpay payments');
        }
        $tab->id_parent = $parentId;
        $tab->module = $this->name;

        return (bool) $tab->add();
    }

    private function uninstallTabs()
    {
        $tabId = (int) Tab::getIdFromClassName('AdminEnotpayPayments');
        if ($tabId) {
            $tab = new Tab($tabId);
            return (bool) $tab->delete();
        }

        return true;
    }
}
