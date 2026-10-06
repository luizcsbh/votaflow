<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Nunca expõe o id sequencial: apenas public_id. */
class VotacaoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'public_id' => $this->public_id,
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'imagem' => $this->imagem,
            'status' => $this->status->value,
            'privacidade' => $this->privacidade->value,
            'inicio_em' => optional($this->inicio_em)->toIso8601String(),
            'fim_em' => optional($this->fim_em)->toIso8601String(),
            'aceita_votos' => $this->aceitaVotos(),
            'permite_alterar_resposta' => $this->permite_alterar_resposta,
            'url' => $this->urlPublica(),
        ];
    }
}
