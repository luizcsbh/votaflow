<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class); // somente admin

        $q = Auditoria::query()->with(['usuario:id,name,email', 'votacao:id,public_id,titulo'])->latest('created_at')->latest('id');
        if ($acao = $request->query('acao')) {
            $q->where('acao', $acao);
        }

        return view('admin.auditoria', [
            'registros' => $q->paginate(30)->withQueryString(),
            'acoes' => Auditoria::query()->select('acao')->distinct()->orderBy('acao')->pluck('acao'),
        ]);
    }
}
