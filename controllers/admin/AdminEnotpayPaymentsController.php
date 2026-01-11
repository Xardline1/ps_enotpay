<?php

require_once __DIR__ . '/../../classes/EnotpayPayment.php';

class AdminEnotpayPaymentsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'enotpay_payment';
        $this->className = 'EnotpayPayment';
        $this->identifier = 'id_enotpay_payment';
        $this->lang = false;
        $this->list_no_link = false;
        $this->_defaultOrderBy = 'date_add';
        $this->_defaultOrderWay = 'DESC';

        parent::__construct();

        $this->addRowAction('edit');
        $this->addRowAction('delete');

        $this->fields_list = [
            'id_enotpay_payment' => [
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ],
            'id_order' => [
                'title' => $this->l('Order ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-sm',
            ],
            'order_reference' => [
                'title' => $this->l('Reference'),
            ],
            'amount' => [
                'title' => $this->l('Amount'),
                'type' => 'price',
                'align' => 'text-right',
            ],
            'currency' => [
                'title' => $this->l('Currency'),
                'align' => 'text-center',
            ],
            'status' => [
                'title' => $this->l('Status'),
            ],
            'transaction_id' => [
                'title' => $this->l('Transaction ID'),
            ],
            'date_add' => [
                'title' => $this->l('Created'),
                'type' => 'datetime',
            ],
            'date_upd' => [
                'title' => $this->l('Updated'),
                'type' => 'datetime',
            ],
        ];

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Add payment'),
                'icon' => 'icon-money',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Order ID'),
                    'name' => 'id_order',
                    'required' => true,
                    'col' => 2,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Order reference'),
                    'name' => 'order_reference',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Amount'),
                    'name' => 'amount',
                    'required' => true,
                    'col' => 2,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Currency'),
                    'name' => 'currency',
                    'required' => true,
                    'col' => 2,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Status'),
                    'name' => 'status',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Transaction ID'),
                    'name' => 'transaction_id',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Payment URL'),
                    'name' => 'payment_url',
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Payload'),
                    'name' => 'payload',
                    'autoload_rte' => false,
                    'rows' => 8,
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();

        $this->page_header_toolbar_btn['back_to_module'] = [
            'href' => $this->getModuleConfigureLink(),
            'desc' => $this->l('Back to module'),
            'icon' => 'process-icon-back',
        ];

        if ($this->display === 'add' || $this->display === 'edit') {
            $this->page_header_toolbar_btn['back_to_list'] = [
                'href' => $this->context->link->getAdminLink('AdminEnotpayPayments'),
                'desc' => $this->l('Back to list'),
                'icon' => 'process-icon-back',
            ];
        } else {
            $this->page_header_toolbar_btn['new'] = [
                'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
                'desc' => $this->l('Add new'),
                'icon' => 'process-icon-new',
            ];
        }
    }

    private function getModuleConfigureLink()
    {
        return $this->context->link->getAdminLink(
            'AdminModules',
            true,
            [],
            ['configure' => $this->module->name]
        );
    }
}
