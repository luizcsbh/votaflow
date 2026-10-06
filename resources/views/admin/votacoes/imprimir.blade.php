@extends('layouts.base')
@section('titulo', 'Imprimir — '.$votacao->titulo)

@section('corpo')
<main id="conteudo" class="mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center p-8 text-center" x-data x-init="setTimeout(() => window.print(), 400)">
    <p class="text-lg font-semibold uppercase tracking-widest text-brand-700">Participe da votação</p>
    <h1 class="mt-2 text-4xl font-extrabold">{{ $votacao->titulo }}</h1>
    <div class="my-8 [&_svg]:h-auto [&_svg]:w-full [&_svg]:max-w-md" role="img" aria-label="QR Code da votação">{!! $qr !!}</div>
    <p class="text-2xl font-semibold">1. Aponte a câmera do celular para o QR Code<br>2. Entre com sua conta Google<br>3. Responda e confirme</p>
    <p class="mt-6 break-all font-mono text-base text-slate-600">{{ $votacao->urlPublica() }}</p>
    <button type="button" class="btn btn-primary no-print mt-8" onclick="window.print()">Imprimir</button>
</main>
@endsection
