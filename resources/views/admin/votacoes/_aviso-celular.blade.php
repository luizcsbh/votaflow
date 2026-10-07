@php($avisos = [])
@if (! $votacao->linkAcessivelPorCelular())
    @php($avisos[] = 'Este link usa <strong>'.e(parse_url($votacao->urlPublica(), PHP_URL_HOST)).'</strong>, que só existe no seu computador. <strong>Celulares não vão abrir</strong> este QR Code. Defina <code>VOTAFLOW_PUBLIC_URL</code> no <code>.env</code> com o endereço público (domínio ou túnel HTTPS) e rode <code>php artisan config:clear</code>.')
@elseif (! $votacao->linkUsaHttps())
    @php($avisos[] = 'O link não usa HTTPS: o celular abre a página, mas o <strong>login com Google não funciona</strong> sem HTTPS. Use um endereço <code>https://</code>.')
@endif
@foreach ($avisos as $aviso)
    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-left text-sm text-amber-900" role="alert">{!! $aviso !!}</div>
@endforeach
