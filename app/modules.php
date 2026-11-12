<?php
declare(strict_types=1);

/** Menu lateral: grupos e telas. 'pronto' => true quando a tela ja foi convertida para PHP. */
const MODULES = [
    ['nome' => 'Atividades',    'icone' => '✅', 'itens' => [
        ['slug' => 'atividades', 'nome' => 'Atividades', 'pronto' => true],
    ]],
    ['nome' => 'Comercial',     'icone' => '📈', 'itens' => []],
    ['nome' => 'Contabilidade', 'icone' => '🧮', 'itens' => []],
    ['nome' => 'Engenharia',    'icone' => '🏗️', 'itens' => [
        ['nome' => 'Previsões', 'itens' => [
            ['slug' => 'medicoes', 'nome' => 'Medições', 'pronto' => true],
        ]],
    ]],
    ['nome' => 'Estoque',       'icone' => '📦', 'itens' => []],
    ['nome' => 'Financeiro',    'icone' => '💰', 'itens' => [
        ['nome' => 'Caixas e Bancos', 'itens' => [
            ['slug' => 'posicao-contas', 'nome' => 'Posição de Contas', 'pronto' => true],
        ]],
        ['nome' => 'Contas a Pagar', 'itens' => [
            ['slug' => 'contas-pagar', 'nome' => 'Relatório de Contas a Pagar', 'pronto' => true],
            ['slug' => 'agenda-pagamentos', 'nome' => 'Agenda de Pagamentos', 'pronto' => true],
        ]],
        ['nome' => 'Prestação de Serviço', 'itens' => [
            ['slug' => 'faturamento', 'nome' => 'Relatório de Faturamento', 'pronto' => true],
        ]],
        ['nome' => 'Fluxo de Caixa', 'itens' => [
            ['slug' => 'fluxo-caixa-grupos', 'nome' => 'Grupos de Centros', 'pronto' => true],
            ['slug' => 'fluxo-caixa-aportes', 'nome' => 'Aportes a Retornar', 'pronto' => true],
        ]],
        ['nome' => 'Diretoria', 'itens' => [
            ['slug' => 'dre-ano', 'nome' => 'DRE por Ano', 'pronto' => true],
            ['slug' => 'dre-mes', 'nome' => 'DRE Mensal', 'pronto' => true],
            ['slug' => 'acompanhamentofinanceiro', 'nome' => 'Acompanhamento Financeiro', 'pronto' => true],
        ]],
        ['nome' => 'Relatorios', 'itens' => [
            ['slug' => 'relatorio-economico', 'nome' => 'Relatorio Economico', 'pronto' => true],
            ['slug' => 'relatorio-financeiro', 'nome' => 'Relatorio Financeiro', 'pronto' => true],
        ]],
    ]],
    ['nome' => 'Suprimentos',   'icone' => '🚚', 'itens' => [
        ['nome' => 'Cadastros', 'itens' => [
            ['slug' => 'insumos', 'nome' => 'Insumos', 'pronto' => false],
            ['slug' => 'credor',  'nome' => 'Credor',  'pronto' => false],
        ]],
        ['nome' => 'Solicitacoes', 'itens' => [
            ['slug' => 'solicitacao', 'nome' => 'Registro de Solicitacao', 'pronto' => true],
            ['slug' => 'matriz-prioridades', 'nome' => 'Matriz de Prioridades', 'pronto' => true],
            ['slug' => 'followup-solicitacoes', 'nome' => 'Follow-up das Solicitacoes', 'pronto' => true],
        ]],
        ['nome' => 'Coletas', 'itens' => [
            ['slug' => 'coleta', 'nome' => 'Coleta de Precos', 'pronto' => true],
        ]],
        ['nome' => 'Pedidos', 'itens' => [
            ['slug' => 'followup-pedidos', 'nome' => 'Follow-up dos Pedidos', 'pronto' => true],
            ['slug' => 'ultimas-compras', 'nome' => 'Ultimas Compras', 'pronto' => true],
        ]],
        ['nome' => 'Avisos de Recebimentos', 'itens' => [
        ]],
        ['nome' => 'Relatorios', 'itens' => [
        ]],
    ]],
    ['nome' => 'Indicadores',   'icone' => '📊', 'itens' => [
        ['slug' => 'indicadores-comercial',     'nome' => 'Comercial',     'pronto' => false],
        ['slug' => 'indicadores-contabilidade', 'nome' => 'Contabilidade', 'pronto' => false],
        ['slug' => 'indicadores-engenharia',    'nome' => 'Engenharia',    'pronto' => false],
        ['slug' => 'indicadores-estoque',       'nome' => 'Estoque',       'pronto' => false],
        ['slug' => 'indicadores-financeiro',    'nome' => 'Financeiro',    'pronto' => false],
        ['slug' => 'indicadores-suprimentos',   'nome' => 'Suprimentos',   'pronto' => true],
    ]],
    ['nome' => 'Configuracoes', 'icone' => '⚙️', 'itens' => [
        ['slug' => 'configuracoes', 'nome' => 'Painel de Configuracoes', 'pronto' => true],
        ['slug' => 'usuarios', 'nome' => 'Usuarios e Acessos', 'pronto' => true],
        ['slug' => 'grupos-usuarios', 'nome' => 'Grupos de Permissao', 'pronto' => true],
        ['slug' => 'funcoes', 'nome' => 'Funções', 'pronto' => true],
        ['slug' => 'dicas', 'nome' => 'Dicas', 'pronto' => true],
    ]],
];

/** Telas publicas ja convertidas (controla links na tela de login). */
const TELAS_PUBLICAS_PRONTAS = ['acesso' => true];
