<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#4f46e5">
    <title>@yield('titulo', config('votaflow.nome'))</title>
    @if (app()->environment('testing') || ! file_exists(public_path('build/manifest.json')) && ! file_exists(public_path('hot')))
        {{-- Sem assets compilados (testes/primeiro setup): a página continua utilizável. --}}
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @stack('head')
</head>
<body class="min-h-screen font-sans">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Pular para o conteúdo</a>
    @yield('corpo')
    @stack('scripts')
</body>
</html>
