<?php
declare(strict_types=1);
/*
 * TELA TRAVADA: Impressao de Pedidos (porte fiel de pedido_impressao.py). Somente leitura.
 * Nao alterar sem 3 confirmacoes do usuario.
 */

// Alturas em mm usadas na paginacao (devem casar com views/pedido_impressao.php)
const AREA_ITENS_FOLHA = 162.0;   // folhas intermediarias (sem rodape, com "Continua")
const AREA_ITENS_ULTIMA = 68.0;   // ultima folha (com rodape completo)
const ALTURA_LINHA = 3.2;
const PADDING_ITEM = 2.4;
const LARGURA_DESCRICAO = 36;     // caracteres por linha da discriminacao

const INICIO_PRAZO = ['F' => 'Faturamento', 'P' => 'Pedido', 'E' => 'Entrega'];
const PRAZO_FORA = ['S' => ' fora a semana', 'Q' => ' fora a quinzena', 'M' => ' fora o mês'];

const SQL_PEDIDO = "
SELECT
    p.Empresa_Cod, p.Filial_Cod, p.Centro_Cod, p.Pedido_Numero, p.Pedido_CentroNumero,
    p.Pedido_Data, p.Instrucao_Cod, p.Instrucao_Data, RTRIM(p.Usuario_Instrucao) AS Usuario_Instrucao,
    p.Cre_Cod, p.CreCom_Seq, p.PedForn_PrazoDias, p.PedForn_PrazoPerc, p.PedForn_PrazoFora,
    p.PedForn_PrazoInicio, p.PedForn_NumParc, p.PedForn_CTR, p.Pedido_FreteTotal, p.Pedido_DescPed,
    p.Pedido_Observacao, p.Pedido_ObservacaoCondForn,
    p.Obra_Numero, p.CodigoLocalPrestacao,
    RTRIM(cr.Cre_RazSoc) AS Cre_RazSoc, RTRIM(cr.Cre_CGCCPF) AS Cre_CGCCPF,
    RTRIM(cr.Cre_Endereco) AS Cre_Endereco, RTRIM(cr.Cre_Numero) AS Cre_Numero,
    RTRIM(cr.Cre_Complto) AS Cre_Complto, RTRIM(cr.Cre_Bairro) AS Cre_Bairro,
    RTRIM(cr.Cre_CEP) AS Cre_CEP, RTRIM(cr.Cre_Cidade) AS Cre_Cidade, cr.UF_Sigla AS Cre_UF,
    RTRIM(e.Empresa_Nome) AS Empresa_Nome,
    RTRIM(f.Filial_Nome) AS Filial_Nome, RTRIM(f.Filial_CGC) AS Filial_CGC,
    RTRIM(c.Centro_Nome) AS Centro_Nome, RTRIM(c.MATCEI) AS MATCEI,
    RTRIM(c.Centro_Endereco) AS Centro_Endereco, RTRIM(c.Centro_Numero) AS Centro_Numero,
    RTRIM(c.Centro_Complto) AS Centro_Complto, RTRIM(c.Centro_Bairro) AS Centro_Bairro,
    RTRIM(c.Centro_CEP) AS Centro_CEP, RTRIM(c.Centro_Cidade) AS Centro_Cidade, c.UF_Sigla AS Centro_UF,
    c.Centro_EnderecoFaturamento, c.Centro_FilialEnderecoFaturamen,
    c.Centro_EnderecoCobranca, c.Centro_FilialEnderecoCobranca,
    RTRIM(et.EspTit_Titulo) AS EspTit_Titulo,
    RTRIM(o.Obra_Cno) AS Obra_Cno
FROM dbo.Pedido p
INNER JOIN dbo.Centro_de_Custo c
    ON c.Empresa_Cod = p.Empresa_Cod AND c.Filial_Cod = p.Filial_Cod AND c.Centro_Cod = p.Centro_Cod
INNER JOIN dbo.Filial f ON f.Empresa_Cod = p.Empresa_Cod AND f.Filial_Cod = p.Filial_Cod
INNER JOIN dbo.Credor cr ON cr.Cre_Cod = p.Cre_Cod
INNER JOIN dbo.Empresa e ON e.Empresa_Cod = p.Empresa_Cod
LEFT JOIN dbo.Especie_de_Titulos_Apagar et ON et.EspTit_Cod = p.EspTit_Cod
LEFT JOIN dbo.Cadastro_de_Obras o ON o.Obra_Numero = p.Obra_Numero
WHERE p.Pedido_Numero = ?
";

const SQL_FILIAL = "
SELECT RTRIM(Filial_Endereco) AS Endereco, RTRIM(Filial_Numero) AS Numero,
       RTRIM(Filial_Complto) AS Complto, RTRIM(Filial_Bairro) AS Bairro,
       RTRIM(Filial_CEP) AS CEP, RTRIM(Filial_Cidade) AS Cidade, UF_Sigla AS UF
FROM dbo.Filial WHERE Empresa_Cod = ? AND Filial_Cod = ?
";

const SQL_CREDOR_COM = "
SELECT CreCom_Seq, RTRIM(CreCom_Contato) AS Contato, RTRIM(CreCom_Telefone) AS Telefone,
       RTRIM(CreCom_Fax) AS Fax, RTRIM(CreCom_eMail) AS Email
FROM dbo.CredorComunicacao WHERE Cre_Cod = ? ORDER BY CreCom_Seq
";

const SQL_FILIAL_COM = "
SELECT RTRIM(FilialCom_Departamento) AS Departamento, RTRIM(FilialCom_Contato) AS Contato,
       RTRIM(FilialCom_Telefone) AS Telefone, RTRIM(FilialCom_Fax) AS Fax,
       RTRIM(FilialCom_eMail) AS Email
FROM dbo.Filial_Comunicacao WHERE Empresa_Cod = ? AND Filial_Cod = ? ORDER BY FilialCom_Seq
";

const SQL_CENTRO_COM = "
SELECT RTRIM(CenCom_Departamento) AS Departamento, RTRIM(CenCom_Contato) AS Contato,
       RTRIM(CenCom_Telefone) AS Telefone, RTRIM(CenCom_Fax) AS Fax, RTRIM(CenCom_eMail) AS Email
FROM dbo.CentroComunicacao
WHERE Empresa_Cod = ? AND Filial_Cod = ? AND Centro_Cod = ? ORDER BY CenCom_Seq
";

// Total e IPI de cada item calculados em decimal no SQL Server, com arredondamento meio-para-cima,
// igual ao Decimal(...).quantize(ROUND_HALF_UP) do Python.
const SQL_ITENS = "
SELECT
    x.*,
    ROUND(x.Total_Item * CAST(COALESCE(x.PedItem_AliqIPI, 0) AS decimal(38, 10)) / 100, 2) AS IPI_Item
FROM (
    SELECT
        pi.PedItem_Numero, pi.PedItem_Item, pi.PedItem_Quantidade, pi.PedItem_PrecoInsumo,
        pi.PedItem_AliqIPI, pi.PedItem_DataEntrega, i.Unid_Cod,
        sci.Insumo_Cod, sci.SubInsumo_Cod, sc.Solic_CentroNumero,
        Insumo_Especificacao = CASE WHEN pi.PedItem_AnulaEspecCptoSolic = 'S'
            THEN RTRIM(COALESCE(si.SubInsumo_Especificacao, '')) + ' '
                 + RTRIM(COALESCE(pi.PedItem_EspecificacaoCpto, '')) + ' ' + RTRIM(COALESCE(pi.PedItem_Marca, ''))
            ELSE RTRIM(COALESCE(si.SubInsumo_Especificacao, '')) + ' '
                 + RTRIM(COALESCE(sci.SolicItem_EspecificacaoCpto, '')) + ' '
                 + RTRIM(COALESCE(pi.PedItem_EspecificacaoCpto, '')) + ' ' + RTRIM(COALESCE(pi.PedItem_Marca, ''))
            END,
        ROUND(CAST(COALESCE(pi.PedItem_Quantidade, 0) AS decimal(38, 10))
              * CAST(COALESCE(pi.PedItem_PrecoInsumo, 0) AS decimal(38, 10)), 2) AS Total_Item
    FROM dbo.Pedido_Item pi
    INNER JOIN dbo.Solicitacao_de_Compra_Itens sci
        ON sci.Solic_Numero = pi.Solic_Numero AND sci.SolicItem_Numero = pi.SolicItem_Numero
    INNER JOIN dbo.Solicitacao_de_Compra sc ON sc.Solic_Numero = sci.Solic_Numero
    INNER JOIN dbo.Insumo i ON i.Insumo_Cod = sci.Insumo_Cod
    LEFT JOIN dbo.InsumoSub si ON si.Insumo_Cod = sci.Insumo_Cod AND si.SubInsumo_Cod = sci.SubInsumo_Cod
    WHERE pi.Pedido_Numero = ?
) x
ORDER BY x.PedItem_Numero
";

/** Valor decimal em texto ("4107.920000", "-1.5") -> centavos inteiros, arredondando meio-para-cima. */
function centavos(mixed $v): int
{
    $s = trim((string) ($v ?? '0'));
    if ($s === '') {
        return 0;
    }
    if (stripos($s, 'e') !== false) {
        $s = number_format((float) $s, 10, '.', '');
    }
    $neg = str_starts_with($s, '-');
    $s = ltrim($s, '+-');
    [$int, $frac] = array_pad(explode('.', $s, 2), 2, '');
    $frac = str_pad($frac, 3, '0');
    $c = (int) ($int === '' ? '0' : $int) * 100 + (int) substr($frac, 0, 2) + ((int) $frac[2] >= 5 ? 1 : 0);
    return $neg ? -$c : $c;
}

function so_digitos(?string $v): string
{
    return preg_replace('/\D/', '', (string) $v);
}

function fmt_cnpj(?string $v): string
{
    $d = so_digitos($v);
    if (strlen($d) === 14) {
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12);
    }
    if (strlen($d) === 11) {
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9);
    }
    return $v ?? '';
}

function fmt_cep(?string $v): string
{
    $d = so_digitos($v);
    return strlen($d) === 8 ? substr($d, 0, 2) . '.' . substr($d, 2, 3) . '-' . substr($d, 5) : ($v ?? '');
}

function fmt_fone(?string $v): string
{
    $d = so_digitos($v);
    if (strlen($d) >= 10) {
        $ddd = substr($d, 0, 2);
        $num = substr($d, 2);
        return "(0xx$ddd) " . substr($num, 0, -4) . '-' . substr($num, -4);
    }
    if ($d !== '') {
        return '(0xx ) ' . substr($d, 0, -4) . '-' . substr($d, -4);
    }
    return '(0xx )  -';
}

/** Numero no formato brasileiro (igual ao filtro "br" do Python). */
function br(mixed $valor, int $casas = 2): string
{
    return number_format((float) ($valor ?? 0), $casas, ',', '.');
}

function texto_parcelas(array $p): string
{
    $inicio = INICIO_PRAZO[trim((string) $p['PedForn_PrazoInicio'])] ?? 'Faturamento';
    $fora = PRAZO_FORA[trim((string) $p['PedForn_PrazoFora'])] ?? '';
    $dias = trim((string) $p['PedForn_PrazoDias']);
    $percs = trim((string) $p['PedForn_PrazoPerc']);
    $sufixo = "contados a partir da data do $inicio$fora.";
    if ($dias !== '') {
        $blocosD = str_split($dias, 3);
        $blocosP = $percs === '' ? [] : str_split($percs, 3);
        $partes = [];
        foreach ($blocosD as $i => $d) {
            $perc = isset($blocosP[$i]) && ctype_digit($blocosP[$i]) ? (int) $blocosP[$i] : 0;
            $partes[] = "$perc% com " . (int) $d . ' dias';
        }
        return implode(', ', $partes) . ',  ' . $sufixo;
    }
    if ($p['PedForn_NumParc']) {
        return sprintf('%02d', (int) $p['PedForn_NumParc']) . " parcelas iguais  $sufixo";
    }
    return $sufixo;
}

/** Divide uma palavra nos hifens onde o textwrap do Python (break_on_hyphens) tambem dividiria. */
function _pedacos_palavra(string $palavra): array
{
    $ch = mb_str_split($palavra);
    $n = count($ch);
    $letra = fn(int $i) => $i >= 0 && $i < $n && preg_match('/^[\p{L}\p{M}_]$/u', $ch[$i]) === 1;
    $pedacos = [];
    $atual = '';
    for ($i = 0; $i < $n; $i++) {
        $atual .= $ch[$i];
        if ($ch[$i] !== '-') {
            continue;
        }
        $antes = ($letra($i - 2) && $letra($i - 1))
            || ($letra($i - 3) && ($ch[$i - 2] ?? '') === '-' && $letra($i - 1));
        $depois = $letra($i + 1) && ($letra($i + 2) || (($ch[$i + 2] ?? '') === '-' && $letra($i + 3)));
        if ($antes && $depois) {
            $pedacos[] = $atual;
            $atual = '';
        }
    }
    if ($atual !== '') {
        $pedacos[] = $atual;
    }
    return $pedacos;
}

/** Equivalente a textwrap.wrap(texto, largura) do Python 3.12+ (texto ja com espacos simples). */
function quebrar_texto(string $texto, int $largura): array
{
    $chunks = [];
    foreach (preg_split('/( )/', $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $parte) {
        array_push($chunks, ...($parte === ' ' ? [' '] : _pedacos_palavra($parte)));
    }
    $chunks = array_reverse($chunks);
    $linhas = [];
    while ($chunks) {
        $cur = [];
        $curLen = 0;
        if (trim(end($chunks)) === '' && $linhas) {
            array_pop($chunks);
        }
        while ($chunks) {
            $l = mb_strlen(end($chunks));
            if ($curLen + $l > $largura) {
                break;
            }
            $cur[] = array_pop($chunks);
            $curLen += $l;
        }
        if ($chunks && mb_strlen(end($chunks)) > $largura) {
            $chunk = end($chunks);
            $espaco = $largura < 1 ? 1 : $largura - $curLen;
            $fim = $espaco;
            $hifen = mb_strrpos(mb_substr($chunk, 0, $espaco), '-');
            if ($hifen !== false && $hifen > 0 && trim(mb_substr($chunk, 0, $hifen), '-') !== '') {
                $fim = $hifen + 1;
            }
            $cur[] = mb_substr($chunk, 0, $fim);
            $chunks[array_key_last($chunks)] = mb_substr($chunk, $fim);
        }
        if ($cur && trim(end($cur)) === '') {
            array_pop($cur);
        }
        if ($cur) {
            $linhas[] = implode('', $cur);
        }
    }
    return $linhas;
}

function _endereco(?array $end): array
{
    if (!$end) {
        return ['linha' => '', 'cep_cidade' => ''];
    }
    $linha = ($end['Endereco'] ?? '') . ', ' . ($end['Numero'] ?? '');
    if (!empty($end['Complto'])) {
        $linha .= ' ' . $end['Complto'];
    }
    $linha .= ' Bairro: ' . ($end['Bairro'] ?? '');
    return ['linha' => $linha,
        'cep_cidade' => 'CEP: ' . fmt_cep($end['CEP'] ?? null) . ' - ' . ($end['Cidade'] ?? '') . '-' . ($end['UF'] ?? '')];
}

/** tipo 'F' = endereco da filial indicada; demais = endereco do centro. */
function _endereco_por_tipo(?string $tipo, string $empresa, ?string $filialRef, array $centro, string $banco): array
{
    if (trim((string) $tipo) === 'F') {
        $rows = query(SQL_FILIAL, [$empresa, $filialRef], $banco);
        return _endereco($rows[0] ?? null);
    }
    return _endereco($centro);
}

function _depto(array $rows, string $nome): array
{
    foreach ($rows as $r) {
        if (strtoupper((string) $r['Departamento']) === strtoupper($nome)) {
            return $r;
        }
    }
    return [];
}

function _paginar(array $itens): array
{
    foreach ($itens as &$it) {
        $it['altura'] = count($it['linhas']) * ALTURA_LINHA + PADDING_ITEM;
    }
    unset($it);
    $folhas = [];
    $restantes = $itens;
    while (array_sum(array_column($restantes, 'altura')) > AREA_ITENS_ULTIMA) {
        $folha = [];
        $usado = 0.0;
        while ($restantes && (!$folha || $usado + $restantes[0]['altura'] <= AREA_ITENS_FOLHA)) {
            $usado += $restantes[0]['altura'];
            $folha[] = array_shift($restantes);
        }
        if (!$restantes && count($folha) > 1) {
            array_unshift($restantes, array_pop($folha));
        }
        $folhas[] = $folha;
        if (!$restantes) {
            break;
        }
    }
    $folhas[] = $restantes;
    return $folhas;
}

function carregar_pedido(string $base, int $pedidoNumero): ?array
{
    $banco = banco_da_base($base);
    $rows = query(SQL_PEDIDO, [$pedidoNumero], $banco);
    if (!$rows) {
        return null;
    }
    $p = $rows[0];

    $centroEnd = ['Endereco' => $p['Centro_Endereco'], 'Numero' => $p['Centro_Numero'],
        'Complto' => $p['Centro_Complto'], 'Bairro' => $p['Centro_Bairro'],
        'CEP' => $p['Centro_CEP'], 'Cidade' => $p['Centro_Cidade'], 'UF' => $p['Centro_UF']];
    $creEnd = ['Endereco' => $p['Cre_Endereco'], 'Numero' => $p['Cre_Numero'], 'Complto' => $p['Cre_Complto'],
        'Bairro' => $p['Cre_Bairro'], 'CEP' => $p['Cre_CEP'], 'Cidade' => $p['Cre_Cidade'], 'UF' => $p['Cre_UF']];

    $creCom = query(SQL_CREDOR_COM, [$p['Cre_Cod']], $banco);
    $seq = trim((string) $p['CreCom_Seq']);
    $fornecedorCom = [];
    foreach ($creCom as $c) {
        if (trim((string) $c['CreCom_Seq']) === $seq) {
            $fornecedorCom = $c;
            break;
        }
    }
    $fornecedorCom = $fornecedorCom ?: ($creCom[0] ?? []);

    $filCom = query(SQL_FILIAL_COM, [$p['Empresa_Cod'], $p['Filial_Cod']], $banco);
    $cenCom = query(SQL_CENTRO_COM, [$p['Empresa_Cod'], $p['Filial_Cod'], $p['Centro_Cod']], $banco);
    $compras = _depto($filCom, 'Compras');
    $financeiro = _depto($filCom, 'Financeiro');
    $almox = _depto($cenCom, 'Almoxarifado');

    $itens = [];
    $valorCent = $ipiCent = 0;
    foreach (query(SQL_ITENS, [$pedidoNumero], $banco) as $r) {
        $totalCent = centavos($r['Total_Item']);
        $valorCent += $totalCent;
        $ipiCent += centavos($r['IPI_Item']);
        $desc = trim(preg_replace('/\s+/u', ' ', (string) $r['Insumo_Especificacao']));
        $itens[] = [
            'item' => $r['PedItem_Item'],
            'sc' => $r['Solic_CentroNumero'],
            'codigo' => sprintf('%06d-%03d', (int) $r['Insumo_Cod'], (int) $r['SubInsumo_Cod']),
            'linhas' => quebrar_texto($desc, LARGURA_DESCRICAO) ?: [''],
            'entrega' => $r['PedItem_DataEntrega'],
            'unidade' => trim((string) $r['Unid_Cod']),
            'quantidade' => (float) $r['PedItem_Quantidade'],
            'preco' => (float) $r['PedItem_PrecoInsumo'],
            'ipi' => (float) $r['PedItem_AliqIPI'],
            'total' => $totalCent / 100,
        ];
    }

    $descontoCent = centavos($p['Pedido_DescPed']);
    $freteCent = centavos($p['Pedido_FreteTotal']);
    $totalPedCent = $valorCent + $ipiCent + $freteCent - $descontoCent;

    $autorizacao = '';
    if (trim((string) $p['Instrucao_Cod']) === 'A' && $p['Instrucao_Data']) {
        $autorizacao = 'Autorizado ' . date('d/m/Y H:i:s', strtotime($p['Instrucao_Data'])) . ' ' . ($p['Usuario_Instrucao'] ?? '');
    }

    return [
        'base' => $base,
        'numero' => sprintf('%06d', (int) $p['Pedido_CentroNumero']),
        'filial_nome' => mb_strtoupper((string) $p['Filial_Nome']),
        'data' => $p['Pedido_Data'],
        'centro' => "{$p['Empresa_Cod']}.{$p['Filial_Cod']}.{$p['Centro_Cod']} - " . ($p['Centro_Nome'] ?? ''),
        'cei' => $p['MATCEI'] ?? '',
        'fornecedor' => [
            'nome' => $p['Cre_RazSoc'], 'cnpj' => fmt_cnpj($p['Cre_CGCCPF']),
            'contato' => $fornecedorCom['Contato'] ?? '',
            'fone' => fmt_fone($fornecedorCom['Telefone'] ?? null), 'fax' => fmt_fone($fornecedorCom['Fax'] ?? null),
            'email' => $fornecedorCom['Email'] ?? '',
        ] + _endereco($creEnd),
        'faturamento' => [
            'nome' => $p['Empresa_Nome'], 'cnpj' => fmt_cnpj($p['Filial_CGC']),
            'fone' => fmt_fone($compras['Telefone'] ?? null), 'email' => $compras['Email'] ?? '',
        ] + _endereco_por_tipo($p['Centro_EnderecoFaturamento'], $p['Empresa_Cod'],
            $p['Centro_FilialEnderecoFaturamen'], $centroEnd, $banco),
        'cobranca' => [
            'contato' => $financeiro['Contato'] ?? '', 'fone' => fmt_fone($financeiro['Telefone'] ?? null),
            'email' => $financeiro['Email'] ?? '',
        ] + _endereco_por_tipo($p['Centro_EnderecoCobranca'], $p['Empresa_Cod'],
            $p['Centro_FilialEnderecoCobranca'], $centroEnd, $banco),
        'entrega' => [
            'cmun' => trim((string) $p['CodigoLocalPrestacao']),
            'contato' => $almox['Contato'] ?? '', 'fone' => fmt_fone($almox['Telefone'] ?? null),
            'email' => $almox['Email'] ?? '',
        ] + _endereco($centroEnd),
        'obra' => $p['Obra_Numero'] ? sprintf('%04d', (int) $p['Obra_Numero']) : '',
        'cno' => $p['Obra_Cno'] ?? '',
        'obs_fornecimento' => trim((string) $p['Pedido_ObservacaoCondForn']),
        'observacao' => trim((string) $p['Pedido_Observacao']),
        'transporte' => trim((string) $p['PedForn_CTR']) === 'C' ? 'CIF' : 'FOB',
        'especie' => $p['EspTit_Titulo'] ?? '',
        'parcelas' => texto_parcelas($p),
        'valor_pedido' => $valorCent / 100, 'desconto' => $descontoCent / 100, 'total_ped' => $totalPedCent / 100,
        'autorizacao' => $autorizacao,
        'retorno_fone' => fmt_fone(($compras['Fax'] ?? '') ?: ($compras['Telefone'] ?? null)),
        'folhas' => _paginar($itens),
        'impresso_em' => date('d/m/Y H:i:s'),
    ];
}

/** Le os parametros p=BASE:NUM repetidos da URL (o $_GET do PHP guardaria so o ultimo). */
function pedidos_da_url(): array
{
    $chaves = [];
    foreach (explode('&', (string) ($_SERVER['QUERY_STRING'] ?? '')) as $par) {
        [$k, $v] = array_pad(explode('=', $par, 2), 2, '');
        if (urldecode($k) === 'p') {
            $chaves[] = urldecode($v);
        }
    }
    return $chaves;
}

function dados_impressao_pedidos(array $chaves): array
{
    $pedidos = $erros = [];
    foreach ($chaves as $chave) {
        [$base, $numero] = array_pad(explode(':', $chave, 2), 2, '');
        if (!in_array($base, ['CONV', 'MGA'], true) || !ctype_digit($numero)) {
            continue;
        }
        try {
            $doc = carregar_pedido($base, (int) $numero);
        } catch (Throwable $e) {
            $erros[] = "$base:$numero - " . $e->getMessage();
            continue;
        }
        if ($doc) {
            $pedidos[] = $doc;
        }
    }
    return ['pedidos' => $pedidos, 'erros' => $erros];
}
