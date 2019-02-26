<?php

require_once(dirname(__FILE__) . '/lib/fpayments.php');

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;


class Modulbank extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'modulbank';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'АО&nbsp;КБ&nbsp;«Модульбанк»';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->controllers = [
            'validation',
            'return',
            'callback',
        ];

        parent::__construct();

        $this->displayName = $this->l('Оплата через Модульбанк');
        $this->description = $this->l('Модуль приема платежей через Модульбанк');

        if (!Configuration::get('MODULBANK_MERCHANT_ID'))
            $this->warning = $this->l('Пожалуйста, заполните MERCHANT_ID');

        if (!Configuration::get('MODULBANK_SECRET_KEY'))
            $this->warning = $this->l('Пожалуйста, заполните SECRET_KEY');
    }


    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('paymentOptions') ||
            !$this->registerHook('paymentReturn'))
            return false;

        Configuration::updateValue('MODULBANK_MERCHANT_ID', '');
        Configuration::updateValue('MODULBANK_SECRET_KEY', '');
        Configuration::updateValue('MODULBANK_TEST_MODE', 1);

        return true;
    }


    public function uninstall()
    {
        Configuration::deleteByName('MODULBANK_MERCHANT_ID');
        Configuration::deleteByName('MODULBANK_SECRET_KEY');
        Configuration::deleteByName('MODULBANK_TEST_MODE');

        return parent::uninstall();
    }


    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }


    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            Configuration::updateValue('MODULBANK_MERCHANT_ID', Tools::getValue('MODULBANK_MERCHANT_ID'));
            Configuration::updateValue('MODULBANK_SECRET_KEY', Tools::getValue('MODULBANK_SECRET_KEY'));
            Configuration::updateValue('MODULBANK_TEST_MODE', Tools::getValue('MODULBANK_TEST_MODE'));


            Configuration::updateValue('MODULBANK_SNO', Tools::getValue('MODULBANK_SNO'));
            Configuration::updateValue('MODULBANK_PAYMENT_OBJECT', Tools::getValue('MODULBANK_PAYMENT_OBJECT'));
            Configuration::updateValue('MODULBANK_PAYMENT_METHOD', Tools::getValue('MODULBANK_PAYMENT_METHOD'));

            $output .= $this->displayConfirmation($this->l('Настройки сохранены.'));
        }

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Настройки оплаты через Модульбанк'),
                'icon' => 'icon-cogs'
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Идентификатор магазина'),
                    'name' => 'MODULBANK_MERCHANT_ID',
                    'desc' => $this->l('merchant_id из Личного кабинета Модульбанка ("Интернет-эквайринг" -> Ваш магазин -> Интеграция > Параметры подключения)'),
                    'required' => true
                ], [
                    'type' => 'text',
                    'label' => $this->l('Секретный ключ'),
                    'name' => 'MODULBANK_SECRET_KEY',
                    'desc' => $this->l('secret_key из Личного кабинета Модульбанка ("Интернет-эквайринг" -> Ваш магазин -> Интеграция > Параметры подключения)'),
                    'size' => 32,
                    'required' => true
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Тестовый режим'),
                    'name' => 'MODULBANK_TEST_MODE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Включен')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Выключен')
                        ]
                    ]
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Система налогообложения'),
                    'name' => 'MODULBANK_SNO',
                    'options' => [
                        'query' => [
                                [
                                    'id' => 'osn',
                                    'label' => $this->l('Общая')
                                ],
                                [
                                    'id' => 'usn_income',
                                    'label' => $this->l('Упрощенная СН (доходы)')
                                ],
                                [
                                    'id' => 'usn_income_outcome',
                                    'label' => $this->l('Упрощенная СН (доходы минус расходы)')
                                ],
                                [
                                    'id' => 'envd',
                                    'label' => $this->l('Единый налог на вмененный доход')
                                ],
                                [
                                    'id' => 'esn',
                                    'label' => $this->l('Единый сельскохозяйственный налог')
                                ],
                                [
                                    'id' => 'patent',
                                    'label' => $this->l('Патентная СН')
                                ]
,                            ],
                                'id' => 'id',
                                'name' => 'label'
                    ]
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Предмет расчета'),
                    'name' => 'MODULBANK_PAYMENT_OBJECT',
                    'options' => [
                        'query' => [
                            [
                                'id' => 'commodity',
                                'label' => $this->l('Товар')
                            ],
                            [
                                'id' => 'excise',
                                'label' => $this->l('Подакцизный товар')
                            ],
                            [
                                'id' => 'job',
                                'label' => $this->l('Работа')
                            ],
                            [
                                'id' => 'service',
                                'label' => $this->l('Услуга')
                            ],
                            [
                                'id' => 'gambling_bet',
                                'label' => $this->l('Ставка азартной игры')
                            ],
                            [
                                'id' => 'gambling_prize',
                                'label' => $this->l('Выигрыш азартной игры')
                            ],

                            [
                                'id' => 'lottery',
                                'label' => $this->l('Лотерейный билет')
                            ],
                            [
                                'id' => 'lottery_prize',
                                'label' => $this->l('Выигрыш лотереи')
                            ],
                            [
                                'id' => 'intellectual_activity',
                                'label' => $this->l('Предоставление результатов интеллектуальной деятельности')
                            ],
                            [
                                'id' => 'payment',
                                'label' => $this->l('Платеж')
                            ],
                            [
                                'id' => 'agent_commission',
                                'label' => $this->l('Агентское вознаграждение')
                            ],
                            [
                                'id' => 'composite',
                                'label' => $this->l('Составной предмет расчета')
                            ],
                            [
                                'id' => 'another',
                                'label' => $this->l('Другое')
                            ]
                        ],
                        'id' => 'id',
                        'name' => 'label'
                    ]
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Метод платежа'),
                    'name' => 'MODULBANK_PAYMENT_METHOD',
                    'options' => [
                        'query' => [
                            [
                                'id' => 'full_prepayment',
                                'label' => $this->l('Предоплата 100%')
                            ],
                            [
                                'id' => 'prepayment',
                                'label' => $this->l('Предоплата')
                            ],
                            [
                                'id' => 'advance',
                                'label' => $this->l('Аванс')
                            ],
                            [
                                'id' => 'full_payment',
                                'label' => $this->l('Полный расчет')
                            ],
                            [
                                'id' => 'partial_payment',
                                'label' => $this->l('Частичный расчет и кредит')
                            ],
                            [
                                'id' => 'credit',
                                'label' => $this->l('Передача в кредит')
                            ],

                            [
                                'id' => 'credit_payment',
                                'label' => $this->l('Оплата кредита')
                            ]
                        ],
                        'id' => 'id',
                        'name' => 'label'
                    ]
                ]

            ],
            'submit' => [
                'title' => $this->l('Сохранить')
            ],
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->submit_action = 'submit' . $this->name;

        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Сохранить'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token' . Tools::getAdminTokenLite('AdminModules')
            ),
            'back' => array(
                'desc' => $this->l('Назад'),
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules')
            )
        );

        $helper->fields_value['MODULBANK_MERCHANT_ID'] = Configuration::get('MODULBANK_MERCHANT_ID');
        $helper->fields_value['MODULBANK_SECRET_KEY'] = Configuration::get('MODULBANK_SECRET_KEY');
        $helper->fields_value['MODULBANK_TEST_MODE'] = Configuration::get('MODULBANK_TEST_MODE');

        $helper->fields_value['MODULBANK_SNO'] = Configuration::get('MODULBANK_SNO');
        $helper->fields_value['MODULBANK_PAYMENT_OBJECT'] = Configuration::get('MODULBANK_PAYMENT_OBJECT');
        $helper->fields_value['MODULBANK_PAYMENT_METHOD'] = Configuration::get('MODULBANK_PAYMENT_METHOD');

        return $helper->generateForm($fields_form);
    }

    public function initializeFPaymentsForm()
    {
        return new \FPayments\FPaymentsForm(
            Configuration::get('MODULBANK_MERCHANT_ID'),
            Configuration::get('MODULBANK_SECRET_KEY'),
            Configuration::get('MODULBANK_TEST_MODE'),
            null,
            'Prestashop ' . _PS_VERSION_
        );
    }

    public function hookPaymentOptions($params)
    {
        $currency = new Currency($params['cart']->id_currency);
        if ($currency->iso_code != 'RUB') {
            return;
        }

        $options = new PaymentOption();
        $options->setCallToActionText($this->l('Оплатить банковской картой через Модульбанк'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:modulbank/views/templates/front/payment-option.tpl')
            );

        return [
            $options,
        ];
    }

    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active)
            return;

        if (!$order = $params['order'])
            return;

        if ($this->context->cookie->id_customer != $order->id_customer)
            return;

        if (!$order->hasBeenPaid())
            return;

        return $this->display(__FILE__, 'payment-return.tpl');
    }

    public function processPaymentResult($order, $data)
    {
        if ($order->id != $data['order_id']) {
            die('Incorrect order_id');
        }
        if ($data['state'] === 'COMPLETE') {
            $state = _PS_OS_PAYMENT_;///Configuration::get('PS_OS_PAYMENT');
            //$state = Configuration::get('PS_OS_BANKWIRE');
            $message = $this->l("Заказ оплачен");
            $order->setCurrentState(_PS_OS_PREPARATION_);

        } else {
            $state = _PS_OS_CANCELED_;//Configuration::get('PS_OS_ERROR');
            $message = $this->l("При оплате произошла ошибка");
            if ($data['message']) {
                $message .= ": " . $data['message'];
            }
        }


        $order->setCurrentState($state);

    }
}
