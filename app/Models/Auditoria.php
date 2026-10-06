<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    public $timestamps = false;

    protected $table = 'auditorias';

    protected $fillable = ['usuario_id', 'votacao_id', 'acao', 'ip', 'user_agent', 'dados', 'created_at'];

    protected $casts = ['dados' => 'array', 'created_at' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function votacao()
    {
        return $this->belongsTo(Votacao::class);
    }
}
