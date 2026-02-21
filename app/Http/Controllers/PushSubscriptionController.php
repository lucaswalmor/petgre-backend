<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey()
    {
        $key = config('services.webpush.vapid_public');
        return response()->json([
            'vapid_public_key' => $key,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string|max:500',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = Auth::user();
        // Uma subscription por usuário (evita duplicata ao reativar em outra aba ou mesmo navegador)
        PushSubscription::where('usuario_id', $user->id)->delete();
        PushSubscription::create([
            'usuario_id' => $user->id,
            'endpoint' => $request->endpoint,
            'public_key' => $request->keys['p256dh'],
            'auth_token' => $request->keys['auth'],
        ]);

        return response()->json(['success' => true]);
    }
}
