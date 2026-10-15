<?php
declare(strict_types=1);

const TOKEN_REDEFINIR_MIN = 120;   // 2h: link de redefinicao para quem ja tem cadastro
const TOKEN_DEFINIR_MIN = 2880;    // 48h: link de primeiro acesso
const TOKEN_INTERVALO_MIN = 5;     // no maximo 1 link por usuario a cada 5 min

/** Resposta neutra da tela de acesso: nao revela se o e-mail existe ou foi processado. */
const MSG_ACESSO_NEUTRA = 'Se o e-mail estiver apto a usar o sistema, voce recebera uma mensagem com as instrucoes em alguns minutos.';

/**
 * Processa o pedido de link de senha. Nunca retorna detalhes (resposta neutra):
 *  - e-mail cadastrado  -> envia link de redefinicao ao usuario
 *  - e-mail novo no dominio da empresa -> cria usuario pendente, envia link de
 *    primeiro acesso ao usuario e aviso ao administrador
 *  - demais casos -> nada acontece
 */
function processar_pedido_acesso(string $email): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
        return;
    }
    try {
        $u = query('SELECT Id, Nome, Email, Status FROM msoft.Usuarios WHERE Email = ?', [$email])[0] ?? null;
    } catch (Throwable $e) {
        error_log('Msoft acesso: ' . $e->getMessage());
        return;
    }
    if ($u) {
        enviar_link_senha($u, 'redefinir');
        return;
    }
    $dominio = strtolower(env('DOMINIO_PERMITIDO', 'cgconstrucoes.com'));
    if (!str_ends_with($email, '@' . $dominio)) {
        return;
    }
    try {
        execute(
            'INSERT INTO msoft.Usuarios (Email, Nome, SenhaHash) VALUES (?, ?, ?)',
            [$email, strstr($email, '@', true), SENHA_PYTHON_INDISPONIVEL]
        );
        $u = query('SELECT Id, Nome, Email, Status FROM msoft.Usuarios WHERE Email = ?', [$email])[0] ?? null;
    } catch (Throwable $e) {
        error_log('Msoft acesso (novo usuario): ' . $e->getMessage());
        return;
    }
    if ($u && enviar_link_senha($u, 'definir')) {
        avisar_admin_novo_usuario($u['Email']);
    }
}

/**
 * Gera token de uso unico e envia o link por e-mail.
 * Retorna false se ja houver link recente (limite) ou se o envio falhar.
 */
function enviar_link_senha(array $u, string $tipo): bool
{
    $recente = query(
        'SELECT COUNT(*) AS n FROM msoft.TokensSenha
         WHERE UsuarioId = ? AND CriadoEm > DATEADD(MINUTE, -' . TOKEN_INTERVALO_MIN . ', GETDATE())',
        [$u['Id']]
    )[0]['n'] ?? 0;
    if ($recente > 0) {
        return false;
    }
    $token = bin2hex(random_bytes(32));
    $minutos = $tipo === 'definir' ? TOKEN_DEFINIR_MIN : TOKEN_REDEFINIR_MIN;
    execute(
        'INSERT INTO msoft.TokensSenha (UsuarioId, TokenHash, Tipo, ExpiraEm, IpOrigem)
         VALUES (?, ?, ?, DATEADD(MINUTE, ' . $minutos . ', GETDATE()), ?)',
        [$u['Id'], hash('sha256', $token), $tipo, $_SERVER['REMOTE_ADDR'] ?? null]
    );
    $link = rtrim(env('APP_URL', 'http://localhost'), '/') . '/definir-senha?token=' . $token;
    if ($tipo === 'definir') {
        $assunto = 'nouvellesobra - Cadastre sua senha';
        $corpo = '<p>Ola,</p>'
            . '<p>Recebemos seu pedido de acesso ao <strong>nouvellesobra</strong>. Cadastre sua senha pelo link:</p>'
            . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
            . '<p>O link vale por 48 horas e so pode ser usado uma vez. Depois de cadastrar a senha, o administrador libera o seu acesso.</p>'
            . '<p style="color:#6b7280;font-size:12px">Se voce nao pediu acesso, ignore este e-mail.</p>';
    } else {
        $assunto = 'nouvellesobra - Redefinir senha';
        $corpo = '<p>Ola, ' . e($u['Nome']) . '.</p>'
            . '<p>Recebemos um pedido para redefinir sua senha no <strong>nouvellesobra</strong>. Use o link:</p>'
            . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
            . '<p>O link vale por 2 horas e so pode ser usado uma vez.</p>'
            . '<p style="color:#6b7280;font-size:12px">Se voce nao pediu a redefinicao, ignore este e-mail: sua senha atual continua valendo.</p>';
    }
    return enviar_email($u['Email'], $assunto, $corpo);
}

/** Avisa o administrador (ADMIN_EMAIL) sobre a nova solicitacao de acesso. */
function avisar_admin_novo_usuario(string $email): void
{
    $link = rtrim(env('APP_URL', 'http://localhost'), '/') . '/usuarios';
    $corpo = '<p>Ola,</p>'
        . '<p>O usuario <strong>' . e($email) . '</strong> solicitou acesso ao <strong>nouvellesobra</strong> e recebeu um link para cadastrar a senha.</p>'
        . '<p>Depois que a senha for cadastrada, libere o acesso e marque as telas que ele podera usar em:<br>'
        . '<a href="' . e($link) . '">' . e($link) . '</a></p>'
        . '<p style="color:#6b7280;font-size:12px">Mensagem automatica do nouvellesobra.</p>';
    enviar_email(env('ADMIN_EMAIL', 'misrain@cgconstrucoes.com'), 'nouvellesobra - Nova solicitacao de acesso', $corpo);
}

/** Retorna dados do token+usuario se o token existir, nao estiver usado e nao tiver expirado. */
function validar_token_senha(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    try {
        return query(
            'SELECT t.Tipo, u.Id AS UsuarioId, u.Email, u.Nome, u.Status
             FROM msoft.TokensSenha t JOIN msoft.Usuarios u ON u.Id = t.UsuarioId
             WHERE t.TokenHash = ? AND t.UsadoEm IS NULL AND t.ExpiraEm > GETDATE()',
            [hash('sha256', $token)]
        )[0] ?? null;
    } catch (Throwable $e) {
        error_log('Msoft token: ' . $e->getMessage());
        return null;
    }
}

/**
 * Consome o token e grava a nova senha (e o nome, no primeiro acesso).
 * Retorna ['erro' => msg] ou ['status' => status_do_usuario].
 */
function definir_senha_por_token(array $dados, ?string $nome, string $senha, string $confirma): array
{
    if ($dados['Tipo'] === 'definir') {
        $nome = trim((string) $nome);
        if ($nome === '' || mb_strlen($nome) > 120) {
            return ['erro' => 'Informe seu nome completo.'];
        }
    }
    if (mb_strlen($senha) < 4) {
        return ['erro' => 'A senha deve ter ao menos 4 caracteres.'];
    }
    if ($senha !== $confirma) {
        return ['erro' => 'As senhas nao conferem.'];
    }
    if ($dados['Tipo'] === 'definir') {
        execute(
            'UPDATE msoft.Usuarios SET SenhaHashPHP = ?, Nome = ? WHERE Id = ?',
            [password_hash($senha, PASSWORD_DEFAULT), $nome, $dados['UsuarioId']]
        );
    } else {
        execute(
            'UPDATE msoft.Usuarios SET SenhaHashPHP = ? WHERE Id = ?',
            [password_hash($senha, PASSWORD_DEFAULT), $dados['UsuarioId']]
        );
    }
    // invalida este e qualquer outro link pendente do usuario
    execute('UPDATE msoft.TokensSenha SET UsadoEm = GETDATE() WHERE UsuarioId = ? AND UsadoEm IS NULL', [$dados['UsuarioId']]);
    return ['status' => $dados['Status']];
}
