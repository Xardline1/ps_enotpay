{if $status === 'fail'}
  <p>{l s='Payment was not completed. Please try again or contact support.' d='Modules.Enotpay.Shop'}</p>
{else}
  <p>{l s='We are processing your payment. You can track the order status in your account.' d='Modules.Enotpay.Shop'}</p>
{/if}
<p>{l s='Order reference:' d='Modules.Enotpay.Shop'} {$order_reference|escape:'htmlall':'UTF-8'}</p>
