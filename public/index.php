<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

iniciar_sessao();

$rota = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '/');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Permissao por tela: rotas mapeadas exigem acesso via grupos do usuario
// (msoft.UsuarioGrupoMembro -> UsuarioGrupoTelas; admin sempre passa).
$ROTAS_TELA = [
    'pedidos' => 'pedidos',
    'pedidos/imprimir' => 'pedidos',
    'pedidos/detalhe' => 'pedidos',
    'indicadores-suprimentos' => 'indicadores-suprimentos',
    'indicador-pedidos-prazo' => 'indicadores-suprimentos',
    'indicador-pedidos-prazo/pedidos' => 'indicadores-suprimentos',
    'relatorio-economico' => 'relatorio-economico',
    'relatorio-financeiro' => 'relatorio-financeiro',
    'posicao-contas' => 'posicao-contas',
    'fluxo-caixa-grupos' => 'fluxo-caixa-grupos',
    'fluxo-caixa-grupos/relatorio' => 'fluxo-caixa-grupos',
    'fluxo-caixa-aportes' => 'fluxo-caixa-aportes',
    'fluxo-caixa' => 'fluxo-caixa',
    'fluxo-caixa/imprimir' => 'fluxo-caixa',
    'fluxo-caixa/resumo' => 'fluxo-caixa',
    'matriz-prioridades' => 'matriz-prioridades',
    'followup-solicitacoes' => 'followup-solicitacoes',
    'medicoes' => 'medicoes',
    'contas-pagar' => 'contas-pagar',
    'agenda-pagamentos' => 'agenda-pagamentos',
    'agenda-pagamentos/col-order' => 'agenda-pagamentos',
    'faturamento' => 'faturamento',
    'dre-ano' => 'dre-ano',
    'dre-mes' => 'dre-mes',
    'dre-ano-arquivada' => 'dre-ano-arquivada',
    'dre-mes-arquivada' => 'dre-mes-arquivada',
];
if (isset($ROTAS_TELA[$rota])) {
    exigir_tela($ROTAS_TELA[$rota]);
}

switch ($rota) {
    case '':
        redirecionar(usuario_logado() ? 'menu' : 'login');

    case 'login':
        if (usuario_logado()) {
            redirecionar('menu');
        }
        $erro = null;
        $email = '';
        if ($metodo === 'POST') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $erro = csrf_valido()
                ? autenticar($email, (string) ($_POST['senha'] ?? ''))
                : 'Sessao expirada. Tente novamente.';
            if ($erro === null) {
                redirecionar('menu');
            }
        }
        render('login', ['titulo' => 'Login', 'erro' => $erro, 'email' => $email], 'base');
        break;

    case 'cadastro':
        // o cadastro direto foi substituido pelo fluxo de e-mail (primeiro acesso)
        redirecionar('acesso');

    case 'acesso':
        if (usuario_logado()) {
            redirecionar('menu');
        }
        $ok = false;
        if ($metodo === 'POST') {
            if (csrf_valido()) {
                processar_pedido_acesso(strtolower(trim((string) ($_POST['email'] ?? ''))));
            }
            $ok = true; // resposta neutra: nao revela se o e-mail existe ou foi processado
        }
        render('acesso', ['titulo' => 'Primeiro acesso', 'ok' => $ok], 'base');
        break;

    case 'definir-senha':
        $token = (string) ($metodo === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? ''));
        $dados = validar_token_senha($token);
        $erro = null;
        $status = null;
        $nome = '';
        if ($metodo === 'POST' && $dados) {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            if (!csrf_valido()) {
                $erro = 'Sessao expirada. Tente novamente.';
            } else {
                $r = definir_senha_por_token($dados, $nome, (string) ($_POST['senha'] ?? ''), (string) ($_POST['confirma'] ?? ''));
                if (isset($r['erro'])) {
                    $erro = $r['erro'];
                } else {
                    $status = $r['status'];
                    $dados = null; // senha gravada: nao renderiza mais o formulario
                }
            }
        }
        render('definir_senha', ['titulo' => 'Definir senha', 'dados' => $dados, 'token' => $token,
            'erro' => $erro, 'status' => $status, 'nome' => $nome], 'base');
        break;

    case 'configuracoes':
    case 'usuarios':
        exigir_admin();
        if ($metodo === 'POST' && ($_POST['acao'] ?? '') === 'criar_usuario') {
            if (!csrf_valido()) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
                redirecionar('configuracoes');
            }
            $res = criar_usuario_sistema($_POST);
            definir_aviso($res);
            $dest = !empty($res['id']) ? 'configuracoes?usuario=' . (int) $res['id'] : 'configuracoes';
            redirecionar($dest);
        }
        $usuarioSel = isset($_GET['usuario']) ? usuario_get((int) $_GET['usuario']) : null;
        render('configuracoes', [
            'titulo' => 'Configuracoes · Usuarios e Permissoes',
            'active' => $rota,
            'aviso' => consumir_aviso(),
            'usuarioSel' => $usuarioSel,
            'telasDisp' => telas_disponiveis(),
        ] + listar_usuarios());
        break;

    case 'grupos-usuarios':
        exigir_admin();
        if ($metodo === 'POST') {
            if (!csrf_valido()) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
                redirecionar('grupos-usuarios');
            }
            $acao = (string) ($_POST['acao'] ?? 'inserir');
            $id = (int) ($_POST['id'] ?? 0);
            $destino = 'grupos-usuarios';
            try {
                if ($acao === 'excluir') {
                    ug_excluir($id);
                    definir_aviso(['tipo' => 'ok', 'html' => 'Grupo excluido.']);
                } elseif ($acao === 'telas') {
                    $n = ug_salvar_telas($id, (array) ($_POST['perm'] ?? []));
                    definir_aviso(['tipo' => 'ok', 'html' =>
                        "Permissoes do grupo atualizadas ($n tela(s))."]);
                    $destino = 'grupos-usuarios?permissoes=' . $id;
                } else {
                    $d = ug_validar($_POST);
                    if ($acao === 'editar') {
                        ug_atualizar($id, $d);
                        definir_aviso(['tipo' => 'ok', 'html' => 'Grupo atualizado.']);
                    } else {
                        $novo = ug_inserir($d);
                        definir_aviso(['tipo' => 'ok', 'html' =>
                            'Grupo cadastrado. Agora marque as telas e funcoes.']);
                        $destino = 'grupos-usuarios?permissoes=' . $novo;
                    }
                }
            } catch (InvalidArgumentException $e) {
                definir_aviso(['tipo' => 'erro', 'html' => e($e->getMessage())]);
            } catch (Throwable $e) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Erro ao salvar: ' . e($e->getMessage())]);
            }
            redirecionar($destino);
        }
        $edicao = isset($_GET['editar']) ? ug_get((int) $_GET['editar']) : null;
        $grupoSel = isset($_GET['permissoes']) ? ug_get((int) $_GET['permissoes']) : null;
        render('grupos_usuarios', ['titulo' => 'Grupos de Usuarios',
            'active' => 'grupos-usuarios', 'aviso' => consumir_aviso(),
            'grupos' => ug_listar(), 'edicao' => $edicao, 'grupoSel' => $grupoSel,
            'telasGrp' => telas_disponiveis_agrupadas(),
            'permGrupo' => $grupoSel ? ug_telas((int) $grupoSel['UsGrupo_Id']) : [],
            'membros' => $grupoSel ? ug_membros((int) $grupoSel['UsGrupo_Id']) : []]);
        break;

    case 'dicas':
        exigir_admin();
        if ($metodo === 'POST') {
            if (!csrf_valido()) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
                redirecionar('dicas');
            }
            $acao = (string) ($_POST['acao'] ?? 'inserir');
            $id = (int) ($_POST['id'] ?? 0);
            try {
                if ($acao === 'excluir') {
                    dc_excluir($id);
                    definir_aviso(['tipo' => 'ok', 'html' => 'Dica excluida.']);
                } else {
                    $d = dc_validar($_POST);
                    if ($acao === 'editar') {
                        dc_atualizar($id, $d);
                        definir_aviso(['tipo' => 'ok', 'html' => 'Dica atualizada.']);
                    } else {
                        dc_inserir($d);
                        definir_aviso(['tipo' => 'ok', 'html' => 'Dica cadastrada.']);
                    }
                }
            } catch (InvalidArgumentException $e) {
                definir_aviso(['tipo' => 'erro', 'html' => e($e->getMessage())]);
            } catch (Throwable $e) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Erro ao salvar: ' . e($e->getMessage())]);
            }
            redirecionar('dicas');
        }
        render('dicas', ['titulo' => 'Dicas',
            'active' => 'dicas', 'aviso' => consumir_aviso(),
            'dicas' => dc_listar(),
            'edicao' => isset($_GET['editar']) ? dc_get((int) $_GET['editar']) : null]);
        break;

    case 'dica':
        // Dica aleatoria em JSON p/ o modal de progresso (?area=financeiro ...)
        exigir_login();
        session_write_close(); // nao segurar o lock da sessao durante o calculo
        header('Content-Type: application/json; charset=utf-8');
        try {
            echo json_encode(['dica' => dc_aleatoria((string) ($_GET['area'] ?? ''))],
                JSON_UNESCAPED_UNICODE);
        } catch (Throwable $t) {
            echo json_encode(['dica' => null]);
        }
        exit;

    case 'pedidos':
        exigir_login();
        $filtros = [
            'data_ini' => (string) ($_GET['data_ini'] ?? ''),
            'data_fim' => (string) ($_GET['data_fim'] ?? ''),
            'data_corte' => (string) ($_GET['data_corte'] ?? ''),
            'pedido' => trim((string) ($_GET['pedido'] ?? '')),
            'fornecedor' => trim((string) ($_GET['fornecedor'] ?? '')),
            'centro' => trim((string) ($_GET['centro'] ?? '')),
            'nucleo' => trim((string) ($_GET['nucleo'] ?? '')),
            'insumo' => trim((string) ($_GET['insumo'] ?? '')),
            'situacao' => (string) ($_GET['situacao'] ?? ''),
        ];
        // Abertura da tela (URL sem parametros): padrao = pedidos dos ultimos 30 dias.
        // Enviar o formulario com a data vazia volta a listar todos os pedidos.
        if (!$_GET) {
            $filtros['data_ini'] = date('Y-m-d', strtotime('-30 days'));
        }
        render('pedidos', ['titulo' => 'Pedidos', 'active' => 'pedidos', 'filtros' => $filtros]
            + dados_tela_pedidos($filtros));
        break;

    case 'pedidos/detalhe':
        exigir_login();
        $base = strtoupper((string) ($_GET['base'] ?? 'CONV')) === 'MGA' ? 'MGA' : 'CONV';
        $det = pedido_detalhe($base, trim((string) ($_GET['pedido'] ?? '')));
        render('pedido_detalhe', ['titulo' => 'Pedido ' . ($det['cab']['Pedido_CentroNumero'] ?? ''),
            'det' => $det], 'base');
        break;

    case 'pedidos/imprimir':
        exigir_login();
        extract(dados_impressao_pedidos(pedidos_da_url()));
        require ROOT . '/views/pedido_impressao.php';
        break;

    case 'indicadores-suprimentos':
        exigir_login();
        render('indicadores_suprimentos', ['titulo' => 'Indicadores - Suprimentos',
            'active' => 'indicadores-suprimentos']);
        break;

    case 'indicador-pedidos-prazo':
        exigir_login();
        $dataIni = (string) ($_GET['data_ini'] ?? '');
        $dataFim = (string) ($_GET['data_fim'] ?? '');
        // Abertura da tela (URL sem parametros): padrao = ultimos 30 dias.
        if (!$_GET) {
            $dataIni = date('Y-m-d', strtotime('-30 days'));
        }
        render('indicador_pedidos_prazo', ['titulo' => 'Indice de Pedidos Autorizados solicitados no prazo',
            'active' => 'indicadores-suprimentos', 'data_ini' => $dataIni, 'data_fim' => $dataFim]
            + dados_indicador_pedidos_prazo($dataIni, $dataFim));
        break;

    case 'indicador-pedidos-prazo/pedidos':
        exigir_login();
        $centro = (string) ($_GET['centro'] ?? '');
        $d = dados_indicador_pedidos_prazo(
            (string) ($_GET['data_ini'] ?? ''), (string) ($_GET['data_fim'] ?? ''));
        $lista = array_values(array_filter($d['linhas'], fn($l) => $l['centro'] === $centro));
        render('indicador_pedidos_prazo_lista', ['titulo' => 'Pedidos - ' . $centro,
            'centro' => $centro, 'linhas' => $lista, 'erro' => $d['erro'],
            'data_ini' => (string) ($_GET['data_ini'] ?? ''), 'data_fim' => (string) ($_GET['data_fim'] ?? '')], 'base');
        break;

    case 'relatorio-economico':
        exigir_login();
        $base = strtoupper((string) ($_GET['base'] ?? 'MGA')) === 'CONV' ? 'CONV' : 'MGA';
        $gerado = isset($_GET['gerar']);
        $opcoes = [
            'empresa'        => trim((string) ($_GET['empresa'] ?? '')),
            'data_ini'       => (string) ($_GET['data_ini'] ?? ''),
            'data_fim'       => (string) ($_GET['data_fim'] ?? ''),
            'centros'        => match ($_GET['centro_op'] ?? 'todos') {
                'apenas'       => array_filter([trim((string) ($_GET['centro'] ?? ''))]),
                'selecionados' => array_values(array_filter((array) ($_GET['centros_sel'] ?? []))),
                default        => [],
            },
            'nucleos'        => match ($_GET['nucleo_op'] ?? 'todos') {
                'apenas'       => array_filter([trim((string) ($_GET['nucleo'] ?? ''))]),
                'selecionados' => array_values(array_filter((array) ($_GET['nucleos_sel'] ?? []))),
                default        => [],
            },
            'inc_inativos'   => isset($_GET['inc_inativos']),
            'sin_classe'     => isset($_GET['sin_classe']),
            'sin_assunto'    => isset($_GET['sin_assunto']),
            'sin_grupo'      => isset($_GET['sin_grupo']),
            'sec_receitas'   => !isset($_GET['sec_receitas']) ? false : true,
            'sec_despesas'   => !isset($_GET['sec_despesas']) ? false : true,
            'sec_sociedade'  => !isset($_GET['sec_sociedade']) ? false : true,
            'sec_outros'     => !isset($_GET['sec_outros']) ? false : true,
            'det_centro'     => isset($_GET['det_centro']),
            'apenas_periodo' => isset($_GET['apenas_periodo']),
            'medicao'        => isset($_GET['medicao']),
        ];
        if ($gerado && !in_array(true, [$opcoes['sec_receitas'], $opcoes['sec_despesas'],
                $opcoes['sec_sociedade'], $opcoes['sec_outros']], true)) {
            $opcoes['sec_receitas'] = $opcoes['sec_despesas'] =
            $opcoes['sec_sociedade'] = $opcoes['sec_outros'] = true;
        }
        $dados = null;
        $erro = '';
        if ($gerado) {
            try {
                $dados = relatorio_economico_dados($base, $opcoes);
            } catch (Throwable $t) {
                $erro = 'Erro ao gerar o relatorio: ' . $t->getMessage();
            }
        } else {
            try {
                $centros = relatorio_economico_centros($base, $opcoes['inc_inativos']);
                $nucleos = relatorio_economico_nucleos($base);
                $empresas = relatorio_economico_empresas($base);
            } catch (Throwable $t) {
                $centros = [];
                $nucleos = [];
                $empresas = [['Empresa_Cod' => 'CGC', 'Empresa_Nome' => 'C. G. Construcoes']];
                $erro = 'Erro ao listar filtros: ' . $t->getMessage();
            }
        }
        render('relatorio_economico', ['titulo' => 'Relatorio Economico',
            'active' => 'relatorio-economico', 'gerado' => $gerado, 'opcoes' => $opcoes,
            'centros' => $centros ?? [], 'nucleos' => $nucleos ?? [],
            'empresas' => $empresas ?? [], 'dados' => $dados, 'erro' => $erro]);
        break;

    case 'matriz-prioridades':
        exigir_login();
        if ($metodo === 'POST') {
            if (!csrf_valido()) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
                redirecionar('matriz-prioridades');
            }
            $acao = (string) ($_POST['acao'] ?? 'inserir');
            $id = (int) ($_POST['id'] ?? 0);
            try {
                if ($acao === 'excluir') {
                    exigir_tela('matriz-prioridades', 'excluir');
                    mp_excluir($id);
                    definir_aviso(['tipo' => 'ok', 'html' => 'Cadastro excluido.']);
                } else {
                    exigir_tela('matriz-prioridades', $acao === 'editar' ? 'alterar' : 'inserir');
                    $dados = mp_validar($_POST);
                    if ($acao === 'editar') {
                        mp_atualizar($id, $dados);
                        definir_aviso(['tipo' => 'ok', 'html' => 'Cadastro atualizado.']);
                    } else {
                        mp_inserir($dados);
                        definir_aviso(['tipo' => 'ok', 'html' => 'Cadastro incluido.']);
                    }
                }
            } catch (InvalidArgumentException $e) {
                definir_aviso(['tipo' => 'erro', 'html' => e($e->getMessage())]);
            } catch (Throwable $e) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Erro ao salvar: ' . e($e->getMessage())]);
            }
            redirecionar('matriz-prioridades');
        }
        $edicao = isset($_GET['editar']) && usuario_pode('matriz-prioridades', 'alterar')
            ? mp_get((int) $_GET['editar']) : null;
        render('matriz_prioridades', ['titulo' => 'Matriz de Prioridades',
            'active' => 'matriz-prioridades', 'aviso' => consumir_aviso(),
            'registros' => mp_listar(), 'centros' => mp_centros(),
            'edicao' => $edicao,
            'podeInserir' => usuario_pode('matriz-prioridades', 'inserir'),
            'podeAlterar' => usuario_pode('matriz-prioridades', 'alterar'),
            'podeExcluir' => usuario_pode('matriz-prioridades', 'excluir')]);
        break;

    case 'followup-solicitacoes':
        exigir_login();
        $gerado = isset($_GET['gerar']);
        $f = [
            'data_ini'   => (string) ($_GET['data_ini'] ?? date('Y-m-d', strtotime('-30 days'))),
            'data_fim'   => (string) ($_GET['data_fim'] ?? date('Y-m-d')),
            'empresa'    => trim((string) ($_GET['empresa'] ?? '')),
            'filial'     => trim((string) ($_GET['filial'] ?? '')),
            'centro'     => trim((string) ($_GET['centro'] ?? '')),
            'nucleo'     => trim((string) ($_GET['nucleo'] ?? '')),
            'solicitante' => trim((string) ($_GET['solicitante'] ?? '')),
            'comprador'  => trim((string) ($_GET['comprador'] ?? '')),
            'tipsol'     => trim((string) ($_GET['tipsol'] ?? '')),
            'depsol'     => trim((string) ($_GET['depsol'] ?? '')),
            'numero'     => trim((string) ($_GET['numero'] ?? '')),
            'texto'      => trim((string) ($_GET['texto'] ?? '')),
            'insumo'     => trim((string) ($_GET['insumo'] ?? '')),
            'nao_concluidas' => isset($_GET['nao_concluidas']),
            'autorizadas' => (string) ($_GET['autorizadas'] ?? ''),
            'recusadas'   => (string) ($_GET['recusadas'] ?? ''),
            'agrupar'     => (string) ($_GET['agrupar'] ?? 'centro'),
            'sit' => [
                'ab'  => isset($_GET['sit_ab']) || !$gerado,
                'co'  => isset($_GET['sit_co']) || !$gerado,
                'pe'  => isset($_GET['sit_pe']),
                'ar'  => isset($_GET['sit_ar']),
                'arp' => isset($_GET['sit_arp']),
            ],
        ];
        $linhas = [];
        $grupos = [];
        $erros = [];
        $combos = [];
        try {
            $combos = flw_combos();
        } catch (Throwable $t) {
            $erros[] = 'Erro ao listar filtros: ' . $t->getMessage();
        }
        if ($gerado) {
            $f['empresa_in'] = flw_resolver($f['empresa'], $combos['empresas'] ?? []);
            $f['centro_in'] = flw_resolver($f['centro'], $combos['centros'] ?? [], true);
            try {
                $r = flw_dados($f);
                $linhas = flw_enriquecer($r['linhas']);
                if ($f['atraso_min'] = (int) ($_GET['atraso_min'] ?? 0)) {
                    $linhas = array_values(array_filter($linhas,
                        fn($l) => $l['Atrasada'] && $l['Atraso'] >= $f['atraso_min']));
                }
                $grupos = flw_agrupar($linhas, $f['agrupar']);
                $erros = array_merge($erros, $r['erros']);
            } catch (Throwable $t) {
                $erros[] = $t->getMessage();
            }
        }
        render('followup_solicitacoes', [
            'titulo' => 'Follow-up das Solicitações',
            'active' => 'followup-solicitacoes', 'f' => $f, 'gerado' => $gerado,
            'linhas' => $linhas, 'grupos' => $grupos, 'erros' => $erros,
            'combos' => $combos]);
        break;

    case 'medicoes':
        exigir_login();
        if ($metodo === 'POST') {
            if (!csrf_valido()) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
                redirecionar('medicoes');
            }
            $acao = (string) ($_POST['acao'] ?? '');
            $id = (int) ($_POST['id'] ?? 0);
            $destino = 'medicoes';
            try {
                if ($acao === 'incluir_centro') {
                    exigir_tela('medicoes', 'inserir');
                    $novo = med_incluir_centro((string) ($_POST['centro'] ?? ''));
                    definir_aviso(['tipo' => 'ok', 'html' => 'Centro de custo incluido na previsao.']);
                    $destino = 'medicoes?centro=' . $novo;
                } elseif ($acao === 'excluir_centro') {
                    exigir_tela('medicoes', 'excluir');
                    med_excluir_centro($id);
                    definir_aviso(['tipo' => 'ok', 'html' => 'Centro de custo removido da previsao.']);
                } elseif ($acao === 'incluir_medicao') {
                    exigir_tela('medicoes', 'inserir');
                    med_incluir_medicao($id, $_POST);
                    definir_aviso(['tipo' => 'ok', 'html' => 'Medicao incluida.']);
                    $destino = 'medicoes?centro=' . $id;
                } elseif ($acao === 'alterar_medicao') {
                    exigir_tela('medicoes', 'alterar');
                    med_atualizar_medicao($id, (int) ($_POST['medicao_id'] ?? 0), $_POST);
                    definir_aviso(['tipo' => 'ok', 'html' => 'Medicao alterada.']);
                    $destino = 'medicoes?centro=' . $id;
                } elseif ($acao === 'excluir_medicao') {
                    exigir_tela('medicoes', 'excluir');
                    med_excluir_medicao($id, (int) ($_POST['medicao_id'] ?? 0));
                    definir_aviso(['tipo' => 'ok', 'html' => 'Medicao excluida.']);
                    $destino = 'medicoes?centro=' . $id;
                } else {
                    definir_aviso(['tipo' => 'erro', 'html' => 'Acao invalida.']);
                }
            } catch (InvalidArgumentException $e) {
                definir_aviso(['tipo' => 'erro', 'html' => e($e->getMessage())]);
                if ($id) { $destino = 'medicoes?centro=' . $id; }
            } catch (Throwable $e) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Erro ao salvar: ' . e($e->getMessage())]);
            }
            redirecionar($destino);
        }
        $centroAberto = isset($_GET['centro']) ? med_get_centro((int) $_GET['centro']) : null;
        $edicao = $centroAberto && isset($_GET['editar'])
            ? med_get_medicao((int) $centroAberto['PMedC_Id'], (int) $_GET['editar'])
            : null;
        render('medicoes', ['titulo' => 'Previsao de Medicoes',
            'active' => 'medicoes', 'aviso' => consumir_aviso(),
            'centros' => med_listar(), 'centrosDisp' => mp_centros(),
            'centroAberto' => $centroAberto,
            'medicoes' => $centroAberto
                ? med_medicoes((int) $centroAberto['PMedC_Id']) : [],
            'edicao' => $edicao,
            'podeInserir' => usuario_pode('medicoes', 'inserir'),
            'podeAlterar' => usuario_pode('medicoes', 'alterar'),
            'podeExcluir' => usuario_pode('medicoes', 'excluir')]);
        break;

    case 'fluxo-caixa-grupos':
        exigir_login();
        if ($metodo === 'POST') {
            if (!csrf_valido()) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
                redirecionar('fluxo-caixa-grupos');
            }
            $acao = (string) ($_POST['acao'] ?? 'inserir');
            $id = (int) ($_POST['id'] ?? 0);
            $destino = 'fluxo-caixa-grupos';
            try {
                if ($acao === 'excluir') {
                    exigir_tela('fluxo-caixa-grupos', 'excluir');
                    fc_excluir($id);
                    definir_aviso(['tipo' => 'ok', 'html' => 'Grupo excluido.']);
                } elseif ($acao === 'incluir_avulso' || $acao === 'excluir_avulso') {
                    exigir_tela('fluxo-caixa-grupos', 'alterar');
                    if ($acao === 'incluir_avulso') {
                        fc_incluir_avulso($id, (string) ($_POST['descricao'] ?? ''));
                        definir_aviso(['tipo' => 'ok', 'html' => 'Valor Avulso incluido no grupo.']);
                    } else {
                        fc_excluir_avulso($id, (int) ($_POST['avulso_id'] ?? 0));
                        definir_aviso(['tipo' => 'ok', 'html' => 'Valor Avulso removido do grupo.']);
                    }
                    $destino = 'fluxo-caixa-grupos?centros=' . $id;
                } elseif ($acao === 'incluir_centro' || $acao === 'excluir_centro') {
                    exigir_tela('fluxo-caixa-grupos', 'alterar');
                    if ($acao === 'incluir_centro') {
                        $r = fc_incluir_centros($id, (array) ($_POST['centros'] ?? []),
                                                trim((string) ($_POST['centro'] ?? '')));
                        $msg = $r['ok'] === 0
                            ? 'Selecione ou digite um centro de custo.'
                            : ($r['ok'] === 1 ? '1 centro adicionado ao grupo.'
                                              : $r['ok'] . ' centros adicionados ao grupo.');
                        if ($r['erros']) {
                            $msg .= ' Nao incluidos: ' . e(implode(' | ', $r['erros']));
                        }
                        definir_aviso(['tipo' => $r['ok'] > 0 ? 'ok' : 'erro', 'html' => $msg]);
                    } else {
                        fc_excluir_centro($id, (string) ($_POST['centro'] ?? ''));
                        definir_aviso(['tipo' => 'ok', 'html' => 'Centro removido do grupo.']);
                    }
                    $destino = 'fluxo-caixa-grupos?centros=' . $id;
                } else {
                    $d = fc_validar($_POST);
                    if ($acao === 'editar') {
                        exigir_tela('fluxo-caixa-grupos', 'alterar');
                        fc_atualizar($id, $d);
                        definir_aviso(['tipo' => 'ok', 'html' => 'Grupo atualizado.']);
                    } else {
                        exigir_tela('fluxo-caixa-grupos', 'inserir');
                        $novo = fc_inserir($d);
                        definir_aviso(['tipo' => 'ok', 'html' =>
                            'Grupo cadastrado. Agora selecione os centros de custo.']);
                        redirecionar('fluxo-caixa-grupos?centros=' . $novo);
                    }
                }
            } catch (InvalidArgumentException $e) {
                definir_aviso(['tipo' => 'erro', 'html' => e($e->getMessage())]);
                if ($id && in_array($acao, ['incluir_centro', 'excluir_centro',
                        'incluir_avulso', 'excluir_avulso'], true)) {
                    $destino = 'fluxo-caixa-grupos?centros=' . $id;
                }
            } catch (Throwable $e) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Erro ao salvar: ' . e($e->getMessage())]);
            }
            redirecionar($destino);
        }
        $edicao = isset($_GET['editar']) && usuario_pode('fluxo-caixa-grupos', 'alterar')
            ? fc_get((int) $_GET['editar']) : null;
        $grupoCentros = isset($_GET['centros']) && usuario_pode('fluxo-caixa-grupos', 'alterar')
            ? fc_get((int) $_GET['centros']) : null;
        render('fluxo_caixa_grupos', ['titulo' => 'Grupos de Centros',
            'active' => 'fluxo-caixa-grupos', 'aviso' => consumir_aviso(),
            'grupos' => fc_listar(), 'centros' => fc_centros(),
            'ocupados' => fc_centros_em_grupos(),
            'edicao' => $edicao, 'grupoCentros' => $grupoCentros,
            'centrosDoGrupo' => $grupoCentros
                ? fc_centros_do_grupo((int) $grupoCentros['FCGrupo_Id']) : [],
            'avulsosDoGrupo' => $grupoCentros
                ? fc_avulsos_do_grupo((int) $grupoCentros['FCGrupo_Id']) : [],
            'podeInserir' => usuario_pode('fluxo-caixa-grupos', 'inserir'),
            'podeAlterar' => usuario_pode('fluxo-caixa-grupos', 'alterar'),
            'podeExcluir' => usuario_pode('fluxo-caixa-grupos', 'excluir')]);
        break;

    case 'fluxo-caixa-aportes':
        exigir_login();
        if ($metodo === 'POST') {
            if (!csrf_valido()) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
                redirecionar('fluxo-caixa-aportes');
            }
            $acao = (string) ($_POST['acao'] ?? 'inserir');
            $id = (int) ($_POST['id'] ?? 0);          // FCGrupo_Id
            $aporteId = (int) ($_POST['aporte_id'] ?? 0);
            $destino = 'fluxo-caixa-aportes' . ($id ? "?grupo=$id" : '');
            try {
                // o aporte tem que pertencer ao grupo do formulario
                if (in_array($acao, ['editar', 'excluir'], true)) {
                    $ap = fca_get($aporteId);
                    if (!$ap || (int) $ap['FCGrupo_Id'] !== $id) {
                        throw new InvalidArgumentException('Aporte nao encontrado neste grupo.');
                    }
                }
                if ($acao === 'excluir') {
                    exigir_tela('fluxo-caixa-aportes', 'excluir');
                    fca_excluir($aporteId);
                    definir_aviso(['tipo' => 'ok', 'html' => 'Aporte excluido.']);
                } else {
                    $d = fca_validar($_POST);
                    if ($acao === 'editar') {
                        exigir_tela('fluxo-caixa-aportes', 'alterar');
                        fca_atualizar($aporteId, $d);
                        definir_aviso(['tipo' => 'ok', 'html' => 'Aporte atualizado.']);
                    } else {
                        exigir_tela('fluxo-caixa-aportes', 'inserir');
                        fca_inserir($id, $d);
                        definir_aviso(['tipo' => 'ok', 'html' => 'Aporte incluido no grupo.']);
                    }
                }
            } catch (InvalidArgumentException $e) {
                definir_aviso(['tipo' => 'erro', 'html' => e($e->getMessage())]);
            } catch (Throwable $e) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Erro ao salvar: ' . e($e->getMessage())]);
            }
            redirecionar($destino);
        }
        $grupoSel = isset($_GET['grupo']) ? fc_get((int) $_GET['grupo']) : null;
        $edicao = $grupoSel && isset($_GET['editar'])
                  && usuario_pode('fluxo-caixa-aportes', 'alterar')
            ? fca_get((int) $_GET['editar']) : null;
        if ($edicao && (int) $edicao['FCGrupo_Id'] !== (int) $grupoSel['FCGrupo_Id']) {
            $edicao = null; // aporte de outro grupo
        }
        render('fluxo_caixa_aportes', ['titulo' => 'Aportes a Retornar',
            'active' => 'fluxo-caixa-aportes', 'aviso' => consumir_aviso(),
            'grupos' => fca_listar_grupos(), 'grupoSel' => $grupoSel,
            'edicao' => $edicao,
            'aportes' => $grupoSel ? fca_aportes_do_grupo((int) $grupoSel['FCGrupo_Id']) : [],
            'podeInserir' => usuario_pode('fluxo-caixa-aportes', 'inserir'),
            'podeAlterar' => usuario_pode('fluxo-caixa-aportes', 'alterar'),
            'podeExcluir' => usuario_pode('fluxo-caixa-aportes', 'excluir')]);
        break;

    case 'fluxo-caixa':
        exigir_login();
        if ($metodo === 'POST') {
            if (!csrf_valido()) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
                redirecionar('fluxo-caixa');
            }
            // Setas de ordenacao (acao=mover) — nao precisam de periodo.
            if (($_POST['acao'] ?? '') === 'mover') {
                exigir_tela('fluxo-caixa', 'alterar');
                fcs_mover((int) ($_POST['id'] ?? 0), (string) ($_POST['dir'] ?? ''));
                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => true]);
                    exit;
                }
                redirecionar('fluxo-caixa');
            }
            // Arrastar e soltar: recebe a ordem completa das linhas.
            if (($_POST['acao'] ?? '') === 'reordenar') {
                exigir_tela('fluxo-caixa', 'alterar');
                fcs_reordenar((array) ($_POST['ordem'] ?? []));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true]);
                exit;
            }
            // Checkbox Exibir (item 3): liga/desliga a visibilidade do
            // grupo em TODOS os itens — tela, totais e recalculo.
            if (($_POST['acao'] ?? '') === 'exibir') {
                exigir_tela('fluxo-caixa', 'alterar');
                fcs_exibir_definir((int) ($_POST['id'] ?? 0), !empty($_POST['v']));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true]);
                exit;
            }
            if (($_POST['acao'] ?? '') === 'exibir_todos') {
                exigir_tela('fluxo-caixa', 'alterar');
                fcs_exibir_todos(!empty($_POST['v']));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true]);
                exit;
            }
            // Bases: formulario da tela enviado sem nenhuma marcada.
            if (fcs_bases_post() === []) {
                $msgBases = 'Marque ao menos uma base (DBMGA / DBCONV).';
                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'erro' => $msgBases]);
                    exit;
                }
                definir_aviso(['tipo' => 'erro', 'html' => e($msgBases)]);
                redirecionar('fluxo-caixa');
            }
            $ini = trim((string) ($_POST['data_ini'] ?? ''));
            $fim = trim((string) ($_POST['data_fim'] ?? ''));
            // Recalculo do item 5 (contas a pagar) — dispara so pelo Atualizar.
            if (!empty($_POST['ajax']) && ($_POST['acao'] ?? '') === 'pagar') {
                header('Content-Type: application/json; charset=utf-8');
                exigir_tela('fluxo-caixa', 'alterar');
                set_time_limit(600);
                // libera o lock da sessao p/ o fetch das dicas nao ficar bloqueado
                session_write_close();
                try {
                    // item 5 antes: as projecoes (itens 3 e 1) leem o snapshot dele
                    fcs_pagar_calcular_salvar(fcs_listar());
                    fcs_aportes_calcular_salvar();
                    // projecao do item 3: so dos grupos atualizados neste Atualizar
                    fcs_projecao_calcular_salvar(
                        array_filter(array_map('intval', (array) ($_POST['grupos'] ?? []))));
                    $rPos = fcs_posicao_calcular_salvar();
                    $erro = $rPos['erros']
                        ? 'Item 1 falhou: ' . implode(' | ', $rPos['erros']) : null;
                    echo json_encode(['ok' => $erro === null, 'erro' => $erro]);
                } catch (Throwable $t) {
                    echo json_encode(['ok' => false, 'erro' => $t->getMessage()]);
                }
                exit;
            }
            // Chamada AJAX da barra de progresso: um grupo por requisicao.
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                $id = (int) ($_POST['grupo'] ?? 0);
                if (!$id || $ini === '' || $fim === '' || $ini > $fim) {
                    echo json_encode(['ok' => false, 'erro' => 'Parametros invalidos.']);
                    exit;
                }
                exigir_tela('fluxo-caixa', 'alterar');
                set_time_limit(600); // a proc roda 2x por centro do grupo
                // libera o lock da sessao p/ o fetch das dicas nao ficar bloqueado
                session_write_close();
                $avMap = [];
                foreach ((array) ($_POST['avulso'][$id] ?? []) as $aid => $v) {
                    if (trim((string) $v) !== '') {
                        $avMap[(int) $aid] = fcs_valor($v);
                    }
                }
                fcs_salvar_avulsos($avMap);
                $r = fcs_atualizar_saldos([$id], $ini, $fim, [$id => $avMap]);
                echo json_encode(['ok' => $r['ok'] > 0, 'erro' => $r['erros'][0] ?? null]);
                exit;
            }
            $ids = array_filter(array_map('intval', (array) ($_POST['grupos'] ?? [])));
            if (!$ids) {
                // Nada a calcular: o botao recalcula o item 5
                // (quem pode alterar) e abre o resumo.
                if (usuario_pode('fluxo-caixa', 'alterar')) {
                    set_time_limit(600);
                    fcs_pagar_calcular_salvar(fcs_listar());
                    fcs_aportes_calcular_salvar();
                    fcs_posicao_calcular_salvar();
                }
                redirecionar('fluxo-caixa/resumo');
            } elseif ($ini === '' || $fim === '') {
                definir_aviso(['tipo' => 'erro', 'html' => 'Informe o periodo (De / a).']);
            } elseif ($ini > $fim) {
                definir_aviso(['tipo' => 'erro', 'html' => 'Data inicial maior que a final.']);
            } else {
                // Valores avulsos digitados: $_POST['avulso'][grupo][avulso_id]
                $avPost = [];
                foreach ((array) ($_POST['avulso'] ?? []) as $gid => $vals) {
                    foreach ((array) $vals as $aid => $v) {
                        if (trim((string) $v) !== '') {
                            $avPost[(int) $gid][(int) $aid] = fcs_valor($v);
                        }
                    }
                }
                $avFlat = [];
                foreach ($avPost as $vals) {
                    $avFlat += $vals;
                }
                fcs_salvar_avulsos($avFlat);
                if ($ids) {
                    exigir_tela('fluxo-caixa', 'alterar');
                    set_time_limit(600); // a proc roda 2x por centro do grupo
                    $r = fcs_atualizar_saldos(array_values($ids), $ini, $fim, $avPost);
                    fcs_pagar_calcular_salvar(fcs_listar());
                    fcs_aportes_calcular_salvar();
                    fcs_projecao_calcular_salvar(array_values($ids));
                    fcs_posicao_calcular_salvar();
                    $msg = $r['ok'] === 1 ? '1 grupo atualizado.'
                                         : $r['ok'] . ' grupos atualizados.';
                    if ($r['erros']) {
                        $msg .= ' Falhas: ' . e(implode(' | ', $r['erros']));
                    }
                    definir_aviso(['tipo' => $r['ok'] > 0 ? 'ok' : 'erro', 'html' => $msg]);
                }
                redirecionar('fluxo-caixa/resumo');
            }
            redirecionar('fluxo-caixa');
        }
        $grupos = fcs_listar();
        $nVis = count(fcs_grupos_visiveis($grupos));
        if (empty($_GET['keep']) && $nVis !== count($grupos)) {
            // entrada na tela: o Exibir volta tudo marcado por padrao;
            // so a navegacao com ?keep=1 (reload do proprio toggle) mantem
            fcs_exibir_todos(true);
            $grupos = fcs_listar();
            $nVis = count($grupos);
        }
        render('fluxo_caixa', ['titulo' => 'Acompanhamento Financeiro',
            'active' => 'fluxo-caixa', 'aviso' => consumir_aviso(),
            'grupos' => $grupos, // a tela de filtro lista todos; o Exibir so oculta do relatorio
            'temOcultos' => $nVis !== count($grupos),
            'fcPag' => fcs_pagar_salvo($grupos),
            'fcRec' => fcs_receber_dados(),
            'fcPos' => fcs_posicao_salva(),
            'fcApo' => fcs_aportes_salvo($grupos),
            'sugIni' => '2014-01-01',
            'sugFim' => '2050-12-31',
            'centrosPorGrupo' => fcs_centros_por_grupo(),
            'avulsosPorGrupo' => fcs_avulsos_por_grupo(),
            'basesSel' => fcs_bases_salvas(),
            'podeAtualizar' => usuario_pode('fluxo-caixa', 'alterar')]);
        break;

    case 'fluxo-caixa/imprimir':
        exigir_login();
        $idsImp = array_values(array_filter(array_map('intval',
            explode(',', (string) ($_GET['grupos'] ?? '')))));
        $iniImp = trim((string) ($_GET['data_ini'] ?? ''));
        $fimImp = trim((string) ($_GET['data_fim'] ?? ''));
        $relatorios = [];
        $erro = '';
        if (!$idsImp || $iniImp === '' || $fimImp === '' || $iniImp > $fimImp) {
            $erro = 'Parametros invalidos.';
        } else {
            set_time_limit(600);
            try {
                // av=Avulso_Id:valor,Avulso_Id:valor -> FCGrupo_Id => [Avulso_Id => valor]
                $avPar = [];
                foreach (explode(',', (string) ($_GET['av'] ?? '')) as $par) {
                    if (preg_match('/^(\d+):(-?[\d.,]+)$/', trim($par), $m)) {
                        $avPar[(int) $m[1]] = fcs_valor($m[2]);
                    }
                }
                fcs_salvar_avulsos($avPar);
                $avPorGrupo = [];
                if ($avPar) {
                    $gidDoAvulso = [];
                    foreach (query('SELECT Avulso_Id, FCGrupo_Id
                                    FROM msoft.GrupoCentro_Avulso') as $r) {
                        $gidDoAvulso[(int) $r['Avulso_Id']] = (int) $r['FCGrupo_Id'];
                    }
                    foreach ($avPar as $aid => $val) {
                        if (isset($gidDoAvulso[$aid])) {
                            $avPorGrupo[$gidDoAvulso[$aid]][$aid] = $val;
                        }
                    }
                }
                $relatorios = fcs_relatorio_impressao($idsImp, $iniImp, $fimImp, $avPorGrupo);
            } catch (Throwable $t) {
                $erro = 'Erro ao gerar os relatorios: ' . $t->getMessage();
            }
        }
        require ROOT . '/views/fluxo_caixa_impressao.php';
        break;

    case 'fluxo-caixa/resumo':
        exigir_login();
        $att = array_filter(array_map('intval',
            explode(',', (string) ($_GET['att'] ?? ''))));
        $grupos = fcs_listar();
        render('fluxo_caixa_resumo', ['titulo' => 'Acompanhamento Financeiro',
            'active' => 'fluxo-caixa', 'aviso' => consumir_aviso(),
            'grupos' => fcs_grupos_visiveis($grupos),
            // le so o ultimo quadro calculado; grupos zerados ficam ocultos
            'fcPag' => fcs_pagar_sem_zeros(fcs_pagar_salvo($grupos)),
            'fcRec' => fcs_receber_dados(),
            'fcPos' => fcs_posicao_salva(),
            'fcApo' => fcs_aportes_salvo($grupos),
            'tempoCalc' => isset($_GET['tempo']) ? (int) $_GET['tempo'] : null,
            'atualizados' => $att]);
        break;

    case 'fluxo-caixa-grupos/relatorio':
        exigir_login();
        render('fluxo_caixa_relatorio', ['titulo' => 'Agrupamento de Centros de Custo',
            'active' => 'fluxo-caixa-grupos', 'grupos' => fc_relatorio()]);
        break;

    case 'dre-ano':
    case 'dre-mes':
    case 'dre-ano-arquivada':
    case 'dre-mes-arquivada':
        exigir_login();
        $arquivada = str_contains($rota, 'arquivada');
        $modo = str_contains($rota, '-ano') ? 'ano' : 'mes';
        $gerado = isset($_GET['gerar']);
        $anoAtual = (int) date('Y');
        $o = [
            'empresas' => array_values(array_filter(array_map('trim', (array) ($_GET['empresas'] ?? [])))),
            'nucleos'  => array_values(array_filter(array_map('trim', (array) ($_GET['nucleos_sel'] ?? [])))),
            'centros'  => array_values(array_filter(array_map('trim', (array) ($_GET['centros_sel'] ?? [])))),
        ];
        $erro = '';
        if ($modo === 'ano') {
            $o['ano_ini'] = (int) ($_GET['ano_ini'] ?? $anoAtual);
            $o['ano_fim'] = (int) ($_GET['ano_fim'] ?? $anoAtual);
            if ($gerado) {
                if ($o['ano_ini'] < 2000 || $o['ano_fim'] > $anoAtual + 1 || $o['ano_ini'] > $o['ano_fim']) {
                    $erro = 'Informe o intervalo de anos corretamente.';
                } elseif ($o['ano_fim'] - $o['ano_ini'] > 9) {
                    $erro = 'Limite de 10 anos por consulta (consulta pesada).';
                } else {
                    $o['data_ini'] = $o['ano_ini'] . '-01-01';
                    $o['data_fim'] = $o['ano_fim'] . '-12-31';
                }
            }
        } else {
            $o['ano'] = (int) ($_GET['ano'] ?? $anoAtual);
            $meses = array_values(array_filter(array_map('intval', (array) ($_GET['meses'] ?? []))));
            $o['meses'] = array_values(array_filter($meses, fn($m) => $m >= 1 && $m <= 12));
            if ($gerado) {
                if ($o['ano'] < 2000 || $o['ano'] > $anoAtual + 1) {
                    $erro = 'Informe o ano corretamente.';
                } else {
                    if (!$o['meses']) {
                        $o['meses'] = range(1, 12);
                    }
                    $o['data_ini'] = $o['ano'] . '-01-01';
                    $o['data_fim'] = $o['ano'] . '-12-31';
                }
            } elseif (!$o['meses']) {
                $o['meses'] = range(1, 12);
            }
        }
        $dados = null;
        $listas = ['empresas' => [], 'nucleos' => [], 'centros' => []];
        if ($gerado && $erro === '') {
            set_time_limit(900); // as procedures sao pesadas: 2 execucoes por base
            try {
                $t0 = microtime(true);
                $dados = dre_pivot(
                    ($arquivada ? dre_dados_arquivada($o) : dre_dados($o))['linhas'], $modo);
                $dados['tempo'] = microtime(true) - $t0;
            } catch (Throwable $t) {
                $erro = 'Erro ao gerar o DRE: ' . $t->getMessage();
            }
        }
        if (!$gerado) {
            try {
                $listas = ['empresas' => dre_empresas(), 'nucleos' => dre_nucleos(),
                           'centros' => dre_centros()];
            } catch (Throwable $t) {
                $erro = 'Erro ao listar filtros: ' . $t->getMessage();
            }
        }
        if ($dados && !empty($_GET['excel'])) {
            $nome = 'dre_' . ($modo === 'ano'
                    ? $o['ano_ini'] . '-' . $o['ano_fim']
                    : $o['ano'] . '_' . implode('-', $o['meses']))
                  . ($arquivada ? '_arquivada' : '') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $nome . '"');
            $formato = dre_formato();
            require ROOT . '/views/dre_excel.php';
            exit;
        }
        render('dre', [
            'titulo' => ($modo === 'ano' ? 'DRE por Ano' : 'DRE Mensal')
                        . ($arquivada ? ' Arquivada' : ''),
            'active' => $rota, 'modo' => $modo, 'arquivada' => $arquivada,
            'gerado' => $gerado, 'o' => $o,
            'formato' => $gerado ? dre_formato() : [],
            'dados' => $dados, 'erro' => $erro] + $listas);
        break;

    case 'relatorio-financeiro':
        exigir_login();
        $base = strtoupper((string) ($_GET['base'] ?? 'MGA')) === 'CONV' ? 'CONV' : 'MGA';
        $gerado = isset($_GET['gerar']);
        $opcoes = [
            'empresa'        => trim((string) ($_GET['empresa'] ?? '')),
            'data_ini'       => (string) ($_GET['data_ini'] ?? ''),
            'data_fim'       => (string) ($_GET['data_fim'] ?? ''),
            'centros'        => match ($_GET['centro_op'] ?? 'todos') {
                'apenas'       => array_filter([trim((string) ($_GET['centro'] ?? ''))]),
                'selecionados' => array_values(array_filter((array) ($_GET['centros_sel'] ?? []))),
                default        => [],
            },
            'nucleos'        => match ($_GET['nucleo_op'] ?? 'todos') {
                'apenas'       => array_filter([trim((string) ($_GET['nucleo'] ?? ''))]),
                'selecionados' => array_values(array_filter((array) ($_GET['nucleos_sel'] ?? []))),
                default        => [],
            },
            'inc_inativos'   => isset($_GET['inc_inativos']),
            'sin_classe'     => isset($_GET['sin_classe']),
            'sin_assunto'    => isset($_GET['sin_assunto']),
            'sin_grupo'      => isset($_GET['sin_grupo']),
            'analitico'      => isset($_GET['analitico']),
            'sec_receitas'   => isset($_GET['sec_receitas']),
            'sec_despesas'   => isset($_GET['sec_despesas']),
            'sec_sociedade'  => isset($_GET['sec_sociedade']),
            'sec_outros'     => isset($_GET['sec_outros']),
            'det_centro'     => isset($_GET['det_centro']),
            'apenas_periodo' => isset($_GET['apenas_periodo']),
            'resumo_agente'  => isset($_GET['resumo_agente']),
        ];
        if ($gerado && !in_array(true, [$opcoes['sec_receitas'], $opcoes['sec_despesas'],
                $opcoes['sec_sociedade'], $opcoes['sec_outros']], true)) {
            $opcoes['sec_receitas'] = $opcoes['sec_despesas'] = $opcoes['sec_sociedade']
                = $opcoes['sec_outros'] = true;
        }
        $dados = null;
        $erro = '';
        if ($gerado) {
            try {
                $dados = relatorio_financeiro_dados($base, $opcoes);
            } catch (Throwable $t) {
                $erro = 'Erro ao gerar o relatorio: ' . $t->getMessage();
            }
        } else {
            try {
                $centros = relatorio_financeiro_centros($base, $opcoes['inc_inativos']);
                $nucleos = relatorio_financeiro_nucleos($base);
                $empresas = relatorio_financeiro_empresas($base);
            } catch (Throwable $t) {
                $centros = [];
                $nucleos = [];
                $empresas = [['Empresa_Cod' => 'CGC', 'Empresa_Nome' => 'C. G. Construcoes']];
                $erro = 'Erro ao listar filtros: ' . $t->getMessage();
            }
        }
        render('relatorio_financeiro', ['titulo' => 'Relatorio Financeiro',
            'active' => 'relatorio-financeiro', 'gerado' => $gerado, 'opcoes' => $opcoes,
            'centros' => $centros ?? [], 'nucleos' => $nucleos ?? [],
            'empresas' => $empresas ?? [], 'dados' => $dados, 'erro' => $erro]);
        break;

    case 'contas-pagar':
        exigir_login();
        $base = strtoupper((string) ($_GET['base'] ?? 'MGA')) === 'CONV' ? 'CONV' : 'MGA';
        $gerado = isset($_GET['gerar']);
        set_time_limit(600);
        $o = [
            'empresa'      => trim((string) ($_GET['empresa'] ?? '')),
            'ini'          => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['data_ini'] ?? ''))
                              ? $_GET['data_ini'] : '',
            'fim'          => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['data_fim'] ?? ''))
                              ? $_GET['data_fim'] : '',
            'ck_venc'      => isset($_GET['ck_venc']) || !$gerado,
            'ck_agenda'    => isset($_GET['ck_agenda']) || !$gerado,
            'ck_autor'     => isset($_GET['ck_autor']),
            'atrasadas'    => isset($_GET['atrasadas']),
            'pendente'     => isset($_GET['pendente']),
            'inc_ep'       => isset($_GET['inc_ep']) || !$gerado,
            'inc_ed'       => isset($_GET['inc_ed']) || !$gerado,
            'apenas_ep'    => ($_GET['apenas_ep_ed'] ?? '') === 'EP',
            'apenas_ed'    => ($_GET['apenas_ep_ed'] ?? '') === 'ED',
            'centro'       => ($_GET['centro_op'] ?? 'todos') === 'apenas'
                              ? strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '',
                                  (string) ($_GET['centro'] ?? '')), 0, 9)) : '',
            'classificacao' => trim((string) ($_GET['classificacao'] ?? '')),
            'doc_op'       => (string) ($_GET['doc_op'] ?? 'todos'),
            'doc_sel'      => array_values(array_filter(
                array_map('trim', (array) ($_GET['doc_sel'] ?? [])))),
            'esp_op'       => (string) ($_GET['esp_op'] ?? 'todos'),
            'esp_sel'      => array_values(array_filter(
                array_map('trim', (array) ($_GET['esp_sel'] ?? [])))),
            'credor'       => strtoupper(substr(trim(explode(' - ',
                                  (string) ($_GET['credor'] ?? ''))[0]), 0, 6)),
            'nucleo'       => trim((string) ($_GET['nucleo'] ?? '')),
            'grupo_nucleo' => trim((string) ($_GET['grupo_nucleo'] ?? '')),
            'excl_aclass'  => isset($_GET['excl_aclass']),
            'so_autor'     => isset($_GET['so_autor']),
            'so_nautor'    => isset($_GET['so_nautor']),
            'exibe_obs'    => isset($_GET['exibe_obs']),
            'suprime'      => isset($_GET['suprime']),
            'tot_dia'      => isset($_GET['tot_dia']),
            'tot_quinzena' => isset($_GET['tot_quinzena']),
            'tot_mes'      => isset($_GET['tot_mes']),
            'agrupar'      => array_key_exists((string) ($_GET['agrupar'] ?? 'agenda'), CP_AGRUP)
                              ? (string) $_GET['agrupar'] : 'agenda',
        ];
        $grupos = [];
        $erro = '';
        if ($gerado) {
            if ($o['ini'] === '' || $o['fim'] === '' || $o['ini'] > $o['fim']) {
                $erro = 'Informe o período (De / a) corretamente.';
            } else {
                try {
                    $r = cp_dados($base, $o);
                    $erro = $r['erro'];
                    $grupos = $r['erro'] === '' ? cp_agrupar($r['linhas'], $o['agrupar']) : [];
                } catch (Throwable $t) {
                    $erro = 'Erro ao gerar o relatorio: ' . $t->getMessage();
                }
            }
            $empNome = '';
            foreach (cp_empresas($base) as $emp) {
                if (trim($emp['Empresa_Cod']) === $o['empresa']) {
                    $empNome = trim((string) $emp['Empresa_Nome']);
                }
            }
            $o['empresa_nome'] = $empNome;
        }
        render('contas_pagar', ['titulo' => 'Relatorio de Contas a Pagar',
            'active' => 'contas-pagar', 'base' => $base, 'gerado' => $gerado,
            'o' => $o, 'grupos' => $grupos, 'erro' => $erro,
            'empresas' => cp_empresas($base), 'centros' => mp_centros(),
            'credores' => cp_credores($base), 'especies' => cp_especies($base),
            'doctipos' => cp_doctipos($base), 'nucleos' => cp_nucleos($base),
            'gruposNucleo' => cp_grupos_nucleo($base)]);
        break;

    case 'faturamento':
        exigir_login();
        $base = strtoupper((string) ($_GET['base'] ?? 'MGA')) === 'CONV' ? 'CONV' : 'MGA';
        $gerado = isset($_GET['gerar']);
        set_time_limit(600);
        $empreendimentos = rf_empreendimentos($base);
        // empreendimento escolhido vira filtro do centro de custo dele
        $emprRot = trim((string) ($_GET['empreendimento'] ?? ''));
        $emprCodc = '';
        foreach ($empreendimentos as $e2) {
            if ($e2['rotulo'] === $emprRot) { $emprCodc = $e2['codc']; }
        }
        $centroRot = trim((string) ($_GET['centro'] ?? ''));
        $centroCodc = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '',
            explode(' - ', $centroRot)[0]), 0, 9));
        if (strlen($centroCodc) !== 9) { $centroCodc = ''; }
        $o = [
            'empresa'   => trim((string) ($_GET['empresa'] ?? '')),
            'ini'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['data_ini'] ?? ''))
                           ? $_GET['data_ini'] : '',
            'fim'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['data_fim'] ?? ''))
                           ? $_GET['data_fim'] : '',
            'ordem'     => ($_GET['ordem'] ?? 'data') === 'numero' ? 'numero' : 'data',
            'centro'    => $emprCodc !== '' ? $emprCodc : $centroCodc,
            'centro_rot' => $centroRot,
            'empr_rot'  => $emprRot,
            'cliente_rot' => trim((string) ($_GET['cliente'] ?? '')),
            'cliente'   => strtoupper(substr(trim(explode(' - ',
                                (string) ($_GET['cliente'] ?? ''))[0]), 0, 5)),
            'nucleo'    => trim((string) ($_GET['nucleo'] ?? '')),
            'reidi'     => ($_GET['reidi'] ?? '') === 'S' ? 'S' : '',
            'esp'       => trim((string) ($_GET['esp'] ?? '')),
            'tipo'      => trim((string) ($_GET['tipo'] ?? '')),
            'obs'       => isset($_GET['obs']),
            'imprimir'  => isset($_GET['imprimir']),
        ];
        if (strlen($o['cliente']) > 5) { $o['cliente'] = substr($o['cliente'], 0, 5); }
        $grupos = [];
        $erro = '';
        $cab = ['nome' => '', 'cnpj' => ''];
        if ($gerado) {
            if ($o['ini'] === '' || $o['fim'] === '' || $o['ini'] > $o['fim']) {
                $erro = 'Informe o período (De / a) corretamente.';
            } else {
                try {
                    $linhas = rf_dados($base, $o);
                    $grupos = rf_agrupar($linhas, $o['ordem']);
                } catch (Throwable $t) {
                    $erro = 'Erro ao gerar o relatorio: ' . $t->getMessage();
                }
            }
            $cab = rf_empresa_cabecalho($base, $o['empresa']);
        }
        render('faturamento', ['titulo' => 'Relatorio de Faturamento',
            'active' => 'faturamento', 'base' => $base, 'gerado' => $gerado,
            'o' => $o, 'grupos' => $grupos, 'erro' => $erro, 'cab' => $cab,
            'empresas' => rf_empresas($base), 'centros' => mp_centros(),
            'clientes' => rf_clientes($base), 'nucleos' => rf_nucleos($base),
            'empreendimentos' => $empreendimentos,
            'especies' => rf_especies($base), 'tipos' => rf_tipos($base)]);
        break;

    case 'agenda-pagamentos/col-order':
        // POST ajax: salva a ordem das colunas do grid por usuario
        exigir_login();
        header('Content-Type: application/json');
        if ($metodo !== 'POST' || !csrf_valido()) {
            http_response_code(403);
            echo '{"ok":false}';
            break;
        }
        ap_salvar_col_order((string) $_SESSION['usuario'],
            (string) ($_POST['order'] ?? ''));
        echo '{"ok":true}';
        break;

    case 'agenda-pagamentos':
        exigir_login();
        $base = strtoupper((string) ($_GET['base'] ?? 'MGA')) === 'CONV' ? 'CONV' : 'MGA';
        $gerado = isset($_GET['gerar']);
        $usuario = (string) $_SESSION['usuario'];
        $ordemSalva = ap_pref($usuario, 'agenda-pagamentos.ordem');
        set_time_limit(600);
        $data = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$k] ?? ''))
            ? $_GET[$k] : '';
        $num = function ($k) {
            $v = trim((string) ($_GET[$k] ?? ''));
            if ($v === '') { return null; }
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
            return is_numeric($v) ? (float) $v : null;
        };
        $restringe = isset($_GET['restringir']);
        $o = [
            'empresas'  => array_values(array_filter(array_map('trim',
                              (array) ($_GET['emp'] ?? [])))),
            'ini'       => $data('data_ini'),
            'fim'       => $data('data_fim'),
            'ref'       => date('Y-m-d'),
            'atrasadas' => isset($_GET['vencidas']),
            'pendentes' => isset($_GET['pendentes']),
            'nao_aut'   => isset($_GET['nao_aut']),
            'ed'        => isset($_GET['inc_ed']) || !$gerado,
            'ep'        => isset($_GET['inc_ep']) || !$gerado,
            'cv'        => isset($_GET['inc_cv']),
            'centros'   => trim((string) ($_GET['centro'] ?? '')) !== ''
                              ? [strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '',
                                  explode(' ', trim((string) $_GET['centro']))[0]), 0, 9))]
                              : [],
            'nucleo'    => trim((string) ($_GET['nucleo'] ?? '')),
            'especie'   => trim((string) ($_GET['especie'] ?? '')),
            'cre'       => strtoupper(substr(trim(explode(' - ',
                              (string) ($_GET['credor'] ?? ''))[0]), 0, 6)),
            'docnum'    => trim((string) ($_GET['docnum'] ?? '')),
            'ficha'     => trim((string) ($_GET['ficha'] ?? '')),
            'vmin'      => $restringe ? $num('valor_min') : null,
            'vmax'      => $restringe ? $num('valor_max') : null,
            'agr_tipo'  => isset($_GET['agr_tipo']),
            'agr_mod'   => isset($_GET['agr_mod']),
            'tot_dia'   => isset($_GET['tot_dia']),
            'tot_quinzena' => isset($_GET['tot_quinzena']),
            'tot_mes'   => isset($_GET['tot_mes']),
            'ordem'     => (string) ($_GET['ordem'] ?? $ordemSalva ?: 'vencimento'),
        ];
        $linhas = [];
        $status = null;
        $erro = '';
        if ($gerado) {
            if ($o['ini'] === '' || $o['fim'] === '' || $o['ini'] > $o['fim']) {
                $erro = 'Informe o período da agenda (De / a) corretamente.';
            } else {
                try {
                    $linhas = ap_dados($base, $o);
                    $grpKey = ($o['agr_tipo'] || $o['agr_mod'])
                        ? function ($r) use ($o) {
                            $k = [];
                            if ($o['agr_tipo']) {
                                $k[] = trim((string) $r['TitaPag_PagDocTipo']);
                            }
                            if ($o['agr_mod']) {
                                $k[] = trim((string) $r['pge_Modalidade']);
                            }
                            return implode('|', $k);
                        } : null;
                    $linhas = ap_ordernar($linhas, $o['ordem'], $grpKey);
                    $status = ap_status($linhas, $o['ref']);
                    ap_salvar_pref($usuario, 'agenda-pagamentos.ordem', $o['ordem']);
                } catch (Throwable $t) {
                    $erro = 'Erro ao gerar a agenda: ' . $t->getMessage();
                    $linhas = [];
                }
            }
        }
        render('agenda_pagamentos', ['titulo' => 'Agenda de Pagamentos',
            'active' => 'agenda-pagamentos', 'base' => $base, 'gerado' => $gerado,
            'o' => $o, 'linhas' => $linhas, 'status' => $status, 'erro' => $erro,
            'cols' => ap_col_order($usuario),
            'empresas' => cp_empresas($base), 'centros' => mp_centros(),
            'credores' => cp_credores($base), 'especies' => cp_especies($base),
            'nucleos' => cp_nucleos($base)]);
        break;

    case 'posicao-contas':
        exigir_login();
        $base = strtoupper((string) ($_GET['base'] ?? 'MGA')) === 'CONV' ? 'CONV' : 'MGA';
        $gerado = isset($_GET['gerar']);
        $empOp = ((string) ($_GET['empresa_op'] ?? 'selecionadas')) === 'todas'
            ? 'todas' : 'selecionadas';
        $empSel = array_values(array_filter(array_map('trim',
            (array) ($_GET['empresas_sel'] ?? (!isset($_GET['empresa_op']) ? ['CGC'] : [])))));
        $opcoes = [
            'empresa_op'   => $empOp,
            'empresas_sel' => $empSel,
            'empresas'     => $empOp === 'selecionadas' ? $empSel : [],
            'data'         => (string) ($_GET['data'] ?? date('Y-m-d')),
            'tipo'         => trim((string) ($_GET['tipo'] ?? 'BC')),
            'nucleo'       => trim((string) ($_GET['nucleo'] ?? '')),
            'agente'       => ($_GET['agente_op'] ?? 'todos') === 'um'
                              ? trim((string) ($_GET['agente'] ?? '')) : '',
            'conciliados'  => !$_GET || isset($_GET['conciliados']),
        ];
        $dados = null;
        $erro = '';
        $banco = banco_da_base($base);
        if ($gerado) {
            try {
                $dados = posicao_contas_dados($base, $opcoes);
            } catch (Throwable $t) {
                $erro = 'Erro ao gerar o relatorio: ' . $t->getMessage();
            }
        }
        try {
            $empresas = posicao_contas_empresas($banco);
            $tipos = posicao_contas_tipos($banco);
            $nucleos = posicao_contas_nucleos($banco);
            $agentes = posicao_contas_agentes($banco, $opcoes['tipo']);
        } catch (Throwable $t) {
            $empresas = [['Empresa_Cod' => 'CGC', 'Empresa_Nome' => 'C. G. Construcoes']];
            $tipos = [['AgenteFinanceiroTipo_Cod' => 'BC', 'AgenteFinanceiroTipo_Titulo' => 'Bancos']];
            $nucleos = [];
            $agentes = [];
            if (!$gerado) {
                $erro = 'Erro ao listar filtros: ' . $t->getMessage();
            }
        }
        render('posicao_contas', ['titulo' => 'Posicao de Contas',
            'active' => 'posicao-contas', 'gerado' => $gerado, 'opcoes' => $opcoes,
            'base' => $base, 'empresas' => $empresas, 'tipos' => $tipos,
            'nucleos' => $nucleos, 'agentes' => $agentes,
            'dados' => $dados, 'erro' => $erro]);
        break;

    case 'logout':
        $_SESSION = [];
        session_destroy();
        redirecionar('login');

    case 'menu':
        exigir_login();
        render('menu', ['titulo' => 'Menu Principal', 'active' => 'menu',
            'pendentes' => is_admin() ? pendentes_count() : 0]);
        break;

    default:
        if ($metodo === 'POST' && preg_match('#^usuarios/(\d+)/(liberar|bloquear|gerar-senha|definir-senha|grupos|permissoes)$#', $rota, $m)) {
            exigir_admin();
            definir_aviso(csrf_valido()
                ? acao_usuario((int) $m[1], $m[2])
                : ['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
            $manterSel = in_array($m[2], ['grupos', 'permissoes', 'definir-senha'], true);
            redirecionar($manterSel ? 'configuracoes?usuario=' . (int) $m[1] : 'configuracoes');
        }
        http_response_code(404);
        render('erro404', ['titulo' => 'Pagina nao encontrada'], 'base');
}
