<?php
declare(strict_types=1);

function iniciar_sessao(): void
{
    session_name('MSOFTSESS');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function usuario_logado(): bool
{
    return isset($_SESSION['usuario']);
}

function is_admin(): bool
{
    return !empty($_SESSION['admin']);
}

function exigir_login(): void
{
    if (!usuario_logado()) {
        redirecionar('login');
    }
}

function exigir_admin(): void
{
    exigir_login();
    if (!is_admin()) {
        redirecionar('menu');
    }
}

/** Acoes de permissao por tela (colunas P_* em msoft.UsuarioGrupoTelas). */
const PERM_ACOES = ['consultar', 'inserir', 'alterar', 'excluir'];

/** Permissoes do usuario logado: slug => ['consultar'=>bool,'inserir'=>bool,...].
 *  Efetiva = UNIAO das permissoes de todos os grupos dos quais o usuario participa. */
function permissoes_usuario(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    if (!usuario_logado() || is_admin()) {
        return $cache;
    }
    try {
        $rows = query(
            'SELECT RTRIM(t.Tela) Tela,
                    MAX(CAST(t.P_Consultar AS INT)) P_Consultar,
                    MAX(CAST(t.P_Inserir AS INT))   P_Inserir,
                    MAX(CAST(t.P_Alterar AS INT))   P_Alterar,
                    MAX(CAST(t.P_Excluir AS INT))   P_Excluir
             FROM msoft.UsuarioGrupoTelas t
             JOIN msoft.UsuarioGrupoMembro m ON m.UsGrupo_Id = t.UsGrupo_Id
             JOIN msoft.Usuarios u ON u.Id = m.UsuarioId
             WHERE u.Email = ?
             GROUP BY t.Tela',
            [$_SESSION['usuario']]
        );
        foreach ($rows as $r) {
            $cache[$r['Tela']] = [
                'consultar' => (bool) $r['P_Consultar'],
                'inserir'   => (bool) $r['P_Inserir'],
                'alterar'   => (bool) $r['P_Alterar'],
                'excluir'   => (bool) $r['P_Excluir'],
            ];
        }
    } catch (Throwable $e) {
        error_log('Msoft permissoes: ' . $e->getMessage());
    }
    return $cache;
}

/** Admin pode tudo; demais usuarios precisam da permissao da acao na tela. */
function usuario_pode(string $slug, string $acao = 'consultar'): bool
{
    return is_admin() || !empty(permissoes_usuario()[$slug][$acao]);
}

/** Exige login e permissao da acao na tela; sem permissao, responde 403. */
function exigir_tela(string $slug, string $acao = 'consultar'): void
{
    exigir_login();
    if (!usuario_pode($slug, $acao)) {
        http_response_code(403);
        render('erro403', ['titulo' => 'Acesso negado'], 'base');
        exit;
    }
}

/** Valida e-mail/senha. Retorna mensagem de erro ou null em caso de sucesso (e grava a sessao). */
function autenticar(string $email, string $senha): ?string
{
    try {
        $rows = query(
            'SELECT Email, Nome, SenhaHashPHP, Status, Admin FROM msoft.Usuarios WHERE Email = ?',
            [$email]
        );
    } catch (Throwable $e) {
        error_log('Msoft login: ' . $e->getMessage());
        return 'Falha ao conectar no banco de dados.';
    }
    if (!$rows) {
        return 'E-mail ou senha invalidos.';
    }
    $u = $rows[0];
    if ($u['Status'] === 'pendente') {
        return 'Seu acesso ainda nao foi liberado. Aguarde o administrador.';
    }
    if ($u['Status'] === 'bloqueado') {
        return 'Acesso bloqueado. Fale com o administrador.';
    }
    if (empty($u['SenhaHashPHP'])) {
        return 'Sua senha precisa ser redefinida pelo administrador para este sistema.';
    }
    if (!password_verify($senha, $u['SenhaHashPHP'])) {
        return 'E-mail ou senha invalidos.';
    }
    session_regenerate_id(true);
    $_SESSION['usuario'] = $u['Email'];
    $_SESSION['nome'] = $u['Nome'];
    $_SESSION['admin'] = (bool) $u['Admin'];
    return null;
}
