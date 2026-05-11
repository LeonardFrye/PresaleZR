<?php
$projectForm = $editingProject ?: [
    'id' => 0,
    'project_name' => '',
    'project_region' => '',
    'project_sales' => '',
    'support_role' => '售前',
    'support_personnel' => '',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d'),
    'task_summary' => '',
    'completion_feedback' => '',
    'transfer_flag' => 0,
    'completion_flag' => 0,
];
?>
<section class="section-stack">
    <form class="filter-panel" method="get">
        <input type="hidden" name="view" value="projects">
        <label><span>关键字</span><input type="text" name="keyword" value="<?= e($filters['keyword']) ?>" placeholder="项目名称 / 销售 / 支撑人员"></label>
        <label>
            <span>项目区域</span>
            <select name="region">
                <option value="">全部区域</option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?= e($region) ?>" <?= $filters['region'] === $region ? 'selected' : '' ?>><?= e($region) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>支撑岗位</span>
            <select name="role">
                <option value="">全部岗位</option>
                <option value="售前" <?= $filters['role'] === '售前' ? 'selected' : '' ?>>售前</option>
                <option value="实施" <?= $filters['role'] === '实施' ? 'selected' : '' ?>>实施</option>
            </select>
        </label>
        <label><span>支撑人员</span><input type="text" name="person" value="<?= e($filters['person']) ?>" placeholder="支持模糊筛选"></label>
        <label><span>项目销售</span><input type="text" name="sales" value="<?= e($filters['sales']) ?>" placeholder="销售姓名"></label>
        <label>
            <span>标签</span>
            <select name="tag">
                <option value="">全部</option>
                <option value="conflict" <?= $filters['tag'] === 'conflict' ? 'selected' : '' ?>>人员冲突</option>
                <option value="transfer" <?= $filters['tag'] === 'transfer' ? 'selected' : '' ?>>转接延续</option>
                <option value="completion" <?= $filters['tag'] === 'completion' ? 'selected' : '' ?>>项目完成</option>
            </select>
        </label>
        <div class="filter-actions">
            <button class="primary-btn" type="submit">筛选项目</button>
            <a class="ghost-btn" href="index.php?view=projects&edit=0">新建项目</a>
        </div>
    </form>

    <div class="split-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>项目列表</h3>
                    <p>可查看、编辑、删除项目，标签和冲突自动联动</p>
                </div>
                <a class="inline-link" href="index.php?action=export_report&<?= http_build_query(array_merge(['view' => 'projects'], $filters)) ?>">导出 Excel</a>
            </div>
            <div class="table-shell">
                <table>
                    <thead>
                    <tr>
                        <th>项目名称</th>
                        <th>区域</th>
                        <th>销售</th>
                        <th>岗位</th>
                        <th>支撑人员</th>
                        <th>日期</th>
                        <th>天数</th>
                        <th>标签</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projects as $item): ?>
                        <tr>
                            <td><?= e($item['project_name']) ?></td>
                            <td><?= e($item['project_region']) ?></td>
                            <td><?= e($item['project_sales']) ?></td>
                            <td><?= e($item['support_role']) ?></td>
                            <td><?= e($item['support_personnel']) ?></td>
                            <td><?= e($item['start_date']) ?> ~ <?= e($item['end_date']) ?></td>
                            <td><?= e((string) $item['duration_days']) ?></td>
                            <td>
                                <div class="badge-row">
                                    <?php if (!empty($item['conflict_flag'])): ?><span class="badge danger">人员冲突</span><?php endif; ?>
                                    <?php if ((int) $item['transfer_flag'] === 1): ?><span class="badge warn">转接延续</span><?php endif; ?>
                                    <?php if ((int) $item['completion_flag'] === 1): ?><span class="badge ok">项目完成</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="action-row">
                                <a class="inline-link" href="index.php?view=projects&edit=<?= e((string) $item['id']) ?>">编辑</a>
                                <form method="post" onsubmit="return confirm('确认删除该项目吗？');">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_project">
                                    <input type="hidden" name="project_id" value="<?= e((string) $item['id']) ?>">
                                    <button class="danger-link" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3><?= $projectForm['id'] ? '编辑项目' : '新建项目' ?></h3>
                    <p>开始/结束时间会自动计算工期天数，并可上传回执与附件</p>
                </div>
            </div>
            <form method="post" enctype="multipart/form-data" class="form-grid">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_project">
                <input type="hidden" name="project_id" value="<?= e((string) $projectForm['id']) ?>">

                <label><span>项目销售</span><input type="text" name="project_sales" value="<?= e($projectForm['project_sales']) ?>" required></label>
                <label><span>项目名称</span><input type="text" name="project_name" value="<?= e($projectForm['project_name']) ?>" required></label>
                <label><span>项目区域</span><input type="text" name="project_region" value="<?= e($projectForm['project_region']) ?>" required></label>
                <label>
                    <span>支撑岗位</span>
                    <select name="support_role" required>
                        <option value="售前" <?= $projectForm['support_role'] === '售前' ? 'selected' : '' ?>>售前</option>
                        <option value="实施" <?= $projectForm['support_role'] === '实施' ? 'selected' : '' ?>>实施</option>
                    </select>
                </label>
                <label><span>支撑人员</span><input type="text" name="support_personnel" value="<?= e($projectForm['support_personnel']) ?>" placeholder="多人可用 、 / ， 分隔" required></label>
                <label><span>开始时间</span><input type="date" name="start_date" value="<?= e($projectForm['start_date']) ?>" data-duration-start required></label>
                <label><span>结束时间</span><input type="date" name="end_date" value="<?= e($projectForm['end_date']) ?>" data-duration-end required></label>
                <label><span>工期天数</span><input type="text" value="<?= e((string) (((strtotime($projectForm['end_date']) - strtotime($projectForm['start_date'])) / 86400) + 1)) ?>" data-duration-output readonly></label>
                <label class="wide"><span>工作任务【简述】</span><textarea name="task_summary" rows="5" required><?= e($projectForm['task_summary']) ?></textarea></label>
                <label class="wide"><span>完成评价【销售反馈简述】</span><textarea name="completion_feedback" rows="4"><?= e($projectForm['completion_feedback']) ?></textarea></label>
                <label><span>技术完成回执单</span><input type="file" name="receipt_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"></label>
                <label><span>项目附件</span><input type="file" name="attachment_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.txt,.zip,.rar"></label>
                <label class="wide"><span>附件说明</span><input type="text" name="attachment_description" placeholder="例如：项目过程文档 / 培训资料 / 交付清单"></label>
                <label class="inline-check"><input type="checkbox" name="transfer_flag" value="1" <?= (int) $projectForm['transfer_flag'] === 1 ? 'checked' : '' ?>><span>转接延续</span></label>
                <label class="inline-check"><input type="checkbox" name="completion_flag" value="1" <?= (int) $projectForm['completion_flag'] === 1 ? 'checked' : '' ?>><span>项目完成</span></label>
                <div class="form-actions wide">
                    <button class="primary-btn" type="submit">保存项目</button>
                    <a class="ghost-btn" href="index.php?view=projects">清空表单</a>
                </div>
            </form>

            <?php if ($editingProject): ?>
                <div class="subpanel">
                    <h4>当前项目文档</h4>
                    <div class="list-stack">
                        <?php if ($projectDocuments === []): ?>
                            <div class="empty-box">当前项目还没有上传文档。</div>
                        <?php else: ?>
                            <?php foreach ($projectDocuments as $doc): ?>
                                <div class="doc-row">
                                    <div>
                                        <strong><?= e($doc['original_name']) ?></strong>
                                        <span><?= e($doc['category']) ?> / v<?= e((string) $doc['version_no']) ?> / <?= e(format_bytes((int) $doc['file_size'])) ?></span>
                                    </div>
                                    <div class="badge-row">
                                        <a class="inline-link" href="index.php?action=download_document&id=<?= e((string) $doc['id']) ?>">下载</a>
                                        <?php if (in_array($doc['extension'], ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true)): ?>
                                            <a class="inline-link" target="_blank" href="index.php?action=preview_document&id=<?= e((string) $doc['id']) ?>">预览</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

