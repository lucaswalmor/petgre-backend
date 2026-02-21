<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\PushSubscription;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    public function sendNewOrderToEmpresa(int $empresaId, string $pedidoCodigo): void
    {
        $publicKey = config('services.webpush.vapid_public');
        $privateKey = config('services.webpush.vapid_private');
        if (!$publicKey || !$privateKey) {
            return;
        }

        $empresa = Empresa::find($empresaId);
        $userIds = $empresa ? $empresa->usuarios->pluck('id') : collect();
        if ($userIds->isEmpty()) {
            return;
        }

        $subscriptions = PushSubscription::whereIn('usuario_id', $userIds)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth);
        $payload = json_encode([
            'title' => 'Novo pedido!',
            'body' => "Pedido {$pedidoCodigo} recebido. Abra o painel para ver.",
            'url' => '/painel',
        ]);

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'p256dh' => $sub->public_key,
                        'auth' => $sub->auth_token,
                    ],
                ]);
                $webPush->queueNotification($subscription, $payload);
            } catch (\Exception $e) {
                report($e);
            }
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
