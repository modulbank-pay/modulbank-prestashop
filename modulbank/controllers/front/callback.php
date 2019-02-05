<?php

if (!defined('_PS_VERSION_'))
    exit;


class ModulbankCallbackHandler extends \FPayments\AbstractFPaymentsCallbackHandler
{
    private $module;
    private $order;

    public function __construct(Modulbank $module)
    {
        $this->module = $module;
    }

    protected function get_fpayments_form()
    {
        return $this->module->initializeFPaymentsForm();
    }

    protected function load_order($order_id)
    {
        if (!isset($this->order)) {
            $this->order = new Order(intval($order_id));
        }

        return $this->order;
    }

    protected function get_order_currency($order)
    {
        $currency = new Currency(intval($order->id_currency));
        return $currency->iso_code;
    }

    protected function get_order_amount($order)
    {
        return $order->total_paid;
    }

    protected function is_order_completed($order)
    {
        return $order->current_state === _PS_OS_PAYMENT_; //Configuration::get('PS_OS_PAYMENT');
    }

    protected function mark_order_as_completed($order, array $data)
    {
        $this->module->processPaymentResult($order, $data);

        return true;
    }

    protected function mark_order_as_error($order, array $data)
    {
        return true;
    }
}


class ModulbankCallbackModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $content_only = false;

    public function __construct($response = array()) {
        parent::__construct($response);
        $this->display_header = false;
        $this->display_header_javascript = false;
        $this->display_footer = false;
        $this->contentOnly = true;

    }


    public function postProcess()
    {
        PrestaShopLogger::addLog(
            'Modulbank callback data: ' . var_export($_POST, true),
            1,
            null,
            'Modulbank payment module',
            null,
            false
        );

        $order_id = Tools::getValue('order_id');
        $order = new Order(intval($order_id));

        $context = Context::getContext();
        $context->currency = new Currency($order->id_currency);

        PrestaShopLogger::addLog(
            'Modulbank callback data: ' . var_export($_POST, true),
            1,
            null,
            'Modulbank payment module',
            (int)$order_id,
            false
        );

        if (!Validate::isLoadedObject($order))
            die('Error to load cart');

        $modulbankModule = Module::getInstanceByName('modulbank');

        $handler = new ModulbankCallbackHandler($modulbankModule);
        echo $handler->show($_POST);
    }

    public function display()
    {
        return;
    }
}
