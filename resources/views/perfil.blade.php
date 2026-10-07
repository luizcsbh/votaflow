@extends($user->podeAcessarPainel() ? 'layouts.admin' : 'layouts.participante')
@section('titulo', 'Meu perfil')

@section('conteudo')
<h1 class="text-2xl font-bold">Meu perfil</h1>

<section class="card mt-5" aria-labelledby="perfil-nome">
    <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
        <x-avatar :user="$user" tamanho="size-24 text-3xl" />
        <div class="min-w-0">
            <h2 id="perfil-nome" class="text-xl font-semibold">{{ $user->name }}</h2>
            <p class="break-all text-slate-600">{{ $user->email }}</p>
            <span class="badge mt-2 bg-brand-50 text-brand-700">{{ $user->role->rotulo() }}</span>
        </div>
    </div>

    <dl class="mt-6 divide-y divide-slate-100 border-t border-slate-100 text-sm">
        <div class="flex justify-between gap-4 py-2">
            <dt class="text-slate-600">Conta</dt>
            <dd class="flex items-center gap-2 font-medium"><x-google-icon /> Google</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
            <dt class="text-slate-600">E-mail verificado</dt>
            <dd class="font-medium">{{ $user->email_verified_at ? 'Sim, em '.$user->email_verified_at->format('d/m/Y') : 'Não' }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
            <dt class="text-slate-600">Membro desde</dt>
            <dd class="font-medium">{{ optional($user->created_at)->format('d/m/Y') ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
            <dt class="text-slate-600">Votações respondidas</dt>
            <dd class="font-medium tabular-nums">{{ number_format($votosRegistrados, 0, ',', '.') }}</dd>
        </div>
    </dl>

    <p class="mt-5 text-sm text-slate-500">Nome, foto e e-mail vêm da sua conta Google e são atualizados a cada login. Para alterá-los, edite sua conta Google e entre novamente.</p>

    <form method="POST" action="{{ route('logout') }}" class="mt-5">
        @csrf
        <button class="btn btn-secondary w-full sm:w-auto">Sair</button>
    </form>
</section>
@endsection
