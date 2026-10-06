<?php

namespace App\Models;

use App\Casts\EnumCast;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'google_id', 'avatar', 'role', 'email_verified_at'];

    protected $hidden = ['remember_token', 'google_id'];

    protected $casts = ['email_verified_at' => 'datetime'];

    public function __construct(array $attributes = [])
    {
        $this->casts['role'] = EnumCast::class.':'.Role::class;
        parent::__construct($attributes);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin();
    }

    public function isOperador(): bool
    {
        return $this->role === Role::Operador();
    }

    /** Admin ou operador: acesso ao painel. */
    public function podeAcessarPainel(): bool
    {
        return $this->isAdmin() || $this->isOperador();
    }

    public function participacoes()
    {
        return $this->hasMany(Participacao::class, 'usuario_id');
    }

    public function primeiroNome(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }
}
