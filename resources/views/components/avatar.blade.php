@props(['user', 'tamanho' => 'size-10'])
@php
    // Google entrega a foto em 96px (".../photo=s96-c"); pede 256px para não ficar borrada.
    $url = $user->avatar ? preg_replace('/=s\d+(-c)?$/', '=s256-c', $user->avatar) : null;
    $iniciais = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<span {{ $attributes->merge(['class' => "relative inline-grid shrink-0 place-items-center overflow-hidden rounded-full bg-brand-100 font-semibold text-brand-700 {$tamanho}"]) }} x-data="{ falhou: false }">
    <span aria-hidden="true">{{ $iniciais ?: '?' }}</span>
    @if ($url)
        {{-- referrerpolicy evita 403 do googleusercontent; se a foto falhar, ficam as iniciais. --}}
        <img src="{{ $url }}" alt="Foto de perfil de {{ $user->name }}" referrerpolicy="no-referrer" loading="lazy"
             class="absolute inset-0 size-full object-cover" x-show="!falhou" x-on:error="falhou = true">
    @endif
</span>
