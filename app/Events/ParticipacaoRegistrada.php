<?php

namespace App\Events;

use App\Services\ResultadoService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após o COMMIT do voto. É enviado para a fila: o registro do voto
 * nunca espera pelo broadcast (desacoplado do caminho crítico).
 */
class ParticipacaoRegistrada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public $queue = 'broadcasts';

    /** @var string */
    public $publicId;

    /** @var int */
    public $votacaoId;

    public function __construct(string $publicId, int $votacaoId)
    {
        $this->publicId = $publicId;
        $this->votacaoId = $votacaoId;
    }

    public function broadcastOn(): array
    {
        return [new Channel('votacao.'.$this->publicId)];
    }

    public function broadcastAs(): string
    {
        return 'participacao.registrada';
    }

    public function broadcastWith(): array
    {
        return ['participantes' => app(ResultadoService::class)->totalParticipantes($this->votacaoId, true)];
    }
}
