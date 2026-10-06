@extends('layouts.admin')
@section('titulo', 'QR Code — '.$votacao->titulo)

@section('conteudo')
<div class="mx-auto max-w-md text-center">
    <h1 class="text-2xl font-bold">{{ $votacao->titulo }}</h1>
    <div class="card mt-5 [&_svg]:mx-auto [&_svg]:h-auto [&_svg]:max-w-full" role="img" aria-label="QR Code da votação">{!! $qr !!}</div>
    <p class="mt-3 break-all font-mono text-sm">{{ $votacao->urlPublica() }}</p>
    <div class="mt-4 flex justify-center gap-2">
        <a class="btn btn-secondary" href="{{ route('admin.votacoes.qrcode.download', $votacao) }}">Baixar</a>
        <a class="btn btn-secondary" href="{{ route('admin.votacoes.imprimir', $votacao) }}" target="_blank" rel="noopener">Imprimir</a>
        <a class="btn btn-secondary" href="{{ route('admin.votacoes.show', $votacao) }}">Voltar</a>
    </div>
</div>
@endsection
