<?php
/**
 * Gera chaves VAPID para Web Push. Execute: php gerar_vapid_keys.php
 * Copie a saída para o .env (VAPID_PUBLIC_KEY e VAPID_PRIVATE_KEY).
 */
require __DIR__ . '/vendor/autoload.php';

$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . PHP_EOL;
echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . PHP_EOL;
