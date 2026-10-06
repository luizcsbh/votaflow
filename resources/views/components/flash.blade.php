@if (session('ok'))
    <div class="alert alert-ok mb-4" role="status">{{ session('ok') }}</div>
@endif
@if (session('erro'))
    <div class="alert alert-erro mb-4" role="alert">{{ session('erro') }}</div>
@endif
