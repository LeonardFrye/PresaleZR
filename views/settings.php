<section class="section-stack">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>账号权限</h3>
                <p>管理员可新增、编辑、删除账号。</p>
            </div>
        </div>

        <div class="split-grid">
            <div class="subpanel">
                <h4><?= $editingUser ? '编辑账号' : '新增账号' ?></h4>
                <form class="form-grid mt-4" method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="user_id" value="<?= e((string) ($editingUser['id'] ?? 0)) ?>">
                    <label><span>账号名</span><input type="text" name="username" value="<?= e((string) ($editingUser['username'] ?? '')) ?>" required></label>
                    <label><span>显示名称</span><input type="text" name="display_name" value="<?= e((string) ($editingUser['display_name'] ?? '')) ?>" required></label>
                    <label>
                        <span>角色</span>
                        <select name="role" required>
                            <option value="editor" <?= (($editingUser['role'] ?? 'editor') === 'editor') ? 'selected' : '' ?>>普通用户</option>
                            <option value="admin" <?= (($editingUser['role'] ?? '') === 'admin') ? 'selected' : '' ?>>系统管理员</option>
                        </select>
                    </label>
                    <label><span>密码</span><input type="password" name="password" placeholder="<?= $editingUser ? '留空则保持原密码' : '新建账号必须填写' ?>"></label>
                    <div class="form-actions wide">
                        <?php if ($editingUser): ?>
                            <a href="index.php?view=settings#settings" class="secondary-btn">取消</a>
                        <?php endif; ?>
                        <button class="primary-btn" type="submit"><?= $editingUser ? '保存修改' : '创建账号' ?></button>
                    </div>
                </form>
            </div>

            <div class="subpanel">
                <h4>现有账号</h4>
                <div class="table-shell compact mt-4">
                    <table>
                        <thead>
                        <tr>
                            <th>账号</th>
                            <th>显示名称</th>
                            <th>角色</th>
                            <th>最近登录</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $item): ?>
                            <tr>
                                <td><?= e($item['username']) ?></td>
                                <td><?= e($item['display_name']) ?></td>
                                <td><?= e(role_label($item['role'])) ?></td>
                                <td><?= e(format_datetime($item['last_login_at'])) ?></td>
                                <td>
                                    <div class="inline-actions">
                                        <a href="index.php?view=settings&user_edit=<?= e((string) $item['id']) ?>#settings">编辑</a>
                                        <form method="post" class="inline" onsubmit="return confirm('确认删除这个账号吗？');">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= e((string) $item['id']) ?>">
                                            <button type="submit">删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</section>
