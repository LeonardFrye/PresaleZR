<?php $editingUser = null; ?>
<section class="section-stack">
    <div class="split-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>外观与图标</h3>
                    <p>支持统一替换背景图和各模块导航图标</p>
                </div>
            </div>
            <form class="form-grid" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_settings">
                <label class="wide"><span>背景图片 URL</span><input type="text" name="appearance_background" value="<?= e($settings['appearance_background'] ?? '') ?>" placeholder="留空则使用默认渐变背景"></label>
                <label class="wide"><span>品牌副标题</span><input type="text" name="brand_subtitle" value="<?= e($settings['brand_subtitle'] ?? '') ?>"></label>
                <?php foreach (app_config('default_icons', []) as $key => $defaultIcon): ?>
                    <label><span><?= e($pageTitleMap[$key] ?? $key) ?> 图标类名</span><input type="text" name="icon_<?= e($key) ?>" value="<?= e($icons[$key] ?? $defaultIcon) ?>"></label>
                <?php endforeach; ?>
                <div class="form-actions wide">
                    <button class="primary-btn" type="submit">保存设置</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>账号与权限</h3>
                    <p>管理员可创建普通用户、审核员，并控制启停状态</p>
                </div>
            </div>
            <form class="form-grid" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="user_id" value="">
                <label><span>账号名</span><input type="text" name="username" placeholder="例如：zhangsan" required></label>
                <label><span>显示名称</span><input type="text" name="display_name" placeholder="例如：张三" required></label>
                <label>
                    <span>角色</span>
                    <select name="role" required>
                        <option value="editor">普通用户</option>
                        <option value="auditor">审核员</option>
                        <option value="admin">管理员</option>
                    </select>
                </label>
                <label><span>密码</span><input type="password" name="password" placeholder="新建必填，修改可留空"></label>
                <label class="inline-check"><input type="checkbox" name="is_active" value="1" checked><span>启用账号</span></label>
                <div class="form-actions wide">
                    <button class="primary-btn" type="submit">新建账号</button>
                </div>
            </form>
            <div class="subpanel">
                <h4>现有账号</h4>
                <div class="table-shell compact">
                    <table>
                        <thead>
                        <tr>
                            <th>账号</th>
                            <th>显示名称</th>
                            <th>角色</th>
                            <th>状态</th>
                            <th>最近登录</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $item): ?>
                            <tr>
                                <td><?= e($item['username']) ?></td>
                                <td><?= e($item['display_name']) ?></td>
                                <td><?= e(role_label($item['role'])) ?></td>
                                <td><?= (int) $item['is_active'] === 1 ? '启用' : '停用' ?></td>
                                <td><?= e(format_datetime($item['last_login_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</section>

