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

    /** Link que vai no QR Code. Usa VOTAFLOW_PUBLIC_URL (se definida) para não depender de como o admin abriu o painel. */
    public function urlPublica(): string
    {
        $base = trim((string) config('votaflow.public_url'));
        if ($base === '') {
            return route('votacao.mostrar', $this->public_id);
        }

        return rtrim($base, '/').route('votacao.mostrar', $this->public_id, false);
    }

    /**
     * O celular consegue abrir este link? Falso para localhost, 127.x, ::1, IPs de rede privada
     * (192.168/10/172.16-31 só funcionam na mesma Wi-Fi e o login Google os recusa) e domínios .local/.test.
     */
    public function linkAcessivelPorCelular(): bool
    {
        $host = strtolower((string) parse_url($this->urlPublica(), PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || $host === '::1' || $host === '[::1]') {
            return false;
        }
        if (preg_match('/\.(local|test|localhost|internal|lan)$/', $host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (config('votaflow.dev_login')) {
                return true; // dev: celular na mesma Wi-Fi alcança 192.168.x.x
            }

            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return true;
    }

    /** Login Google exige HTTPS fora de localhost: sem HTTPS o celular abre a página, mas não consegue entrar. */
    public function linkUsaHttps(): bool
    {
        if (config('votaflow.dev_login')) {
            return true; // dev: o login é o de teste, sem Google
        }

        return stripos($this->urlPublica(), 'https://') === 0;
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
