/**
 * Gera chaves VAPID para Web Push (alternativa quando PHP/OpenSSL falha no Windows).
 * Execute: node gerar_vapid_keys.js
 * Copie a saída para o .env (VAPID_PUBLIC_KEY e VAPID_PRIVATE_KEY).
 */
import crypto from 'node:crypto';

function urlBase64(buffer) {
  return buffer.toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}

const keyPair = crypto.createECDH('prime256v1');
keyPair.generateKeys();

const publicKey = urlBase64(keyPair.getPublicKey());
const privateKey = urlBase64(keyPair.getPrivateKey());

console.log('VAPID_PUBLIC_KEY=' + publicKey);
console.log('VAPID_PRIVATE_KEY=' + privateKey);
