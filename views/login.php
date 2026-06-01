<section class="login-panel">
    <div class="login-brand">
        <div class="login-mark">
            <i class="fas fa-diagram-project"></i>
        </div>
        <div class="login-brand-copy">
            <p class="login-kicker">项目管理平台</p>
            <h1>技术支撑事业部项目管理平台 V2.0</h1>
        </div>
    </div>

    <form method="post" class="login-form login-form-compact">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">

        <div class="login-form-head">
            <h2>账号登录</h2>
            <p>请输入账号和密码进入系统。</p>
        </div>

        <label>
            <span>账号</span>
            <input type="text" name="username" placeholder="请输入账号" autocomplete="username" required>
        </label>

        <label>
            <span>密码</span>
            <input type="password" name="password" placeholder="请输入密码" autocomplete="current-password" required>
        </label>

        <button type="submit">登录系统</button>
    </form>
</section>
