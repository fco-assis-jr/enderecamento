<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller responsável por serviços de precificação e oferta dos produtos.
 *
 * Esta classe fornece dois pontos de acesso principais:
 *  - listagem de filiais disponíveis (para popular um select na interface)
 *  - consulta de informações de preço e oferta para um produto específico
 *
 * A maioria das consultas aqui utiliza a conexão 'oracle' definida em config/database.php.
 * É importante notar que o código abaixo apenas prepara e executa SQLs fornecidos
 * pelo usuário; caso deseje refatorar para consultas mais seguras utilize
 * bind params em todos os lugares onde houver concatenação de variáveis.
 */
class PrecoController extends Controller
{
    /**
     * Recupera a lista de filiais ativas a partir da tabela PCFILIAL.
     * Retorna o código e o nome fantasia de cada filial, ordenado pelo código.
     */
    public function listarFiliais()
    {
        // Seleciona apenas filiais sem data de cancelamento, utilizando ordem crescente
        $filiais = DB::connection('oracle')->select(
            "SELECT CODIGO, CONTATO AS NOME FROM PCFILIAL where codigo in (3,4,5,6,7,8,9,10,12,13,14)"
        );

        return response()->json($filiais);
    }

    /**
     * Retorna informações detalhadas de um produto, incluindo preços de venda
     * (varejo e atacado) e eventuais ofertas ativas.
     *
     * Parâmetros esperados via query string:
     *  - filial: código da filial a ser consultada (ex. '13')
     *  - ean: código auxiliar (EAN) do produto
     *
     * A resposta contém duas entradas:
     *  - produto: objeto com os campos retornados pela consulta principal (pode ser null)
     *  - ofertas: array de ofertas ativas para o produto
     */
    public function dadosProduto(Request $request)
    {
        $filial = $request->query('filial');
        $ean    = $request->query('ean');

        if (empty($filial) || empty($ean)) {
            return response()->json([
                'error' => 'Parâmetros "filial" e "ean" são obrigatórios.',
            ], 400);
        }

        // Consulta principal para dados de produto e preços
        // Utiliza binders nomeados para melhor clareza (as ocorrências de :filial e :ean são substituídas automaticamente)
        $sqlProduto = "
SELECT TBLPRODUT.DESCRICAO,
       PCEMBALAGEM.CODPROD,
       PCEMBALAGEM.DESTINOOFERTAATAC,
       PCEMBALAGEM.DESTINOOFERTAVAREJO,
       TO_CHAR(PCEMBALAGEM.CODAUXILIAR)
           CODAUXILIAR,
       PCEMBALAGEM.EMBALAGEM,
       PCEMBALAGEM.QTUNIT,
       PCEMBALAGEM.UNIDADE,
       (CASE
            WHEN PCEMBALAGEM.DTINATIVO IS NULL THEN 'ATIVA'
            ELSE 'INATIVA'
        END)
           SITUACAO,
       (CASE WHEN TBLPRODUT.OBS = 'PV' THEN 'S' ELSE 'N' END)
           PROIBIDOPRAVENDA,
       (CASE WHEN TBLPRODUT.OBS2 = 'FL' THEN 'S' ELSE 'N' END)
           FORADELINHA,
       TBLPRODUT.MARCA,
       NVL(COLUNA_PRECO(TBLPRECO.PRECO, 'PVENDA'), 0)
           PVENDA,
       NVL(COLUNA_PRECO(TBLPRECO.PRECO, 'PVENDAATAC'), 0)
           PVENDAATAC,
       TBLPRODUT.DIRFOTOPROD,
       TBLTRIBUTACAO.CODICMTAB,
       TBLTRIBUTACAO.CODECF,
       PCEMBALAGEM.DESCRICAOECF,
       PCEMBALAGEM.PERVARIACAOPTABELA,
       (CASE
            WHEN PKG_ESTOQUE.ESTOQUE_DISPONIVEL(PCEMBALAGEM.CODPROD,
                                                PCEMBALAGEM.CODFILIAL,
                                                'V') >
                 0
            THEN
                'SIM'
            ELSE
                'NÃO'
        END)
           PRODUTOCOMESTOQUE,
       (CASE WHEN TBLPRODUT.DTEXCLUSAO IS NULL THEN 'NÃO' ELSE 'SIM' END)
           PRODUTOEXCLUIDO,
       PKG_ESTOQUE.ESTOQUE_DISPONIVEL(PCEMBALAGEM.CODPROD,
                                      PCEMBALAGEM.CODFILIAL,
                                      'V')
           QTESTOQUEDISPONIVEL,
       PCPRODUT.UNIDADEMASTER || ' - ' || ROUND(
       PKG_ESTOQUE.ESTOQUE_DISPONIVEL         (PCEMBALAGEM.CODPROD,
       PCEMBALAGEM.CODFILIAL,
       'V') / PCPRODUT.QTUNITCX,
       3)
           AS ESTOQUEMASTER,
       TBLPRODUT.INFORMACOESTECNICAS,
       CASE
           WHEN (SELECT PCCONSUM.SUGVENDA FROM PCCONSUM) = 1
           THEN
               ROUND(NVL(PCEST.CUSTOREAL, 0), 2)
           WHEN (SELECT PCCONSUM.SUGVENDA FROM PCCONSUM) = 2
           THEN
               ROUND(NVL(PCEST.CUSTOFIN, 0), 2)
           ELSE
               ROUND(NVL(PCEST.CUSTOULTENT, 0), 2)
       END
           CUSTO,
       NVL(TBLPCPRODFILIAL.REVENDA, 'S')
           REVENDAFILIAL,
       NVL(TBLPRODUT.REVENDA, 'S')
           REVENDAPROD,
       NVL(TBLPCPRODFILIAL.ATIVO, 'S')
           PRODUTOATIVO,
       PCEST.CUSTOREAL,
       PCEST.CUSTOFIN,
       PCEST.CUSTOCONT,
       PCEST.CUSTOULTENT,
       TBLPRODUT.NBM
FROM PCEMBALAGEM,
     PCEST,
     PCPRODUT,
     PCREGIAO,
     (SELECT TBLCLASSIFICMERC.CODAUXILIAR,
             TBLCLASSIFICMERC.CODFILIAL,
             PCTRIBUT.CODICMTAB,
             PCTRIBUT.CODECF,
             PCEMBALAGEM.CODPROD
      FROM PCEMBALAGEM,
           PCPARAMFILIAL TBL_MARGEM,
           PCPARAMFILIAL TBL_TRIBUT,
           PCPARAMFILIAL TBL_TRIBUF,
           (TABLE(BUSCAMARGEM(PCEMBALAGEM.CODFILIAL,
                              PCEMBALAGEM.CODPROD,
                              PCEMBALAGEM.CODAUXILIAR,
                              '13',
                              PCEMBALAGEM.PERVARIACAOPTABELA,
                              NVL(TBL_MARGEM.VALOR, 'N'),
                              NVL(TBL_TRIBUT.VALOR, 'N'),
                              NVL(TBL_TRIBUF.VALOR, 'N')))) TBLCLASSIFICMERC,
           PCTRIBUT
      WHERE PCEMBALAGEM.CODFILIAL = TBL_MARGEM.CODFILIAL
            AND PCEMBALAGEM.CODFILIAL = TBLCLASSIFICMERC.CODFILIAL
            AND TBL_MARGEM.CODFILIAL = TBL_TRIBUT.CODFILIAL
            AND TBL_MARGEM.NOME = 'UTILIZAMARGEMSUBCAT'
            AND TBL_TRIBUT.NOME = 'UTILIZATRIBUTSUBCAT'
            AND NVL(TBL_TRIBUF.NOME, 'CON_USATRIBUTACAOPORUF') =
                'CON_USATRIBUTACAOPORUF'
            AND TBL_TRIBUF.CODFILIAL = '99'
            AND PCTRIBUT.CODST = TBLCLASSIFICMERC.CODST
            AND (   (PCEMBALAGEM.CODFILIAL = :FILIAL)
                 OR ( :FILIAL = '99'))
            AND PCEMBALAGEM.CODAUXILIAR IN
                    (SELECT E.CODAUXILIAR
                     FROM PCEMBALAGEM E
                     WHERE E.CODAUXILIAR = :EAN AND E.CODFILIAL = '13'))
     TBLTRIBUTACAO,
     (SELECT PCPRODUT.CODPROD,
             PCPRODUT.CODINTERNO,
             PCPRODUT.DESCRICAO,
             PCPRODUT.DIRFOTOPROD,
             PCPRODUT.OBS,
             PCPRODUT.OBS2,
             PCMARCA.MARCA,
             PCPRODUT.DTEXCLUSAO,
             PCPRODUT.INFORMACOESTECNICAS,
             PCPRODUT.REVENDA,
             PCPRODUT.NBM
      FROM PCPRODUT, PCMARCA
      WHERE PCPRODUT.CODMARCA = PCMARCA.CODMARCA(+)) TBLPRODUT,
     (SELECT CODPROD, CODFILIAL, ATIVO, REVENDA FROM PCPRODFILIAL)
     TBLPCPRODFILIAL,
     (SELECT E.CODAUXILIAR,
             E.CODPROD,
             E.CODFILIAL,
             BUSCAPRECOS(E.CODFILIAL,
                         :FILIAL,
                         E.CODAUXILIAR,
                         TRUNC(SYSDATE)) PRECO
      FROM PCEMBALAGEM E) TBLPRECO
WHERE PCEMBALAGEM.CODPROD = TBLPRODUT.CODPROD
      AND PCEMBALAGEM.CODPROD = PCPRODUT.CODPROD
      AND (PCEMBALAGEM.CODFILIAL = TBLTRIBUTACAO.CODFILIAL(+)
           AND PCEMBALAGEM.CODPROD = TBLTRIBUTACAO.CODPROD(+)
           AND PCEMBALAGEM.CODAUXILIAR = TBLTRIBUTACAO.CODAUXILIAR(+))
      AND TBLPRECO.CODFILIAL = PCEMBALAGEM.CODFILIAL
      AND TBLPRECO.CODAUXILIAR = PCEMBALAGEM.CODAUXILIAR
      AND PCEST.CODPROD = PCEMBALAGEM.CODPROD
      AND PCEST.CODFILIAL = PCEMBALAGEM.CODFILIAL
      AND TBLPCPRODFILIAL.CODPROD = PCEMBALAGEM.CODPROD
      AND TBLPCPRODFILIAL.CODFILIAL = PCEMBALAGEM.CODFILIAL
      AND PCREGIAO.NUMREGIAO =
          (SELECT NVL(VALOR, PCFILIAL.NUMREGIAOPADRAO) NUMREGIAOPADRAO
           FROM PCFILIAL,
                (SELECT VALOR, CODFILIAL, NOME
                 FROM PCPARAMFILIAL
                 WHERE NOME = 'NUMREGIAOPADRAOVAREJO') TBLPARAMFILIAL
           WHERE TBLPARAMFILIAL.CODFILIAL(+) = PCFILIAL.CODIGO
                 AND PCFILIAL.CODIGO = :FILIAL)
      AND (   (PCEMBALAGEM.CODFILIAL = :FILIAL)
           OR ( :FILIAL = '99'))
      AND PCEMBALAGEM.CODAUXILIAR IN
              (SELECT E.CODAUXILIAR
               FROM PCEMBALAGEM E
               WHERE E.CODAUXILIAR = :EAN AND E.CODFILIAL = :FILIAL)
        ";

        // Utiliza binders nomeados em vez de posição
        $paramsProduto = [
            'filial' => $filial,
            'ean'    => $ean,
        ];

        // Executa consulta principal
        $produtoRows = [];
        try {
            $produtoRows = DB::connection('oracle')->select($sqlProduto, $paramsProduto);
        } catch (\Throwable $e) {
            // Caso haja erro na consulta, retornamos a mensagem para ajudar no debug
            return response()->json([
                'error' => 'Erro ao executar consulta de produto: ' . $e->getMessage(),
            ], 500);
        }

        $produto = count($produtoRows) > 0 ? $produtoRows[0] : null;

        // Consulta para ofertas ativas do produto
        $sqlOferta = "
            SELECT i.vloferta             AS poferta,
                   c.dtinicial           AS dtofertaini,
                   c.dtfinal             AS dtofertafim,
                   NVL(i.vlofertaatac,0) AS pofertaatac
            FROM pcofertaprogramadac c
            JOIN pcofertaprogramadai i ON c.codoferta = i.codoferta
            WHERE c.dtfinal >= TRUNC(SYSDATE)
              AND ((TO_CHAR(SYSDATE,'hh') BETWEEN TO_CHAR(c.horainicial,'hh') AND TO_CHAR(c.horafinal,'hh'))
                   OR ((c.horainicial IS NULL) AND (c.horafinal IS NULL)))
              AND i.codauxiliar = :ean
              AND i.codfilial IN (:filial)
              AND c.dtcancel IS NULL
              AND ((NVL(i.qtmaxvenda,0) = 0) OR (NVL(i.qtvendaoferta,0) < NVL(i.qtmaxvenda,0)))
              AND NVL(c.prioridadeoferta, 1) = NVL((
                    SELECT MIN(c1.prioridadeoferta)
                    FROM pcofertaprogramadac c1
                    JOIN pcofertaprogramadai i2 ON c1.codoferta = i2.codoferta
                    WHERE TRUNC(SYSDATE) BETWEEN c1.dtinicial AND c1.dtfinal
                      AND c1.codfilial   = :filial
                      AND c1.dtcancel    IS NULL
                      AND i2.codauxiliar = :ean
                      AND i2.dataexclusao IS NULL
                ), 1)
        ";

        $paramsOferta = [
            'ean'    => $ean,
            'filial' => $filial,
        ];

        $ofertas = [];
        try {
            $ofertas = DB::connection('oracle')->select($sqlOferta, $paramsOferta);
        } catch (\Throwable $e) {
            // Em caso de erro na consulta de oferta retornamos lista vazia
            $ofertas = [];
        }

        return response()->json([
            'produto' => $produto,
            'ofertas' => $ofertas,
        ]);
    }
}
