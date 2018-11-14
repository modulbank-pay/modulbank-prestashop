{extends file='checkout/checkout.tpl'}
{block name="content"}
	<section id="content">
		<div class="row">
			<div class="col-md-12">
				<section id="checkout-payment-step" class="checkout-step -current -reachable js-current-step">
					<h1 class="text-xs-center">{l s='Ошибка при совершении платежа' mod='modulbank'}</h1>
					<div class="text-xs-center" style="height: 8em; line-height: 8em; vertical-align: middle;">
						{$message}
					</div>
					<div class="text-xs-center">
						<a href="{$link->getPageLink('order-detail', true, NULL, $order_param)}"
						   class="btn btn-default">
							{l s='Перейти к заказу' mod='modulbank'}
						</a>
					</div>
				</section>
			</div>
		</div>
	</section>
{/block}
