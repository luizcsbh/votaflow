<?php

namespace App\Models;

use App\Casts\EnumCast;
use App\Enums\TipoPergunta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pergunta extends Model
{
    protected $table = 'perguntas';

    protected $fillable = ['votacao_id', 'tipo', 'titulo', 'descricao', 'ordem', 'obrigatoria'];

    protected $casts = ['obrigatoria' => 'boolean', 'ordem' => 'integer'];

    /** Laravel 8 não tem cast nativo de enum: o cast é registrado no construtor. */
    public function __construct(array $attributes = [])
    {
        $this->casts['tipo'] = EnumCast::class.':'.TipoPergunta::class;
        parent::__construct($attributes);
    }

    public function votacao(): BelongsTo
    {
        return $this->belongsTo(Votacao::class);
    }

    public function alternativas(): HasMany
    {
        return $this->hasMany(Alternativa::class)->orderBy('ordem')->orderBy('id');
    }
}
