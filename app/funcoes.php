<?php
declare(strict_types=1);

/*
 * Funcoes dos usuarios (msoft.Funcoes): define Nome, Setor gerenciado
 * (NULL = global, ex.: Diretor) e se pode determinar/gerenciar atividades.
 * Setor efetivo do usuario = COALESCE(funcao.Setor, usuario.Setor).
 */

function fun_listar(): array
{
    return query(
        'SELECT f.*, (SELECT COUNT(*) FROM msoft.Usuarios u WHERE u.Funcao_Id = f.Funcao_Id) AS QtdUsuarios
         FROM msoft.Funcoes f
         ORDER BY CASE WHEN f.Funcao_Setor IS NULL THEN 0 ELSE 1 END,
                  f.Funcao_Setor, f.Funcao_Gestor DESC, f.Funcao_Nome'
    );
}

function fun_get(int $id): ?array
{
    $rows = query('SELECT * FROM msoft.Funcoes WHERE Funcao_Id = ?', [$id]);
    return $rows[0] ?? null;
}

function fun_validar(array $post): array
{
    $nome = trim((string) ($post['nome'] ?? ''));
    $setor = trim((string) ($post['setor'] ?? ''));
    $gestor = !empty($post['gestor']) ? 1 : 0;
    if ($nome === '') {
        throw new InvalidArgumentException('Informe o nome da funcao.');
    }
    if (mb_strlen($nome) > 60) {
        throw new InvalidArgumentException('Nome da funcao deve ter no maximo 60 caracteres.');
    }
    return ['nome' => $nome, 'setor' => $setor !== '' ? $setor : null, 'gestor' => $gestor];
}

function fun_inserir(array $d): int
{
    $ins = query(
        'INSERT INTO msoft.Funcoes (Funcao_Nome, Funcao_Setor, Funcao_Gestor)
         OUTPUT INSERTED.Funcao_Id VALUES (?, ?, ?)',
        [$d['nome'], $d['setor'], $d['gestor']]
    );
    return (int) ($ins[0]['Funcao_Id'] ?? 0);
}

function fun_atualizar(int $id, array $d): void
{
    execute(
        'UPDATE msoft.Funcoes SET Funcao_Nome = ?, Funcao_Setor = ?, Funcao_Gestor = ? WHERE Funcao_Id = ?',
        [$d['nome'], $d['setor'], $d['gestor'], $id]
    );
}

function fun_excluir(int $id): void
{
    $f = fun_get($id);
    if (!$f) {
        throw new InvalidArgumentException('Funcao nao encontrada.');
    }
    $uso = query('SELECT COUNT(*) AS n FROM msoft.Usuarios WHERE Funcao_Id = ?', [$id])[0]['n'] ?? 0;
    if ((int) $uso > 0) {
        throw new InvalidArgumentException("Funcao em uso por $uso usuario(s) — remova o vinculo antes de excluir.");
    }
    execute('DELETE FROM msoft.Funcoes WHERE Funcao_Id = ?', [$id]);
}
