
# PHPNuxBill Simple Queue Plugin (MikroTik)

Plugin comunitario para PHPNuxBill que crea y sincroniza Simple Queues en MikroTik
usando IP fija (/32) y velocidad tomada del plan (max-limit).

## Características
- Crea Simple Queue automáticamente
- Actualiza si el plan cambia
- No duplica queues
- Compatible RouterOS v6/v7

## Uso rápido
```php
sq_sync_customer_queue($api, $username, $ip, $down, $up);
```

Licencia MIT.
