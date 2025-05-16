<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CadastroController extends Controller
{
    public function buscarProduto(Request $request)
    {
        $termo = $request->input('termo');

        $produto = DB::connection('oracle')->selectOne("
        SELECT
            PCPRODUT.CODPROD AS CODPROD,
            PCPRODUT.DESCRICAO AS DESCRICAO,
            PCPRODUT.EMBALAGEM AS EMBALAGEM,
            PCPRODUT.CODEPTO AS CODEPTO,
            PCDEPTO.DESCRICAO AS DEPTO_DESCRICAO,
            PCPRODUT.CODSEC AS CODSEC,
            PCSECAO.DESCRICAO AS SECAO_DESCRICAO,
            PCPRODUT.RUA AS RUA,
            PCPRODUT.MODULO AS MODULO,
            PCPRODUT.NUMERO AS NUMERO,
            PCPRODUT.APTO AS APTO,
            PCPRODUT.QTUNIT AS QTUNIT,
            PCPRODUT.QTUNITCX AS QTUNITCX,
            PCPRODUT.UNIDADEMASTER AS UNIDADEMASTER,
            PCPRODUT.UNIDADE AS UNIDADE,
            PCPRODUT.OBS2 AS OBS2,
            PCPRODUT.CODAUXILIAR AS CODAUXILIAR,
            PCPRODUT.CODAUXILIAR2 AS CODAUXILIAR2,
            PCEST.QTESTGER AS QTESTGER,
            PCEST.QTEST AS QTEST,
            PCEST.QTRESERV AS QTRESERV,
            (NVL(PCEST.QTRESERV, 0) + NVL(PCEST.QTPENDENTE, 0)) AS RESERVPENDENTE,
            (NVL(PCEST.QTBLOQUEADA, 0) - NVL(PCEST.QTINDENIZ, 0)) AS QTBLOQUEADA

        FROM PCPRODUT
        JOIN PCDEPTO ON PCPRODUT.CODEPTO = PCDEPTO.CODEPTO
        JOIN PCSECAO ON PCPRODUT.CODSEC = PCSECAO.CODSEC
        LEFT JOIN PCFORNEC ON PCPRODUT.CODFORNEC = PCFORNEC.CODFORNEC
        JOIN PCEST ON PCPRODUT.CODPROD = PCEST.CODPROD
        WHERE PCEST.CODFILIAL = '2'
          AND PCPRODUT.CODPROD = :termo
    ", ['termo' => $termo]);

        if (!$produto) {
            return response()->json(['mensagem' => 'Produto não encontrado.'], 404);
        }

        return response()->json($produto);
    }

    public function atualizarEndereco(Request $request)
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
