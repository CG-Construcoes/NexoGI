<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

iniciar_sessao();

$rota = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '/');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Permissao por tela: rotas mapeadas exigem registro em msoft.UsuarioTelas (admin sempre passa).
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
    'matriz-prioridades' => 'matriz-prioridades',
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

    case 'usuarios':
        exigir_admin();
        render('usuarios', ['titulo' => 'Usuarios do sistema', 'active' => 'usuarios',
            'aviso' => consumir_aviso()] + listar_usuarios());
        break;

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
            'sec_outros'     => isset($_GET['sec_outros']),
            'det_centro'     => isset($_GET['det_centro']),
            'apenas_periodo' => isset($_GET['apenas_periodo']),
            'resumo_agente'  => isset($_GET['resumo_agente']),
        ];
        if ($gerado && !in_array(true, [$opcoes['sec_receitas'], $opcoes['sec_despesas'],
                $opcoes['sec_outros']], true)) {
            $opcoes['sec_receitas'] = $opcoes['sec_despesas'] = $opcoes['sec_outros'] = true;
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
        if ($metodo === 'POST' && preg_match('#^usuarios/(\d+)/(liberar|bloquear|gerar-senha|telas)$#', $rota, $m)) {
            exigir_admin();
            definir_aviso(csrf_valido()
                ? acao_usuario((int) $m[1], $m[2])
                : ['tipo' => 'erro', 'html' => 'Sessao expirada. Tente novamente.']);
            redirecionar('usuarios');
        }
        http_response_code(404);
        render('erro404', ['titulo' => 'Pagina nao encontrada'], 'base');
}
