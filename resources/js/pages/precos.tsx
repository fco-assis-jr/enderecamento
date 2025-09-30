/*
 * Tela de consulta de preços e ofertas de produtos.
 *
 * Esta tela permite ao usuário selecionar uma filial e informar um EAN
 * (código auxiliar) para pesquisar informações de precificação. As
 * informações retornadas incluem preços de venda (varejo e atacado),
 * dados básicos do produto, estoque disponível e ofertas ativas. Quando
 * houver uma oferta vigente, a tela informa o período e os valores
 * promocionais.
 */
import { useEffect, useState } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';

// Tipo de filial retornado pela API. Os campos são minúsculos para refletir o retorno real.
type Filial = {
    codigo: string;
    nome: string;
};

interface ProdutoDetalhado {
    descricao: string;
    codprod: string;
    destinoofertaatac: string;
    destinoofertavarejo: string;
    codauxiliar: string;
    embalagem: string;
    qtunit: number;
    unidade: string;
    situacao: string;
    proibidopravenda: string;
    foradelinha: string;
    marca: string;
    pvenda: number;
    pvendaatac: number;
    dirfotoprod: string;
    codicmtab: string | null;
    codecf: string | null;
    descricaoecf: string | null;
    pervariacaoptabela: number | null;
    produtocomestoque: string;
    produtoexcluido: string;
    qtestoquedisponivel: number;
    estoquemaster: string | null;
    informacoestecnicas: string | null;
    custo: number;
    revendafilial: string;
    revendaprod: string;
    produtoativo: string;
    custoreal: number;
    custofin: number;
    custocont: number;
    custoultent: number;
    nbm: string | null;
}

interface OfertaAtiva {
    poferta: number;
    dtofertaini: string;
    dtofertafim: string;
    pofertaatac: number;
}

export default function Precos() {
    // Estado para filiais e seleção atual
    const [filiais, setFiliais] = useState<Filial[]>([]);
    const [filialSelecionada, setFilialSelecionada] = useState('');
    const [ean, setEan] = useState('');

    // Estado para resultado da busca
    const [produto, setProduto] = useState<ProdutoDetalhado | null>(null);
    const [ofertas, setOfertas] = useState<OfertaAtiva[]>([]);
    const [carregando, setCarregando] = useState(false);

    const { props: pageProps } = usePage<{ auth: { user: { matricula: string; nome: string } } }>();
    const user = pageProps.auth.user;

    // Carrega lista de filiais ao montar o componente
    useEffect(() => {
        async function buscarFiliais() {
            try {
                const res = await axios.get('/api/filiais');
                setFiliais(res.data as Filial[]);
            } catch {
                toast.error('Erro ao carregar filiais.');
            }
        }
        buscarFiliais();
    }, []);

    // Função principal para buscar dados de produto e ofertas
    const buscarProduto = async () => {
        if (!filialSelecionada || !ean) {
            toast.warning('Selecione a filial e informe o EAN para pesquisar.');
            return;
        }
        setCarregando(true);
        try {
            const res = await axios.get('/api/produto', {
                params: { filial: filialSelecionada, ean },
            });
            setProduto(res.data.produto as ProdutoDetalhado | null);
            setOfertas(res.data.ofertas as OfertaAtiva[]);
            if (!res.data.produto) {
                toast.warning('Produto não encontrado.');
            }

        } catch (error) {
            toast.error('Erro ao buscar dados do produto.');
            setProduto(null);
            setOfertas([]);
        } finally {
            setCarregando(false);
            setEan('');

        }
    };

    // Calcula se há oferta vigente e tempo da oferta em dias
    const ofertaAtiva = ofertas && ofertas.length > 0 ? ofertas[0] : null;
    let diasOferta: number | null = null;
    if (ofertaAtiva) {
        try {
            const inicio = new Date(ofertaAtiva.dtofertaini);
            const fim = new Date(ofertaAtiva.dtofertafim);
            const diffMs = fim.getTime() - inicio.getTime();
            diasOferta = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        } catch {
            diasOferta = null;
        }
    }

    // Obtém o caminho atual de forma segura (SSR friendly)
    const pathname = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
         <div className="bg-muted/50 mx-auto mt-0 max-w-5xl space-y-6 rounded-xl p-4">
            {/* Barra superior com menu de navegação */}
             <div className="flex items-center justify-between">
                {/* Placeholder para alinhar com o botão de logout na tela de logística */}
                <a href="/logout" className="text-white transition hover:text-red-500">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        className="h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </a>

                <nav className="flex space-x-4">
                    <a
                        href="/home"
                        className={`${
                            pathname === '/home'
                                ? 'font-semibold text-white border-b border-white'
                                : 'text-white/70'
                        } px-2 py-1`}
                    >
                        Dados Logísticos
                    </a>
                    <a
                        href="/precos"
                        className={`${
                            pathname === '/precos'
                                ? 'font-semibold text-white border-b border-white'
                                : 'text-white/70'
                        } px-2 py-1`}
                    >
                        Dados do Produto
                    </a>
                </nav>
                <div className="ml-2 text-right text-[9px] leading-none font-medium text-white">
                    <div>{user.matricula}</div>
                    <div>{user.nome.split(' ')[0]}</div>
                </div>
            </div>

            {/* Seletor de filial e input de EAN */}
              <div className="flex flex-col gap-2 rounded-md bg-white/10 p-3 shadow-inner sm:flex-row sm:items-center sm:justify-between">
                <div className="flex w-full flex-col gap-2 sm:flex-row sm:gap-4">
                    <Select value={filialSelecionada} onValueChange={(val) => setFilialSelecionada(val)}>
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Selecione Filial" />
                        </SelectTrigger>
                        {/* Define altura máxima e rolagem para longas listas de filiais */}
                        <SelectContent className="max-h-60 overflow-y-auto">
                            {filiais.map((f) => (
                                <SelectItem key={f.codigo} value={f.codigo}>
                                    {f.codigo} - {f.nome}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {/* Campo EAN com botão de busca sobreposto, semelhante à tela de logística */}
                    <div className="relative w-full sm:flex-1">
                        <Input

                            type="number"
                            placeholder="EAN"
                            value={ean}
                            onChange={(e) => setEan(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && buscarProduto()}
                            className="w-full pr-10"
                        />
                        <Button
                            onClick={buscarProduto}
                            disabled={carregando}
                            className="absolute top-1/2 right-1 -translate-y-1/2 p-1 text-white"
                            size="icon"
                            variant="ghost"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                strokeWidth={1.5}
                                stroke="currentColor"
                                className="h-4 w-4"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z"
                                />
                            </svg>
                        </Button>
                    </div>
                </div>
            </div>
            {/* Exibe dados do produto se houver */}
            {produto && (
                <div className="space-y-3 border-t pt-4 font-mono text-lg">
                    <div className="text-xl font-bold tracking-tight break-words text-white uppercase">
                        {produto.codprod} - {produto.descricao}
                    </div>
                    {/* Preços de venda */}
                    <div className="flex justify-between border-t pt-3 text-[28px] font-semibold">
                        <span>Preço Venda </span>
                        <span>{produto.pvenda.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
                    </div>
                    {/* Se houver oferta ativa */}
                    {ofertaAtiva && (
                        <div className="mt-4 rounded-md bg-white/10 p-3">
                            <div className="mb-2 text-lg font-semibold ">Oferta Ativa</div>
                            <div className="flex justify-between text-[15px] font-semibold">
                                <span>Preço de Oferta</span>
                                <span>{ofertaAtiva.poferta.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
                            </div>

                            <div className="flex justify-between text-[15px] font-semibold">
                                <span>Início da Oferta</span>
                                <span>{new Date(ofertaAtiva.dtofertaini).toLocaleDateString('pt-BR')}</span>
                            </div>
                            <div className="flex justify-between text-[15px] font-semibold">
                                <span>Fim da Oferta</span>
                                <span>{new Date(ofertaAtiva.dtofertafim).toLocaleDateString('pt-BR')}</span>
                            </div>
                            {diasOferta !== null && (
                                <div className="flex justify-between text-[15px] font-semibold">
                                    <span>Duração da Oferta</span>
                                    <span>{diasOferta} dias</span>
                                </div>
                            )}
                        </div>
                    )}
                    {/* Linha de informações básicas */}
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>EAN</strong>
                        </span>
                        <span>{produto.codauxiliar}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>Unidade</strong>
                        </span>
                        <span>{produto.unidade}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>Embalagem</strong>
                        </span>
                        <span>{produto.embalagem}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>Quantidade por Unidade</strong>
                        </span>
                        <span>{produto.qtunit}</span>
                    </div>
                    {/* Estoque */}
                    <div className="flex justify-between border-t pt-3 text-[15px] font-semibold">
                        <span>Estoque  Disponivel Unid.</span>
                        <span>{produto.qtestoquedisponivel.toLocaleString('pt-BR')}</span>
                    </div>
                    <div className="flex justify-between text-[15px] font-semibold">
                        <span>Estoque Master</span>
                        <span>{produto.estoquemaster}</span>
                    </div>

                </div>
            )}
        </div>
    );
}
