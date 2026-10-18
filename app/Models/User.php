<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'username', 'password', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function displayName(): string
    {
        return $this->name ?: $this->username;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->displayName(), 0, 2));
    }
}
