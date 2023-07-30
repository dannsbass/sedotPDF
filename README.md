## Sedot PDF Archive

Ini adalah bot Telegram yang berfungsi untuk menyedot file-file PDF dari situs archive.org ke Telegram.

## Cara install

1. Clone repo ini beserta dependensinya:

```bash
git clone https://github.com/dannsbass/sedotPDF
git clone https://github.com/dannsbass/bot
git clone https://github.com/dannsbass/dom

```

2. Ubah token dan nama bot sesuai data asli dari @BotFather

```php
define('TOKEN_BOT', '1234567890:AAEywzNZOoQK_B0Wp3w5Go2q48WnkJCkZHY');
define('NAMA_BOT', 'SedotPDFBot');
```

3. Set Webhook (sesuaikan TOKEN_BOT dan URL_WEBHOOK_KAMU dengan data sebenarnya)

```php
file_get_contents('https://api.telegram.org/bot'.TOKEN_BOT.'/setWebhook?url='.URL_WEBHOOK_KAMU);
```