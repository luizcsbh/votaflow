<?php

namespace App\Models;

use App\Casts\EnumCast;
use App\Enums\PrivacidadeVotacao;
use App\Enums\StatusVotacao;
use Database\Factories\VotacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Votacao extends Model
{
    use HasFactory;

    protected $table = 'votacoes';

    protected static function newFactory()
    {
        return VotacaoFactory::new();
    }

    protected $fillable = [
        'titulo', 'descricao', 'imagem', 'status', 'privacidade', 'inicio_em', 'fim_em',
        'limite_participantes', 'permite_alterar_resposta',
    ];

    protected $casts = [
        'inicio_em' => 'datetime',
        'fim_em' => 'datetime',
        'permite_alterar_resposta' => 'boolean',
        'limite_participantes' => 'integer',
        'participantes_count' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        $this->casts['status'] = EnumCast::class.':'.StatusVotacao::class;
        $this->casts['privacidade'] = EnumCast::class.':'.PrivacidadeVotacao::class;
        parent::__construct($attributes);
    }

    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem caracteres ambíguos (0/O, 1/I)

    protected static function booted(): void
    {
        static::creating(function (Votacao $v) {
            if (! $v->public_id) {
                $v->public_id = self::gerarPublicId();
            }
        });
    }

    public static function gerarPublicId(int $tamanho = 8): string
    {
        $id = '';
        for ($i = 0; $i < $tamanho; $i++) {
            $id .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $id;
    }

    /** Rotas usam o identificador público, nunca o id sequencial. */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function perguntas(): HasMany
    {
        return $this->hasMany(Pergunta::class)->orderBy('ordem')->orderBy('id');
    }

    public function participacoes(): HasMany
    {
        return $this->hasMany(Participacao::class);
    }

    public function urlPublica(): string
    {
        return route('votacao.mostrar', $this->public_id);
    }

    /** Backend é a fonte da verdade: status ABERTA e dentro da janela de tempo. */
    public function aceitaVotos(?\DateTimeInterface $agora = null): bool
    {
        $agora = $agora ? Carbon::instance($agora) : now();

        if ($this->status !== StatusVotacao::Aberta()) {
            return false;
        }
        if ($this->inicio_em && $agora->lt($this->inicio_em)) {
            return false;
        }
        if ($this->fim_em && $agora->gte($this->fim_em)) {
            return false;
        }

        return true;
    }
}
