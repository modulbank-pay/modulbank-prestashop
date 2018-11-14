<?php

if (!defined('_PS_VERSION_'))
    exit;

class ModulbankFailModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function initContent()
	{
		parent::InitContent();

		$cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart))
			Tools::redirect('index.php?controller=order');

		$this->setTemplate('module:modulbank/views/templates/hook/error.tpl');
	}
}