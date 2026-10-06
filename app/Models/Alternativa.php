<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alternativa extends Model
{
    protected $table = 'alternativas';

    protected $fillable = ['pergunta_id', 'texto', 'ordem'];

    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(Pergunta::class);
    }
}
