<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuário do sistema (3.1.1 Entidade USUÁRIO).
 * Perfis: solicitante (médico assistente), regulador (médico regulador) e nir.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const PERFIL_SOLICITANTE = 'solicitante';
    public const PERFIL_REGULADOR   = 'regulador';
    public const PERFIL_NIR         = 'nir';

    protected $fillable = [
        'name',
        'email',
        'password',
        'crm',
        'perfil',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
