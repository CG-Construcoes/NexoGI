# Msoft — ERP interno (versao PHP)

Conversao, tela a tela, do Msoft em Python (`H:\Meu Drive\Msoft`, ver o AGENTS.md de la para
regras de negocio e historico). A versao Python continua funcionando e NAO deve ser alterada.

## Ambiente
- WSL2 `Ubuntu-26.04`, usuario Linux `usuario`; Apache 2.4 + PHP 8.5 (mod_php) + pdo_sqlsrv 5.13.3 + ODBC 18
- Projeto em `/var/www/msoft` (no Windows: `\\wsl.localhost\Ubuntu-26.04\var\www\msoft`)
- Site Apache `msoft.conf`: DocumentRoot `public/`, `FallbackResource /index.php` (sem .htaccess/rewrite,
  para facilitar migrar para outro servidor); acesso em http://localhost
- `.env` (chmod 640) igual ao do Python: DB_SERVER, DB_NAME (CGGestao), DB_ERP_CONV, DB_ERP_MGA, DB_USER, DB_PASSWORD
  e, para e-mail: APP_URL, EMAIL_MODO, SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASSWORD, SMTP_FROM_NAME,
  ADMIN_EMAIL, DOMINIO_PERMITIDO (detalhes na secao "Acesso por e-mail")
- Comandos como root: `wsl.exe -d Ubuntu-26.04 -u root -- <comando>` (a partir do Windows, sem senha)
- **Rede (2026-09-30)**: `C:\Users\Usuario\.wslconfig` com `networkingMode=mirrored`, `hostAddressLoopback=true`
  e `[general] instanceIdleTimeout=-1` (Ubuntu nao desliga por ociosidade). O Ubuntu usa o IP do Windows
  (Wi-Fi, **IP fixo 172.16.1.230**/24, gateway 172.16.1.124, DNS 172.16.1.111; computador `CGCOPNTB06`).
  Acesso pela rede: http://172.16.1.230. Nenhum codigo depende do IP (Apache escuta em todos os enderecos)
- Apache com override `/etc/systemd/system/apache2.service.d/msoft.conf` (Restart=on-failure, apos
  network-online): no boot a rede espelhada pode ficar pronta depois do Apache
- Tarefa agendada do Windows "Msoft - Iniciar Ubuntu (Apache)": no logon roda `wsl -d Ubuntu-26.04 --exec /bin/true`
- Firewall: `H:\Meu Drive\Msoft\msoft-setup\liberar_rede.ps1` (Admin) cria regras `Msoft-HTTP` (Hyper-V + Windows),
  TCP 80, somente redes internas 10/8, 172.16/12, 192.168/16. Regras criadas e acesso de outro PC da rede
  CONFIRMADO pelo usuario em 2026-09-30. O proprio notebook nao abre o proprio IP (limitacao host->WSL):
  no notebook usar http://localhost. A regra Hyper-V nao pode ser removida (Acesso negado), so mantida

## Servidor dedicado NexoGI (2026-10-13)
- O app roda numa maquina Linux dedicada (usuario `suporte`, interface `enp2s0`,
  IP 172.16.1.13/24 DHCP, DNS 172.16.1.111). Projeto em `/home/suporte/Documents/NexoGI`
  (git: github.com/CG-Construcoes/NexoGI). PHP 8.5 + Apache 2.4 (mod_php) +
  PDO_ODBC + ODBC Driver 18 (nesta maquina NAO tem pdo_sqlsrv — `conexao()` cai
  no fallback ODBC).
- **Acesso: https://sistemacg.com** — zona de pesquisa direta `sistemacg.com`
  criada no DNS do AD (VM-CG-SRV001 / 172.16.1.111) com registro A da raiz
  (nome em branco) -> 172.16.1.13. So resolve em maquinas que usam o DNS
  interno; quem nao usa segue pelo IP. NAO criar zona `com` (quebraria todos
  os .com da rede).
- **HTTPS**: certificado autoassinado (SANs `sistemacg.com` + IP 172.16.1.13,
  ~825 dias) em `/etc/ssl/nexogi/` (originais em `/home/suporte/nexogi-tls/`).
  Porta 80 redireciona para 443. Navegadores mostram aviso ate o .crt ser
  instalado como Autoridade de Certificacao Raiz Confiavel nos PCs
  (GPO ou certmgr.msc) — o certificado tem CA:TRUE justamente para isso.
- Site Apache `/etc/apache2/sites-available/nexogi.conf` (copia versionada em
  `scripts/nexogi.apache.conf`): DocumentRoot `public/`, `FallbackResource
  /index.php`, `ServerName sistemacg.com`, vhosts :80 (redirect) e :443 (app).
  `000-default` desabilitado.
- Permissoes por ACL (projeto fica no home do usuario): `setfacl -m
  u:www-data:--x /home/suporte`, `u:www-data:r` no `.env` e `u:www-data:rwX` +
  default ACL em `storage/`.
- Sob mod_php o .env fica cacheado por processo-filho: depois de mexer nele,
  `sudo systemctl reload apache2`.
- `php -S :8000` (scripts/servidor.sh) virou apenas fallback/dev — nao sobe
  sozinho no boot; o Apache (systemd) e o servico principal.

## Estrutura
- `public/index.php` — controlador unico (switch de rotas); `public/static/css/style.css` (copia do Python)
- `app/bootstrap.php` (env, timezone, includes), `app/db.php` (`conexao()`, `query()`, `execute()`),
  `app/auth.php` (sessao, `exigir_login()`, `exigir_admin()`, `autenticar()`, permissoes
  `telas_usuario()`/`usuario_pode()`/`exigir_tela()`), `app/helpers.php`
  (`e()`, `url()`, `redirecionar()`, `render()`, CSRF, `tela_pronta()`), `app/modules.php` (menu MODULES),
  `app/mail.php` (`enviar_email()`), `app/acesso.php` (tokens de senha e fluxo de primeiro acesso)
- `views/layout_base.php` (publicas), `views/layout_app.php` (menu lateral), `views/<tela>.php`
- `scripts/migrar_senhas_php.php` (coluna SenhaHashPHP + senha provisoria admin),
  `scripts/migrar_acesso_email.php` (tabelas TokensSenha/UsuarioTelas — ja rodado),
  `scripts/verificar_sintaxe.sh` (php -l em tudo), `scripts/backup_para_drive.ps1` (espelho em `H:\Meu Drive\Msoft-PHP`),
  `scripts/promover_admin.php`, `scripts/definir_senha_cli.php`, `scripts/gerar_link_acesso.php`,
  `scripts/ver_usuarios_cli.php`, `scripts/teste_smtp_auth.php` (utilitarios de linha de comando)

## Convencoes
- Tela convertida = case em `public/index.php` + view + `'pronto' => true` em MODULES
- Sempre escapar saida com `e()`; formularios POST com `csrf_campo()` e `csrf_valido()`
- SQL sempre com parametros (`?`); nunca concatenar entrada do usuario
- Senhas: coluna `msoft.Usuarios.SenhaHashPHP` (`password_hash`). A coluna `SenhaHash` (scrypt) e do Python
- Depois de mudar codigo: `bash scripts/verificar_sintaxe.sh` e rodar o backup para o Drive

## Acesso por e-mail, senha e permissoes (2026-09-30)
- Rota `/acesso` ("Primeiro acesso ou esqueci a senha", link no login): resposta sempre neutra
  (MSG_ACESSO_NEUTRA), nao revela se o e-mail existe.
  - E-mail cadastrado -> token tipo 'redefinir' (2h) e link `/definir-senha?token=` ao usuario
  - E-mail novo **@cgconstrucoes.com** (env DOMINIO_PERMITIDO) -> cria Usuario pendente
    (Nome temporario = prefixo do e-mail, atualizado na definicao da senha), token tipo 'definir'
    (48h) ao usuario + aviso ao ADMIN_EMAIL (misrain@cgconstrucoes.com) com link para /usuarios
  - Outros dominios -> nada acontece (mesma resposta neutra)
- Tokens em `msoft.TokensSenha`: gravados como hash sha256, uso unico, expiracao, IpOrigem;
  todos os tokens do usuario sao invalidados ao definir a senha. Limite: 1 envio a cada 5 min
  por usuario (conta tokens recentes, usados ou nao)
- `/definir-senha`: token via GET; POST grava `SenhaHashPHP` (+Nome no 1o acesso). Token invalido/
  usado/expirado mostra tela propria. `/cadastro` agora redireciona para `/acesso`
  (`solicitar_acesso()` e `views/cadastro.php` ficaram sem uso)
- **Permissao por tela** (`msoft.UsuarioTelas`): admin ve tudo; demais usuarios so as telas marcadas.
  `telas_disponiveis()` = itens do menu sem flag admin. Tela Usuarios tem checkboxes: ao liberar um
  pendente ("Liberar com estas telas") e na coluna "Telas" dos demais ("Salvar telas")
- Menu (layout_app.php e views/menu.php) so mostra itens permitidos via `usuario_pode()`
- **Bloqueio de rota**: mapa `$ROTAS_TELA` no topo de `public/index.php` (ANTES do switch) aplica
  `exigir_tela()` — sem tocar nos cases das telas travadas; quem nao tem permissao recebe 403
  (`views/erro403.php`). Usuarios ja existentes receberam 'pedidos' na migracao (acesso mantido)
- E-mail (`app/mail.php`): EMAIL_MODO=log grava em `storage/emails.log` (teste); EMAIL_MODO=smtp
  envia via PHPMailer (pacote Debian libphp-phpmailer, autoload `/usr/share/php/libphp-phpmailer/autoload.php`),
  Gmail Workspace STARTTLS 587. SMTP_PASSWORD = senha de app Google; remetente = SMTP_USER (ou SMTP_FROM)
- Estado (2026-10-02): EMAIL_MODO=smtp FUNCIONANDO. SMTP_USER=msolutionsltda@gmail.com (Gmail do
  sistema) com **senha de app** de 16 letras em SMTP_PASSWORD — autenticacao e envio real testados
  OK (e-mail de teste entregue a ADMIN_EMAIL). Senha normal da conta NUNCA funciona no SMTP Google;
  misrain e admin (Admin=1) e segue como ADMIN_EMAIL (quem recebe os avisos de novo cadastro)
- Teste de autenticacao SMTP sem enviar e-mail: `php scripts/teste_smtp_auth.php`
- Teste de ponta a ponta: `/tmp/teste_acesso.sh` (26 verificacoes, modo log)

## Status da conversao
- [x] Login, logout, menu principal, layout com menu lateral
- [x] Cadastro (solicitar acesso) — SUBSTITUIDO pelo fluxo de e-mail acima (rota redireciona)
- [x] Usuarios (admin) — liberar/bloquear/reativar, "Gerar senha" (senha provisoria mostrada 1 vez),
  coluna "Senha (PHP)", checkboxes de telas; admin nao pode bloquear a si mesmo;
  avisos via `definir_aviso()`/`consumir_aviso()`
- [x] Primeiro acesso / redefinir senha por e-mail + permissao por tela (secao acima)
- [ ] "Minha senha" (usuario trocar a propria senha) — sugerido, ainda nao pedido
- Testes automatizados (Windows): `H:\Meu Drive\Msoft\msoft-setup\testar_login.ps1` e `testar_usuarios.ps1`
- [x] Telas travadas convertidas (2026-09-30, com 3 confirmacoes) — ver secao abaixo

## Permissoes por acao (2026-10-05)
- `msoft.UsuarioTelas` tem colunas `P_Consultar`, `P_Inserir`, `P_Alterar`, `P_Excluir`
  (bit). Migracao: grants antigos viraram Consultar=1, demais=0.
- `app/auth.php`: constante `PERM_ACOES`; `permissoes_usuario()` retorna
  slug => mapa de acoes; `usuario_pode(slug, acao='consultar')`;
  `exigir_tela(slug, acao='consultar')`. Rotas de escrita futuras usam
  `exigir_tela('slug', 'inserir'|'alterar'|'excluir')`; views podem ocultar
  botoes com `usuario_pode('slug', 'acao')`.
- `definir_telas()` em `app/usuarios.php` recebe `perm[slug][acao]` do form;
  tela sem Consultar nao e gravada (nao aparece no menu do usuario).
- `views/usuarios.php` renderiza a matriz Tela x acao pela funcao local
  `tabela_permissoes()`; checkbox no cabecalho marca a coluna inteira (permColuna).
- `/usuarios` ja era `exigir_admin()` — restrito a administradores.

## Matriz de Prioridades (2026-10-05)
- Suprimentos > Solicitacoes > Matriz de Prioridades (rota `matriz-prioridades`, permissao propria).
- Tabela `msoft.MatrizPrioridades`: `IDMatrizPrioridades` int IDENTITY PK (auto),
  `Centro_CodC` char(9) (= Empresa_Cod+Filial_Cod+Centro_Cod), `DiaSemana` char(2)
  ('01' segunda .. '07' domingo), `MailGestor` char(200), `Horario` char(5) 'HH:MM'
  (default '08:00'), `Ativado` bit default 1.
- `app/matriz_prioridades.php`: `mp_centros()` monta Centro_CodC de dbMGA+dbConv
  (Centro_de_Custo); `mp_validar`/`mp_inserir`/`mp_atualizar`/`mp_excluir`/`mp_listar`/`mp_get`.
- `views/matriz_prioridades.php`: formulario (centro = combobox datalist, dia = select,
  e-mail com hint "para mais de um e-mail, separe com ponto e virgula", input time
  Horario, checkbox Ativado)
  + grade. Primeira tela a usar as permissoes por acao: form so aparece com Inserir,
  botao Editar com Alterar, Excluir com Excluir; POST chama `exigir_tela(slug,acao)`.

## Centros de Custo — inativos (2026-10-06)
- O ERP ja tem a flag nativa: `Centro_de_Custo.Centro_Inativo` ('S' = inativo,
  'N'/NULL = ativo). Distribuicao hoje: dbMGA 227 inativos / 57 ativos,
  dbConv 15 / 46. NAO criar tabela propria de status (tentativa anterior
  `msoft.CentroCustoStatus` e a tela `centros-custo` foram descartadas).
- `mp_centros()` (Matriz de Prioridades) ja filtra `Centro_Inativo <> 'S'`
  no SQL (combo caiu de 331 para 103 ativos).
- PENDENTE (telas travadas): combos de centro em `/pedidos` e
  `/relatorio-economico` ainda listam inativos — exige 3 confirmacoes.

## Financeiro > Relatorios > Relatorio Financeiro (2026-10-06)
- Rota `relatorio-financeiro`, permissao propria; `app/relatorio_financeiro.php` +
  `views/relatorio_financeiro.php` (reutiliza os estilos `.rel-econ-*`).
- Procedure `spMovFinLancamentosDetalhe` (params nomeados) chamada 2x por
  combinacao centro x nucleo: `@Utilizacao='J'` = detalhe dos lancamentos;
  `@Utilizacao='5'` = Resumo por Agente Financeiro (Tipo/Agente/Conta/ValorOrig).
  ATENCAO: chamada posicional com 'S' na posicao errada da "varchar to int" —
  usar sempre parametros nomeados.
- Secoes pelo campo `RDZ` do detalhe: '1R' Receitas, '2D' Despesas, demais
  Outros Grupos (`SALDO='1'` = linhas que entram no SALDO). Detalhe 'J' NAO
  devolve Classe_Tipo — usar RDZ.
- Parametros iguais ao Economico (Base, Empresa obrigatoria, Periodo, Sintese,
  Grupos sem sociedade, Detalhar por Centro, Apenas Periodo, Nucleo e Centro
  com "Incluir inativos") + checkbox "Resumo por Agente Financeiro".
- Validado vs PDF Informakon (centro 0107, 09/2026+): todos os totais identicos
  ao centavo; total do resumo por agente = Total Geral.
- Linhas de movimento: o detalhe 'J' traz MovFin_Data/MovFin_Numero/
  MovFin_Historico/MovFinItem_ValorOrig por lancamento; sao exibidas embaixo de
  cada GO (classe `rel-mov`) somente quando o checkbox "Analitico (movimentos)"
  esta marcado (desmarcado por padrao, igual ao PDF RelFin_20261013.pdf que
  mostra so os totais por GO). `GO_Titulo` ja vem com o codigo embutido
  ("1202 Impostos...") — o prefixo e removido para nao duplicar na linha.
- "Detalhar por Centro" gera um bloco por centro (como as folhas do PDF); sem
  ele, o relatorio consolida todos os centros — ao comparar com o Informakon,
  somar as folhas ou marcar a opcao para bater folha a folha.
- Layout alinhado ao Informakon em 2026-10-13 (PDF RelFin_20261013.pdf,
  CGC/09-2026, todos os valores identicos ao centavo):
  - Cabecalho: `Empresa_Nome + " CNPJ: " + Filial.Filial_CGC` (filial matriz =
    menor Filial_CGC da empresa, formatado XX.XXX.XXX/XXXX-XX), titulo,
    "Impresso em" e linha de filtro `(*).99.9999` = (centros).(nucleos) —
    `(*)`/`99.9999` quando "todos" (notacao INFERIDA do PDF; com filtro
    especifico mostra os codigos).
  - Colunas: Data | No Mvto | Discriminacao | Acum. Anterior | Periodo |
    Acumulado (Data/No Mvto so preenchidas no modo Analitico).
  - SALDO (Receitas+Despesas) logo apos "Total Despesas", antes de
    "Outros Grupos"; total da secao outros = "Total Outros".
  - "Folha No N" do Informakon NAO reproduzido (HTML nao pagina).
- APROVADO pelo usuario em 2026-10-13 ("relatorio ficou perfeito") —
  comparacao visual com RelFin_20261013.pdf OK. Commit 4948ca3 + merge
  no NexoGI. Se precisar alterar, retomar a partir deste estado.

## Financeiro > Caixas e Bancos > Posicao de Contas (2026-10-09)
- Rota `posicao-contas`, permissao propria; `app/posicao_contas.php` +
  `views/posicao_contas.php` (reutiliza estilos `.rel-econ-*`). Subgrupo novo
  "Caixas e Bancos" em MODULES dentro de Financeiro.
- Replica "Posicao Agente Financeiro" do Informakon (trace
  `traces/rel_Posicao_Contas.trc`, PDF `Relatorios/Financeiro-RelatorioPosicaoContas.pdf`).
  NAO usa proc unica: e um conjunto de queries por conta.
- Contas: `AgenteFinanceiroConta` JOIN `AgenteFinanceiro`/`Empresa`, filtros
  Tipo (combo AgenteFinanceiroTipo, default 'BC'), Empresa (obrigatoria),
  Nucleo e Agente opcionais (`X = ? OR ? IS NULL`, valor nos 2 params = filtra),
  `AgeFin_SuspendeImpressao <> 'S'`, `AgeFin_ImpDataSaldoRazao <= data`.
- Por conta: `spGetSaldoDiaAnteriorCon(tipo,ag,conta,data,emp,nuc)` (OUT saldo+data)
  = Saldo Anterior; a mesma proc com `data+1` = Saldo Atual. A proc le
  `Movimento_Financeiro_SaldosCon.MovFin_SaldoConciliado` da ultima
  `MovFin_DataConciliado < data`; sem movs -> `AgeFin_ImpSaldoConciliado` da conta.
- Debitos/Creditos = movs `MovFin_DataConciliado = data` (`Cancelado='N'`,
  `Conciliado='C'`). ATENCAO: no trace o param aparece como datetime2 com fracao
  ('2026-10-06 00:00:00.195...'), mas a semantica e a data pura — o literal com
  fracao da erro de conversao se usado direto.
- Valor Aplicado = soma por conta do SaldoAplicado de `Aplicacao_Financeira`
  (subselects `AplMov_Data <= data`, `MovFinTipo_Cod IN (01..07,09,12,13,16)`,
  DC='D' menos DC='C').
- Ultima Conc = `MAX(dbo.MaiorData(MovFin_DataConciliado, MovFin_DataConciliaAte))`
  sem filtro de data (pode ser posterior a data do relatorio).
- Disponivel = Saldo Atual + Limite (`AgeFin_LimitedeCredito`) + Valor Aplicado.
- Colunas: Conta | Agencia | Saldo Anterior D/C | Debitos | Creditos |
  Saldo Atual D/C | Limite Credito | Valor Aplicado | Disponivel D/C | Ultima Conc.
  D/C = 'C' >= 0, 'D' < 0 (valor absoluto). Totais por agente, por empresa
  ("Total: CGC") e "Total Geral". Header: Empresa / Nucleo / titulo
  "Posicao Agente Financeiro - VALORES CONCILIADOS em <data>" / Impresso em.
- Checkbox "Movimentos Conciliados" desmarcado = modo razao (confirmado pelo
  trace `rel_Posicao_Contas_NaoConciliados.trc`): `spGetSaldoDiaAnterior`
  (saldo `MovFin_SaldoRazao` em `Movimento_Financeiro_Saldos`, por
  `MovFin_Data`), CR/DE do dia por `MovFin_Data` SEM filtro de conciliacao;
  "Ultima Conc" continua = ultima conciliacao (a query nao muda).
- Validado 09/10/2026: CGC/BC/06-10-2026 conciliados — 15 contas, todos os
  valores identicos ao PDF ao centavo; CONV/CVP funciona (empresas distintas
  por base). O `update ... set X=X` do trace e no-op, nao reproduzido.
- Multi-empresa (2026-10-13): filtro Empresa virou radio Todas/Selecionadas
  com checkboxes (padrao dos boxes Centro/Nucleo; vazio = todas). Combo
  Agente lista agentes do tipo na base inteira (sem depender da empresa).
  `posicao_contas_dados` recebe `empresas` (array; [] = todas) e devolve
  `blocos` por empresa + `tot` geral; queries por conta usam o Empresa_Cod
  da propria conta. Layout "Relatorio de Multiplas empresas" (PDF
  Posiçãodecontas_todas.pdf): UMA tabela continua, linha
  `Empresa: COD - Nome` (`.rel-emp`, azul mais escuro) como secao antes dos
  grupos de agente, "Total: COD" por empresa e "Total Geral" na ultima
  linha — SEM quebra de pagina por empresa (confirmado pelo usuario).
  Com 1 empresa so, mantem o layout anterior (cabecalho "Empresa: nome",
  sem linha de empresa). Titulo sempre com sufixo: "- VALORES CONCILIADOS"
  ou "- VALORES DO RAZAO". Revalidado: CVC/CONV/BC/09-10-2026 =
  107.884,40 (igual ao PDF Posiçãodecontas.pdf).

## TELAS TRAVADAS (NAO ALTERAR)
O usuario travou estas telas. **Nao fazer nenhuma alteracao nelas.** Se o usuario pedir para
alterar alguma, **pedir confirmacao 3 vezes** (em 3 mensagens separadas) antes de mexer.
0. **Indice de Pedidos Autorizados solicitados no prazo** (TRAVADA em 2026-10-02) — rota
   `indicador-pedidos-prazo` e `indicador-pedidos-prazo/pedidos`, `app/indicadores.php`,
   `views/indicador_pedidos_prazo.php`, `views/indicador_pedidos_prazo_lista.php`
   e os estilos do indicador em `style.css`
1. **Autorizacao de Pedidos** (agora "Pedidos") — rotas `pedidos`, `pedidos/detalhe` e `pedidos/imprimir`
   em `public/index.php`, `app/pedidos.php` (`pedidos_query`, `dados_tela_pedidos`, `pedido_detalhe`),
   `views/pedidos.php`, `views/pedido_detalhe.php`, estilos em `style.css`
2. **Relacao de Pedidos** (botao "Imprimir Relação") — `imprimirSelecionados`/`imprimirLista` no `<script>` de `views/pedidos.php`
3. **Resumo / Indicador** (painel + botao "Imprimir resumo") — `atualizarResumo`, `imprimirResumo`,
   `gerarGraficoSVG`, `gerarGraficoComLegenda` no `<script>` de `views/pedidos.php`
4. **Impressao de Pedidos** (botao "Imprimir pedidos", so os marcados) — rota `pedidos/imprimir`,
   `app/pedido_impressao.php`, `views/pedido_impressao.php`, `imprimirTodos` em `views/pedidos.php`
5. **Relatorio Economico** (Financeiro > Relatorios, TRAVADO em 2026-10-05) — rota
   `relatorio-economico` em `public/index.php`, `app/relatorio_economico.php`
   (chamada da `spMovEconomicoDetalhe` + agregacao Classe/Assunto/GO + regra de secoes
   com excecao da classe 44 em Outros Grupos), `views/relatorio_economico.php`
   (tela de parametros + relatorio + tela de carregamento), entrada de menu de 3 niveis
   em `app/modules.php` e renderizacao aninhada em `views/layout_app.php`,
   `views/menu.php` e `app/usuarios.php`, estilos `.rel-econ-*` em `style.css`.

Notas do porte (fidelidade ao Python):
- O `<script>` de `views/pedidos.php` e o `<style>` de `views/pedido_impressao.php` sao copias identicas do Python
- Totais/IPI de cada item calculados em decimal no SQL Server (ROUND meio-para-cima) e somados em centavos
  inteiros (`centavos()`), igual ao Decimal/ROUND_HALF_UP do Python
- `quebrar_texto()` reproduz `textwrap.wrap` do Python 3.12+ (inclusive quebra em hifen)
- `pedidos_da_url()` le os `p=` repetidos da query string ($_GET guardaria so o ultimo)
- Validado em 2026-09-30 com `H:\Meu Drive\Msoft\msoft-setup\comparar_python_php.py` e `comparar_impressao_lote.py`:
  grid (6 filtros), combobox, itens JSON (18 pedidos), impressao de 600 pedidos / 608 folhas — tudo identico

Alteracao autorizada (3 confirmacoes em 2026-09-30): ao abrir `/pedidos` sem parametros na URL,
`data_ini` recebe hoje − 30 dias (grid abre com os ultimos 30 dias). Enviar o formulario com a
data vazia lista todos os pedidos. Implementado no case `pedidos` de `public/index.php`.

Alteracao autorizada (3 confirmacoes em 2026-10-02): tela `/pedidos` passa a se chamar "Pedidos";
Centro e Fornecedor viraram comboboxes digitaveis (`input` + `datalist`) limitados aos valores
presentes no resultado do periodo; novo filtro `situacao` (''=todos, 'aut'=Instrucao_Data NOT NULL,
'naut'=NULL). Comboboxes alimentados em `dados_tela_pedidos` a partir de `$rows` (nao mais da
tabela Centro_de_Custo inteira).

Alteracao autorizada (3 confirmacoes em 2026-10-02): filtro "No Pedido" passa a
buscar por `Pedido_CentroNumero` OU `Pedido_Numero` — antes so `Pedido_Numero`,
que nao e o numero exibido na coluna (a coluna mostra `Pedido_CentroNumero`,
numeracao por centro de custo que se repete entre centros/anos).

Alteracao autorizada (3 confirmacoes em 2026-10-02): clicar no Nº do pedido abre
janela nova `/pedidos/detalhe?base=&pedido=` (Pedido_Numero interno) com a ficha
do pedido estilo Informakon — `pedido_detalhe()` em `app/pedidos.php`,
`views/pedido_detalhe.php`, layout `base`. Mapeamento via trace
`traces/Tel_Pedidos.trc`: Solic exibido = `Solic_CentroNumero`+'/'+`SolicItem_Item`;
Sit = `SolicItem_Situacao`; "Cc" = `Insumo.CarIns_Cod`; Especificacao = SubInsumo +
complemento (se `PedItem_AnulaEspecCptoSolic`='S' usa PedItem_EspecificacaoCpto +
Marca, senao SolicItem_EspecificacaoCpto; fallback Insumo_Especificacao);
Insumo-Sub = Insumo_Cod(6d)+SubInsumo_Cod(3d); CNO = `Cadastro_de_Obras.Obra_Cno`
(join `Pedido.Obra_Numero`); CNPJs = `Filial.Filial_CGC` e `Credor.Cre_CGCCPF`,
"Codigo" = `Municipios.Municipio_CodigoContribuinte` de cada um; UF/Municipio/IBGE
= `UFPrestacao`/`MunicipioPrestacao`/`CodigoLocalPrestacao`; rodape = Audit_*,
Pedido_DescPed, Pedido_FreteTotal, Total = soma itens − desconto + frete.

Alteracao autorizada (3 confirmacoes em 2026-10-02): REMOVIDO o painel lateral
"Itens do pedido" da tela `/pedidos` — `aside.pedidos-detalhe`, funcao
`carregarDetalhes`/`dataBR` em `views/pedidos.php`, rota `pedidos/itens`,
funcao `pedido_itens_json` em `app/pedidos.php` e CSS `.pedidos-detalhe`/`.detalhe-*`.
A linha inteira (e o numero) abre a janela `/pedidos/detalhe`.

Alteracao autorizada (3 confirmacoes em 2026-10-02): novos filtros em `/pedidos` —
"Nucleo" (combobox digitavel, opcoes `Nucleo_Cod + ' ' + Nucleo_Nome` limitadas ao
resultado; join `Centro_de_Custo.Nucleo_Cod` -> `Nucleo_de_Negocio`) e
"Descricao de Insumo" (EXISTS em Pedido_Item -> Solicitacao_de_Compra_Itens ->
Insumo/InsumoSub, LIKE na especificacao e complementos).

Alteracao autorizada (3 confirmacoes em 2026-10-05): grupos de centro recolhiveis
(dropdown) em `/pedidos` — clicar no `.grupo-titulo` alterna a classe
`recolhido` no `.grupo` (tabela some/aparece; seta `.grupo-seta` gira).
O clique no `.grupo-check` nao dispara o recolhimento.

Alteracao autorizada (3 confirmacoes em 2026-10-05): coluna "Valor" na grade de
`/pedidos` — exibe `Total_Pedido` (soma dos itens, ja calculada na query)
formatada `R$ #.###,00`, alinhada a direita (`.col-valor`), ultima coluna.

Alteracao autorizada (3 confirmacoes em 2026-10-05): checkbox `.grupo-check` no
cabecalho `.grupo-titulo` de cada centro em `/pedidos` — marca/desmarca todos os
`.row-check` do grupo; estado sincronizado por `atualizarCheckGrupos()`
(checked/indeterminate) dentro do hook de `atualizarResumo`. Estilo
`.grupo-check` em `style.css`.

Alteracao autorizada (3 confirmacoes em 2026-10-09): no Relatorio Economico,
a linha SALDO (Receitas + Despesas — Despesas ja vêm negativas) passou a ser
impressa logo apos "Total Despesas", antes da secao "Recursos em Sociedade"
(antes ficava depois de "Outros Grupos", junto ao Total Geral). Condicao
inalterada: so aparece quando Receitas E Despesas estao marcadas nos Grupos
do filtro (`$dados['mostrar_saldo']`). Alteracao em `views/relatorio_economico.php`
— soma pre-computada antes do loop de secoes (`$saldoAnt`/`$saldoPer`) e emitida
quando `$sec['id'] === 'despesas'`.

## Indicadores - Suprimentos: Indice de Pedidos AUTORIZADOS solicitados no prazo
- Rota `/indicador-pedidos-prazo` (permissao `indicadores-suprimentos`); cartao de acesso em `views/indicadores_suprimentos.php`.
- So entram pedidos AUTORIZADOS (`Pedido.Instrucao_Data` preenchida); o periodo
  (data_ini/data_fim) filtra essa data de autorizacao, nao a Pedido_Data.
- Logica em `app/indicadores.php`: "data da solicitacao" = data de AUTORIZACAO do item
  (`Solicitacao_de_Compra_Itens.Instrucao_Data`, ligado via `Pedido_Item.Solic_Numero`
  + `SolicItem_Numero`). Pedido com varias solicitacoes usa a mais antiga (MIN).
  Dias = Pedido_Data - SolicItem.Instrucao_Data. >=10d = no prazo; <10d = fora do prazo.
- Colunas: Total de Pedidos, Valor Pedidos (soma PedItem_Quantidade*PedItem_PrecoInsumo),
  Fora do Prazo (<10d) + valor + %, No Prazo (>=10d) + valor + %,
  Corrigidos no Periodo + valor + %.
- "Corrigido" = pedido com 2+ eventos 'A' em `Pedido_Autorizacoes` (log de instrucoes:
  A=autorizacao, R=recusa, vazio=outro). dbConv nao tem reautorizacoes; dbMGA tem ~480.
- Grafico de pizza (SVG, sem lib) ao lado da legenda: fatia verde = % no prazo do
  Resumo Geral, atualiza junto com os checkboxes.
- Resumo Geral = soma dos centros com checkbox marcado (todos marcados por padrao,
  recalcula em JS sem recarregar). Periodo padrao ao abrir: ultimos 30 dias.

## Financeiro > Relatorios > Relatorio Economico
- Menu de 3 niveis: MODULES aceita subgrupo `['nome'=>..., 'itens'=>[...telas]]`
  dentro de `itens` do grupo (Financeiro > Relatorios > Relatorio Economico).
  Renderizacao aninhada em views/layout_app.php (sidebar) e views/menu.php (cards);
  telas_disponiveis() em app/usuarios.php recursiva um nivel.
- Rota /relatorio-economico (permissao propria relatorio-economico).
- Fluxo em DUAS telas (a procedure e pesada): views/relatorio_economico.php mostra
  primeiro a tela de parametros (Empresa obrigatoria, periodo, centro
  Todos/Apenas 1/Selecionados via checkboxes,
  Sintetico em Classe/Assunto/Grupo, secoes Receitas/Despesas/Recursos em Sociedade/
  Outros, Detalhar por Centro, Detalhar Apenas Periodo, Medicoes independentes de AR)
  e so executa com gerar=1.
- Dados: app/relatorio_economico.php chama spMovEconomicoDetalhe (dbMGA/dbConv)
  UMA vez e agrega em PHP: Classe > Assunto > GO. ATENCAO: @Empresa_Cod NULL
  retorna 0 linhas — empresa e obrigatoria (combo da tabela Empresa).
- Secoes por Classe_Tipo: 01/06 Receitas; 02/07/08 Despesas; 09 Recursos em
  Sociedade; demais Outros Grupos. EXCECAO do modelo Informakon: classe 44
  (D/C Indevidos, tipo 02) vai para Outros Grupos, nao Despesas — cuidado:
  chave numerica de array vira int, comparar com (string).
  SALDO = Total Receitas + Total Despesas. Ultima linha:
  "Total Geral (Receita-Despesa + Total outros grupos)" = Receitas + Despesas
  (negativas) + Outros Grupos, SEM Recursos em Sociedade — alterado com 3
  confirmacoes em 2026-10-06 (antes somava todas as secoes, como o Informakon).
- Validado contra PDF oficial (out/2026): folhas identicas ao centavo.
  ATENCAO: o banco e alimentado continuamente — valores podem divergir de
  PDFs antigos por lancamentos posteriores; nao e erro.
- "Detalhar Apenas Periodo" marcado: consulta so o periodo (rapido) e mostra so a
  coluna Periodo; desmarcado: consulta desde 1900 e mostra Acum. Anterior / Periodo /
  Acumulado (linhas anteriores a data_ini = Acum. Anterior) — consulta mais lenta.
- "Detalhar por Centro": passa @Detalha_Centro='S' e gera um bloco de relatorio por centro.
- Filtro Nucleo (3 confirmacoes 2026-10-06): box "Nucleo" Todos/Apenas/Selecionados
  igual ao de centro; param posicional 7 (@Nucleo_Cod) da proc — execucao no
  produto cartesiano centros x nucleos; cabecalho mostra "Nucleo: ...".
- Checkbox "Incluir inativos" no box Centro (3 conf 2026-10-06): desmarcado =
  so `Centro_Inativo <> 'S'`; recarrega a tela de parametros via form.submit()
  (sem 'gerar'). REGRA GERAL: toda tela/relatorio com selecao de centro deve ter
  esse checkbox, desmarcado por padrao.
- Centro de Negocio: a procedure aceita UM @Centro_Cod por execucao; para
  "Selecionados" a funcao executa a procedure uma vez por centro e soma os
  resultados. O cabecalho mostra "Centro: Todos", o centro unico ou
  "N centros selecionados" + linha com a lista completa.
- Botao Imprimir usa window.print() com CSS de impressao (esconde filtros/sidebar).
