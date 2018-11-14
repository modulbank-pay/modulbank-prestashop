<?php

if (!defined('_PS_VERSION_'))
    exit;


class ModulbankResultModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $module;

    private function redirectToNextStep($order)
    {
        if ($order->hasBeenPaid()) {
            $customer = new Customer((int)$order->id_customer);
            Tools::redirectLink(__PS_BASE_URI__ . 'order-confirmation.php?key=' . $customer->secure_key . '&id_cart=' . (int)$order->id_cart . '&id_module=' . (int)$this->module->id . '&id_order=' . (int)$order->id);
        } else {
            Tools::redirectLink(__PS_BASE_URI__ . 'history');
        }
    }

    private function showError($message, $order_id = '')
    {
        $this->context->smarty->assign([
            'message' => $message,
            'order_param' => "id_order=$order_id"  // для ссылки на детали заказа
        ]);
        $this->setTemplate('module:modulbank/views/templates/front/error.tpl');
    }

    public function initContent()
    {
        parent::initContent();

        $modulbank = Module::getInstanceByName('modulbank');

        $cart_id = Tools::getValue('cart_id');
        $order_id = Order::getIdByCartId($cart_id);
        $order = new Order($order_id);
        $transaction_id = Tools::getValue('transaction_id');

        // Если платеж уже проведен (создан заказ на базе корзины), делаем редирект на страницу статуса заказа
        if (Validate::isLoadedObject($order)) {
            return $this->redirectToNextStep($order);
        }

        // Иначе - загружаем корзину, запрашиваем состояние транзакции и проводим платеж
        $cart = new Cart($cart_id);

        if (!Validate::isLoadedObject($cart)) {
            return $this->showError($this->l("Корзина не найдена", $order_id));
        }

        if (!$transaction_id) {
            Tools::redirectLink(__PS_BASE_URI__ . 'cart&action=show');
        }

        $transaction_info = $modulbank->initializeFPaymentsForm()->getTransactionInfo($transaction_id);

        if (!is_array($transaction_info)) {
            return $this->showError($this->l("Ошибка при запросе статуса транзакции", $order_id));
        }

        $modulbank->processPaymentResult($cart, $transaction_info);

        // Делаем еще одну попытку загрузить заказ и делаем редирект на страницу статуса заказа
        $order_id = Order::getIdByCartId($cart_id);
        $order = new Order($order_id);

        return $this->redirectToNextStep($order);
    }
}