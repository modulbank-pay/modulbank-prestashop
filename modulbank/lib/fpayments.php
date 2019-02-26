<?php

namespace FPayments;

if (!function_exists('mb_str_split')) {
    function mb_str_split($string, $split_length = 1, $encoding = null) {
        if (is_null($encoding)) {
            $encoding = mb_internal_encoding();
        }

        if ($split_length < 1) {
            return false;
        }

        $return_value = array();
        $string_length  = mb_strlen($string, $encoding);
        for ($i = 0; $i < $string_length; $i += $split_length)
        {
            $return_value[] = mb_substr($string, $i, $split_length, $encoding);
        }
        return $return_value;
    }
}

if (!function_exists('stripslashes_gpc')) {
    function stripslashes_gpc(&$value) {
        $value = stripslashes($value);
    }
}

require_once "fpayments_config.php";


class FPaymentsError extends \Exception {}


class FPaymentsForm {
    private $merchant_id;
    private $secret_key;
    private $is_test;
    private $plugininfo;
    private $cmsinfo;

    function __construct(
        $merchant_id,
        $secret_key,
        $is_test,
        $plugininfo = '',
        $cmsinfo = ''
    ) {
        $this->merchant_id = $merchant_id;
        $this->secret_key = $secret_key;
        $this->is_test = (bool) $is_test;
        $this->plugininfo = $plugininfo ?: 'FPayments/PHP v.' . phpversion();
        $this->cmsinfo = $cmsinfo;
    }

    public static function abs($path) {
        return FPaymentsConfig::HOST . $path;
    }

    function get_url() {
        return self::abs('/pay/');
    }

    function get_transaction_info_url() {
        return self::abs('/api/v1/transaction/');
    }

    function get_rebill_url() {
        return self::abs('/api/v1/rebill/');
    }

    function compose(
        $amount,
        $currency,
        $order_id,
        $client_email,
        $client_name,
        $client_phone,
        $success_url,
        $fail_url,
        $cancel_url,
        $callback_url,
        $meta = '',
        $description = '',
        $receipt_contact = '',
        array $receipt_items = null,
        $recurring_frequency = '',
        $recurring_finish_date = ''
    ) {
        if (!$description) {
            $description = "Заказ №$order_id";
        }
        $form = array(
            'testing'               => (int) $this->is_test,
            'merchant'              => $this->merchant_id,
            'unix_timestamp'        => time(),
            'salt'                  => $this->get_salt(32),
            'amount'                => $amount,
            'currency'              => $currency,
            'description'           => $description,
            'order_id'              => $order_id,
            'client_email'          => $client_email,
            'client_name'           => $client_name,
            'client_phone'          => $client_phone,
            'success_url'           => $success_url,
            'fail_url'              => $fail_url,
            'cancel_url'            => $cancel_url,
            'callback_url'          => $callback_url,
            'meta'                  => $meta,
            'sysinfo'               => $this->get_sysinfo(),
            'recurring_frequency'   => $recurring_frequency,
            'recurring_finish_date' => $recurring_finish_date,
        );
        if ($receipt_items) {
            if (!$receipt_contact) {
                throw new FPaymentsError('receipt_contact required');
            }
            $items_sum = $this->get_items_sum($receipt_items);
            $items_arr = array();

            foreach ($receipt_items as $item) {
                $items_arr[] = $item->as_dict();
            }
            $items_sum = round($items_sum, 2);

            if ($items_sum != $amount) {

                throw new FPaymentsError("Amounts mismatch: sum of cart items: ${items_sum}, order amount: ${amount}");
            }
            $form['receipt_contact'] = $receipt_contact;
            $form['receipt_items'] = json_encode($items_arr);
        };
        $form['signature'] = $this->get_signature($form);
        return $form;
    }

    private function get_items_sum($receipt_items)
    {
        $items_sum = 0;
        foreach ($receipt_items as $item) {
            $items_sum += $item->get_sum();
        }

        return round($items_sum, 2);
    }

    private function get_sysinfo() {
        return json_encode(array(
            'language' => 'PHP ' . phpversion(),
            'plugin' => $this->plugininfo,
            'cms' => $this->cmsinfo,
        ));
    }

    function is_signature_correct(array $form) {
        if (!array_key_exists('signature', $form)) {
            return false;
        }
        return $this->get_signature($form) == $form['signature'];
    }

    function is_order_completed(array $form) {
        $is_testing_transaction = ($form['testing'] === '1');
        return ($form['state'] == 'COMPLETE') && ($is_testing_transaction == $this->is_test);
    }

    public static function array_to_hidden_fields(array $form) {
        $result = '';
        foreach ($form as $k => $v) {
            $result .= '<input name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '" type="hidden">';
        }
        return $result;
    }

    function get_signature(array $params, $key = 'signature') {
        $keys = array_keys($params);
        sort($keys);
        $chunks = array();
        foreach ($keys as $k) {
            $v = (string) $params[$k];
            if (($v !== '') && ($k != 'signature')) {
                $chunks[] = $k . '=' . base64_encode($v);
            }
        }

        $sig = $this->double_sha1(implode('&', $chunks));
        //echo $sig.' ';
        return $sig;
    }

    private function double_sha1($data) {
        for ($i = 0; $i < 2; $i++) {
            $data = sha1($this->secret_key . $data);
        }
        return $data;
    }

    private function get_salt($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $result;
    }

    function rebill(
        $amount,
        $currency,
        $order_id,
        $recurrind_tx_id,
        $recurring_token,
        $description = ''
    ){
        if (!$description) {
            $description = "Заказ №$order_id";
        }
        $form = array(
            'testing'               => (int) $this->is_test,
            'merchant'              => $this->merchant_id,
            'unix_timestamp'        => time(),
            'salt'                  => $this->get_salt(32),
            'amount'                => $amount,
            'currency'              => $currency,
            'description'           => $description,
            'order_id'              => $order_id,
            'initial_transaction'   => $recurrind_tx_id,
            'recurring_token'       => $recurring_token,
        );
        $form['signature'] = $this->get_signature($form);
        $paramstr = http_build_query($form);
        $ch = curl_init($this->get_rebill_url());
        curl_setopt($ch, CURLOPT_USERAGENT, $this->plugininfo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramstr);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);
    }

    function getTransactionInfo($transaction_id) {
        $form = array(
            'transaction_id'        => $transaction_id,
            'merchant'              => $this->merchant_id,
            'unix_timestamp'        => time(),
            'salt'                  => $this->get_salt(32),
        );
        $form['signature'] = $this->get_signature($form);
        $paramstr = http_build_query($form);
        $ch = curl_init($this->get_transaction_info_url() . '?' . $paramstr);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->plugininfo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);

        if (curl_error($ch)) {
            error_log("Error while requesting transaction info: ".curl_error($ch));
            return;
        }
        curl_close($ch);

        $data = json_decode($result, true);

        if ($data['status'] != 'ok') {
            return;
        }
        return $data['transaction'];
    }
}


abstract class AbstractFPaymentsCallbackHandler {
    /**
    * @return FPaymentsForm
    */
    abstract protected function get_fpayments_form();
    abstract protected function load_order($order_id);
    abstract protected function get_order_currency($order);
    abstract protected function get_order_amount($order);
    /**
    * @return bool
    */
    abstract protected function is_order_completed($order);
    /**
    * @return bool
    */
    abstract protected function mark_order_as_completed($order, array $data);
    /**
    * @return bool
    */
    abstract protected function mark_order_as_error($order, array $data);

    function show(array $data) {
        if (get_magic_quotes_gpc()) {
           array_walk_recursive($data, 'stripslashes_gpc');
        }
        $error = null;
        $debug_messages = array();
        $ff = $this->get_fpayments_form();

        if (!$ff->is_signature_correct($data)) {
            $error = 'Incorrect "signature"';
        } else if (!($order_id = (int) $data['order_id'])) {
            $error = 'Empty "order_id"';
        } else if (!($order = $this->load_order($order_id))) {
            $error = 'Unknown order_id';
        } else if ($this->get_order_currency($order) != $data['currency']) {
            $error = 'Currency mismatch: "' . $this->get_order_currency($order) . '" != "' . $data['currency'] . '"';
        } else if ($this->get_order_amount($order) != $data['amount']) {
            $error = 'Amount mismatch: "' . $this->get_order_amount($order) . '" != "' . $data['amount'] . '"';
        } else if ($ff->is_order_completed($data)) {
            $debug_messages[] = "info: order completed";
            if ($this->is_order_completed($order)) {
                $debug_messages[] = "order already marked as completed";
            } else if ($this->mark_order_as_completed($order, $data)) {
                $debug_messages[] = "mark order as completed";
            } else {
                $error = "Can't mark order as completed";
            }
        } else {
            $debug_messages[] = "info: order not completed";
            if (!$this->is_order_completed($order)) {
                if ($this->mark_order_as_error($order, $data)) {
                    $debug_messages[] = "mark order as error";
                } else {
                    $error = "Can't mark order as error";
                }
            }
        }

        if ($error) {
            echo "ERROR: $error\n";
        } else {
            echo "OK $order_id\n";
        }
        foreach ($debug_messages as $msg) {
            echo "...$msg\n";
        }
    }
}


class FPaymentsRecieptItem {
    const TAX_NO_NDS = 'none';  # без НДС;
    const TAX_0_NDS = 'vat0';  # НДС по ставке 0%;
    const TAX_10_NDS = 'vat10';  # НДС чека по ставке 10%;
    const TAX_18_NDS = 'vat18';  # НДС чека по ставке 18%
    const TAX_20_NDS = 'vat20';  # НДС чека по ставке 20%
    const TAX_10_110_NDS = 'vat110';  # НДС чека по расчетной ставке 10/110;
    const TAX_18_118_NDS = 'vat118';  # НДС чека по расчетной ставке 18/118.

    private $title;
    private $price;
    private $n;
    private $total;
    private $nds;
    private $sno;
    private $payment_object;
    private $payment_method;

    function __construct($title, $price, $n = 1, $total = null, $nds = null, $sno=null, $payment_object=null, $payment_method=null) {
        $this->title = self::clean_title($title);
        $this->price = round($price, 2);
        $this->n = $n;
        $this->total = $total == null ?  round($this->price * $this->n, 2) : $total;
        $this->nds = $nds ? $nds : self::TAX_NO_NDS;
        $this->sno = $sno;
        $this->payment_object = $payment_object;
        $this->payment_method = $payment_method;
    }

    function as_dict() {
        return array(
            'quantity' => $this->n,
            'price' =>  $this->price,
            'name' => $this->title,
            'sno' => $this->sno,
            'payment_object' => $this->payment_object,
            'payment_method' => $this->payment_method,
            'vat' => $this->nds
        );
    }

    function get_sum() {
        return $this->total;
    }

    function set_total($total) {

        $price = $this->price;
        $n = $this->n;

        $current_total = $this->n * $this->price;
        $diff = $total - $current_total;

        $this->price =  $price + $diff / $n;
    }

    function split_items_to_correct_price($totalRowAmount=null)
    {
        $price = $this->price;
        $n = $this->n;

        $total = $price * $n;

        if($totalRowAmount == null)
            $totalRowAmount = $this->total;

        // ничего менять не надо, все сходится
        if($totalRowAmount == $total)
            return [$this];

        // можно просто изменить цену
        if($n == 1)
        {
            $this->price = round($totalRowAmount,2);
            $this->total = round($totalRowAmount,2);
            return [$this];
        }

        // цена сейчас меньше чем требуется... хз чо делать-то
        if($total < $totalRowAmount)
        {
            return [$this];
        }

        // рассматриваем ситуацию, когда применяем скидку
        // будем считать все в копейках, чтобы удобно округлять

        $roundItemsPrice = round($totalRowAmount * 100 / ($n));
        $roundItemsTotal = $roundItemsPrice * $n;
        $diffItemPrice = round($totalRowAmount * 100 - $roundItemsTotal);


        if(($diffItemPrice + 0) == 0)
        {
            $this->price = round($roundItemsPrice / 100, 2);
            $this->total = round($totalRowAmount, 2);
            return [$this];
        }

        $resultItems = array();
        $roundItemsPrice = round($totalRowAmount * 100 / ($n - 1));
        $roundItemsTotal = round($roundItemsPrice * ($n - 1));

        $resultItems[]= new FPaymentsRecieptItem(
            $this->title,
            $roundItemsPrice / 100,
            $n-1,
            $roundItemsTotal / 100,
            $this->nds,
            $this->sno,
            $this->payment_object,
            $this->payment_method);


            $lastItemPrice =  $totalRowAmount * 100 - $roundItemsTotal;

            $resultItems[] = new FPaymentsRecieptItem($this->title,
                $lastItemPrice / 100,
                1,
                $lastItemPrice / 100,
                $this->nds,
                $this->sno,
                $this->payment_object,
                $this->payment_method);


        return $resultItems;
    }


    private static function clean_title($s, $max_chars=64) {
        $result = '';
        $arr = mb_str_split($s);
        $allowed_chars = mb_str_split('0123456789"(),.:;- йцукенгшщзхъфывапролджэёячсмитьбюqwertyuiopasdfghjklzxcvbnm');
        foreach ($arr as $char) {
            if (mb_strlen($result) >= $max_chars) {
                break;
            }
            if (in_array(mb_strtolower($char), $allowed_chars)) {
                $result .= $char;
            }
        }
        return $result;
    }
}
