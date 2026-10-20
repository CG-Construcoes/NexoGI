<div class="login-page">
    <div class="login-card">
        <div class="login-logos">
            <img src="<?= url('static/img/logo-cg.png') ?>" alt="CG Construcoes">
            <img src="<?= url('static/img/logo-conceito-viver.png') ?>" alt="Conceito Viver">
        </div>
        <p class="login-sub">Acesso ao sistema</p>
        <?php if ($erro): ?>
        <div class="alert-erro"><?= e($erro) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= url('login') ?>">
            <?= csrf_campo() ?>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required autofocus
                   autocomplete="username" value="<?= e($email) ?>">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required
                   autocomplete="current-password">
            <button type="submit" class="btn btn-block">Entrar</button>
        </form>
        <?php if (tela_pronta('acesso')): ?>
        <p class="login-link">
            <a href="<?= url('acesso') ?>">Primeiro acesso ou esqueci a senha</a>
        </p>
        <?php endif; ?>
    </div>
    <footer class="login-footer">Nouvellesobra</footer>
</div>
