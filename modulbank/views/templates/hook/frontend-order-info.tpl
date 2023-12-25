<div class="box modulbank-payment-box">
	<form id="modulbank_form" method="post" name="modulbank_form" action="{$action}">
    {foreach from=$form_fields key=field item=value}
		<input name="{$field}" value="{$value}" type="hidden">
    {/foreach}
    <button type="submit" class="btn btn-primary modulbank-pay-button">Оплатить заказ</button>
</form>
</div>