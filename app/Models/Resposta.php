<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resposta extends Model
{
    protected $table = 'respostas';

    protected $fillable = ['participacao_id', 'pergunta_id', 'alternativa_id', 'valor'];

    public function alternativa()
    {
        return $this->belongsTo(Alternativa::class);
    }

    public function pergunta()
    {
        return $this->belongsTo(Pergunta::class);
    }
}
