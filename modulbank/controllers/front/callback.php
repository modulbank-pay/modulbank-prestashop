<?php

if (!defined('_PS_VERSION_'))
    exit;


class ModulbankCallbackHandler extends \FPayments\AbstractFPaymentsCallbackHandler
{
    private $module;
    private $cart;

    public function __construct(Modulbank $module)
    {
        $this->module = $module;
    }

    protected function get_fpayments_form()
    {
        return $this->module->initializeFPaymentsForm();
    }

    protected function load_order($cart_id)
    {
        if (!isset($this->cart)) {
            $this->cart = new Cart(intval($cart_id));
        }

        return $this->cart;
    }

    protected function get_order_currency($cart)
    {
        $currency = new Currency(intval($cart->id_currency));
        return $currency->iso_code;
    }

    protected function get_order_amount($cart)
    {
        return $cart->getOrderTotal(true, Cart::BOTH);
    }

    protected function is_order_completed($cart)
    {
        $order_id = Order::getIdByCartId($cart->id);
        $order = new Order($order_id);

        return $order->current_state === Configuration::get('PS_OS_PAYMENT');
    }

    protected function mark_order_as_completed($cart, array $data)
    {
        $this->module->processPaymentResult($cart, $data);

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
        $cart_id = Tools::getValue('order_id');
        $cart = new Cart($cart_id);

        $context = Context::getContext();
        $context->currency = new Currency($cart->id_currency);

        $amount = $cart->getOrderTotal(true, Cart::BOTH);

        PrestaShopLogger::addLog(
            'Modulbank callback data: ' . var_export($_POST, true),
            1,
            null,
            'Modulbank payment module',
            (int)$cart->id,
            false
        );

        if (!Validate::isLoadedObject($cart))
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
