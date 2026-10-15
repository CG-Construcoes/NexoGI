<?php
declare(strict_types=1);

/*
 * Grupos de Usuarios (Configuracoes) — permissoes por grupo.
 * msoft.UsuarioGrupo      : UsGrupo_Id (identity), UsGrupo_Nome, UsGrupo_Descricao
 * msoft.UsuarioGrupoTelas : UsGrupo_Id + Tela + P_Consultar/P_Inserir/P_Alterar/P_Excluir
 * msoft.UsuarioGrupoMembro: UsGrupo_Id + UsuarioId
 * Permissao efetiva do usuario = UNIAO das permissoes dos grupos dele
 * (ver permissoes_usuario() em app/auth.php). Admin ignora grupos.
 */

function ug_listar(): array
{
    return query("SELECT g.UsGrupo_Id, RTRIM(g.UsGrupo_Nome) UsGrupo_Nome,
                         RTRIM(g.UsGrupo_Descricao) UsGrupo_Descricao,
                         (SELECT COUNT(*) FROM msoft.UsuarioGrupoMembro m
                          WHERE m.UsGrupo_Id = g.UsGrupo_Id) QtdeMembros,
                         (SELECT COUNT(*) FROM msoft.UsuarioGrupoTelas t
                          WHERE t.UsGrupo_Id = g.UsGrupo_Id) QtdeTelas
                  FROM msoft.UsuarioGrupo g
                  WHERE ISNULL(RTRIM(g.UsGrupo_Descricao), '') NOT LIKE '__INDIVIDUAL_USER_%'
                  ORDER BY g.UsGrupo_Nome");
}

function ug_get(int $id): ?array
{
    $rows = query("SELECT UsGrupo_Id, RTRIM(UsGrupo_Nome) UsGrupo_Nome,
                          RTRIM(UsGrupo_Descricao) UsGrupo_Descricao
                   FROM msoft.UsuarioGrupo WHERE UsGrupo_Id = ?", [$id]);
    return $rows[0] ?? null;
}

/** Valida e normaliza os dados do formulario. Lanca InvalidArgumentException. */
function ug_validar(array $post): array
{
    $nome = trim((string) ($post['nome'] ?? ''));
    if ($nome === '') {
        throw new InvalidArgumentException('Informe o nome do grupo.');
    }
    if (strlen($nome) > 60) {
        throw new InvalidArgumentException('Nome excede 60 caracteres.');
    }
    $descricao = trim((string) ($post['descricao'] ?? ''));
    if (strlen($descricao) > 100) {
        throw new InvalidArgumentException('Descricao excede 100 caracteres.');
    }
    return ['nome' => $nome, 'descricao' => $descricao !== '' ? $descricao : null];
}

/** Insere o grupo e devolve o UsGrupo_Id gerado. */
function ug_inserir(array $d): int
{
    $rows = query('INSERT INTO msoft.UsuarioGrupo (UsGrupo_Nome, UsGrupo_Descricao)
                   OUTPUT INSERTED.UsGrupo_Id VALUES (?, ?)',
                  [$d['nome'], $d['descricao']]);
    return (int) ($rows[0]['UsGrupo_Id'] ?? 0);
}

function ug_atualizar(int $id, array $d): void
{
    execute('UPDATE msoft.UsuarioGrupo
             SET UsGrupo_Nome = ?, UsGrupo_Descricao = ? WHERE UsGrupo_Id = ?',
            [$d['nome'], $d['descricao'], $id]);
}

function ug_excluir(int $id): void
{
    execute('DELETE FROM msoft.UsuarioGrupo WHERE UsGrupo_Id = ?', [$id]);
}

/** Matriz slug => [consultar,inserir,alterar,excluir] das permissoes do grupo. */
function ug_telas(int $id): array
{
    $mapa = [];
    foreach (query('SELECT RTRIM(Tela) Tela, P_Consultar, P_Inserir, P_Alterar, P_Excluir
                    FROM msoft.UsuarioGrupoTelas WHERE UsGrupo_Id = ?', [$id]) as $r) {
        $mapa[$r['Tela']] = [
            'consultar' => (bool) $r['P_Consultar'],
            'inserir'   => (bool) $r['P_Inserir'],
            'alterar'   => (bool) $r['P_Alterar'],
            'excluir'   => (bool) $r['P_Excluir'],
        ];
    }
    return $mapa;
}

/** Substitui as telas/permissoes do grupo pelas marcadas.
 *  $perm = [slug => [acao => 1,...]] (formato do formulario). Retorna telas salvas. */
function ug_salvar_telas(int $id, array $perm): int
{
    $validos = array_column(telas_disponiveis(), 'slug');
    execute('DELETE FROM msoft.UsuarioGrupoTelas WHERE UsGrupo_Id = ?', [$id]);
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
        execute('INSERT INTO msoft.UsuarioGrupoTelas (UsGrupo_Id, Tela, P_Consultar, P_Inserir, P_Alterar, P_Excluir)
                 VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $slug, $flags['consultar'], $flags['inserir'], $flags['alterar'], $flags['excluir']]);
        $salvas++;
    }
    return $salvas;
}

/** Membros do grupo: [Id, Nome, Email, Status]. */
function ug_membros(int $id): array
{
    return query('SELECT u.Id, RTRIM(u.Nome) Nome, RTRIM(u.Email) Email, RTRIM(u.Status) Status
                  FROM msoft.UsuarioGrupoMembro m JOIN msoft.Usuarios u ON u.Id = m.UsuarioId
                  WHERE m.UsGrupo_Id = ? ORDER BY u.Nome', [$id]);
}

/** Mapa UsuarioId => [UsGrupo_Id => nome do grupo] (para a tela de usuarios). */
function ug_mapa_grupos(array $usuarioIds): array
{
    if (!$usuarioIds) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($usuarioIds), '?'));
    $mapa = [];
    foreach (query("SELECT m.UsuarioId, m.UsGrupo_Id, RTRIM(g.UsGrupo_Nome) UsGrupo_Nome
                    FROM msoft.UsuarioGrupoMembro m
                    JOIN msoft.UsuarioGrupo g ON g.UsGrupo_Id = m.UsGrupo_Id
                    WHERE m.UsuarioId IN ($ph)
                      AND ISNULL(RTRIM(g.UsGrupo_Descricao), '') NOT LIKE '__INDIVIDUAL_USER_%'
                    ORDER BY g.UsGrupo_Nome", $usuarioIds) as $r) {
        $mapa[(int) $r['UsuarioId']][(int) $r['UsGrupo_Id']] = $r['UsGrupo_Nome'];
    }
    return $mapa;
}

/** Substitui os grupos do usuario pelos marcados (preservando permissoes individuais). Retorna a quantidade gravada. */
function ug_definir_grupos(int $usuarioId, array $grupoIds): int
{
    $validos = array_map('intval', array_column(ug_listar(), 'UsGrupo_Id'));
    execute("DELETE m FROM msoft.UsuarioGrupoMembro m
             JOIN msoft.UsuarioGrupo g ON g.UsGrupo_Id = m.UsGrupo_Id
             WHERE m.UsuarioId = ?
               AND ISNULL(RTRIM(g.UsGrupo_Descricao), '') NOT LIKE '__INDIVIDUAL_USER_%'", [$usuarioId]);
    $salvos = 0;
    foreach (array_unique(array_map('intval', $grupoIds)) as $gid) {
        if (!in_array($gid, $validos, true)) {
            continue;
        }
        execute('INSERT INTO msoft.UsuarioGrupoMembro (UsGrupo_Id, UsuarioId) VALUES (?, ?)',
                [$gid, $usuarioId]);
        $salvos++;
    }
    return $salvos;
}

/** Retorna as permissoes efetivas de um usuario especifico (uniao de grupos + individual). */
function ug_permissoes_efetivas_usuario(int $usuarioId): array
{
    $mapa = [];
    $rows = query(
        'SELECT RTRIM(t.Tela) Tela,
                MAX(CAST(t.P_Consultar AS INT)) P_Consultar,
                MAX(CAST(t.P_Inserir AS INT))   P_Inserir,
                MAX(CAST(t.P_Alterar AS INT))   P_Alterar,
                MAX(CAST(t.P_Excluir AS INT))   P_Excluir
         FROM msoft.UsuarioGrupoTelas t
         JOIN msoft.UsuarioGrupoMembro m ON m.UsGrupo_Id = t.UsGrupo_Id
         WHERE m.UsuarioId = ?
         GROUP BY t.Tela',
        [$usuarioId]
    );
    foreach ($rows as $r) {
        $mapa[$r['Tela']] = [
            'consultar' => (bool) $r['P_Consultar'],
            'inserir'   => (bool) $r['P_Inserir'],
            'alterar'   => (bool) $r['P_Alterar'],
            'excluir'   => (bool) $r['P_Excluir'],
        ];
    }
    return $mapa;
}

/** Salva permissoes individuais de telas e acoes diretamente para um usuario. */
function ug_salvar_permissoes_usuario(int $usuarioId, array $perm): int
{
    $marcador = '__INDIVIDUAL_USER_' . $usuarioId;
    $row = query('SELECT UsGrupo_Id FROM msoft.UsuarioGrupo WHERE RTRIM(UsGrupo_Descricao) = ?', [$marcador])[0] ?? null;
    if (!$row) {
        $ins = query(
            'INSERT INTO msoft.UsuarioGrupo (UsGrupo_Nome, UsGrupo_Descricao) OUTPUT INSERTED.UsGrupo_Id VALUES (?, ?)',
            ['Individual #' . $usuarioId, $marcador]
        );
        $gid = (int) ($ins[0]['UsGrupo_Id'] ?? 0);
    } else {
        $gid = (int) $row['UsGrupo_Id'];
    }
    if ($gid <= 0) {
        return 0;
    }
    $temMembro = query('SELECT 1 AS ok FROM msoft.UsuarioGrupoMembro WHERE UsGrupo_Id = ? AND UsuarioId = ?', [$gid, $usuarioId]);
    if (!$temMembro) {
        execute('INSERT INTO msoft.UsuarioGrupoMembro (UsGrupo_Id, UsuarioId) VALUES (?, ?)', [$gid, $usuarioId]);
    }
    return ug_salvar_telas($gid, $perm);
}
