'use client';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import { toast } from 'sonner';

type Produto = {
    CODPROD: string;
    DESCRICAO: string;
    EMBALAGEM: string;
    CODEPTO: string;
    DEPTO_DESCRICAO: string;
    CODSEC: string;
    SECAO_DESCRICAO: string;
    RUA: string;
    MODULO: string;
    NUMERO: string;
    APTO: string;
    QTUNIT: number;
    QTUNITCX: number;
    UNIDADE: string;
    OBS2: string;
    CODAUXILIAR: string;
    CODAUXILIAR2: string;
    QTESTGER: number;
    QTRESERV: number;
    RESERVPENDENTE: number;
    QTBLOQUEADA: number;
    QTDISPONIVEL: number;
    UNIDADEMASTER: string;
    QTEST: number;
};

export default function ConsultaProduto() {
    const [busca, setBusca] = useState('');
    const [produto, setProduto] = useState<Produto | null>(null);
    const [carregando, setCarregando] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [editando, setEditando] = useState(false);
    const [confirmarSobrescrever, setConfirmarSobrescrever] = useState(false);
    const [produtosEmConflito, setProdutosEmConflito] = useState<{ codprod: number; descricao: string }[]>([]);
    const { props: pageProps } = usePage<{ auth: { user: { matricula: string; nome: string } } }>();
    const user = pageProps.auth.user;

    const [novoEndereco, setNovoEndereco] = useState({
        rua: produto?.RUA || '',
        modulo: produto?.MODULO || '',
        numero: produto?.NUMERO || '',
        apto: produto?.APTO || '',
    });
    const camposPreenchidos =
        novoEndereco.rua.trim() !== '' && novoEndereco.modulo.trim() !== '' && novoEndereco.numero.trim() !== '' && novoEndereco.apto.trim() !== '';
    const buscarProduto = async () => {
        if (!busca.trim()) {
            toast.warning('Digite um código ou nome de produto.');
            return;
        }

        setCarregando(true);

        try {
            const response = await axios.get('/api/buscar', {
                params: { termo: busca },
            });

            if (!response.data) {
                toast.warning('Produto não encontrado.');
                setProduto(null);
                setBusca("")
            } else {
                setBusca("")
                const dados = response.data;

                const formatado: Produto = {
                    CODPROD: dados.codprod,
                    DESCRICAO: dados.descricao,
                    EMBALAGEM: dados.embalagem,
                    CODEPTO: dados.coddepto,
                    DEPTO_DESCRICAO: dados.descricaodepto,
                    CODSEC: dados.codsec,
                    SECAO_DESCRICAO: dados.descricaosecao,
                    RUA: dados.rua,
                    MODULO: dados.modulo,
                    NUMERO: dados.numero,
                    APTO: dados.apto,
                    QTUNIT: Number(dados.qtunit),
                    QTUNITCX: Number(dados.qtunitcx),
                    UNIDADE: dados.unidade,
                    OBS2: dados.obs2,
                    CODAUXILIAR: dados.codauxiliar,
                    CODAUXILIAR2: dados.codauxiliar2,
                    QTESTGER: Number(dados.qtestger),
                    QTRESERV: Number(dados.qtreserv),
                    RESERVPENDENTE: Number(dados.reservpendente),
                    QTBLOQUEADA: Number(dados.qtblocada),
                    QTDISPONIVEL: Number(dados.qtdisponivel),
                    UNIDADEMASTER: dados.unidademaster,
                    QTEST: Number(dados.qtest),
                };

                setProduto(formatado);
            }
        } catch (error: unknown) {
            setBusca("")
            if (axios.isAxiosError(error) && error.response?.data?.mensagem) {
                toast.error('Erro ao buscar produto', {
                    description: error.response.data.mensagem,
                });
            } else {
                toast.error('Erro ao buscar produto', {
                    description: 'Erro inesperado.',
                });
            }
            setProduto(null);
        } finally {
            setBusca("")
            setCarregando(false);
        }
    };
    const validarEndereco = async () => {
        try {
            const response = await axios.post('/api/endereco/validar', {
                codprod: produto?.CODPROD,
                endereco: novoEndereco,
            });

            if (response.data.status === 'validado') {
                await sobrescreverEndereco();
            }

            if (response.data.status === 'confirmar_sobrescrever_pcestend' || response.data.status === 'confirmar_sobrescrever_pcprodut') {
                setProdutosEmConflito(response.data.produtos || []);
                setConfirmarSobrescrever(true);
            }
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.data?.mensagem) {
                toast.error(error.response.data.mensagem);
            } else {
                toast.error('Erro inesperado.');
            }
        }
    };
    const sobrescreverEndereco = async () => {
        try {
            const response = await axios.post('/api/endereco/sobrescrever', {
                codprod: produto?.CODPROD,
                endereco: novoEndereco,
            });

            toast.success(response.data.mensagem || 'Endereço atualizado.');
            setShowModal(false);
            setEditando(false);
            setConfirmarSobrescrever(false);

            // Atualiza na tela o novo endereço salvo
            setProduto((prev) =>
                prev
                    ? {
                          ...prev,
                          RUA: novoEndereco.rua,
                          MODULO: novoEndereco.modulo,
                          NUMERO: novoEndereco.numero,
                          APTO: novoEndereco.apto,
                      }
                    : prev,
            );
        } catch (error) {
            if (axios.isAxiosError(error)) {
                toast.error(error.response?.data?.mensagem || 'Erro ao sobrescrever.');
            } else {
                toast.error('Erro inesperado.');
            }
        }
    };
    return (
        <div className="bg-muted/50 mx-auto mt-0 max-w-5xl space-y-6 rounded-xl p-4">
            <div className="flex items-center justify-between">
                <a href="/logout" className="text-white transition hover:text-red-500">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h2 className="flex-1 text-center text-xl font-semibold sm:text-left">Dados Logísticos</h2>
                <div className="ml-2 text-right text-[9px] leading-none font-medium text-white">
                    <div>{user.matricula}</div>
                    <div>{user.nome.split(' ')[0]}</div>
                </div>
            </div>

            <div className="flex flex-col gap-2 rounded-md bg-white/10 p-3 shadow-inner sm:flex-row sm:items-center sm:justify-between">
                <div className="relative mx-auto w-full max-w-sm">
                    <Input
                        id="busca"
                        type="number"
                        className="bg-background/80 placeholder:text-muted-foreground focus:ring-primary w-full rounded-md border border-white/30 pr-10 text-base text-white ring-1 ring-white/20 focus:ring-2"
                        placeholder="Código Produto"
                        value={busca}
                        onChange={(e) => setBusca(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && buscarProduto()}
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
            {produto && (
                <div className="mt-4 space-y-3 border-t pt-4 font-mono text-lg">
                    {/* Título com quebra para nomes grandes */}
                    <div className="text-xl font-bold tracking-tight break-words text-white uppercase">
                        {produto.CODPROD} - {produto.DESCRICAO}
                    </div>

                    {/* Campos com divisores */}
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>EAN</strong>
                        </span>
                        <span>{produto.CODAUXILIAR}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>UNIDADE</strong>
                        </span>
                        <span>{produto.UNIDADE}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>EMBALAGEM UNID.</strong>
                        </span>
                        <span>{produto.EMBALAGEM}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>QTUN</strong>
                        </span>
                        <span>{produto.QTUNIT}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>EAN CX</strong>
                        </span>
                        <span>{produto.CODAUXILIAR2}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>UNID MASTER</strong>
                        </span>
                        <span>{produto.UNIDADEMASTER || '-'}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>EMBALAGEM MASTER</strong>
                        </span>
                        <span>{produto.EMBALAGEM}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>QT CX</strong>
                        </span>
                        <span>{produto.QTUNITCX}</span>
                    </div>
                    <div className="mb-1 flex justify-between border-b border-white/20 pb-2">
                        <span>
                            <strong>FORA DE LINHA</strong>
                        </span>
                        <span>{produto.OBS2}</span>
                    </div>

                    {/* Endereço como tabela */}
                    <div className="mt-4 overflow-auto">
                        <button onClick={() => setShowModal(true)} className="w-full cursor-pointer focus:outline-none">
                            <table className="border-muted-foreground w-full overflow-hidden rounded-md border text-center text-[15px]">
                                <thead className="bg-muted text-white uppercase">
                                    <tr>
                                        <th className="px-2 py-1 font-semibold">Rua</th>
                                        <th className="px-2 py-1 font-semibold">Módulo</th>
                                        <th className="px-2 py-1 font-semibold">Número</th>
                                        <th className="px-2 py-1 font-semibold">Apto</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-background text-foreground">
                                    <tr>
                                        <td className="px-2 py-1">{produto.RUA || '00'}</td>
                                        <td className="px-2 py-1">{produto.MODULO || '00'}</td>
                                        <td className="px-2 py-1">{produto.NUMERO || '00'}</td>
                                        <td className="px-2 py-1">{produto.APTO || '00'}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </button>
                    </div>

                    {/* Estoque */}
                    <div className="flex justify-between border-t pt-3 text-[15px] font-semibold">
                        <span>Estoque Disp.</span>
                        <span>{isNaN(produto.QTEST) ? '0,00' : produto.QTEST.toLocaleString('pt-BR')}</span>
                    </div>

                    <div className="flex justify-between text-[15px] font-semibold">
                        <span>Estoque Adm.</span>
                        <span>{isNaN(produto.QTESTGER) ? '0,00' : produto.QTESTGER.toLocaleString('pt-BR')}</span>
                    </div>
                </div>
            )}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
                    <div className="bg-background relative w-11/12 max-w-sm space-y-4 rounded-xl p-6 text-white shadow-lg">
                        <button
                            onClick={() => {
                                setShowModal(false);
                                setEditando(false);
                            }}
                            className="text-muted-foreground absolute top-2 right-2 text-xl font-bold hover:text-white focus:outline-none"
                            aria-label="Fechar"
                        >
                            ×
                        </button>

                        {!editando ? (
                            <>
                                <h3 className="text-lg font-semibold">Ações do Endereço</h3>
                                <p className="text-muted-foreground text-sm">Deseja editar este endereço?</p>
                                <div className="mt-4 flex justify-end gap-2">
                                    <Button variant="default" onClick={() => setEditando(true)}>
                                        Editar
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <>
                                <h3 className="text-lg font-semibold">Editar Endereço</h3>
                                <div className="grid gap-3">
                                    <Input
                                        name="rua-custom"
                                        placeholder="Rua"
                                        type="number"
                                        autoComplete="off"
                                        value={novoEndereco.rua}
                                        onChange={(e) =>
                                            setNovoEndereco({
                                                ...novoEndereco,
                                                rua: e.target.value,
                                            })
                                        }
                                    />
                                    <Input
                                        placeholder="Módulo"
                                        name="modulo-custom"
                                        type="number"
                                        autoComplete="off"
                                        value={novoEndereco.modulo}
                                        onChange={(e) =>
                                            setNovoEndereco({
                                                ...novoEndereco,
                                                modulo: e.target.value,
                                            })
                                        }
                                    />
                                    <Input
                                        placeholder="Número"
                                        type="number"
                                        name="numero-custom"
                                        autoComplete="off"
                                        value={novoEndereco.numero}
                                        onChange={(e) =>
                                            setNovoEndereco({
                                                ...novoEndereco,
                                                numero: e.target.value,
                                            })
                                        }
                                    />
                                    <Input
                                        placeholder="Apto"
                                        name="apto-custom"
                                        type="number"
                                        autoComplete="off"
                                        value={novoEndereco.apto}
                                        onChange={(e) =>
                                            setNovoEndereco({
                                                ...novoEndereco,
                                                apto: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="mt-4 flex justify-end gap-2">
                                    <Button variant="default" onClick={validarEndereco} disabled={!camposPreenchidos}>
                                        Salvar
                                    </Button>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}
            {confirmarSobrescrever && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
                    <div className="bg-background w-11/12 max-w-sm space-y-4 rounded-xl p-6 text-white shadow-lg">
                        <h3 className="text-lg font-semibold">Confirmar sobrescrição</h3>
                        <p className="text-muted-foreground text-sm">Este endereço está ocupado pelos produtos abaixo. Deseja sobrescrever?</p>

                        <ul className="text-muted-foreground bg-muted/10 max-h-40 overflow-y-auto rounded-md border p-2 text-sm">
                            {produtosEmConflito.map((p) => (
                                <li key={p.codprod} className="border-b py-1 last:border-0">
                                    <strong>{p.codprod}</strong> - {p.descricao}
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4 flex justify-end gap-2">
                            <Button variant="destructive" onClick={sobrescreverEndereco}>
                                Sim, sobrescrever
                            </Button>
                            <Button variant="ghost" onClick={() => setConfirmarSobrescrever(false)}>
                                Cancelar
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
