<style>
	p.payment_module > a.modulbank {
		background-position: 15px 50%;
		background-repeat: no-repeat;
	}
</style>

<script type="text/javascript" charset="utf-8">
    window.onload = function () {
        var form = document.getElementById('modulbank_form');
        form.submit();
    };
</script>


<form class="hidden" id="modulbank_form" method="post" name="modulbank_form" action="{$action}">
    {foreach from=$form_fields key=field item=value}
		<input name="{$field}" value="{$value}" type="hidden"><br>
    {/foreach}
    <button type="submit">Continue to payment gateway</button>
</form>
