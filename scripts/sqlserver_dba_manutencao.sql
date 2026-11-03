-- ============================================================================
-- NexoGI — Script de manutencao e permissoes no SQL Server (rodar como sysadmin)
-- ============================================================================
-- Antes de executar: substitua [USUARIO_APP] pelo login/usuario usado no .env
-- do NexoGI (DB_USER) e revise os tamanhos na secao 3 conforme o espaco livre
-- do disco onde fica a tempdb (unidade E:\ no servidor 172.16.1.123).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) PERMISSAO DE EXECUCAO NA PROCEDURE FINANCEIRA
--    Hoje o usuario do NexoGI ja tem EXECUTE em spMovEconomicoDetalhe e
--    sp_ParcelasAgendaPagto, mas NAO tem em spMovFinLancamentosDetalhe.
--    Sem isso, Relatorio Financeiro, Atualizar do Fluxo de Caixa e o lado
--    financeiro do DRE falham com erro de permissao (SQLSTATE 42000 / 229).
-- ----------------------------------------------------------------------------
USE [dbMGA];
GO
GRANT EXECUTE ON dbo.spMovFinLancamentosDetalhe TO [USUARIO_APP];
GO

USE [dbConv];
GO
GRANT EXECUTE ON dbo.spMovFinLancamentosDetalhe TO [USUARIO_APP];
GO

-- Se recriarem o banco de testes (dbMGATeste), conceder as 3 procedures nele:
-- USE [dbMGATeste];
-- GRANT EXECUTE ON dbo.spMovFinLancamentosDetalhe TO [USUARIO_APP];
-- GRANT EXECUTE ON dbo.spMovEconomicoDetalhe     TO [USUARIO_APP];
-- GRANT EXECUTE ON dbo.sp_ParcelasAgendaPagto     TO [USUARIO_APP];

-- ----------------------------------------------------------------------------
-- 2) PERMISSAO DE DIAGNOSTICO (OPCIONAL, SOMENTE LEITURA)
--    Permite consultar DMVs (sys.dm_exec_sessions, sys.dm_db_session_space_usage,
--    sys.dm_db_file_space_usage) para identificar qual sessao/consulta esta
--    consumindo tempdb ou CPU em tempo real. Nao da permissao de alterar dados.
-- ----------------------------------------------------------------------------
USE [master];
GO
GRANT VIEW SERVER STATE TO [USUARIO_APP];
GO

-- ----------------------------------------------------------------------------
-- 3) TETO DE SEGURANCA (MAXSIZE) NA TEMPDB
--    Por design do SQL Server, os arquivos da tempdb crescem ate o pico
--    necessario e NUNCA encolhem sozinhos (so voltam ao tamanho inicial quando
--    o servico do SQL Server reinicia). Com MAXSIZE=UNLIMITED (padrao atual),
--    uma unica consulta com plano degenerado pode crescer a tempdb ate encher
--    o disco inteiro.
--    Definindo MAXSIZE=4096MB (4 GB) em cada um dos 8 arquivos de dados
--    (= 32 GB somados) e 4 GB no log, qualquer consulta descontrolada e
--    abortada com erro 1105 em vez de lotar o disco.
-- ----------------------------------------------------------------------------
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'tempdev', MAXSIZE = 4096MB, FILEGROWTH = 64MB);
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'temp2',   MAXSIZE = 4096MB, FILEGROWTH = 64MB);
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'temp3',   MAXSIZE = 4096MB, FILEGROWTH = 64MB);
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'temp4',   MAXSIZE = 4096MB, FILEGROWTH = 64MB);
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'temp5',   MAXSIZE = 4096MB, FILEGROWTH = 64MB);
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'temp6',   MAXSIZE = 4096MB, FILEGROWTH = 64MB);
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'temp7',   MAXSIZE = 4096MB, FILEGROWTH = 64MB);
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'temp8',   MAXSIZE = 4096MB, FILEGROWTH = 64MB);
ALTER DATABASE [tempdb] MODIFY FILE (NAME = N'templog', MAXSIZE = 4096MB, FILEGROWTH = 64MB);
GO

-- ----------------------------------------------------------------------------
-- 4) AO CRIAR O BANCO DE TESTES (dbMGATeste)
--    Sempre criar via RESTORE DATABASE a partir de um backup (.bak) do dbMGA
--    (nunca por "Import/Export Data" ou SELECT INTO, que nao copiam indices).
--    Logo apos restaurar o dbMGATeste, atualizar as estatisticas para evitar
--    planos de execucao degenerados que despejam GBs na tempdb:
-- ----------------------------------------------------------------------------
-- USE [dbMGATeste];
-- GO
-- EXEC sp_updatestats;
-- GO
