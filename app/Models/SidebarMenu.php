<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidebarMenu extends Model
{
    protected $table = 'sidebar_menu';

    protected $fillable = [
        'parent_id',
        'chave',
        'label',
        'path',
        'icon',
        'permission_slug',
        'ordem',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SidebarMenu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SidebarMenu::class, 'parent_id')->orderBy('ordem');
    }
}
