<?php
declare(strict_types=1);

/** Dias da semana gravados na matriz (char(2)). */
const MP_DIAS = [
    '01' => 'Segunda-feira', '02' => 'Terça-feira', '03' => 'Quarta-feira',
    '04' => 'Quinta-feira',  '05' => 'Sexta-feira', '06' => 'Sábado',
    '07' => 'Domingo',
];

/** Centros de custo (dbMGA + dbConv): Centro_CodC (9 chars = Empresa+Filial+Centro) => rotulo. */
function mp_centros(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    foreach ([env('DB_ERP_MGA'), env('DB_ERP_CONV')] as $base) {
        try {
            $rows = query("SELECT RTRIM(Empresa_Cod) Empresa_Cod, RTRIM(Filial_Cod) Filial_Cod,
                                  RTRIM(Centro_Cod) Centro_Cod, CONVERT(varchar(4000), RTRIM(Centro_Nome)) Centro_Nome
                           FROM $base.dbo.Centro_de_Custo
                           WHERE ISNULL(RTRIM(Centro_Inativo), 'N') <> 'S'
                           ORDER BY Centro_Nome");
        } catch (Throwable $e) {
            continue;
        }
        foreach ($rows as $r) {
            $codc = strtoupper($r['Empresa_Cod'] . $r['Filial_Cod'] . $r['Centro_Cod']);
            if (strlen($codc) === 9) {
                $cache[$codc] = $codc . ' - ' . $r['Centro_Nome'];
            }
        }
    }
    asort($cache);
    return $cache;
}

function mp_listar(): array
{
    return query("SELECT IDMatrizPrioridades, RTRIM(Centro_CodC) Centro_CodC,
                         RTRIM(DiaSemana) DiaSemana, RTRIM(MailGestor) MailGestor,
                         RTRIM(Horario) Horario, CAST(Ativado AS int) Ativado
                  FROM msoft.MatrizPrioridades
                  ORDER BY Centro_CodC, DiaSemana, MailGestor");
}

function mp_get(int $id): ?array
{
    $rows = query("SELECT IDMatrizPrioridades, RTRIM(Centro_CodC) Centro_CodC,
                          RTRIM(DiaSemana) DiaSemana, RTRIM(MailGestor) MailGestor,
                          RTRIM(Horario) Horario, CAST(Ativado AS int) Ativado
                   FROM msoft.MatrizPrioridades WHERE IDMatrizPrioridades = ?", [$id]);
    return $rows[0] ?? null;
}

/** Valida e normaliza os dados do formulario. Lanca InvalidArgumentException. */
function mp_validar(array $post): array
{
    // aceita "CGC010102 - Nome" ou so "CGC010102"
    $centro = strtoupper(trim((string) ($post['centro'] ?? '')));
    if (preg_match('/^([A-Z0-9]{9})/', $centro, $m)) {
        $centro = $m[1];
    }
    if (!isset(mp_centros()[$centro])) {
        throw new InvalidArgumentException('Selecione um centro de custo da lista.');
    }
    $dia = trim((string) ($post['dia'] ?? ''));
    if (!isset(MP_DIAS[$dia])) {
        throw new InvalidArgumentException('Selecione o dia da semana.');
    }
    $mail = trim((string) ($post['mail'] ?? ''));
    if ($mail === '') {
        throw new InvalidArgumentException('Informe o e-mail do gestor.');
    }
    if (strlen($mail) > 200) {
        throw new InvalidArgumentException('E-mails excedem 200 caracteres.');
    }
    $horario = trim((string) ($post['horario'] ?? ''));
    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $horario)) {
        throw new InvalidArgumentException('Informe o horario (HH:MM).');
    }
    return ['centro' => $centro, 'dia' => $dia, 'mail' => $mail, 'horario' => $horario,
            'ativado' => isset($post['ativado']) ? 1 : 0];
}

function mp_inserir(array $d): void
{
    execute('INSERT INTO msoft.MatrizPrioridades (Centro_CodC, DiaSemana, MailGestor, Horario, Ativado)
             VALUES (?, ?, ?, ?, ?)',
            [$d['centro'], $d['dia'], $d['mail'], $d['horario'], $d['ativado']]);
}

function mp_atualizar(int $id, array $d): void
{
    execute('UPDATE msoft.MatrizPrioridades
             SET Centro_CodC = ?, DiaSemana = ?, MailGestor = ?, Horario = ?, Ativado = ?
             WHERE IDMatrizPrioridades = ?',
            [$d['centro'], $d['dia'], $d['mail'], $d['horario'], $d['ativado'], $id]);
}

function mp_excluir(int $id): void
{
    execute('DELETE FROM msoft.MatrizPrioridades WHERE IDMatrizPrioridades = ?', [$id]);
}
