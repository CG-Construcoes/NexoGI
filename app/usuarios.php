<?php
declare(strict_types=1);

/** Marcador gravado na coluna SenhaHash (Python) para contas criadas pelo PHP: o login Python recusa. */
const SENHA_PYTHON_INDISPONIVEL = '!criado-no-php';

/** Grava a solicitacao de acesso. Retorna mensagem de erro ou null em caso de sucesso. */
function solicitar_acesso(string $nome, string $email, string $senha, string $confirma): ?string
{
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Informe nome completo e um e-mail valido.';
    }
    if (mb_strlen($nome) > 120 || mb_strlen($email) > 120) {
        return 'Nome e e-mail devem ter no maximo 120 caracteres.';
    }
    if (mb_strlen($senha) < 4) {
        return 'A senha deve ter ao menos 4 caracteres.';
    }
    if ($senha !== $confirma) {
        return 'As senhas nao conferem.';
    }
    try {
        execute(
            'INSERT INTO msoft.Usuarios (Email, Nome, SenhaHash, SenhaHashPHP) VALUES (?, ?, ?, ?)',
            [$email, $nome, SENHA_PYTHON_INDISPONIVEL, password_hash($senha, PASSWORD_DEFAULT)]
        );
        return null;
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'UNIQUE') !== false || stripos($e->getMessage(), 'duplicate') !== false) {
            return 'Este e-mail ja possui uma solicitacao de acesso.';
        }
        error_log('Msoft cadastro: ' . $e->getMessage());
        return 'Falha ao salvar. Tente novamente.';
    }
}

/** Quantos usuarios aguardam liberacao (para o alerta da tela inicial). */
function pendentes_count(): int
{
    return (int) query("SELECT COUNT(*) AS Qtd FROM msoft.Usuarios WHERE Status = 'pendente'")[0]['Qtd'];
}

function listar_usuarios(): array
{
    $sql = 'SELECT Id, Email, Nome, Status, Admin, CriadoEm,
                   CASE WHEN SenhaHashPHP IS NULL THEN 0 ELSE 1 END AS TemSenhaPHP
            FROM msoft.Usuarios';
    $pendentes = query("$sql WHERE Status = 'pendente' ORDER BY CriadoEm");
    $demais = query("$sql WHERE Status <> 'pendente' ORDER BY Nome");
    return [
        'pendentes' => $pendentes,
        'demais' => $demais,
        'telasDisp' => telas_disponiveis(),
        'telasMapa' => telas_de_usuarios(array_column(array_merge($pendentes, $demais), 'Id')),
    ];
}

/** Telas que podem ser liberadas por usuario (itens do menu que nao sao exclusivos de admin).
 *  O nome exibido leva o grupo entre parenteses, para distinguir homonimos (ex.: Indicadores). */
function telas_disponiveis(): array
{
    $telas = [];
    foreach (MODULES as $g) {
        foreach ($g['itens'] as $item) {
            if (isset($item['itens'])) {
                foreach ($item['itens'] as $sub) {
                    if (empty($sub['admin'])) {
                        $telas[] = array_merge($sub, ['nome' =>
                            $sub['nome'] . ' (' . $g['nome'] . ' > ' . $item['nome'] . ')']);
                    }
                }
                continue;
            }
            if (empty($item['admin'])) {
                $telas[] = array_merge($item, ['nome' => $item['nome'] . ' (' . $g['nome'] . ')']);
            }
        }
    }
    return $telas;
}

/** Mapa UsuarioId => slug => [consultar,inserir,alterar,excluir] das telas liberadas. */
function telas_de_usuarios(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = query("SELECT UsuarioId, Tela, P_Consultar, P_Inserir, P_Alterar, P_Excluir
                   FROM msoft.UsuarioTelas WHERE UsuarioId IN ($ph)", $ids);
    $mapa = [];
    foreach ($rows as $r) {
        $mapa[$r['UsuarioId']][$r['Tela']] = [
            'consultar' => (bool) $r['P_Consultar'],
            'inserir'   => (bool) $r['P_Inserir'],
            'alterar'   => (bool) $r['P_Alterar'],
            'excluir'   => (bool) $r['P_Excluir'],
        ];
    }
    return $mapa;
}

/** Substitui as telas/permissoes do usuario pelas marcadas.
 *  $perm = [slug => [acao => 1,...]] (formato do formulario). Retorna telas salvas. */
function definir_telas(int $id, array $perm): int
{
    $validos = array_column(telas_disponiveis(), 'slug');
    execute('DELETE FROM msoft.UsuarioTelas WHERE UsuarioId = ?', [$id]);
    $salvas = 0;
    foreach ($perm as $slug => $acoes) {
        $slug = (string) $slug;
        if (!in_array($slug, $validos, true)) {
            continue;
        }
        $acoes = (array) $acoes;
        $flags = [];
        foreach (PERM_ACOES as $a) {
            $flags[$a] = !empty($acoes[$a]) ? 1 : 0;
        }
        if (!$flags['consultar']) {
            continue; // sem consultar a tela nem aparece para o usuario
        }
        execute('INSERT INTO msoft.UsuarioTelas (UsuarioId, Tela, P_Consultar, P_Inserir, P_Alterar, P_Excluir)
                 VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $slug, $flags['consultar'], $flags['inserir'], $flags['alterar'], $flags['excluir']]);
        $salvas++;
    }
    return $salvas;
}

function gerar_senha_provisoria(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $senha = '';
    for ($i = 0; $i < 12; $i++) {
        $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return $senha;
}

/** Executa liberar/bloquear/gerar-senha. Retorna ['tipo' => 'ok'|'erro', 'html' => mensagem ja escapada]. */
function acao_usuario(int $id, string $acao): array
{
    $u = query('SELECT Id, Nome, Email FROM msoft.Usuarios WHERE Id = ?', [$id])[0] ?? null;
    if (!$u) {
        return ['tipo' => 'erro', 'html' => 'Usuario nao encontrado.'];
    }
    $nome = e($u['Nome']);
    if ($acao === 'liberar' || $acao === 'bloquear') {
        if ($acao === 'bloquear' && strcasecmp($u['Email'], $_SESSION['usuario'] ?? '') === 0) {
            return ['tipo' => 'erro', 'html' => 'Voce nao pode bloquear o seu proprio usuario.'];
        }
        $status = $acao === 'liberar' ? 'ativo' : 'bloqueado';
        execute('UPDATE msoft.Usuarios SET Status = ?, LiberadoEm = GETDATE() WHERE Id = ?', [$status, $id]);
        $extra = '';
        // so grava as telas quando o formulario enviou o campo de permissoes
        if ($acao === 'liberar' && isset($_POST['telas_enviadas'])) {
            $extra = ', com acesso a ' . definir_telas($id, (array) ($_POST['perm'] ?? [])) . ' tela(s)';
        }
        return ['tipo' => 'ok', 'html' => "$nome agora esta <strong>$status</strong>$extra."];
    }
    if ($acao === 'telas') {
        $n = definir_telas($id, (array) ($_POST['perm'] ?? []));
        return ['tipo' => 'ok', 'html' => "Permissoes de $nome atualizadas ($n tela(s) liberada(s))."];
    }
    if ($acao === 'gerar-senha') {
        $senha = gerar_senha_provisoria();
        execute('UPDATE msoft.Usuarios SET SenhaHashPHP = ? WHERE Id = ?', [password_hash($senha, PASSWORD_DEFAULT), $id]);
        return ['tipo' => 'ok', 'html' => "Senha provisoria de $nome (" . e($u['Email']) . "): "
            . '<strong style="font-family:monospace;font-size:15px">' . e($senha) . '</strong>'
            . ' &mdash; anote e repasse ao usuario; ela nao sera mostrada novamente.'];
    }
    return ['tipo' => 'erro', 'html' => 'Acao invalida.'];
}
