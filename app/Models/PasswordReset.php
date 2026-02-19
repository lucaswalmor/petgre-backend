<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Verifica se o token ainda é válido (não expirado e não usado)
     */
    public function isValid()
    {
        return !$this->used_at && $this->expires_at->isFuture();
    }

    /**
     * Marca o token como usado
     */
    public function markAsUsed()
    {
        $this->update(['used_at' => now()]);
    }

    /**
     * Scope para tokens válidos
     */
    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
                    ->where('expires_at', '>', now());
    }

    /**
     * Gera um código de 6 dígitos único para o email
     */
    public static function generateUniqueToken($email)
    {
        do {
            $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('email', $email)->where('token', $token)->whereNull('used_at')->exists());

        return $token;
    }
}
