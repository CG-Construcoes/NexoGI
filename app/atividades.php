<?php
declare(strict_types=1);

/*
 * Controle de Atividades — camada de dados e regras de negocio.
 * Tabelas msoft.* na base CGGestao (ver scripts/migrar_atividades.php).
 *
 * Papeis: gestor = admin OU Funcao 'Gestor'/'Coordenador' em msoft.Usuarios.
 *   - so gestor cria/edita/conclui/cancela/exclui
 *   - colaborador (participante): status operacional, comentario,
 *     iniciar/parar tempo e transferencia
 * Visibilidade: Publica (todos) / Setor (mesmo Setor) / Individual
 *   (participantes + gestores). Gestor ve tudo.
 * Dois relogios: trabalhado = soma de apontamentos (botao Iniciar/Parar ou
 *   fechamento automatico ao sair de 'Em andamento'); total = continuo da
 *   1a vez 'Em andamento' ate a conclusao.
 */

const ATV_STATUS = ['A Iniciar', 'Em andamento', 'Aguardando retorno', 'Suspensa', 'Concluida', 'Cancelada'];
const ATV_STATUS_FINAIS = ['Concluida', 'Cancelada'];
const ATV_VISIB = ['Publica', 'Setor', 'Individual'];

/** Usuario logado (linha de msoft.Usuarios + dados da Funcao), cache por request. */
function atv_eu(): ?array
{
    static $eu = false;
    if ($eu !== false) {
        return $eu;
    }
    $eu = null;
    if (!usuario_logado()) {
        return $eu;
    }
    try {
        $rows = query(
            'SELECT u.Id, u.Nome, u.Email, u.Setor, u.Admin, u.Funcao_Id,
                    f.Funcao_Nome, f.Funcao_Setor, f.Funcao_Gestor
             FROM msoft.Usuarios u
             LEFT JOIN msoft.Funcoes f ON f.Funcao_Id = u.Funcao_Id
             WHERE u.Email = ?',
            [$_SESSION['usuario']]
        );
        $eu = $rows[0] ?? null;
    } catch (Throwable $e) {
        error_log('Msoft atividades eu: ' . $e->getMessage());
    }
    return $eu;
}

/** Setor efetivo do usuario: o da Funcao, senao o campo Setor livre. */
function atv_setor_efetivo(array $u): string
{
    return trim((string) ($u['Funcao_Setor'] ?? '')) !== ''
        ? trim((string) $u['Funcao_Setor'])
        : trim((string) ($u['Setor'] ?? ''));
}

/** Pode CRIAR atividades: admin OU qualquer funcao com flag gestor. */
function atv_e_gestor(?array $eu = null): bool
{
    $eu ??= atv_eu();
    return $eu !== null && (is_admin() || (int) ($eu['Funcao_Gestor'] ?? 0) === 1);
}

/** Gestor GLOBAL: admin OU funcao gestora sem setor (ex.: Diretor). */
function atv_e_gestor_global(?array $eu = null): bool
{
    $eu ??= atv_eu();
    return $eu !== null
        && (is_admin()
            || ((int) ($eu['Funcao_Gestor'] ?? 0) === 1 && atv_setor_efetivo($eu) === ''));
}

/** A atividade envolve alguem do setor (participante ativo cujo setor efetivo bate). */
function atv_envolve_setor(int $id, string $setor): bool
{
    if ($setor === '') {
        return false;
    }
    $rows = query(
        'SELECT TOP 1 1 AS x
         FROM msoft.AtividadeParticipantes p
         JOIN msoft.Usuarios u ON u.Id = p.Usuario_Id
         LEFT JOIN msoft.Funcoes f ON f.Funcao_Id = u.Funcao_Id
         WHERE p.Atividade_Id = ? AND p.Ativo = 1
           AND COALESCE(NULLIF(LTRIM(RTRIM(f.Funcao_Setor)), \'\'), u.Setor) = ?',
        [$id, $setor]
    );
    return !empty($rows);
}

/** Gestor DESTA atividade: global OU gestor do setor envolvido nela. */
function atv_e_gestor_da(array $a, ?array $eu = null): bool
{
    $eu ??= atv_eu();
    if ($eu === null || (int) ($eu['Funcao_Gestor'] ?? 0) !== 1 && !is_admin()) {
        return false;
    }
    if (is_admin() || atv_setor_efetivo($eu) === '') {
        return true; // gestor global
    }
    return atv_envolve_setor((int) $a['Atividade_Id'], atv_setor_efetivo($eu));
}

function atv_aberto(string $status): bool
{
    return !in_array($status, ATV_STATUS_FINAIS, true);
}

/** Urgente = flag do gestor OU prazo a menos de 24h (e ainda aberta). */
function atv_urgente(array $a): bool
{
    if (!atv_aberto((string) $a['Status'])) {
        return false;
    }
    if ((int) ($a['Urgente'] ?? 0) === 1) {
        return true;
    }
    $prazo = atv_ts($a['Prazo'] ?? null);
    return $prazo !== null && $prazo < time() + 86400;
}

/** Vencida = prazo passou e ainda nao foi concluida/cancelada. */
function atv_vencida(array $a): bool
{
    if (!atv_aberto((string) $a['Status'])) {
        return false;
    }
    $prazo = atv_ts($a['Prazo'] ?? null);
    return $prazo !== null && $prazo < time();
}

function atv_ts(?string $d): ?int
{
    if (!$d) {
        return null;
    }
    $t = strtotime(substr((string) $d, 0, 19));
    return $t === false ? null : $t;
}

/** Minutos em texto: '2d 4h', '3h25m', '45m'. */
function atv_fmt_min(?float $min): string
{
    if ($min === null) {
        return '—';
    }
    $m = (int) round($min);
    if ($m < 60) {
        return $m . 'm';
    }
    $h = intdiv($m, 60);
    $r = $m % 60;
    if ($h < 24) {
        return $h . 'h' . str_pad((string) $r, 2, '0', STR_PAD_LEFT) . 'm';
    }
    $d = intdiv($h, 24);
    $rh = $h % 24;
    return $d . 'd ' . $rh . 'h';
}

/** Classe CSS do status (badge). */
function atv_status_css(string $s): string
{
    return 'atv-st-' . [
        'A Iniciar'          => 'ainiciar',
        'Em andamento'       => 'andamento',
        'Aguardando retorno' => 'aguardando',
        'Suspensa'           => 'suspensa',
        'Concluida'          => 'concluida',
        'Cancelada'          => 'cancelada',
    ][$s] ?? 'ainiciar';
}

/** Cor do icone no canto inferior esquerdo: cinza/amarelo/vermelho. */
function atv_badge_cor(): string
{
    if (!usuario_logado()) {
        return 'cinza';
    }
    try {
        $rows = query(
            "SELECT a.Urgente, a.Prazo
             FROM msoft.Atividades a
             JOIN msoft.AtividadeParticipantes p
                  ON p.Atividade_Id = a.Atividade_Id AND p.Ativo = 1
             JOIN msoft.Usuarios u ON u.Id = p.Usuario_Id
             WHERE u.Email = ? AND a.Excluida = 0
               AND a.Status NOT IN ('Concluida', 'Cancelada')",
            [$_SESSION['usuario']]
        );
    } catch (Throwable $e) {
        return 'cinza'; // tabelas podem nao existir antes da migracao
    }
    if (!$rows) {
        return 'cinza';
    }
    foreach ($rows as $r) {
        if (atv_urgente(['Status' => 'Em andamento', 'Urgente' => $r['Urgente'], 'Prazo' => $r['Prazo']])
            || atv_vencida(['Status' => 'Em andamento', 'Prazo' => $r['Prazo']])) {
            return 'vermelho';
        }
    }
    return 'amarelo';
}

/** Usuarios ativos p/ selects de responsavel/participante/transferencia.
 *  Setor exibido = setor efetivo (Funcao, senao o campo livre). */
function atv_usuarios(): array
{
    return query(
        "SELECT u.Id, u.Nome, u.Email,
                COALESCE(NULLIF(LTRIM(RTRIM(f.Funcao_Setor)), ''), u.Setor) AS Setor,
                f.Funcao_Nome AS Funcao
         FROM msoft.Usuarios u
         LEFT JOIN msoft.Funcoes f ON f.Funcao_Id = u.Funcao_Id
         WHERE u.Status = 'ativo'
         ORDER BY u.Nome"
    );
}

/** Usuarios que o gestor pode atribuir: global (Diretor/admin) = todos os
 *  ativos; gestor de setor = somente colaboradores do seu setor efetivo
 *  (subordinados e demais gestores do setor). */
function atv_usuarios_para_atribuir(?array $eu = null): array
{
    $eu ??= atv_eu();
    if ($eu === null) {
        return [];
    }
    $setor = atv_setor_efetivo($eu);
    $sql = "SELECT u.Id, u.Nome, u.Email,
                   COALESCE(NULLIF(LTRIM(RTRIM(f.Funcao_Setor)), ''), u.Setor) AS Setor,
                   f.Funcao_Nome AS Funcao
            FROM msoft.Usuarios u
            LEFT JOIN msoft.Funcoes f ON f.Funcao_Id = u.Funcao_Id
            WHERE u.Status = 'ativo'";
    $par = [];
    if (!atv_e_gestor_global($eu) && $setor !== '') {
        $sql .= " AND COALESCE(NULLIF(LTRIM(RTRIM(f.Funcao_Setor)), ''), u.Setor) = ?";
        $par[] = $setor;
    }
    return query($sql . ' ORDER BY u.Nome', $par);
}

/** Setores em uso (Funcoes + campo livre dos usuarios). */
function atv_setores(): array
{
    $rows = query(
        "SELECT DISTINCT Setor FROM (
            SELECT Funcao_Setor AS Setor FROM msoft.Funcoes
            UNION
            SELECT Setor FROM msoft.Usuarios
        ) s
         WHERE Setor IS NOT NULL AND LTRIM(RTRIM(Setor)) <> ''
         ORDER BY Setor"
    );
    return array_column($rows, 'Setor');
}

function atv_carregar(int $id): array
{
    $rows = query(
        'SELECT a.*, uc.Nome AS CriadoPorNome, uc.Email AS CriadoPorEmail
         FROM msoft.Atividades a
         JOIN msoft.Usuarios uc ON uc.Id = a.CriadoPor
         WHERE a.Atividade_Id = ? AND a.Excluida = 0',
        [$id]
    );
    if (!$rows) {
        throw new InvalidArgumentException('Atividade nao encontrada.');
    }
    return $rows[0];
}

function atv_participantes(int $id): array
{
    return query(
        'SELECT p.Usuario_Id, p.Papel, p.Ativo, p.IncluidoEm, u.Nome, u.Email, u.Setor,
                f.Funcao_Nome AS Funcao
         FROM msoft.AtividadeParticipantes p
         JOIN msoft.Usuarios u ON u.Id = p.Usuario_Id
         LEFT JOIN msoft.Funcoes f ON f.Funcao_Id = u.Funcao_Id
         WHERE p.Atividade_Id = ?
         ORDER BY p.Ativo DESC, CASE WHEN p.Papel = \'responsavel\' THEN 0 ELSE 1 END, u.Nome',
        [$id]
    );
}

function atv_responsavel(array $parts): ?array
{
    foreach ($parts as $p) {
        if ((int) $p['Ativo'] === 1 && $p['Papel'] === 'responsavel') {
            return $p;
        }
    }
    return null;
}

function atv_participa(int $id, int $uid): bool
{
    $rows = query(
        'SELECT 1 AS x FROM msoft.AtividadeParticipantes
         WHERE Atividade_Id = ? AND Usuario_Id = ? AND Ativo = 1',
        [$id, $uid]
    );
    return !empty($rows);
}

/** Regra de visibilidade: gestor da atividade e criador veem tudo; demais
 *  so Publica, Setor (mesmo setor efetivo) ou atividades em que participa. */
function atv_pode_ver(array $a, ?array $eu = null): bool
{
    $eu ??= atv_eu();
    if ($eu === null) {
        return false;
    }
    if (atv_e_gestor_da($a, $eu) || (int) $a['CriadoPor'] === (int) $eu['Id']) {
        return true;
    }
    if ($a['Visibilidade'] === 'Publica') {
        return true;
    }
    $setorMeu = atv_setor_efetivo($eu);
    if ($a['Visibilidade'] === 'Setor'
        && $setorMeu !== ''
        && strcasecmp(trim((string) $a['Setor']), $setorMeu) === 0) {
        return true;
    }
    return atv_participa((int) $a['Atividade_Id'], (int) $eu['Id']);
}

/** Lista da tela principal (visibilidade + filtros). */
function atv_listar(array $f, ?array $eu = null): array
{
    $eu ??= atv_eu();
    if ($eu === null) {
        return [];
    }
    $where = ['a.Excluida = 0'];
    $par = [];

    if (!atv_e_gestor_global($eu)) {
        $meuSetor = atv_setor_efetivo($eu);
        $vis = ["a.Visibilidade = 'Publica'", 'a.CriadoPor = ?',
                'EXISTS (SELECT 1 FROM msoft.AtividadeParticipantes pm
                          WHERE pm.Atividade_Id = a.Atividade_Id
                            AND pm.Usuario_Id = ? AND pm.Ativo = 1)'];
        $par[] = (int) $eu['Id'];
        $par[] = (int) $eu['Id'];
        if ($meuSetor !== '') {
            $vis[] = "(a.Visibilidade = 'Setor' AND a.Setor = ?)";
            $par[] = $meuSetor;
        }
        // gestor de setor ve tudo que envolve alguem do seu setor
        if ((int) ($eu['Funcao_Gestor'] ?? 0) === 1 && $meuSetor !== '') {
            $vis[] = 'EXISTS (SELECT 1 FROM msoft.AtividadeParticipantes pm
                              JOIN msoft.Usuarios pu ON pu.Id = pm.Usuario_Id
                              LEFT JOIN msoft.Funcoes pf ON pf.Funcao_Id = pu.Funcao_Id
                              WHERE pm.Atividade_Id = a.Atividade_Id AND pm.Ativo = 1
                                AND COALESCE(NULLIF(LTRIM(RTRIM(pf.Funcao_Setor)), \'\'), pu.Setor) = ?)';
            $par[] = $meuSetor;
        }
        $where[] = '(' . implode("\n               OR ", $vis) . ')';
    }

    $escopo = $f['escopo'] ?? 'minhas';
    if ($escopo === 'minhas') {
        $where[] = 'EXISTS (SELECT 1 FROM msoft.AtividadeParticipantes pm
                            WHERE pm.Atividade_Id = a.Atividade_Id
                              AND pm.Usuario_Id = ? AND pm.Ativo = 1)';
        $par[] = (int) $eu['Id'];
    } elseif ($escopo === 'criadas') {
        $where[] = 'a.CriadoPor = ?';
        $par[] = (int) $eu['Id'];
    }

    if (!empty($f['status'])) {
        $in = implode(',', array_fill(0, count($f['status']), '?'));
        $where[] = "a.Status IN ($in)";
        foreach ($f['status'] as $s) {
            $par[] = $s;
        }
    }
    if ($f['data_ini'] !== '') {
        $where[] = 'CONVERT(date, a.CriadoEm) >= ?';
        $par[] = $f['data_ini'];
    }
    if ($f['data_fim'] !== '') {
        $where[] = 'CONVERT(date, a.CriadoEm) <= ?';
        $par[] = $f['data_fim'];
    }
    if (!empty($f['resp'])) {
        $where[] = 'EXISTS (SELECT 1 FROM msoft.AtividadeParticipantes pm
                            WHERE pm.Atividade_Id = a.Atividade_Id
                              AND pm.Usuario_Id = ? AND pm.Ativo = 1)';
        $par[] = (int) $f['resp'];
    }
    if ($f['visib'] !== '' && in_array($f['visib'], ATV_VISIB, true)) {
        $where[] = 'a.Visibilidade = ?';
        $par[] = $f['visib'];
    }
    if ($f['setor'] !== '') {
        $where[] = 'a.Setor = ?';
        $par[] = $f['setor'];
    }
    if (!empty($f['urgente'])) {
        $where[] = "(a.Urgente = 1 OR (a.Prazo IS NOT NULL AND a.Prazo < DATEADD(hour, 24, GETDATE())))
                    AND a.Status NOT IN ('Concluida', 'Cancelada')";
    }
    if (!empty($f['vencidas'])) {
        $where[] = "a.Prazo IS NOT NULL AND a.Prazo < GETDATE()
                    AND a.Status NOT IN ('Concluida', 'Cancelada')";
    }
    if ($f['texto'] !== '') {
        $where[] = '(a.Titulo LIKE ? OR a.Orientacoes LIKE ? OR a.Referencia LIKE ?)';
        $like = '%' . $f['texto'] . '%';
        $par[] = $like;
        $par[] = $like;
        $par[] = $like;
    }

    return query(
        'SELECT a.*, uc.Nome AS CriadoPorNome, resp.Nome AS ResponsavelNome,
                resp.Id AS ResponsavelId,
                ISNULL((SELECT SUM(ISNULL(ap.Minutos, DATEDIFF(minute, ap.Inicio, GETDATE())))
                        FROM msoft.AtividadeApontamentos ap
                        WHERE ap.Atividade_Id = a.Atividade_Id), 0) AS TrabMin,
                (SELECT COUNT(*) FROM msoft.AtividadeEventos ev
                 WHERE ev.Atividade_Id = a.Atividade_Id) AS QtdEventos
         FROM msoft.Atividades a
         JOIN msoft.Usuarios uc ON uc.Id = a.CriadoPor
         LEFT JOIN msoft.AtividadeParticipantes rp
                ON rp.Atividade_Id = a.Atividade_Id AND rp.Ativo = 1 AND rp.Papel = \'responsavel\'
         LEFT JOIN msoft.Usuarios resp ON resp.Id = rp.Usuario_Id
         WHERE ' . implode("\n           AND ", $where) . '
         ORDER BY CASE WHEN a.Status IN (\'Concluida\', \'Cancelada\') THEN 1 ELSE 0 END,
                  CASE WHEN a.Urgente = 1
                        OR (a.Prazo IS NOT NULL AND a.Prazo < DATEADD(hour, 24, GETDATE()))
                       THEN 0 ELSE 1 END,
                  CASE WHEN a.Prazo IS NULL THEN 1 ELSE 0 END,
                  a.Prazo, a.CriadoEm DESC',
        $par
    );
}

function atv_eventos(int $id): array
{
    return query(
        'SELECT ev.*, u.Nome AS UsuarioNome
         FROM msoft.AtividadeEventos ev
         LEFT JOIN msoft.Usuarios u ON u.Id = ev.Usuario_Id
         WHERE ev.Atividade_Id = ?
         ORDER BY ev.DhEvento DESC, ev.Evento_Id DESC',
        [$id]
    );
}

function atv_apontamentos(int $id): array
{
    return query(
        'SELECT ap.*, u.Nome AS UsuarioNome,
                ISNULL(ap.Minutos, DATEDIFF(minute, ap.Inicio, GETDATE())) AS MinutosCalc
         FROM msoft.AtividadeApontamentos ap
         JOIN msoft.Usuarios u ON u.Id = ap.Usuario_Id
         WHERE ap.Atividade_Id = ?
         ORDER BY ap.Inicio DESC',
        [$id]
    );
}

/** Tempo trabalhado por usuario (abertos contam ate agora) + total. */
function atv_trabalhado(int $id): array
{
    $rows = query(
        'SELECT u.Nome, ap.Usuario_Id,
                SUM(ISNULL(ap.Minutos, DATEDIFF(minute, ap.Inicio, GETDATE()))) AS Min
         FROM msoft.AtividadeApontamentos ap
         JOIN msoft.Usuarios u ON u.Id = ap.Usuario_Id
         WHERE ap.Atividade_Id = ?
         GROUP BY ap.Usuario_Id, u.Nome
         ORDER BY Min DESC',
        [$id]
    );
    $total = array_sum(array_column($rows, 'Min'));
    return ['porUsuario' => $rows, 'total' => $total];
}

/** Apontamento aberto (relogio rodando) do usuario na atividade. */
function atv_apont_aberto(int $id, int $uid): ?array
{
    $rows = query(
        'SELECT * FROM msoft.AtividadeApontamentos
         WHERE Atividade_Id = ? AND Usuario_Id = ? AND Fim IS NULL',
        [$id, $uid]
    );
    return $rows[0] ?? null;
}

function atv_evento(int $id, string $tipo, ?int $uid, ?string $texto = null,
                    ?string $de = null, ?string $para = null): void
{
    execute(
        'INSERT INTO msoft.AtividadeEventos (Atividade_Id, Usuario_Id, Tipo, Texto, DeValor, ParaValor)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$id, $uid, $tipo, $texto, $de, $para]
    );
}

/** Fecha todos os apontamentos abertos da atividade (saida de 'Em andamento'). */
function atv_fechar_apontamentos(int $id): int
{
    return execute(
        'UPDATE msoft.AtividadeApontamentos
         SET Fim = GETDATE(), Minutos = DATEDIFF(minute, Inicio, GETDATE())
         WHERE Atividade_Id = ? AND Fim IS NULL',
        [$id]
    );
}

/** Cria atividade (so gestor). Retorna o novo id. */
function atv_criar(array $post, array $eu): int
{
    $titulo = trim((string) ($post['titulo'] ?? ''));
    $orient = trim((string) ($post['orientacoes'] ?? ''));
    $visib = (string) ($post['visibilidade'] ?? 'Individual');
    $setor = trim((string) ($post['setor'] ?? ''));
    $prazo = trim((string) ($post['prazo'] ?? ''));
    $urgente = !empty($post['urgente']) ? 1 : 0;
    $referencia = trim((string) ($post['referencia'] ?? ''));
    $respId = (int) ($post['responsavel'] ?? 0);
    $parts = array_map('intval', (array) ($post['participantes'] ?? []));

    if (mb_strlen($titulo) < 3) {
        throw new InvalidArgumentException('Informe um titulo para a atividade.');
    }
    if (!in_array($visib, ATV_VISIB, true)) {
        throw new InvalidArgumentException('Visibilidade invalida.');
    }
    if ($visib === 'Setor' && $setor === '') {
        $setor = trim((string) ($eu['Setor'] ?? ''));
        if ($setor === '') {
            throw new InvalidArgumentException('Informe o setor da atividade.');
        }
    }
    $prazoDb = null;
    if ($prazo !== '') {
        $ts = strtotime($prazo);
        if ($ts === false) {
            throw new InvalidArgumentException('Prazo invalido.');
        }
        $prazoDb = date('Y-m-d H:i:s', $ts);
    }
    if ($respId <= 0) {
        throw new InvalidArgumentException('Escolha o responsavel pela atividade.');
    }

    $ins = query(
        'INSERT INTO msoft.Atividades
            (Titulo, Orientacoes, Visibilidade, Setor, Urgente, Prazo, Referencia, CriadoPor)
         OUTPUT INSERTED.Atividade_Id
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$titulo, $orient !== '' ? $orient : null, $visib, $visib === 'Setor' ? $setor : null,
         $urgente, $prazoDb, $referencia !== '' ? $referencia : null, (int) $eu['Id']]
    );
    $id = (int) ($ins[0]['Atividade_Id'] ?? 0);

    atv_definir_responsavel($id, $respId, (int) $eu['Id'], false);
    foreach (array_diff($parts, [$respId]) as $pid) {
        atv_participante_add($id, $pid, $eu, false);
    }
    atv_evento($id, 'criacao', (int) $eu['Id'],
        'Atividade criada' . ($urgente ? ' (URGENTE)' : '') . ' — visibilidade ' . $visib
        . ($visib === 'Setor' ? " ($setor)" : ''));

    // vinculos enviados junto com a criacao (chips do formulario)
    $vTipos = (array) ($post['vinc_tipo'] ?? []);
    $vBases = (array) ($post['vinc_base'] ?? []);
    $vChaves = (array) ($post['vinc_chave'] ?? []);
    $vRotulos = (array) ($post['vinc_rotulo'] ?? []);
    foreach ($vTipos as $i => $vt) {
        $ch = trim((string) ($vChaves[$i] ?? ''));
        if (!isset(ATV_VINC_TIPOS[$vt]) || $ch === '') {
            continue;
        }
        execute(
            'INSERT INTO msoft.AtividadeVinculos (Atividade_Id, Tipo, Base, Chave, Rotulo, CriadoPor)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $vt, strtoupper((string) ($vBases[$i] ?? '')) === 'MGA' ? 'MGA' : 'CONV',
             $ch, trim((string) ($vRotulos[$i] ?? '')) !== '' ? $vRotulos[$i] : null, (int) $eu['Id']]
        );
    }

    // vinculos enviados junto com a criacao (chips do formulario)
    $vTipos = (array) ($post['vinc_tipo'] ?? []);
    $vBases = (array) ($post['vinc_base'] ?? []);
    $vChaves = (array) ($post['vinc_chave'] ?? []);
    $vRotulos = (array) ($post['vinc_rotulo'] ?? []);
    foreach ($vTipos as $i => $vt) {
        $ch = trim((string) ($vChaves[$i] ?? ''));
        if (!isset(ATV_VINC_TIPOS[$vt]) || $ch === '') {
            continue;
        }
        execute(
            'INSERT INTO msoft.AtividadeVinculos (Atividade_Id, Tipo, Base, Chave, Rotulo, CriadoPor)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $vt, strtoupper((string) ($vBases[$i] ?? '')) === 'MGA' ? 'MGA' : 'CONV',
             $ch, trim((string) ($vRotulos[$i] ?? '')) !== '' ? $vRotulos[$i] : null, (int) $eu['Id']]
        );
    }

    // vinculos enviados junto com a criacao (chips do formulario)
    $vTipos = (array) ($post['vinc_tipo'] ?? []);
    $vBases = (array) ($post['vinc_base'] ?? []);
    $vChaves = (array) ($post['vinc_chave'] ?? []);
    $vRotulos = (array) ($post['vinc_rotulo'] ?? []);
    foreach ($vTipos as $i => $vt) {
        $ch = trim((string) ($vChaves[$i] ?? ''));
        if (!isset(ATV_VINC_TIPOS[$vt]) || $ch === '') {
            continue;
        }
        execute(
            'INSERT INTO msoft.AtividadeVinculos (Atividade_Id, Tipo, Base, Chave, Rotulo, CriadoPor)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $vt, strtoupper((string) ($vBases[$i] ?? '')) === 'MGA' ? 'MGA' : 'CONV',
             $ch, trim((string) ($vRotulos[$i] ?? '')) !== '' ? $vRotulos[$i] : null, (int) $eu['Id']]
        );
    }

    atv_email_atribuicao($id, $respId, $eu, 'atribuiu uma nova atividade a voce');
    return $id;
}

/** Marca/desmarca responsavel mantendo os demais como participantes. */
function atv_definir_responsavel(int $id, int $uid, int $porUid, bool $evento = true): void
{
    $atual = query(
        "SELECT p.Usuario_Id, u.Nome FROM msoft.AtividadeParticipantes p
         JOIN msoft.Usuarios u ON u.Id = p.Usuario_Id
         WHERE p.Atividade_Id = ? AND p.Papel = 'responsavel' AND p.Ativo = 1",
        [$id]
    )[0] ?? null;
    $novo = query('SELECT Nome FROM msoft.Usuarios WHERE Id = ?', [$uid])[0] ?? null;
    if (!$novo) {
        throw new InvalidArgumentException('Usuario de destino nao encontrado.');
    }
    if ($atual && (int) $atual['Usuario_Id'] === $uid) {
        return;
    }
    execute(
        "UPDATE msoft.AtividadeParticipantes SET Papel = 'participante'
         WHERE Atividade_Id = ? AND Papel = 'responsavel'",
        [$id]
    );
    execute(
        "MERGE msoft.AtividadeParticipantes AS t
         USING (SELECT ? AS Atividade_Id, ? AS Usuario_Id) AS s
            ON t.Atividade_Id = s.Atividade_Id AND t.Usuario_Id = s.Usuario_Id
         WHEN MATCHED THEN UPDATE SET Ativo = 1, Papel = 'responsavel'
         WHEN NOT MATCHED THEN INSERT (Atividade_Id, Usuario_Id, Papel, Ativo)
            VALUES (s.Atividade_Id, s.Usuario_Id, 'responsavel', 1);",
        [$id, $uid]
    );
    if ($evento) {
        atv_evento($id, 'transferencia', $porUid, null,
            $atual ? (string) $atual['Nome'] : '(ninguem)', (string) $novo['Nome']);
    }
}

function atv_transferir(int $id, int $paraUid, array $eu): void
{
    $a = atv_carregar($id);
    atv_exige_participante_ou_gestor($a, $eu);
    if (!atv_aberto((string) $a['Status'])) {
        throw new InvalidArgumentException('Atividade encerrada nao pode ser transferida.');
    }
    if (!atv_e_gestor_da($a, $eu) && !atv_participa($id, $paraUid)) {
        throw new InvalidArgumentException('O destino precisa ser um participante da atividade.');
    }
    atv_definir_responsavel($id, $paraUid, (int) $eu['Id']);
    atv_email_atribuicao($id, $paraUid, $eu, 'transferiu uma atividade para voce');
}

/** Transicoes permitidas ao colaborador (participante); gestor pode todas. */
function atv_transicoes_colaborador(): array
{
    return [
        'A Iniciar'          => ['Em andamento'],
        'Em andamento'       => ['Aguardando retorno', 'Suspensa'],
        'Aguardando retorno' => ['Em andamento'],
        'Suspensa'           => ['Em andamento'],
        'Concluida'          => [],
        'Cancelada'          => [],
    ];
}

function atv_mudar_status(int $id, string $novo, array $eu): void
{
    $a = atv_carregar($id);
    atv_exige_participante_ou_gestor($a, $eu);
    $atual = (string) $a['Status'];
    if (!in_array($novo, ATV_STATUS, true)) {
        throw new InvalidArgumentException('Status invalido.');
    }
    if ($novo === $atual) {
        throw new InvalidArgumentException('A atividade ja esta nesse status.');
    }
    if (!atv_e_gestor_da($a, $eu)) {
        $perm = atv_transicoes_colaborador()[$atual] ?? [];
        if (!in_array($novo, $perm, true)) {
            throw new InvalidArgumentException("Transicao '$atual' -> '$novo' permitida somente ao gestor.");
        }
    }

    $campos = ['Status = ?'];
    $par = [$novo];
    if ($novo === 'Em andamento' && $a['IniciadoEm'] === null) {
        $campos[] = 'IniciadoEm = GETDATE()';
    }
    if ($novo === 'Concluida') {
        $campos[] = 'ConcluidoEm = GETDATE()';
        $campos[] = 'CanceladaEm = NULL';
    }
    if ($novo === 'Cancelada') {
        $campos[] = 'CanceladaEm = GETDATE()';
        $campos[] = 'ConcluidoEm = NULL';
    }
    if ($atual === 'Em andamento' && $novo !== 'Em andamento') {
        atv_fechar_apontamentos($id);
    }
    if (in_array($atual, ATV_STATUS_FINAIS, true) && atv_aberto($novo)) {
        $campos[] = 'ConcluidoEm = NULL';
        $campos[] = 'CanceladaEm = NULL';
    }
    $par[] = $id;
    execute('UPDATE msoft.Atividades SET ' . implode(', ', $campos) . ' WHERE Atividade_Id = ?', $par);
    atv_evento($id, 'status', (int) $eu['Id'], null, $atual, $novo);
}

/** Botao Iniciar/Parar do colaborador (apontamento de tempo). */
function atv_trabalhar(int $id, array $eu): string
{
    $a = atv_carregar($id);
    if (!atv_participa($id, (int) $eu['Id'])) {
        throw new InvalidArgumentException('Somente participantes podem apontar tempo.');
    }
    $status = (string) $a['Status'];
    if (in_array($status, ['Aguardando retorno', 'Suspensa'], true)) {
        throw new InvalidArgumentException("Atividade '$status' — retorne para 'Em andamento' antes de apontar tempo.");
    }
    if (!atv_aberto($status)) {
        throw new InvalidArgumentException('Atividade encerrada nao aceita apontamentos.');
    }
    $aberto = atv_apont_aberto($id, (int) $eu['Id']);
    if ($aberto) {
        execute(
            'UPDATE msoft.AtividadeApontamentos
             SET Fim = GETDATE(), Minutos = DATEDIFF(minute, Inicio, GETDATE())
             WHERE Apont_Id = ?',
            [(int) $aberto['Apont_Id']]
        );
        atv_evento($id, 'apontamento', (int) $eu['Id'],
            'Parou o relogio (' . atv_fmt_min(max(0, time() - (atv_ts($aberto['Inicio']) ?? time()))) . ')');
        return 'parado';
    }
    if ($status === 'A Iniciar') {
        atv_mudar_status($id, 'Em andamento', $eu); // trabalhar ja inicia a atividade
    }
    execute(
        'INSERT INTO msoft.AtividadeApontamentos (Atividade_Id, Usuario_Id, Inicio) VALUES (?, ?, GETDATE())',
        [$id, (int) $eu['Id']]
    );
    atv_evento($id, 'apontamento', (int) $eu['Id'], 'Iniciou o relogio de trabalho');
    return 'iniciado';
}

function atv_comentar(int $id, string $texto, array $eu): void
{
    $a = atv_carregar($id);
    atv_exige_participante_ou_gestor($a, $eu);
    $texto = trim($texto);
    if ($texto === '') {
        throw new InvalidArgumentException('Escreva um comentario.');
    }
    atv_evento($id, 'comentario', (int) $eu['Id'], $texto);
}

function atv_participante_add(int $id, int $uid, array $eu, bool $validaPermissao = true): void
{
    $a = atv_carregar($id);
    if ($validaPermissao) {
        atv_exige_gestor_da($a, $eu);
    }
    $u = query('SELECT Nome FROM msoft.Usuarios WHERE Id = ?', [$uid])[0] ?? null;
    if (!$u) {
        throw new InvalidArgumentException('Usuario nao encontrado.');
    }
    execute(
        "MERGE msoft.AtividadeParticipantes AS t
         USING (SELECT ? AS Atividade_Id, ? AS Usuario_Id) AS s
            ON t.Atividade_Id = s.Atividade_Id AND t.Usuario_Id = s.Usuario_Id
         WHEN MATCHED THEN UPDATE SET Ativo = 1
         WHEN NOT MATCHED THEN INSERT (Atividade_Id, Usuario_Id, Papel, Ativo)
            VALUES (s.Atividade_Id, s.Usuario_Id, 'participante', 1);",
        [$id, $uid]
    );
    atv_evento($id, 'participante', (int) $eu['Id'], null, null, '+ ' . $u['Nome']);
}

function atv_participante_remover(int $id, int $uid, array $eu): void
{
    $a = atv_carregar($id);
    atv_exige_gestor_da($a, $eu);
    $p = query(
        'SELECT p.Papel, u.Nome FROM msoft.AtividadeParticipantes p
         JOIN msoft.Usuarios u ON u.Id = p.Usuario_Id
         WHERE p.Atividade_Id = ? AND p.Usuario_Id = ? AND p.Ativo = 1',
        [$id, $uid]
    )[0] ?? null;
    if (!$p) {
        throw new InvalidArgumentException('Usuario nao participa desta atividade.');
    }
    if ($p['Papel'] === 'responsavel') {
        throw new InvalidArgumentException('Transfira a responsabilidade antes de remover o responsavel.');
    }
    atv_fechar_apont_usuario($id, $uid);
    execute(
        'UPDATE msoft.AtividadeParticipantes SET Ativo = 0 WHERE Atividade_Id = ? AND Usuario_Id = ?',
        [$id, $uid]
    );
    atv_evento($id, 'participante', (int) $eu['Id'], null, null, '- ' . $p['Nome']);
}

function atv_fechar_apont_usuario(int $id, int $uid): void
{
    execute(
        'UPDATE msoft.AtividadeApontamentos
         SET Fim = GETDATE(), Minutos = DATEDIFF(minute, Inicio, GETDATE())
         WHERE Atividade_Id = ? AND Usuario_Id = ? AND Fim IS NULL',
        [$id, $uid]
    );
}

/** Edicao dos dados da atividade (somente gestor). */
function atv_editar(int $id, array $post, array $eu): void
{
    $a = atv_carregar($id);
    atv_exige_gestor_da($a, $eu);
    $titulo = trim((string) ($post['titulo'] ?? ''));
    $orient = trim((string) ($post['orientacoes'] ?? ''));
    $visib = (string) ($post['visibilidade'] ?? 'Individual');
    $setor = trim((string) ($post['setor'] ?? ''));
    $prazo = trim((string) ($post['prazo'] ?? ''));
    $urgente = !empty($post['urgente']) ? 1 : 0;
    // campo Referencia saiu do formulario; preserva o valor se nao enviado
    $referencia = array_key_exists('referencia', $post)
        ? trim((string) $post['referencia'])
        : (string) ($a['Referencia'] ?? '');

    if (mb_strlen($titulo) < 3) {
        throw new InvalidArgumentException('Informe um titulo para a atividade.');
    }
    if (!in_array($visib, ATV_VISIB, true)) {
        throw new InvalidArgumentException('Visibilidade invalida.');
    }
    $prazoDb = null;
    if ($prazo !== '') {
        $ts = strtotime($prazo);
        if ($ts === false) {
            throw new InvalidArgumentException('Prazo invalido.');
        }
        $prazoDb = date('Y-m-d H:i:s', $ts);
    }

    $mudancas = [];
    if ($titulo !== (string) $a['Titulo']) {
        $mudancas[] = 'titulo';
    }
    if ((string) ($a['Visibilidade']) !== $visib || ($visib === 'Setor' && (string) $a['Setor'] !== $setor)) {
        $mudancas[] = 'visibilidade';
    }
    if ((string) ($a['Prazo'] ?? '') !== (string) ($prazoDb ?? '')) {
        $mudancas[] = 'prazo';
    }
    if ((int) $a['Urgente'] !== $urgente) {
        $mudancas[] = $urgente ? 'marcada URGENTE' : 'urgencia removida';
    }
    if ($referencia !== (string) ($a['Referencia'] ?? '')) {
        $mudancas[] = 'referencia';
    }

    execute(
        'UPDATE msoft.Atividades
         SET Titulo = ?, Orientacoes = ?, Visibilidade = ?, Setor = ?,
             Urgente = ?, Prazo = ?, Referencia = ?
         WHERE Atividade_Id = ?',
        [$titulo, $orient !== '' ? $orient : null, $visib,
         $visib === 'Setor' ? ($setor !== '' ? $setor : null) : null,
         $urgente, $prazoDb, $referencia !== '' ? $referencia : null, $id]
    );
    if ($mudancas) {
        atv_evento($id, 'edicao', (int) $eu['Id'], 'Alterou: ' . implode(', ', $mudancas));
    }
}

function atv_excluir(int $id, array $eu): void
{
    $a = atv_carregar($id);
    if (!usuario_pode('atividades', 'excluir') && !atv_e_gestor_da($a, $eu)) {
        throw new InvalidArgumentException('Sem permissao para excluir atividades.');
    }
    atv_fechar_apontamentos($id);
    execute('UPDATE msoft.Atividades SET Excluida = 1 WHERE Atividade_Id = ?', [$id]);
}

function atv_exige_gestor_da(array $a, array $eu): void
{
    if (!atv_e_gestor_da($a, $eu)) {
        throw new InvalidArgumentException('Acao permitida somente a gestores do setor da atividade.');
    }
}

function atv_exige_participante_ou_gestor(array $a, array $eu): void
{
    if (!atv_e_gestor_da($a, $eu) && !atv_participa((int) $a['Atividade_Id'], (int) $eu['Id'])) {
        throw new InvalidArgumentException('Voce nao participa desta atividade.');
    }
}

/** E-mail ao ser atribuido (criacao ou transferencia). Falha silenciosa. */
function atv_email_atribuicao(int $id, int $paraUid, array $eu, string $motivo): void
{
    try {
        $para = query('SELECT Nome, Email FROM msoft.Usuarios WHERE Id = ?', [$paraUid])[0] ?? null;
        if (!$para || trim((string) $para['Email']) === '') {
            return;
        }
        $a = atv_carregar($id);
        $link = rtrim(env('APP_URL', 'http://localhost'), '/') . url('atividade?id=' . $id);
        $prazoTxt = $a['Prazo'] ? data_br(substr((string) $a['Prazo'], 0, 19), true) : 'sem prazo';
        $urgTxt = atv_urgente($a) ? ' <strong style="color:#b91c1c">[URGENTE]</strong>' : '';
        $corpo = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1e293b">'
            . '<p>Ola, <strong>' . e($para['Nome']) . '</strong>.</p>'
            . '<p><strong>' . e($eu['Nome']) . '</strong> ' . e($motivo) . ':' . $urgTxt . '</p>'
            . '<p style="background:#f1f5f9;border-left:4px solid #2563eb;padding:10px 14px">'
            . '<strong>' . e($a['Titulo']) . '</strong><br>'
            . 'Prazo: ' . e($prazoTxt)
            . ($a['Referencia'] ? '<br>Referencia: ' . e($a['Referencia']) : '')
            . '</p>'
            . ($a['Orientacoes'] ? '<p>' . nl2br(e($a['Orientacoes'])) . '</p>' : '')
            . '<p><a href="' . e($link) . '" style="color:#2563eb">Abrir a atividade no sistema</a></p>'
            . '</div>';
        enviar_email((string) $para['Email'], '[Atividades] ' . (string) $a['Titulo'], $corpo);
    } catch (Throwable $e) {
        error_log('Msoft atividades email: ' . $e->getMessage());
    }
}

/* ===================== VINCULOS AO ERP ===================== */

const ATV_VINC_TIPOS = ['Solicitacao' => 'Solicitação', 'Coleta' => 'Coleta',
                        'Pedido' => 'Pedido', 'AR' => 'AR'];

function atv_vinculos(int $id): array
{
    return query(
        'SELECT v.*, u.Nome AS CriadoPorNome
         FROM msoft.AtividadeVinculos v
         LEFT JOIN msoft.Usuarios u ON u.Id = v.CriadoPor
         WHERE v.Atividade_Id = ? ORDER BY v.Tipo, v.Chave',
        [$id]
    );
}

function atv_vinculo_add(int $id, string $tipo, string $base, string $chave,
                         string $rotulo, array $eu): void
{
    $a = atv_carregar($id);
    atv_exige_participante_ou_gestor($a, $eu);
    if (!isset(ATV_VINC_TIPOS[$tipo])) {
        throw new InvalidArgumentException('Tipo de vinculo invalido.');
    }
    $base = strtoupper($base) === 'MGA' ? 'MGA' : 'CONV';
    $chave = trim($chave);
    if ($chave === '') {
        throw new InvalidArgumentException('Item do vinculo nao informado.');
    }
    $dup = query(
        'SELECT 1 x FROM msoft.AtividadeVinculos
         WHERE Atividade_Id = ? AND Tipo = ? AND Base = ? AND Chave = ?',
        [$id, $tipo, $base, $chave]
    );
    if ($dup) {
        return; // ja vinculado
    }
    execute(
        'INSERT INTO msoft.AtividadeVinculos (Atividade_Id, Tipo, Base, Chave, Rotulo, CriadoPor)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$id, $tipo, $base, $chave, trim($rotulo) !== '' ? $rotulo : null, (int) $eu['Id']]
    );
    atv_evento($id, 'edicao', (int) $eu['Id'],
        'Vinculou ' . ATV_VINC_TIPOS[$tipo] . ' ' . $chave . " ($base)");
}

function atv_vinculo_remover(int $id, int $vincId, array $eu): void
{
    $a = atv_carregar($id);
    atv_exige_participante_ou_gestor($a, $eu);
    $v = query('SELECT * FROM msoft.AtividadeVinculos WHERE Vinculo_Id = ? AND Atividade_Id = ?',
        [$vincId, $id])[0] ?? null;
    if (!$v) {
        return;
    }
    execute('DELETE FROM msoft.AtividadeVinculos WHERE Vinculo_Id = ?', [$vincId]);
    atv_evento($id, 'edicao', (int) $eu['Id'],
        'Desvinculou ' . (ATV_VINC_TIPOS[$v['Tipo']] ?? $v['Tipo']) . ' ' . $v['Chave'] . " ({$v['Base']})");
}

/** Colunas do grid de vinculo por tipo (ordem: Base, Centro, Numero, ...). */
const ATV_VINC_COLS = [
    'Solicitacao' => ['Base', 'Centro', 'Numero', 'Especificacao', 'Qtd', 'Data', 'Sit'],
    'Coleta'      => ['Base', 'Centro', 'Numero', 'Especificacao', 'Qtd', 'Data', 'Sit'],
    'Pedido'      => ['Base', 'Centro', 'Numero', 'Especificacao', 'Qtd', 'Data', 'Sit'],
    'AR'          => ['Base', 'Numero', 'Data'],
];

const ATV_VINC_ESPEC_SQL =
    "RTRIM(COALESCE((SELECT su.SubInsumo_Especificacao FROM InsumoSub su
        WHERE su.Insumo_Cod = i.Insumo_Cod AND su.SubInsumo_Cod = i.SubInsumo_Cod), ''))
     + ' ' + RTRIM(COALESCE(i.SolicItem_EspecificacaoCpto, ''))";

/** Empresas + centros de custo ativos das duas bases (datalists do modal). */
function atv_vinc_combo(): array
{
    $out = ['empresas' => [], 'centros' => []];
    foreach (['CONV' => env('DB_ERP_CONV'), 'MGA' => env('DB_ERP_MGA')] as $base => $db) {
        if (!$db) {
            continue;
        }
        try {
            foreach (query(
                "SELECT RTRIM(Empresa_Cod) c, CONVERT(varchar(4000), Empresa_Nome) n
                 FROM Empresa ORDER BY Empresa_Cod", [], $db) as $r) {
                $out['empresas'][$r['c'] . '|' . $base] =
                    ['cod' => $r['c'], 'nome' => $r['c'] . ' — ' . trim($r['n']), 'base' => $base];
            }
            foreach (query(
                "SELECT RTRIM(Centro_Cod) c, CONVERT(varchar(4000), Centro_Nome) n
                 FROM Centro_de_Custo
                 WHERE ISNULL(RTRIM(Centro_Inativo),'N') <> 'S'
                 ORDER BY Centro_Cod", [], $db) as $r) {
                $out['centros'][$r['c'] . '|' . $base] =
                    ['cod' => $r['c'], 'nome' => $r['c'] . ' — ' . trim($r['n']), 'base' => $base];
            }
        } catch (Throwable $e) {
            error_log("Msoft vinculos combo $base: " . $e->getMessage());
        }
    }
    $out['empresas'] = array_values($out['empresas']);
    $out['centros'] = array_values($out['centros']);
    return $out;
}

/** Codigo antes de " — " (valor da datalist) ou texto livre. */
function atv_vinc_cod_ou_texto(string $v): array
{
    $v = trim($v);
    if (str_contains($v, ' — ')) {
        return [trim(explode(' — ', $v)[0]), true];
    }
    return [$v, false];
}

/** Busca itens do ERP para vincular (item a item) nas 2 bases.
 *  Ordena por Base, Centro, Numero. Solicitacao: somente autorizadas
 *  (Instrucao_Cod='A'). Retorna ['cols'=>[...], 'rows'=>[...]]. */
function atv_vinculos_busca(string $tipo, string $q, string $esp = '',
                            string $centroF = '', string $empresa = '',
                            string $dtDe = '', string $dtAte = ''): array
{
    if (!isset(ATV_VINC_TIPOS[$tipo])) {
        return ['cols' => [], 'rows' => []];
    }
    $like = '%' . trim($q) . '%';
    $espec = ATV_VINC_ESPEC_SQL;
    $centro = "LEFT JOIN centro_de_custo cc ON cc.Filial_Cod = s.Filial_Cod
               AND cc.Empresa_Cod = s.Empresa_Cod AND cc.Centro_Cod = s.Centro_Cod
               LEFT JOIN Empresa em ON em.Empresa_Cod = s.Empresa_Cod";
    $ccSel = "CONVERT(varchar(80), RTRIM(COALESCE(cc.Centro_Cod,'')) + '-' + COALESCE(cc.Centro_Nome,''))";

    // filtros internos do modal: especificacao e centro
    $parExtra = [];
    $extra = '';
    $espExpr = match ($tipo) {
        'Solicitacao', 'Coleta' => "CONVERT(varchar(200), $espec)",
        'Pedido' => "CONVERT(varchar(200), RTRIM(COALESCE(pi.PedItem_EspecificacaoCpto,''))
                        + ' ' + RTRIM(COALESCE(pi.PedItem_Marca,'')))",
        default => null,
    };
    if (trim($esp) !== '' && $espExpr !== null) {
        $extra .= " AND $espExpr LIKE ?";
        $parExtra[] = '%' . trim($esp) . '%';
    }
    if (trim($centroF) !== '' && $tipo !== 'AR') {
        [$cen, $exato] = atv_vinc_cod_ou_texto($centroF);
        if ($exato) {
            $extra .= " AND RTRIM(cc.Centro_Cod) = ?";
            $parExtra[] = $cen;
        } else {
            $extra .= " AND CONVERT(varchar(120), RTRIM(COALESCE(cc.Centro_Cod,''))
                         + ' ' + COALESCE(cc.Centro_Nome,'')) LIKE ?";
            $parExtra[] = '%' . $cen . '%';
        }
    }
    if (trim($empresa) !== '' && $tipo !== 'AR') {
        [$emp, $exato] = atv_vinc_cod_ou_texto($empresa);
        if ($exato) {
            $extra .= " AND s.Empresa_Cod = ?";
            $parExtra[] = $emp;
        } else {
            $extra .= " AND (s.Empresa_Cod LIKE ? OR CONVERT(varchar(200), em.Empresa_Nome) LIKE ?)";
            $parExtra[] = '%' . $emp . '%';
            $parExtra[] = '%' . $emp . '%';
        }
    }
    $dtCol = match ($tipo) {
        'Pedido' => 'p.Pedido_Data',
        'AR'     => 'a.AR_Data',
        default  => 's.Solic_Data',
    };
    if (trim($dtDe) === '' && trim($dtAte) === '') {
        $dtDe = date('Y-m-d', strtotime('-30 days'));
        $dtAte = date('Y-m-d', strtotime('+60 days'));
    }
    if (($ts = strtotime(trim($dtDe))) !== false) {
        $extra .= " AND CONVERT(date, $dtCol) >= ?";
        $parExtra[] = date('Y-m-d', $ts);
    }
    if (($ts = strtotime(trim($dtAte))) !== false) {
        $extra .= " AND CONVERT(date, $dtCol) <= ?";
        $parExtra[] = date('Y-m-d', $ts);
    }

    $sql = match ($tipo) {
        'Solicitacao' => "SELECT TOP 40
                Chave = RTRIM(CONVERT(char(8), s.Solic_CentroNumero)) + '/' + i.SolicItem_Item,
                Centro = $ccSel,
                Espec = CONVERT(varchar(160), $espec),
                Qtd = i.SolicItem_Quantidade,
                Dta = CONVERT(char(10), s.Solic_Data, 103),
                Sit = RTRIM(i.SolicItem_Situacao)
            FROM Solicitacao_de_Compra_Itens i
            JOIN Solicitacao_de_Compra s ON s.Solic_Numero = i.Solic_Numero
            $centro
            WHERE RTRIM(i.Instrucao_Cod) = 'A'
              AND RTRIM(i.SolicItem_Situacao) = 'AB'
              AND (CONVERT(varchar(20), s.Solic_CentroNumero) LIKE ?
                   OR CONVERT(varchar(20), i.SolicItem_Numero) LIKE ?)
              $extra
            ORDER BY s.Solic_CentroNumero DESC, i.SolicItem_Item",

        'Coleta' => "SELECT
                Chave = CONVERT(varchar(20), t.Coleta_Numero) + '/' + RTRIM(i.SolicItem_Item),
                Centro = $ccSel,
                Espec = CONVERT(varchar(160), $espec),
                Qtd = i.SolicItem_Quantidade,
                Dta = CONVERT(char(10), s.Solic_Data, 103),
                Sit = RTRIM(i.SolicItem_Situacao)
            FROM (SELECT DISTINCT TOP 40 ci.Coleta_Numero, ci.Solic_Numero, ci.SolicItem_Numero
                  FROM Coleta_de_Preco_Itens ci
                  WHERE CONVERT(varchar(20), ci.Coleta_Numero) LIKE ?
                  ORDER BY ci.Coleta_Numero DESC) t
            JOIN Solicitacao_de_Compra_Itens i
                ON i.SolicItem_Numero = t.SolicItem_Numero
                AND i.Solic_Numero = t.Solic_Numero
            JOIN Solicitacao_de_Compra s ON s.Solic_Numero = i.Solic_Numero
            $centro
            WHERE 1 = 1
            $extra
            ORDER BY t.Coleta_Numero DESC, i.SolicItem_Item",

        'Pedido' => "SELECT
                Chave = CONVERT(varchar(20), p.Pedido_CentroNumero) + '/' + RTRIM(pi.PedItem_Item),
                Centro = $ccSel,
                Espec = CONVERT(varchar(160), CASE WHEN pi.PedItem_AnulaEspecCptoSolic = 'S'
                          THEN RTRIM(COALESCE(pi.PedItem_EspecificacaoCpto,''))
                               + ' ' + RTRIM(COALESCE(pi.PedItem_Marca,''))
                          ELSE $espec END),
                Qtd = pi.PedItem_Quantidade,
                Dta = CONVERT(char(10), p.Pedido_Data, 103),
                Sit = RTRIM(i.SolicItem_Situacao)
            FROM (SELECT TOP 40 pi.Pedido_Numero, pi.PedItem_Item, p2.Pedido_CentroNumero
                  FROM Pedido_Item pi
                  JOIN Pedido p2 ON p2.Pedido_Numero = pi.Pedido_Numero
                  WHERE CONVERT(varchar(20), p2.Pedido_CentroNumero) LIKE ?
                  ORDER BY p2.Pedido_CentroNumero DESC) t
            JOIN Pedido_Item pi ON pi.Pedido_Numero = t.Pedido_Numero
                AND pi.PedItem_Item = t.PedItem_Item
            JOIN Pedido p ON p.Pedido_Numero = pi.Pedido_Numero
            JOIN Solicitacao_de_Compra_Itens i
                ON i.SolicItem_Numero = pi.SolicItem_Numero
                AND i.Solic_Numero = pi.Solic_Numero
            JOIN Solicitacao_de_Compra s ON s.Solic_Numero = pi.Solic_Numero
            $centro
            WHERE 1 = 1
            $extra
            ORDER BY p.Pedido_CentroNumero DESC, pi.PedItem_Item",

        'AR' => "SELECT TOP 40
                Chave = CONVERT(varchar(20), a.AR_Numero),
                Centro = '', Espec = '', Qtd = NULL,
                Dta = CONVERT(char(10), a.AR_Data, 103)
            FROM Aviso_de_Recebimento a
            WHERE CONVERT(varchar(20), a.AR_Numero) LIKE ?
            $extra
            ORDER BY a.AR_Numero DESC",
    };

    $out = [];
    foreach (['CONV' => env('DB_ERP_CONV'), 'MGA' => env('DB_ERP_MGA')] as $base => $db) {
        if (!$db) {
            continue;
        }
        try {
            $par = array_merge($tipo === 'Solicitacao' ? [$like, $like] : [$like], $parExtra);
            foreach (query($sql, $par, $db) as $r) {
                $chave = trim((string) $r['Chave']);
                $centroV = trim((string) ($r['Centro'] ?? ''));
                $centroV = $centroV === '-' ? '' : $centroV;
                $vals = [$base];
                if ($tipo !== 'AR') {
                    $vals[] = $centroV;
                    $vals[] = $chave;
                    $vals[] = trim((string) ($r['Espec'] ?? ''));
                    $vals[] = $r['Qtd'] !== null ? rtrim(rtrim((string) $r['Qtd'], '0'), '.') : '';
                } else {
                    $vals[] = $chave;
                }
                if (trim((string) ($r['Dta'] ?? '')) !== '') {
                    $vals[] = $r['Dta'];
                }
                if ($tipo !== 'AR') {
                    $vals[] = trim((string) ($r['Sit'] ?? ''));
                }
                $out[] = ['tipo' => $tipo, 'base' => $base, 'chave' => $chave,
                          'centro' => $centroV,
                          'rotulo' => $chave, 'vals' => $vals];
            }
        } catch (Throwable $e) {
            error_log("Msoft vinculos busca $base: " . $e->getMessage());
        }
    }
    usort($out, fn($x, $y) =>
        [$x['base'], $x['centro'], str_pad($x['chave'], 20, ' ', STR_PAD_LEFT)]
        <=> [$y['base'], $y['centro'], str_pad($y['chave'], 20, ' ', STR_PAD_LEFT)]
    );
    return ['cols' => ATV_VINC_COLS[$tipo], 'rows' => $out];
}
