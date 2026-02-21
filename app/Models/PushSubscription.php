<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'usuario_id',
        'endpoint',
        'public_key',
        'auth_token',
    ];

    protected $hidden = ['auth_token'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
