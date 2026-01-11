<p>{l s='Thank you for your order.' d='Modules.Enotpay.Shop'}</p>
<p>{l s='Order reference:' d='Modules.Enotpay.Shop'} {$order_reference|escape:'htmlall':'UTF-8'}</p>
{if $order_state}
  <p>{l s='Current status:' d='Modules.Enotpay.Shop'} {$order_state.name|escape:'htmlall':'UTF-8'}</p>
{/if}
