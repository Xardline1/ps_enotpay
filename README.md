# Enotpay Payment Module for PrestaShop

[🇷🇺 Русская версия](README.ru.md)

Custom payment module for **PrestaShop 8.2.0+**, providing full integration with the **Enotpay** payment system via API.

The module integrates seamlessly into the PrestaShop checkout process and allows customers to retry payment from the order page if the initial attempt failed.

---

## 🚀 Features

- 🔌 Enotpay payment gateway API integration
- 💳 Card and instant payment methods
- 🛒 Checkout payment option
- 🔁 **Retry payment from order page**
- 🧾 Orders created with "Awaiting payment" status
- 🔄 Automatic order status updates
- 📊 Payment history in admin panel
- ⚙️ Flexible module configuration
- 🧠 Uses PrestaShop Advanced Payment API

---

## 🧩 Compatibility

- ✅ **PrestaShop 8.2.0+** (tested)
- ⚠️ **PrestaShop 8.0.0 – 8.1.x** (not tested)

> ⚠️ Developed and tested **only on PrestaShop 8.2.0**.  
> Compatibility with earlier versions is not guaranteed.

---

## 🛒 Payment Flow

### Checkout payment
Customers select Enotpay during checkout and are redirected to the Enotpay payment page.

![Checkout](screenshots/checkout.png)

---

### Retry payment from order page
If the payment was not completed:
- the order remains in pending state
- a **"Pay now"** button is available on the order page
- payment can be completed without creating a new order

![Order Pay Now](screenshots/order-pay-now.png)

---

## ⚙️ Admin Interface

### Module settings
![Admin Settings](screenshots/admin-settings.png)

Configure:
- shop identifier
- API key
- API base URL
- payment title and description
- successful payment order status

---

### Payment history
![Admin Payments](screenshots/admin-payments.png)

Displays:
- order ID
- amount and currency
- payment status
- transaction ID
- creation and update dates

---

## 📦 Installation

1. Copy the `enotpay` module folder into: /modules/
2. Install the module from PrestaShop Back Office
3. Configure Enotpay credentials
4. Ready to use

---

## 👨‍💻 Author

**Xardline**

- Telegram: [@xardlinep](https://t.me/xardlinep)
- Discord: `@xardline`

---

## ⚠️ Disclaimer

This module is provided **"as is"**.  
The author is not responsible for any issues arising from the use of PrestaShop versions below **8.2.0** or incorrect configuration.

---

## 📄 License

No license specified.  
Usage and modification are allowed upon agreement with the author.
