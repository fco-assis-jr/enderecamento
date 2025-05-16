<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pcempr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'usuario' => 'required',
            'senha' => 'required',
        ]);

        $usuario = strtoupper($request->input('usuario'));
        $senha = strtoupper($request->input('senha'));

        $user = Pcempr::whereRaw("
            matricula = ?
            AND CRYPT(?, usuariobd) = senhabd
            AND situacao = 'A'
        ", [$usuario, $senha])->first();

        if (!$user) {
            return back()->withErrors([
                'usuario' => 'Usuário ou senha inválidos.',
            ])->onlyInput('usuario');
        }

        $temAcesso = DB::connection('oracle')->table('PCCONTRO')
            //->where('codrotina', 409)
            ->where('acesso', 'S')
            ->where('codusuario', $user->matricula)
            ->exists();

        if (!$temAcesso) {
            return back()->withErrors([
                'usuario' => 'Usuário sem permissão de acesso.',
            ])->onlyInput('usuario');
        }

        Auth::guard('oracle')->login($user);

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        $sessionId = $request->session()->getId();

        Auth::guard('oracle')->logout();

        DB::table('sessions')->where('id', $sessionId)->delete();

        return redirect('/login');
    }
}
