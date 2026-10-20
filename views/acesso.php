<div class="login-page">
    <div class="login-card">
        <div class="login-logo">Nouvellesobra</div>
        <p class="login-sub">Primeiro acesso ou redefinicao de senha</p>
        <?php if ($ok): ?>
        <div class="alert-ok"><?= e(MSG_ACESSO_NEUTRA) ?></div>
        <a href="<?= url('login') ?>" class="btn btn-block" style="margin-top:16px">Voltar ao login</a>
        <?php else: ?>
        <p style="font-size:13px;color:#6b7280">Informe seu e-mail. Se ele estiver apto a usar o sistema,
            voce recebera um link para cadastrar ou redefinir a senha.</p>
        <form method="post" action="<?= url('acesso') ?>">
            <?= csrf_campo() ?>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required autofocus
                   autocomplete="username" maxlength="120">
            <button type="submit" class="btn btn-block" style="margin-top:18px">Enviar link</button>
        </form>
        <p class="login-link">
            <a href="<?= url('login') ?>">Voltar ao login</a>
        </p>
        <?php endif; ?>
    </div>
</div>
