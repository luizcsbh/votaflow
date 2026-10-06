<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participacao extends Model
{
    protected $table = 'participacoes';

    protected $fillable = ['votacao_id', 'usuario_id', 'protocolo', 'iniciado_em', 'finalizado_em'];

    protected $casts = ['iniciado_em' => 'datetime', 'finalizado_em' => 'datetime'];

    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Código aleatório "ABC8-X92K": não carrega nenhuma informação sobre pessoa ou voto. */
    public static function gerarProtocolo(): string
    {
        $s = '';
        for ($i = 0; $i < 8; $i++) {
            $s .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return substr($s, 0, 4).'-'.substr($s, 4);
    }

    public function votacao(): BelongsTo
    {
        return $this->belongsTo(Votacao::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(Resposta::class);
    }
}
