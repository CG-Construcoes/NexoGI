---
name: Delphi-SQL-toPHP
description: Converte uma tela do ERP Delphi (Msoft) para PHP a partir de um trace do SQL Profiler + print da tela. Usar quando o usuario enviar um trace do SQL Server e/ou print de tela do sistema antigo pedindo a conversao para PHP/Laminas.
---

# Delphi-SQL-toPHP

Rotina de conversão tela-a-tela do ERP legado (Delphi + SQL Server) para PHP.
Entradas típicas do usuário: **trace do SQL Profiler** (texto colado ou arquivo) +
**print da tela** do sistema antigo.

## Entradas

1. **Trace** — texto do Profiler contendo os batches/procs que a tela executa:
   `exec spXxx @Param=...`, `SELECT ... FROM ...`, RPCs, logins.
   Pode vir colado na conversa ou como arquivo (.trc exportado para .sql/.txt).
2. **Print** — imagem da tela Delphi: campos, grids, labels, botões, abas,
   filtros e seus valores padrão.
3. Contexto extra (se o usuário der): nome da tela/menu no ERP, prioridades.

## Passo a passo

### 1. Ler o trace
- Identificar **queries/procs** e seus **parâmetros** (nomes, valores, tipos).
- Separar por função: consulta principal (grid), combos/filtros, ações (insert/update).
- Se faltar parâmetro (ex.: usuário logado, empresa padrão), perguntar antes de assumir.
- Anotar a base de cada query: `dbMGA`/`dbConv` = `env('DB_ERP_MGA')`/`env('DB_ERP_CONV')`;
  `CGGestao` = `env('DB_NAME')`. NUNCA hardcodar nome de banco no SQL — sempre `env()`.

### 2. Ler o print
- Extrair: **colunas do grid** (rótulos visíveis → mapear para colunas do trace),
  **filtros** (campos, máscaras de data/moeda, defaults), **ações** (botões,
  contexto), **abas** (sub-grids = outras queries do trace).
- Layout: mesma família visual das telas já convertidas — ver `views/followup_solicitacoes.php`
  e `views/atividades.php` como referência de grid + filtros.

### 3. Decidir a casa da rota
- **Tela nova**: handler em `src/` + entrada em `src/routes.php` (padrão Laminas)
  ou `case` em `public/index.php` quando a tela for muito próxima de uma
  existente procedural — seguir o padrão da tela mais parecida.
- Registrar em `MODULES` (`app/modules.php`) no grupo certo com `'pronto' => true`.
- Adicionar slug no mapa `$ROTAS_TELA` de `public/index.php` para `exigir_tela()`.

### 4. Implementar
- Backend: funções `xxx_` em `app/<modulo>.php` ou classe em `src/`.
- **SQL sempre parametrizado** (`?`). Nunca concatenar entrada do usuário.
- **Sem literal acentuado em SQL** — usar `db_sql_literal('...')`
  (quirk PDO_ODBC do servidor: UTF-8 chega como Latin1, estoura char(n)).
- Colunas `char(n)` lidas com acento: `CONVERT(varchar(N), col)`.
- View em `views/<tela>.php` renderizada via `render()`; escapar tudo com `e()`;
  POSTs com `csrf_campo()`/`csrf_valido()`; links com `url()`.
- Reusar CSS `.atv-*`/`.fcs-*`/`style.css` antes de criar estilo novo.

### 5. Validar contra o trace
- Rodar a mesma query/proc pelo `query()` e conferir que as colunas/valores
  batem com o resultado que a tela Delphi mostraria.
- Relatórios/cálculos: comparar número a número — o alvo é **100% igual ao ERP**
  (lição do fluxo de caixa: atenção a filial+centro, colunas `ant`+`per`,
  seções receitas/despesas/sociedade/outros).
- `php -l` em tudo → `bash scripts/verificar_sintaxe.sh` → backup Drive.

## Checklist de entrega
- [ ] Todas as queries do trace cobertas (ou marcadas como "não necessária" com motivo)
- [ ] Filtros/defaults do print reproduzidos
- [ ] Rota registrada + permissão por grupo + item de menu
- [ ] Sem acento em literais SQL / char(n) com CONVERT onde aplicável
- [ ] Valores conferidos contra a execução real (não assumir)
- [ ] php -l ok + backup executado

## Perguntas antes de implementar (quando ambíguo)
- Qual menu do ERP é a tela? (posição no MODULES)
- O print tem abas/sub-grids sem trace correspondente? Pedir o trace delas.
- Ações do trace (INSERT/UPDATE) — a tela nova escreve? (Lembrar: escrita nas
  bases ERP é decisão pendente — ver "Pendentes estrategicos" no AGENTS.md)
