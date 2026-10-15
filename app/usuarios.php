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
    $usuarios = query("SELECT Id, Email, Nome, Status, Admin, CriadoEm,
                              CASE WHEN SenhaHashPHP IS NULL THEN 0 ELSE 1 END AS TemSenhaPHP
                       FROM msoft.Usuarios
                       ORDER BY CASE WHEN Status = 'pendente' THEN 0 ELSE 1 END, Nome");
    return [
        'usuarios' => $usuarios,
        'gruposDisp' => ug_listar(),
        'gruposMapa' => ug_mapa_grupos(array_column($usuarios, 'Id')),
    ];
}

function usuario_get(int $id): ?array
{
    $rows = query('SELECT Id, Email, Nome, Status, Admin FROM msoft.Usuarios WHERE Id = ?', [$id]);
    return $rows[0] ?? null;
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

/** Mesmas telas de telas_disponiveis(), agrupadas por menu e submenu (ordem do MODULES).
 *  Retorno: [ ['grupo' => nome do menu, 'subs' => [ ['nome' => submenu|null, 'telas' => [itens]] ]] ]
 *  Telas diretas do menu ficam num sub com nome = null, sempre antes dos submenus. */
function telas_disponiveis_agrupadas(): array
{
    $grupos = [];
    foreach (MODULES as $g) {
        $subs = [];
        foreach ($g['itens'] as $item) {
            if (isset($item['itens'])) {
                $telas = array_values(array_filter(
                    $item['itens'],
                    static fn (array $t): bool => empty($t['admin'])
                ));
                if ($telas) {
                    $subs[] = ['nome' => $item['nome'], 'telas' => $telas];
                }
                continue;
            }
            if (empty($item['admin'])) {
                if (!$subs || $subs[0]['nome'] !== null) {
                    array_unshift($subs, ['nome' => null, 'telas' => []]);
                }
                $subs[0]['telas'][] = $item;
            }
        }
        if ($subs) {
            $grupos[] = ['grupo' => $g['nome'], 'subs' => $subs];
        }
    }
    return $grupos;
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

/** Cria um novo usuario diretamente pelo painel de Configuracoes/Usuarios (ja ativo). */
function criar_usuario_sistema(array $post): array
{
    $nome = trim((string) ($post['nome'] ?? ''));
    $email = strtolower(trim((string) ($post['email'] ?? '')));
    $senha = (string) ($post['senha'] ?? '');
    $admin = !empty($post['admin']) ? 1 : 0;
    $grupos = (array) ($post['grupos'] ?? []);
    $perm = (array) ($post['perm'] ?? []);

    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['tipo' => 'erro', 'html' => 'Informe o nome completo e um e-mail valido.'];
    }
    if (mb_strlen($nome) > 120 || mb_strlen($email) > 120) {
        return ['tipo' => 'erro', 'html' => 'Nome e e-mail devem ter no maximo 120 caracteres.'];
    }
    if (mb_strlen($senha) < 4) {
        return ['tipo' => 'erro', 'html' => 'A senha deve ter ao menos 4 caracteres.'];
    }

    $existente = query('SELECT Id FROM msoft.Usuarios WHERE Email = ?', [$email])[0] ?? null;
    if ($existente) {
        return ['tipo' => 'erro', 'html' => 'Ja existe um usuario cadastrado com o e-mail <strong>' . e($email) . '</strong>.'];
    }

    try {
        $ins = query(
            "INSERT INTO msoft.Usuarios (Email, Nome, SenhaHash, SenhaHashPHP, Status, Admin, LiberadoEm)
             OUTPUT INSERTED.Id
             VALUES (?, ?, ?, ?, 'ativo', ?, GETDATE())",
            [$email, $nome, SENHA_PYTHON_INDISPONIVEL, password_hash($senha, PASSWORD_DEFAULT), $admin]
        );
        $novoId = (int) ($ins[0]['Id'] ?? 0);
        if ($novoId > 0) {
            if ($grupos) {
                ug_definir_grupos($novoId, $grupos);
            }
            if ($perm) {
                ug_salvar_permissoes_usuario($novoId, $perm);
            }
        }
        return [
            'tipo' => 'ok',
            'id' => $novoId,
            'html' => 'Usuario <strong>' . e($nome) . '</strong> (' . e($email) . ') criado e liberado para entrar no sistema.',
        ];
    } catch (Throwable $e) {
        error_log('nouvellesobra criar_usuario: ' . $e->getMessage());
        return ['tipo' => 'erro', 'html' => 'Erro ao criar usuario: ' . e($e->getMessage())];
    }
}

/** Executa liberar/bloquear/gerar-senha/definir-senha/admin/grupos/permissoes. */
function acao_usuario(int $id, string $acao): array
{
    $u = query('SELECT Id, Nome, Email, Admin FROM msoft.Usuarios WHERE Id = ?', [$id])[0] ?? null;
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
        // so grava os grupos quando o formulario enviou o campo de grupos
        if ($acao === 'liberar' && isset($_POST['grupos_enviados'])) {
            $extra = ', em ' . ug_definir_grupos($id, (array) ($_POST['grupos'] ?? [])) . ' grupo(s)';
        }
        return ['tipo' => 'ok', 'html' => "$nome agora esta <strong>$status</strong>$extra."];
    }
    if ($acao === 'grupos') {
        $n = ug_definir_grupos($id, (array) ($_POST['grupos'] ?? []));
        return ['tipo' => 'ok', 'html' => "Grupos de $nome atualizados ($n grupo(s))."];
    }
    if ($acao === 'permissoes') {
        $nGrupos = ug_definir_grupos($id, (array) ($_POST['grupos'] ?? []));
        $nTelas = ug_salvar_permissoes_usuario($id, (array) ($_POST['perm'] ?? []));
        $novoAdmin = !empty($_POST['admin']) ? 1 : 0;
        if (!$novoAdmin && strcasecmp($u['Email'], $_SESSION['usuario'] ?? '') === 0 && !empty($u['Admin'])) {
            $novoAdmin = 1;
        }
        execute('UPDATE msoft.Usuarios SET Admin = ? WHERE Id = ?', [$novoAdmin, $id]);
        return ['tipo' => 'ok', 'html' => "Acessos e permissoes de <strong>$nome</strong> salvos ($nGrupos grupo(s), $nTelas tela(s) individuais)."];
    }
    if ($acao === 'definir-senha') {
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        if (mb_strlen($novaSenha) < 4) {
            return ['tipo' => 'erro', 'html' => 'A nova senha deve ter pelo menos 4 caracteres.'];
        }
        execute("UPDATE msoft.Usuarios SET SenhaHashPHP = ?, Status = CASE WHEN Status = 'pendente' THEN 'ativo' ELSE Status END WHERE Id = ?",
            [password_hash($novaSenha, PASSWORD_DEFAULT), $id]);
        return ['tipo' => 'ok', 'html' => "Nova senha definida com sucesso para <strong>$nome</strong>."];
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
