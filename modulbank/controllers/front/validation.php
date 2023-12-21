<?php

if (!defined('_PS_VERSION_'))
    exit;


class ModulbankValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'modulbank') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $payment_form = $this->getPaymentForm();

        $this->context->smarty->assign([
            'action' => $this->module->initializeFPaymentsForm()->get_url(),
            'form_fields' => $payment_form,
        ]);

        $this->setTemplate('module:modulbank/views/templates/front/payment.tpl');
    }


    private function getPaymentForm()
    {
        $cart = $this->context->cart;
        $cart = new Cart($cart->id);
        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $form = $this->module->initializeFPaymentsForm();

        $amount = $cart->getOrderTotal(true, Cart::BOTH);
        $currency = new Currency(intval($cart->id_currency));

        $customer = new Customer(intval($cart->id_customer));
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $address = new Address(intval($cart->id_address_invoice));
        if (!Validate::isLoadedObject($address))
            Tools::redirect('index.php?controller=order&step=2');


        $this->module->validateOrder($cart->id,
                                       Configuration::get('PS_OS_BANKWIRE'),
                                        $amount,
                                        $this->module->displayName,
                                        NULL,
                                        array(),
                                        (int)$currency->id,
                                        false,
                                        $customer->secure_key);

        $order_id = Order::getIdByCartId($cart->id);


        $cancel_url = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?controller=order&step=3';

        $success_url =  Tools::getHttpHost(true) . __PS_BASE_URI__ . 'order-confirmation?id_order='.$order_id.'&id_cart='.$cart->id.'&key='.$customer->secure_key.'&id_module='.$this->module->id;

        $fail_url = $this->context->link->getModuleLink(
            'modulbank',
            'result',
            [
                'cart_id' => $cart->id,
                'status' => 'failure',
            ]
        );
        $callback_url = $this->context->link->getModuleLink(
            'modulbank',
            'callback',
            [
                'order_id' => $order_id
            ]
        );

        $sno = Configuration::get('MODULBANK_SNO');
        $payment_object = Configuration::get('MODULBANK_PAYMENT_OBJECT');
        $payment_method = Configuration::get('MODULBANK_PAYMENT_METHOD');

        $receipt_items = [];
        $receipt_contact = $this->context->cookie->email;
        $receipt_itemsSum = 0;

        foreach ($cart->getProducts() as $item) {
            $receipt_itemsSum = $receipt_itemsSum + $item['total_wt'];

            $receipt_items[] = new \FPayments\FPaymentsRecieptItem(
                $item['name'],
                $item['price_wt'], // with taxes
                $item['quantity'],
                $item['tax_name'] ? $this->guessTaxType($item['rate']) : 'none',
                $sno,
                $payment_object,
                $payment_method
            );
        }

        $delivery_price = $cart->getPackageShippingCost();
        if ($delivery_price > 0) {
            $carrier = new Carrier($cart->id_carrier, Configuration::get('PS_LANG_DEFAULT'));
            $address_id = (int)$cart->id_address_invoice;
            $address = Address::initialize((int)$address_id);
            $tax_rate = $carrier->getTaxesRate($address);

            $receipt_items[] = new \FPayments\FPaymentsRecieptItem(
                $carrier->name,
                $delivery_price,
                1,
                $this->guessTaxType($tax_rate),
                $sno,
                'service',
                $payment_method
            );
        }

        $itemsTotal = $receipt_itemsSum + $delivery_price;



        return $form->compose(
            $amount,
            $currency->iso_code,
            $order_id,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            $address->phone_mobile,
            $success_url,
            $fail_url,
            $cancel_url,
            $callback_url,
            '',
            '',
            $receipt_contact,
            $receipt_items
        );
    }

    private function correct_items($total, array  $receiptItems)
    {
        $sum = 0;

        $items = $receiptItems;
        $resultItems = array();

        foreach ($items as $item) {
            $sum += $item->get_sum();
        }

        if($sum == $total)
            return $receiptItems;

        $difference = $total - $sum;

        $coeff = 1 + ($difference / $sum);

        foreach ($items as $item_new) {
            $items_amount_new = $item_new->get_sum() * $coeff;
            $resultItems = array_merge($resultItems, $item_new->split_items_to_correct_price($items_amount_new));
        }

        return $resultItems;
    }

    private function guessTaxType($rate)
    {
        switch ($rate) {
            case 0:
                return 'none';
            case 10:
                return 'vat10';
            case 18:
                return 'vat18';
            case 20:
                return 'vat20';  // just in case
        }

        return 'vat0';
    }
}
