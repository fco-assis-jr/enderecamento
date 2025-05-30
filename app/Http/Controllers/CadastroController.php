<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CadastroController extends Controller
{
    public function buscarProduto(Request $request)
    {
        $termo = $request->input('termo');

        $produto = DB::connection('oracle')->selectOne("SELECT NVL(P1.CODPROD, P2.CODPROD) AS CODPROD,
       NVL(P1.DESCRICAO, P2.DESCRICAO) AS DESCRICAO,
       NVL(P1.EMBALAGEM, P2.EMBALAGEM) AS EMBALAGEM,
       NVL(P1.CODEPTO, P2.CODEPTO) AS CODEPTO,
       NVL(P1.DEPTO_DESCRICAO, P2.DEPTO_DESCRICAO) AS DEPTO_DESCRICAO,
       NVL(P1.CODSEC, P2.CODSEC) AS CODSEC,
       NVL(P1.SECAO_DESCRICAO, P2.SECAO_DESCRICAO) AS SECAO_DESCRICAO,
       NVL(P1.RUA, P2.RUA) AS RUA,
       NVL(P1.MODULO, P2.MODULO) AS MODULO,
       NVL(P1.NUMERO, P2.NUMERO) AS NUMERO,
       NVL(P1.APTO, P2.APTO) AS APTO,
       NVL(P1.QTUNIT, P2.QTUNIT) AS QTUNIT,
       NVL(P1.QTUNITCX, P2.QTUNITCX) AS QTUNITCX,
       NVL(P1.UNIDADEMASTER, P2.UNIDADEMASTER) AS UNIDADEMASTER,
       NVL(P1.UNIDADE, P2.UNIDADE) AS UNIDADE,
       NVL(P1.OBS2, P2.OBS2) AS OBS2,
       NVL(P1.CODAUXILIAR, P2.CODAUXILIAR) AS CODAUXILIAR,
       NVL(P1.CODAUXILIAR2, P2.CODAUXILIAR2) AS CODAUXILIAR2,
       NVL(P1.QTESTGER/P1.QTUNITCX, P2.QTESTGER/P2.QTUNITCX) AS QTESTGER,
       NVL(P1.QTEST, P2.QTEST) AS QTEST,
       NVL(P1.QTRESERV, P2.QTRESERV) AS QTRESERV,
       NVL(P1.RESERVPENDENTE, P2.RESERVPENDENTE) AS RESERVPENDENTE,
       NVL(P1.QTBLOQUEADA, P2.QTBLOQUEADA) AS QTBLOQUEADA
FROM -- PCPRODUT (PRIORITÁRIO)
     (SELECT PCPRODUT.CODPROD,
             PCPRODUT.DESCRICAO,
             PCPRODUT.EMBALAGEM,
             PCPRODUT.CODEPTO,
             PCDEPTO.DESCRICAO
                 AS DEPTO_DESCRICAO,
             PCPRODUT.CODSEC,
             PCSECAO.DESCRICAO
                 AS SECAO_DESCRICAO,
             PCPRODUT.RUA,
             PCPRODUT.MODULO,
             PCPRODUT.NUMERO,
             PCPRODUT.APTO,
             PCPRODUT.QTUNIT,
             PCPRODUT.QTUNITCX,
             PCPRODUT.UNIDADEMASTER,
             PCPRODUT.UNIDADE,
             DECODE(TRIM(PCPRODUT.OBS2), 'FL', 'SIM', 'NAO')
                 AS OBS2,
             PCPRODUT.CODAUXILIAR,
             PCPRODUT.CODAUXILIAR2,
             PCEST.QTESTGER,
             PCEST.QTEST,
             PCEST.QTRESERV,
             (NVL(PCEST.QTRESERV, 0) + NVL(PCEST.QTPENDENTE, 0))
                 AS RESERVPENDENTE,
             (NVL(PCEST.QTBLOQUEADA, 0) - NVL(PCEST.QTINDENIZ, 0))
                 AS QTBLOQUEADA
      FROM PCPRODUT
           JOIN PCDEPTO ON PCPRODUT.CODEPTO = PCDEPTO.CODEPTO
           JOIN PCSECAO ON PCPRODUT.CODSEC = PCSECAO.CODSEC
           JOIN PCEST ON PCPRODUT.CODPROD = PCEST.CODPROD
      WHERE PCEST.CODFILIAL = '2'
            AND PCPRODUT.DTEXCLUSAO IS NULL
            AND PCPRODUT.CODPROD = :TERMO) P1
     FULL OUTER JOIN
     (SELECT PCPRODUT.CODPROD,
             PCPRODUT.DESCRICAO,
             PCPRODUT.EMBALAGEM,
             PCPRODUT.CODEPTO,
             PCDEPTO.DESCRICAO
                 AS DEPTO_DESCRICAO,
             PCPRODUT.CODSEC,
             PCSECAO.DESCRICAO
                 AS SECAO_DESCRICAO,
             PCPRODUT.RUA,
             PCPRODUT.MODULO,
             PCPRODUT.NUMERO,
             PCPRODUT.APTO,
             PCPRODUT.QTUNIT,
             PCPRODUT.QTUNITCX,
             PCPRODUT.UNIDADEMASTER,
             PCPRODUT.UNIDADE,
             DECODE(TRIM(PCPRODUT.OBS2), 'FL', 'SIM', 'NAO')
                 AS OBS2,
             PCPRODUT.CODAUXILIAR,
             PCPRODUT.CODAUXILIAR2,
             PCEST.QTESTGER,
             PCEST.QTEST,
             PCEST.QTRESERV,
             (NVL(PCEST.QTRESERV, 0) + NVL(PCEST.QTPENDENTE, 0))
                 AS RESERVPENDENTE,
             (NVL(PCEST.QTBLOQUEADA, 0) - NVL(PCEST.QTINDENIZ, 0))
                 AS QTBLOQUEADA
      FROM PCEMBALAGEM
           JOIN PCPRODUT ON PCEMBALAGEM.CODPROD = PCPRODUT.CODPROD
           JOIN PCDEPTO ON PCPRODUT.CODEPTO = PCDEPTO.CODEPTO
           JOIN PCSECAO ON PCPRODUT.CODSEC = PCSECAO.CODSEC
           JOIN PCEST ON PCPRODUT.CODPROD = PCEST.CODPROD
      WHERE PCEST.CODFILIAL = '2'
            AND PCPRODUT.DTEXCLUSAO IS NULL
            AND PCEMBALAGEM.CODAUXILIAR = :TERMO
            AND PCEMBALAGEM.CODFILIAL = 2) P2
         ON 1 = 1
WHERE ROWNUM = 1
    ", ['termo' => $termo]);

        if (!$produto) {
            return response()->json(['mensagem' => 'Produto não encontrado.'], 404);
        }

        return response()->json($produto);
    }

    public function validarEndereco(Request $request)
    {
        $codprod = $request->codprod;
        $novo = $request->endereco;

        // Validação de endereço reservado
        if (
            in_array($novo['rua'], [1, 9]) &&
            in_array($novo['modulo'], [1, 9]) &&
            in_array($novo['numero'], [1, 9]) &&
            in_array($novo['apto'], [1, 9])
        ) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Endereço inválido. Não é permitido utilizar 1-1-1-1 ou 9-9-9-9.'
            ], 400);
        }

        // Verifica se o endereço existe na PCESTEND
        $endereco = DB::connection('oracle')->table('pcestend')
            ->where('rua', $novo['rua'])
            ->where('modulo', $novo['modulo'])
            ->where('numero', $novo['numero'])
            ->where('apto', $novo['apto'])
            ->first();

        if (!$endereco) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Endereço não cadastrado. Utilize a rotina 1133.'
            ], 404);
        }

        // Verifica se existe outro produto usando o endereço na PCPRODUT
        $produtosConflitantes = DB::connection('oracle')->table('pcprodut')
            ->select('codprod', 'descricao')
            ->where('rua', $novo['rua'])
            ->where('modulo', $novo['modulo'])
            ->where('numero', $novo['numero'])
            ->where('apto', $novo['apto'])
            ->where('codprod', '!=', $codprod)
            ->get();

        if ($produtosConflitantes->count() > 0) {
            return response()->json([
                'status' => 'confirmar_sobrescrever_pcprodut',
                'mensagem' => 'Endereço ocupado por outro(s) produto(s). Deseja sobrescrever?',
                'produtos' => $produtosConflitantes
            ]);
        }

        // Verifica se o codprod do PCESTEND é diferente
        if ($endereco->codprod != 0 && $endereco->codprod != null && $endereco->codprod != $codprod) {
            $produtoEst = DB::connection('oracle')->table('pcprodut')
                ->select('codprod', 'descricao')
                ->where('codprod', $endereco->codprod)
                ->first();

            return response()->json([
                'status' => 'confirmar_sobrescrever_pcestend',
                'mensagem' => 'Endereço já está ocupado na PCESTEND. Deseja sobrescrever?',
                'produtos' => $produtoEst
                    ? [['codprod' => $produtoEst->codprod, 'descricao' => $produtoEst->descricao]]
                    : []
            ]);
        }

        // Nenhum conflito: está validado
        return response()->json([
            'status' => 'validado',
            'mensagem' => 'Endereço disponível para atualização.'
        ]);
    }

    public function sobrescreverEndereco(Request $request)
    {
        $codprod = $request->codprod;
        $novo = $request->endereco;
        // Produtos que estavam no novo endereço
        $produtosSobrescritos = DB::connection('oracle')->table('pcprodut')
            ->select('codprod', 'rua', 'modulo', 'numero', 'apto')
            ->where('rua', $novo['rua'])
            ->where('modulo', $novo['modulo'])
            ->where('numero', $novo['numero'])
            ->where('apto', $novo['apto'])
            ->where('codprod', '!=', $codprod)
            ->get();

        // Log de cada produto sobrescrito
        foreach ($produtosSobrescritos as $produto) {
            DB::connection('oracle')->table('bdc_log_endereco')->insert([
                'codprod' => $produto->codprod,
                'matricula' => auth()->user()->matricula ?? 0,
                'dt_alteracao' => now(),
                'rua_antiga' => $produto->rua,
                'modulo_antigo' => $produto->modulo,
                'numero_antigo' => $produto->numero,
                'apto_antigo' => $produto->apto,
                'rua_nova' => 1,
                'modulo_nova' => 1,
                'numero_nova' => 1,
                'apto_nova' => 1,
                'tipo' => 'S',
                'CODPROD_SOBRESCRITO' => $codprod,
            ]);
        }

        // Recupera o endereço antigo do produto
        $produtoAtual = DB::connection('oracle')->table('pcprodut')
            ->select('rua', 'modulo', 'numero', 'apto')
            ->where('codprod', $codprod)
            ->first();

        // Atualiza endereco antigo do produto atual
        if ($produtoAtual) {
            DB::connection('oracle')->table('pcestend')
                ->where('codprod', $codprod) // Garante que só limpe se for o mesmo produto
                ->update(['codprod' => 0]);
        }

        // Move produtos sobre escritos para o novo endereco 1-1-1-1
        DB::connection('oracle')->table('pcprodut')
            ->where('rua', $novo['rua'])
            ->where('modulo', $novo['modulo'])
            ->where('numero', $novo['numero'])
            ->where('apto', $novo['apto'])
            ->where('codprod', '!=', $codprod)
            ->update([
                'rua' => 1,
                'modulo' => 1,
                'numero' => 1,
                'apto' => 1,
            ]);

        // Atualiza a PCESTEND com o novo produto
        DB::connection('oracle')->table('pcestend')
            ->where('rua', $novo['rua'])
            ->where('modulo', $novo['modulo'])
            ->where('numero', $novo['numero'])
            ->where('apto', $novo['apto'])
            ->update(['codprod' => $codprod]);

        // Atualiza a PCPRODUT com o novo endereço
        DB::connection('oracle')->table('pcprodut')
            ->where('codprod', $codprod)
            ->update([
                'rua' => $novo['rua'],
                'modulo' => $novo['modulo'],
                'numero' => $novo['numero'],
                'apto' => $novo['apto'],
            ]);
        // Log do antigo e novo endereco
        DB::connection('oracle')->table('bdc_log_endereco')->insert([
            'codprod' => $codprod,
            'matricula' => auth()->user()->matricula ?? 0,
            'dt_alteracao' => now(),
            'rua_antiga' => $produtoAtual->rua,
            'modulo_antigo' => $produtoAtual->modulo,
            'numero_antigo' => $produtoAtual->numero,
            'apto_antigo' => $produtoAtual->apto,
            'rua_nova' => $novo['rua'],
            'modulo_nova' => $novo['modulo'],
            'numero_nova' => $novo['numero'],
            'apto_nova' => $novo['apto'],
            'tipo' => 'T',
            'CODPROD_SOBRESCRITO' => null,
        ]);

        return response()->json(['status' => 'ok', 'mensagem' => 'Endereço sobrescrito com sucesso.']);
    }


}
