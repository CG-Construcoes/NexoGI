<div class="login-page">
    <div class="login-card">
        <div class="login-logo">nouvellesobra</div>
        <p class="login-sub">Solicitacao de acesso ao sistema</p>
        <?php if ($sucesso): ?>
        <div class="alert-ok">
            Solicitacao enviada! Seu acesso sera liberado pelo administrador.
        </div>
        <a href="<?= url('login') ?>" class="btn btn-block">Voltar ao login</a>
        <?php else: ?>
        <?php if ($erro): ?>
        <div class="alert-erro"><?= e($erro) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= url('cadastro') ?>">
            <?= csrf_campo() ?>
            <label for="nome">Nome completo</label>
            <input type="text" id="nome" name="nome" required autofocus
                   autocomplete="name" maxlength="120" value="<?= e($nome) ?>">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required
                   autocomplete="username" maxlength="120" value="<?= e($email) ?>">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required
                   autocomplete="new-password">
            <label for="confirma">Confirmar senha</label>
            <input type="password" id="confirma" name="confirma" required
                   autocomplete="new-password">
            <button type="submit" class="btn btn-block">Solicitar acesso</button>
        </form>
        <p class="login-link">
            Ja tem acesso? <a href="<?= url('login') ?>">Entrar</a>
        </p>
        <?php endif; ?>
    </div>
</div>
