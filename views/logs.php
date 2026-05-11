<section class="section-stack">
    <form class="filter-panel" method="get">
        <input type="hidden" name="view" value="logs">
        <label>
            <span>模块筛选</span>
            <select name="module">
                <option value="">全部模块</option>
                <?php foreach (['auth' => '登录鉴权', 'projects' => '项目管理', 'documents' => '文档管理', 'settings' => '系统设置', 'users' => '账号管理'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($_GET['module'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button class="primary-btn" type="submit">筛选日志</button>
        </div>
    </form>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>系统操作日志</h3>
                <p>记录登录、增删改查等关键行为</p>
            </div>
        </div>
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>时间</th>
                    <th>账号</th>
                    <th>动作</th>
                    <th>模块</th>
                    <th>说明</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e(format_datetime($log['created_at'])) ?></td>
                        <td><?= e($log['display_name'] ?: ($log['username'] ?: '-')) ?></td>
                        <td><?= e($log['action_type']) ?></td>
                        <td><?= e($log['module_name']) ?></td>
                        <td><?= e($log['description']) ?></td>
                        <td><?= e($log['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

