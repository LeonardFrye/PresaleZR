<?php
$metrics = $dashboard['metrics'];
$projectForm = $editingProject ?: [
    'id' => 0,
    'project_type' => '',
    'project_name' => '',
    'project_priority' => '普通',
    'project_region' => '',
    'project_sales' => '',
    'support_department' => '技术支撑事业部',
    'cross_department' => '',
    'support_role' => '售前',
    'support_personnel' => '',
    'start_at' => date('Y-m-d 09:00:00'),
    'end_at' => date('Y-m-d 18:00:00'),
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d'),
    'project_hours' => 1,
    'task_summary' => '',
    'completion_feedback' => '',
    'transfer_flag' => 0,
    'completion_flag' => 0,
    'work_order_status' => \App\Services\ProjectService::STATUS_SALES_TASK,
    'feedback_tag' => \App\Services\ProjectService::FEEDBACK_NORMAL,
];
$allProjects = $projectService->list();
$workOrderStatuses = \App\Services\ProjectService::statusOptions();
$feedbackTagOptions = \App\Services\ProjectService::feedbackTagOptions();
$workOrderStatusStyles = [
    \App\Services\ProjectService::STATUS_SALES_TASK => 'bg-red-100 text-red-700 border border-red-200',
    \App\Services\ProjectService::STATUS_MANAGER_REVIEW => 'bg-blue-100 text-blue-700 border border-blue-200',
    \App\Services\ProjectService::STATUS_TECH_EXECUTION => 'bg-white text-gray-700 border border-gray-300',
];
$feedbackTagStyles = [
    \App\Services\ProjectService::FEEDBACK_NORMAL => 'bg-slate-100 text-slate-700 border border-slate-200',
    \App\Services\ProjectService::FEEDBACK_BONUS => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    \App\Services\ProjectService::FEEDBACK_COMPLAINT => 'bg-rose-100 text-rose-700 border border-rose-200',
];
$openProjectModal = $editingProject !== null;
$weekdayOf = static function (?string $date): string {
    return $date ? weekday_label($date) : '';
};
$datePartOf = static function (?string $dateTime): string {
    return $dateTime ? date('Y-m-d', strtotime($dateTime)) : '';
};
$scoreLabel = static function (float $value): string {
    $formatted = number_format($value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
};
$canCreateProjects = $auth->can('create_projects');
$canManageProjects = $auth->can('manage_projects');
$numberLabel = static function ($value): string {
    $formatted = number_format((float) $value, 1, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
};
$personnelWeekdays = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
$adminUserCount = 0;
$editorUserCount = 0;
foreach ($users as $userItem) {
    if (($userItem['role'] ?? '') === 'admin') {
        $adminUserCount++;
    } elseif (($userItem['role'] ?? '') === 'editor') {
        $editorUserCount++;
    }
}
?>
<section id="dashboard-page" class="page-section <?= $view === 'dashboard' ? '' : 'hidden' ?>">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">数据概览</h2>
        <p class="text-gray-600 mt-1">技术支撑事业部项目管理平台数据统计与分析</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <a href="index.php?view=projects#projects" class="card-hover bg-white rounded-lg shadow px-5 py-5 border border-gray-100">
            <div class="flex items-center gap-4">
                <div class="bg-blue-100 p-3 rounded-full flex-shrink-0">
                    <i class="fas fa-project-diagram text-blue-600 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">项目总数</p>
                    <p class="text-2xl font-bold text-gray-900"><?= e((string) $metrics['total_projects']) ?><span class="ml-1 text-base font-medium text-gray-500">个</span></p>
                </div>
            </div>
        </a>
        <a href="index.php?view=reports#reports" class="card-hover bg-white rounded-lg shadow px-5 py-5 border border-gray-100">
            <div class="flex items-center gap-4">
                <div class="bg-green-100 p-3 rounded-full flex-shrink-0">
                    <i class="fas fa-clock text-green-600 text-xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-500">总工期</p>
                    <div class="mt-1 flex items-end justify-between gap-4">
                        <p class="text-2xl font-bold text-gray-900 whitespace-nowrap"><?= e((string) $metrics['total_hours']) ?><span class="ml-1 text-base font-medium text-gray-500">小时</span></p>
                        <p class="text-2xl font-bold text-gray-900 whitespace-nowrap text-right"><?= e($numberLabel($metrics['total_days'])) ?><span class="ml-1 text-base font-medium text-gray-500">天</span></p>
                    </div>
                </div>
            </div>
        </a>
        <a href="index.php?view=attendance#attendance" class="card-hover bg-white rounded-lg shadow px-5 py-5 border border-gray-100">
            <div class="flex items-center gap-4">
                <div class="bg-purple-100 p-3 rounded-full flex-shrink-0">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">整体出勤率</p>
                    <p class="text-2xl font-bold text-gray-900"><?= e((string) $metrics['attendance_rate']) ?>%</p>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">项目区域分布</h3>
            <div class="h-64">
                <canvas id="region-chart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">项目工期分布</h3>
            <div class="h-64">
                <canvas id="hours-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">项目完成趋势</h3>
            <div class="h-64">
                <canvas id="completion-trend-chart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">人员工作量统计</h3>
            <div class="h-64">
                <canvas id="workload-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 border border-gray-100 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold text-gray-900">近期项目</h3>
            <a href="index.php?view=projects#projects" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                查看全部 <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">项目名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">区域</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">支撑人员</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">开始时间</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($dashboard['recent_projects'] as $item): ?>
                    <?php $isDone = (int) $item['completion_flag'] === 1; ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= e($item['project_name']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['project_region']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['support_personnel']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['start_date']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $isDone ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= $isDone ? '已完成' : '进行中' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</section>

<section id="projects-page" class="page-section <?= $view === 'projects' ? '' : 'hidden' ?>">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">项目管理</h2>
            <p class="text-gray-600 mt-1">
                <?= $canManageProjects ? '支持批量选择、预览、编辑、删除和分页查看项目' : ($canCreateProjects ? '当前账号可新增项目并查看现有项目详情' : '当前账号仅可查看现有项目与项目详情') ?>
            </p>
        </div>
        <?php if ($canCreateProjects): ?>
            <div class="flex flex-wrap items-center gap-3">
                <?php if ($canManageProjects): ?>
                    <form method="post" enctype="multipart/form-data" class="inline-flex">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="import_projects">
                        <label class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center cursor-pointer">
                            <i class="fas fa-file-import mr-2"></i> 导入项目
                            <input type="file" name="import_excel_file" accept=".xlsx,.csv" class="hidden" required onchange="if (this.files.length) { this.form.submit(); }">
                        </label>
                    </form>
                <?php endif; ?>
                <button id="add-project-btn" type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i> 新增项目
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow p-5 mb-6 border border-gray-100">
        <form method="get" id="project-filter-form">
            <input type="hidden" name="view" value="projects">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">项目销售</label>
                    <input type="text" name="sales" value="<?= e($filters['sales']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="输入销售姓名">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">销售评价</label>
                    <select name="feedback_tag" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">全部评价</option>
                        <?php foreach ($feedbackTagOptions as $feedbackKey => $feedbackLabel): ?>
                            <option value="<?= e($feedbackKey) ?>" <?= $filters['feedback_tag'] === $feedbackKey ? 'selected' : '' ?>><?= e($feedbackLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">月份</label>
                    <input type="month" name="month" value="<?= e($filters['month']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">支撑人员</label>
                    <input type="text" name="person" value="<?= e($filters['person']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="输入支撑人员">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">工单标签</label>
                    <select name="tag" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">全部状态</option>
                        <?php foreach ($workOrderStatuses as $statusKey => $statusLabel): ?>
                            <option value="<?= e($statusKey) ?>" <?= $filters['tag'] === $statusKey ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <div class="mt-4 flex flex-col gap-4 rounded-xl bg-slate-50/70 px-4 py-4 xl:flex-row xl:items-center xl:justify-between">
            <?php if ($canManageProjects): ?>
                <form method="post" class="flex flex-col gap-3 lg:flex-row lg:items-center" id="project-batch-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="batch_projects">
                    <?php foreach ($projectListQuery as $queryKey => $queryValue): ?>
                        <input type="hidden" name="<?= e($queryKey) ?>" value="<?= e((string) $queryValue) ?>">
                    <?php endforeach; ?>
                    <div class="flex flex-wrap items-center gap-3">
                        <select name="batch_action" id="project-batch-action" class="min-w-[12rem] px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">批量操作</option>
                            <option value="delete">删除选中项目</option>
                            <option value="export">导出选中项目</option>
                        </select>
                        <button type="submit" id="project-batch-submit" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" disabled>执行</button>
                        <div class="text-sm text-gray-500">已选 <span id="project-selected-count" class="font-semibold text-gray-900">0</span> 项</div>
                    </div>
                </form>
            <?php endif; ?>

            <div class="flex flex-wrap justify-end gap-3">
                <button type="submit" form="project-filter-form" class="inline-flex h-12 items-center gap-2 rounded-full bg-blue-600 px-5 text-sm font-semibold text-white shadow-[0_12px_24px_rgba(37,99,235,0.22)] transition hover:-translate-y-0.5 hover:bg-blue-700">
                    <i class="fas fa-filter text-sm"></i>
                    <span>筛选项目</span>
                </button>
                <a href="index.php?view=projects#projects" class="inline-flex h-12 items-center gap-2 rounded-full border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-[0_8px_18px_rgba(15,23,42,0.06)] transition hover:-translate-y-0.5 hover:bg-slate-50">
                    <i class="fas fa-rotate-left text-sm"></i>
                    <span>重置</span>
                </a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-end border-b border-gray-100 px-4 py-3">
            <div class="relative" id="project-column-chooser">
                <button type="button" id="project-column-toggle" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:bg-slate-50 hover:text-slate-700" aria-expanded="false" aria-controls="project-column-menu" title="选择展示字段">
                    <i class="fas fa-sliders-h text-sm"></i>
                </button>
                <div id="project-column-menu" class="project-column-menu hidden">
                    <div class="project-column-menu__title">显示字段</div>
                    <?php
                    $projectColumnOptions = [
                        'sequence' => '序号',
                        'project_name' => '项目名称',
                        'region' => '区域',
                        'sales' => '销售',
                        'role' => '岗位',
                        'personnel' => '支撑人员',
                        'date_range' => '开始 / 结束',
                        'duration' => '项目工时',
                        'tag' => '标签',
                        'feedback' => '反馈标签',
                    ];
                    ?>
                    <div class="project-column-menu__list">
                        <?php foreach ($projectColumnOptions as $columnKey => $columnLabel): ?>
                            <label class="project-column-menu__item">
                                <input type="checkbox" class="project-column-checkbox" value="<?= e($columnKey) ?>" checked>
                                <span><?= e($columnLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="projects-table">
                <thead class="bg-gray-50">
                <tr>
                    <?php if ($canManageProjects): ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" id="projects-select-all" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                    <?php endif; ?>
                    <th data-project-col="sequence" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">序号</th>
                    <th data-project-col="project_name" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">项目名称</th>
                    <th data-project-col="region" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">区域</th>
                    <th data-project-col="sales" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">销售</th>
                    <th data-project-col="role" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">岗位</th>
                    <th data-project-col="personnel" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">支撑人员</th>
                    <th data-project-col="date_range" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">开始 / 结束</th>
                    <th data-project-col="duration" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">项目工时</th>
                    <th data-project-col="tag" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">标签</th>
                    <th data-project-col="feedback" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">反馈标签</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="<?= $canManageProjects ? '11' : '10' ?>" data-project-empty class="px-6 py-10 text-center text-sm text-gray-500">暂无符合条件的项目</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($projects as $index => $item): ?>
                    <tr class="project-table-row cursor-pointer" data-project-row-toggle="<?= e((string) $item['id']) ?>">
                        <?php if ($canManageProjects): ?>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                <input type="checkbox" name="project_ids[]" value="<?= e((string) $item['id']) ?>" form="project-batch-form" class="project-row-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                        <?php endif; ?>
                        <td data-project-col="sequence" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e((string) ((($projectPagination['page'] - 1) * $projectPagination['per_page']) + $index + 1)) ?></td>
                        <td data-project-col="project_name" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= e($item['project_name']) ?></td>
                        <td data-project-col="region" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['project_region']) ?></td>
                        <td data-project-col="sales" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['project_sales']) ?></td>
                        <td data-project-col="role" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['support_role']) ?></td>
                        <td data-project-col="personnel" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['support_personnel']) ?></td>
                        <td data-project-col="date_range" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e(format_datetime($item['start_at'] ?? $item['start_date'])) ?> / <?= e(format_datetime($item['end_at'] ?? $item['end_date'])) ?></td>
                        <td data-project-col="duration" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($numberLabel($item['project_hours'] ?? 0)) ?> 绩效</td>
                        <td data-project-col="tag" class="px-6 py-4 text-sm text-gray-500">
                            <?php
                            $statusKey = $item['work_order_status'] ?? \App\Services\ProjectService::STATUS_SALES_TASK;
                            $statusLabel = $workOrderStatuses[$statusKey] ?? '销售发布任务';
                            $statusClass = $workOrderStatusStyles[$statusKey] ?? $workOrderStatusStyles[\App\Services\ProjectService::STATUS_SALES_TASK];
                            ?>
                            <span class="project-tag-chip inline-flex px-3 py-1 text-xs rounded-full <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                        </td>
                        <td data-project-col="feedback" class="px-6 py-4 text-sm text-gray-500">
                            <?php
                            $feedbackKey = $item['feedback_tag'] ?? \App\Services\ProjectService::FEEDBACK_NORMAL;
                            $feedbackLabel = $feedbackTagOptions[$feedbackKey] ?? '正常';
                            $feedbackClass = $feedbackTagStyles[$feedbackKey] ?? $feedbackTagStyles[\App\Services\ProjectService::FEEDBACK_NORMAL];
                            ?>
                            <span class="project-feedback-chip inline-flex px-3 py-1 text-xs rounded-full <?= e($feedbackClass) ?>"><?= e($feedbackLabel) ?></span>
                        </td>
                    </tr>
                    <tr class="project-action-row hidden" data-project-action-row="<?= e((string) $item['id']) ?>">
                        <td colspan="<?= $canManageProjects ? '11' : '10' ?>" class="px-6 pb-4 pt-0">
                            <div class="project-action-panel">
                                <div class="project-action-panel__label">项目操作</div>
                                <div class="project-action-panel__buttons">
                                    <a href="index.php?<?= http_build_query(array_merge($projectListQuery, ['preview' => $item['id']])) ?>#projects" class="project-action-btn project-action-btn-preview">
                                        <i class="fas fa-eye"></i> 预览
                                    </a>
                                    <?php if ($canManageProjects): ?>
                                        <a href="index.php?<?= http_build_query(array_merge($projectListQuery, ['edit' => $item['id']])) ?>#projects" class="project-action-btn project-action-btn-edit">
                                            <i class="fas fa-edit"></i> 编辑
                                        </a>
                                        <form method="post" class="inline-flex" onsubmit="return confirm('确认删除该项目吗？');">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_project">
                                            <input type="hidden" name="project_id" value="<?= e((string) $item['id']) ?>">
                                            <?php foreach ($projectListQuery as $queryKey => $queryValue): ?>
                                                <input type="hidden" name="<?= e($queryKey) ?>" value="<?= e((string) $queryValue) ?>">
                                            <?php endforeach; ?>
                                            <button class="project-action-btn project-action-btn-delete" type="submit">
                                                <i class="fas fa-trash"></i> 删除
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-4 border-t border-gray-200 px-6 py-4 md:flex-row md:items-center md:justify-between">
            <div class="text-sm text-gray-500">共 <?= e((string) $projectPagination['total']) ?> 条，当前第 <?= e((string) $projectPagination['page']) ?> / <?= e((string) $projectPagination['total_pages']) ?> 页，每页 20 条</div>
            <?php if ($projectPagination['total_pages'] > 1): ?>
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($projectPagination['page'] > 1): ?>
                        <a href="index.php?<?= http_build_query(array_merge($projectListQuery, ['page' => $projectPagination['page'] - 1])) ?>#projects" class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">上一页</a>
                    <?php endif; ?>
                    <?php for ($pageNumber = 1; $pageNumber <= $projectPagination['total_pages']; $pageNumber++): ?>
                        <?php if (abs($pageNumber - $projectPagination['page']) > 2 && $pageNumber !== 1 && $pageNumber !== $projectPagination['total_pages']) { continue; } ?>
                        <a href="index.php?<?= http_build_query(array_merge($projectListQuery, ['page' => $pageNumber])) ?>#projects" class="px-3 py-2 rounded-lg border text-sm <?= $pageNumber === $projectPagination['page'] ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-50' ?>"><?= e((string) $pageNumber) ?></a>
                    <?php endfor; ?>
                    <?php if ($projectPagination['page'] < $projectPagination['total_pages']): ?>
                        <a href="index.php?<?= http_build_query(array_merge($projectListQuery, ['page' => $projectPagination['page'] + 1])) ?>#projects" class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="personnel-page" class="page-section <?= $view === 'personnel' ? '' : 'hidden' ?>">
    <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between personnel-page-head">
        <div class="personnel-page-intro">
            <span class="personnel-page-chip"><?= e($personnelBoard['label']) ?></span>
            <h2 class="text-2xl font-bold text-gray-900">人员绩效</h2>
        </div>
        <form class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm md:flex-row md:items-end personnel-toolbar" method="get">
            <input type="hidden" name="view" value="personnel">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">时间维度</label>
                <div class="flex gap-2 personnel-period-switch">
                    <?php foreach (['year' => '年', 'month' => '月', 'week' => '周'] as $periodKey => $periodLabel): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="personnel_period" value="<?= e($periodKey) ?>" class="sr-only peer" <?= $personnelBoard['period'] === $periodKey ? 'checked' : '' ?>>
                            <span class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-700 personnel-period-pill"><?= e($periodLabel) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">参考日期</label>
                <input type="date" name="personnel_date" value="<?= e($personnelBoard['anchor_date']) ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-2 personnel-toolbar-actions">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 personnel-action-btn personnel-action-btn--primary">切换</button>
                <a href="index.php?view=personnel#personnel" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50 personnel-action-btn personnel-action-btn--muted">重置</a>
                <a href="index.php?action=export_personnel_performance&personnel_period=<?= e($personnelBoard['period']) ?>&personnel_date=<?= e($personnelBoard['anchor_date']) ?>" class="rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700 personnel-action-btn personnel-action-btn--accent">一键生成绩效</a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 personnel-board-grid">
        <?php foreach ($personnelBoard['cards'] as $card): ?>
            <a href="index.php?view=personnel&personnel_period=<?= e($personnelBoard['period']) ?>&personnel_date=<?= e($personnelBoard['anchor_date']) ?>&personnel_person=<?= urlencode($card['name']) ?>#personnel" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md personnel-board-card">
                <div class="personnel-board-card__top">
                    <div class="text-lg font-semibold text-gray-900 personnel-board-card__name"><?= e($card['name']) ?></div>
                    <span class="personnel-board-card__badge"><?= e((string) $card['active_days']) ?> 天</span>
                </div>
                <div class="mt-4 text-sm text-gray-500 personnel-board-card__label">累计绩效</div>
                <div class="mt-1 text-3xl font-bold text-blue-700 personnel-board-card__score"><?= e($scoreLabel((float) $card['total_score'])) ?></div>
                <div class="mt-4 flex items-center justify-between text-sm text-gray-500 personnel-board-card__foot">
                    <span>有项目天数</span>
                    <span class="font-medium text-gray-700"><?= e((string) $card['active_days']) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($personnelDetail): ?>
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 px-4 py-6 personnel-detail-modal">
            <div class="mx-auto max-w-7xl rounded-2xl bg-white shadow-2xl personnel-detail-shell">
                <div class="border-b border-gray-200 px-6 py-5 personnel-detail-header">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="personnel-detail-kicker">人员绩效明细</div>
                            <h3 class="text-2xl font-bold text-gray-900"><?= e($personnelDetail['person_name']) ?></h3>
                            <p class="mt-1 text-sm text-gray-500"><?= e($personnelDetail['label']) ?> 绩效记录</p>
                        </div>
                        <div class="flex items-center gap-3 personnel-detail-actions">
                            <?php if ($auth->can('manage_performance')): ?>
                                <button type="submit" form="personnel-performance-form" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 personnel-action-btn personnel-action-btn--primary">保存绩效</button>
                            <?php endif; ?>
                            <a href="index.php?action=export_personnel_detail_performance&personnel_period=<?= e($personnelDetail['period']) ?>&personnel_date=<?= e($personnelDetail['anchor_date']) ?>&person_name=<?= urlencode($personnelDetail['person_name']) ?>" class="rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700 personnel-action-btn personnel-action-btn--accent">一键生成个人绩效</a>
                            <a href="index.php?view=personnel&personnel_period=<?= e($personnelBoard['period']) ?>&personnel_date=<?= e($personnelBoard['anchor_date']) ?>#personnel" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50 personnel-action-btn personnel-action-btn--muted">关闭</a>
                        </div>
                    </div>
                    <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3 personnel-detail-summary">
                        <div class="personnel-summary-card">
                            <div class="personnel-summary-label">当前周期</div>
                            <div class="personnel-summary-value text-slate-900"><?= e($personnelDetail['label']) ?></div>
                        </div>
                        <div class="personnel-summary-card">
                            <div class="personnel-summary-label">活跃天数</div>
                            <div class="personnel-summary-value text-slate-900"><?= e((string) $personnelDetail['active_days']) ?><span class="personnel-summary-unit">天</span></div>
                        </div>
                        <div class="personnel-summary-card personnel-summary-card--accent">
                            <div class="personnel-summary-label">累计绩效</div>
                            <div class="personnel-summary-value text-blue-700"><?= e($scoreLabel((float) $personnelDetail['total_score'])) ?></div>
                        </div>
                    </div>
                    <?php if ($auth->can('manage_performance')): ?>
                        <p class="mt-4 text-xs text-blue-700 personnel-detail-note">管理员可按项目单独调整绩效分值，系统默认按开始结束时间折算每日绩效。</p>
                    <?php endif; ?>
                </div>

                <form id="personnel-performance-form" method="post" class="px-6 py-6 personnel-detail-body">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_personnel_performance">
                    <input type="hidden" name="person_name" value="<?= e($personnelDetail['person_name']) ?>">
                    <input type="hidden" name="personnel_period" value="<?= e($personnelDetail['period']) ?>">
                    <input type="hidden" name="personnel_date" value="<?= e($personnelDetail['anchor_date']) ?>">

                    <?php foreach ($personnelDetail['calendar_groups'] as $group): ?>
                        <div class="mb-8 last:mb-0 personnel-calendar-group">
                            <div class="mb-4 flex items-center justify-between personnel-group-head">
                                <h4 class="text-lg font-semibold text-gray-900"><?= e($group['title']) ?></h4>
                            </div>
                            <div class="hidden gap-3 mb-3 2xl:grid 2xl:grid-cols-7 personnel-weekdays">
                                <?php foreach ($personnelWeekdays as $weekday): ?>
                                    <div class="rounded-lg bg-gray-100 px-3 py-2 text-center text-sm font-medium text-gray-600"><?= e($weekday) ?></div>
                                <?php endforeach; ?>
                            </div>

                            <div class="<?= $group['compact'] ? 'space-y-3' : 'space-y-4' ?>">
                                <?php foreach ($group['weeks'] as $week): ?>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7 personnel-week-grid">
                                        <?php foreach ($week as $cell): ?>
                                            <?php
                                            $isCurrentMonth = $cell['in_current_month'] ?? true;
                                            $cellClass = $isCurrentMonth ? 'personnel-day-card--current' : 'personnel-day-card--other';
                                            $projectCount = count($cell['tasks']);
                                            $dayTotalScore = 0.0;
                                            foreach ($cell['tasks'] as $task) {
                                                $dayTotalScore += (float) ($task['score'] ?? 0);
                                            }
                                            ?>
                                            <div class="rounded-2xl border p-4 personnel-day-card <?= $cellClass ?>">
                                                <div class="personnel-day-head">
                                                    <div class="personnel-day-meta">
                                                        <div class="text-sm font-semibold text-gray-900 personnel-day-date"><?= e($cell['month_number'] . '/' . $cell['day_number']) ?></div>
                                                        <div class="text-xs text-gray-500 personnel-day-weekday"><?= e($cell['weekday']) ?></div>
                                                    </div>
                                                    <div class="personnel-day-badges">
                                                        <?php if ($cell['has_task']): ?>
                                                            <span class="personnel-day-badge personnel-day-badge--busy"><?= e((string) $projectCount) ?> 个项目</span>
                                                            <span class="personnel-day-badge personnel-day-badge--score">当日绩效 <?= e($scoreLabel($dayTotalScore)) ?></span>
                                                        <?php else: ?>
                                                            <span class="personnel-day-badge personnel-day-badge--idle">无任务</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="mt-4 personnel-task-list">
                                                    <?php if ($cell['has_task']): ?>
                                                        <?php foreach ($cell['tasks'] as $taskIndex => $task): ?>
                                                            <div class="personnel-task-card">
                                                                <div class="personnel-task-main">
                                                                    <div class="min-w-0 flex-1 personnel-task-content">
                                                                        <div class="personnel-task-field">
                                                                            <div class="personnel-task-field-head">
                                                                                <span class="personnel-task-field-label">项目名称</span>
                                                                                <span class="personnel-task-order">项目 <?= e((string) ($taskIndex + 1)) ?></span>
                                                                            </div>
                                                                            <div class="personnel-task-title"><?= e($task['project_name']) ?></div>
                                                                        </div>
                                                                        <div class="personnel-task-field personnel-task-field--compact">
                                                                            <div class="personnel-task-field-label">对口销售</div>
                                                                            <div class="personnel-task-sales-text"><?= e((string) ($task['project_sales'] ?? '')) ?></div>
                                                                        </div>
                                                                        <div class="personnel-task-field personnel-task-field--summary">
                                                                            <div class="personnel-task-field-label">工作内容</div>
                                                                            <div class="personnel-task-summary"><?= e($task['task_summary']) ?></div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="personnel-task-score">
                                                                        <div class="personnel-task-score-label">绩效</div>
                                                                        <?php if ($auth->can('manage_performance')): ?>
                                                                            <input
                                                                                type="number"
                                                                                step="0.1"
                                                                                min="0"
                                                                                name="scores[<?= e($cell['date']) ?>][<?= e((string) ($task['project_id'] ?? 0)) ?>]"
                                                                                value="<?= e($scoreLabel((float) ($task['score'] ?? ($task['default_score'] ?? 0)))) ?>"
                                                                                class="personnel-task-score-input"
                                                                            >
                                                                        <?php else: ?>
                                                                            <div class="personnel-task-score-value">
                                                                                <?= e($scoreLabel((float) ($task['score'] ?? ($task['default_score'] ?? 0)))) ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="personnel-empty-state">当天没有项目安排</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>

<section id="attendance-page" class="page-section <?= $view === 'attendance' ? '' : 'hidden' ?>">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">出勤管理</h2>
    </div>
    <div class="grid grid-cols-1 gap-8">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100 attendance-board-shell">
            <div class="attendance-board-head">
                <h3 class="text-lg font-semibold text-gray-900">本周出勤台账</h3>
            </div>

            <div class="overflow-x-auto attendance-board-wrap">
                <table class="min-w-full divide-y divide-gray-200 attendance-board-table" id="attendance-table">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">人员</th>
                        <?php foreach ($attendanceBoard['days'] as $day): ?>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <div class="attendance-day-head">
                                    <?php
                                    $dayParts = explode(' ', (string) ($day['label'] ?? ''));
                                    $dayDateLabel = $dayParts[0] ?? ($day['label'] ?? '');
                                    $dayWeekLabel = $dayParts[1] ?? '';
                                    ?>
                                    <span class="attendance-day-date"><?= e((string) $dayDateLabel) ?></span>
                                    <span class="attendance-day-week"><?= e((string) $dayWeekLabel) ?></span>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($attendanceBoard['rows'] as $row): ?>
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="attendance-person-card">
                                    <div class="attendance-person-name"><?= e($row['name']) ?></div>
                                </div>
                            </td>
                            <?php foreach ($row['days'] as $cell): ?>
                                <?php
                                $statusClass = 'bg-green-100 text-green-800';
                                if ($cell['status'] === 'busy' || $cell['status'] === 'conflict') {
                                    $statusClass = 'bg-blue-100 text-blue-800';
                                } elseif ($cell['status'] === 'rest') {
                                    $statusClass = 'bg-amber-100 text-amber-800';
                                }
                                ?>
                                <td class="px-4 py-4 align-top">
                                    <div class="attendance-cell-card attendance-cell-<?= e($cell['status']) ?> <?= !empty($cell['items']) && count($cell['items']) > 1 ? 'attendance-cell-multi' : '' ?>">
                                        <div class="attendance-cell-head">
                                            <?php if ($auth->can('manage_performance')): ?>
                                                <form method="post" class="attendance-status-form">
                                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_attendance_override">
                                                    <input type="hidden" name="person_name" value="<?= e($row['name']) ?>">
                                                    <input type="hidden" name="work_date" value="<?= e($cell['date']) ?>">
                                                    <details class="attendance-status-popover">
                                                        <summary class="attendance-status-trigger px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                                            <?= e($cell['text']) ?>
                                                        </summary>
                                                        <div class="attendance-status-menu">
                                                            <button type="submit" name="attendance_status" value="" class="attendance-status-option <?= ($cell['override_status'] ?? '') === '' ? 'is-active' : '' ?>">
                                                                自动
                                                            </button>
                                                            <button type="submit" name="attendance_status" value="rest" class="attendance-status-option <?= ($cell['override_status'] ?? '') === 'rest' ? 'is-active' : '' ?>">
                                                                调休
                                                            </button>
                                                        </div>
                                                    </details>
                                                </form>
                                            <?php else: ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>"><?= e($cell['text']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($cell['items'])): ?>
                                                <span class="attendance-cell-count"><?= e((string) count($cell['items'])) ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($cell['items'])): ?>
                                            <div class="attendance-project-list">
                                                <?php foreach ($cell['items'] as $projectItem): ?>
                                                    <div class="attendance-project-item">
                                                        <div class="attendance-project-line attendance-project-line-name">
                                                            <span class="attendance-project-key">项目名称：</span>
                                                            <span class="attendance-project-value attendance-project-name"><?= e((string) ($projectItem['project_name'] ?? '')) ?></span>
                                                        </div>
                                                        <div class="attendance-project-line attendance-project-line-task">
                                                            <span class="attendance-project-key">工作内容：</span>
                                                            <span class="attendance-project-value attendance-project-task"><?= e((string) ($projectItem['task_summary'] ?? '')) ?></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="attendance-empty-note">
                                                <?= e($cell['text']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section id="documents-page" class="page-section <?= $view === 'documents' ? '' : 'hidden' ?>">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">项目文件</h2>
        <p class="text-gray-600 mt-1">支持按关键字搜索、上传、下载、修订和预览项目相关文件</p>
    </div>

    <form class="bg-white rounded-lg shadow p-5 mb-6 border border-gray-100" method="get">
        <input type="hidden" name="view" value="documents">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">所属项目</label>
                <select name="project_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">全部项目</option>
                    <?php foreach ($allProjects as $project): ?>
                        <option value="<?= e((string) $project['id']) ?>" <?= (string) ($_GET['project_id'] ?? '') === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">文件类别</label>
                <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">全部</option>
                    <option value="receipt" <?= ($_GET['category'] ?? '') === 'receipt' ? 'selected' : '' ?>>技术完成回执单</option>
                    <option value="attachment" <?= ($_GET['category'] ?? '') === 'attachment' ? 'selected' : '' ?>>项目附件</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">搜索内容</label>
                <input type="text" name="keyword" value="<?= e((string) ($_GET['keyword'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="文件名 / 文件说明 / 项目名称">
            </div>
            <div class="flex flex-wrap items-end justify-end gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">筛选文件</button>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">项目文件列表</h3>
            <div class="space-y-3">
                <?php if ($documents === []): ?>
                    <div class="text-sm text-gray-500">暂无匹配的项目文件。</div>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="rounded-lg border border-gray-200 px-4 py-4 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <strong class="block text-gray-900"><?= e($doc['original_name']) ?></strong>
                                <div class="text-sm text-gray-500 mt-1 flex flex-wrap gap-x-3 gap-y-1">
                                    <span><?= e($doc['project_name'] ?: '-') ?></span>
                                    <span><?= e($doc['category']) ?></span>
                                    <span>v<?= e((string) $doc['version_no']) ?></span>
                                </div>
                                <div class="text-sm text-gray-500 mt-1">上传人：<?= e($doc['uploader_name'] ?: '-') ?>，大小：<?= e(format_bytes((int) $doc['file_size'])) ?></div>
                            </div>
                            <div class="text-sm flex flex-wrap items-center gap-3 xl:justify-end">
                                <?php if (in_array($doc['extension'], ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true)): ?>
                                    <a class="text-blue-600 hover:text-blue-800" target="_blank" href="index.php?action=preview_document&id=<?= e((string) $doc['id']) ?>">&#39044;&#35272;</a>
                                <?php endif; ?>
                                <a class="text-blue-600 hover:text-blue-800" href="index.php?action=download_document&id=<?= e((string) $doc['id']) ?>">下载</a>
                                <a class="text-blue-600 hover:text-blue-800" href="index.php?view=projects&edit=<?= e((string) $doc['project_id']) ?>#projects">转到项目</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">上传 / 修订项目文件</h3>
            <form class="grid grid-cols-1 md:grid-cols-2 gap-4" method="post" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_document">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">所属项目</label>
                    <select name="project_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择项目</option>
                        <?php foreach ($allProjects as $project): ?>
                            <option value="<?= e((string) $project['id']) ?>"><?= e($project['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">文件类别</label>
                    <select name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="attachment">项目附件</option>
                        <option value="receipt">技术完成回执单</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">文件说明</label>
                    <input type="text" name="description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="例如：二次修订版 / 交付材料">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">选择文件</label>
                    <input type="file" name="document_file" required class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white">
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">上传文件</button>
                </div>
            </form>
        </div>
    </div>
</section>

<section id="reports-page" class="page-section <?= $view === 'reports' ? '' : 'hidden' ?>">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">统计报表</h2>
        <p class="text-gray-600 mt-1">图表、明细和导出统计联动项目管理详情</p>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">项目区域分布</h3>
            <div class="h-72">
                <canvas id="region-chart-report"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">月度项目趋势</h3>
            <div class="h-72">
                <canvas id="completion-trend-chart-report"></canvas>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-6 border border-gray-100 report-preview-panel">
        <div class="flex justify-between items-center gap-4 mb-6 report-preview-head">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">项目导出预览</h3>
                <p class="mt-1 text-sm text-slate-500">按导出报表视角预览当前项目数据，重点字段已优化排版显示。</p>
            </div>
            <a href="index.php?action=export_report" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg whitespace-nowrap">导出 Excel</a>
        </div>
        <div class="overflow-x-auto report-preview-wrap">
            <table class="min-w-full divide-y divide-gray-200 report-preview-table">
                <colgroup>
                    <col class="report-col-seq">
                    <col class="report-col-region">
                    <col class="report-col-name">
                    <col class="report-col-sales">
                    <col class="report-col-role">
                    <col class="report-col-person">
                    <col class="report-col-date">
                    <col class="report-col-date">
                    <col class="report-col-feedback-text">
                    <col class="report-col-feedback-tag">
                </colgroup>
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">序号</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">项目区域</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">项目名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">项目销售</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">支撑岗位</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">支撑人员</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">开始时间</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">结束时间</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">销售评价</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">反馈标签</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($projects as $index => $item): ?>
                    <tr class="report-preview-row">
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500"><?= e((string) ($index + 1)) ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500"><?= e($item['project_region']) ?></td>
                        <td class="px-4 py-4 text-sm font-medium text-slate-900">
                            <div class="report-project-name"><?= e($item['project_name']) ?></div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-600"><?= e($item['project_sales']) ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500"><?= e($item['support_role']) ?></td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            <div class="report-support-person"><?= e($item['support_personnel']) ?></div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500"><?= e(format_datetime($item['start_at'] ?? $item['start_date'])) ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500"><?= e(format_datetime($item['end_at'] ?? $item['end_date'])) ?></td>
                        <td class="px-4 py-4 text-sm text-slate-600">
                            <div class="report-feedback-text"><?= e($item['completion_feedback'] ?: '-') ?></div>
                        </td>
                        <td class="px-4 py-4 text-sm text-slate-500">
                            <span class="report-feedback-chip"><?= e($feedbackTagOptions[$item['feedback_tag'] ?? \App\Services\ProjectService::FEEDBACK_NORMAL] ?? '正常') ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section id="settings-page" class="page-section <?= $view === 'settings' ? '' : 'hidden' ?>">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">系统设置</h2>
        <p class="text-gray-600 mt-1">管理员可在这里新增、编辑、删除账号。</p>
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-[340px_minmax(0,1fr)] gap-6">
        <div class="bg-white rounded-xl shadow p-6 border border-gray-100">
            <div class="mb-5">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $editingUser ? '编辑账号' : '新增账号' ?></h3>
                    <p class="text-sm text-gray-500 mt-1"><?= $editingUser ? '修改当前账号的信息。' : '创建新的系统管理员或普通用户账号。' ?></p>
                </div>
            </div>
            <form class="grid grid-cols-1 gap-4" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="user_id" value="<?= e((string) ($editingUser['id'] ?? 0)) ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">账号名</label>
                    <input type="text" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="请输入登录账号" value="<?= e((string) ($editingUser['username'] ?? '')) ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">显示名称</label>
                    <input type="text" name="display_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="请输入页面显示名称" value="<?= e((string) ($editingUser['display_name'] ?? '')) ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">角色</label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="editor" <?= (($editingUser['role'] ?? 'editor') === 'editor') ? 'selected' : '' ?>>普通用户</option>
                        <option value="admin" <?= (($editingUser['role'] ?? '') === 'admin') ? 'selected' : '' ?>>系统管理员</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                    <input type="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="<?= $editingUser ? '留空则保持原密码' : '新建账号必须填写密码' ?>">
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    系统管理员拥有全部权限。普通用户可新增项目、查看项目和出勤，但不能进入人员绩效、报表、日志等管理页面。
                </div>
                <div class="flex items-center justify-between gap-3 pt-1">
                    <?php if ($editingUser): ?>
                        <a href="index.php?view=settings#settings" class="px-4 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">取消编辑</a>
                    <?php else: ?>
                        <span class="text-sm text-gray-400">保存后会立即生效</span>
                    <?php endif; ?>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-medium"><?= $editingUser ? '保存修改' : '创建账号' ?></button>
                </div>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between gap-4 px-6 py-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">现有账号</h3>
                    <p class="text-sm text-gray-500 mt-1">支持直接编辑和删除账号。</p>
                </div>
                <div class="text-sm text-gray-500">共 <?= e((string) count($users)) ?> 个账号</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">账号</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">显示名称</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">角色</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">最近登录</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">操作</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $item): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800"><?= e($item['username']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= e($item['display_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?= ($item['role'] ?? '') === 'admin' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-700 border border-slate-200' ?>">
                                    <?= e(role_label($item['role'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e(format_datetime($item['last_login_at'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <div class="inline-flex items-center gap-2">
                                    <a href="index.php?view=settings&user_edit=<?= e((string) $item['id']) ?>#settings" class="px-3 py-1.5 rounded-md border border-blue-200 text-blue-600 hover:bg-blue-50">编辑</a>
                                    <form method="post" class="inline" onsubmit="return confirm('确认删除这个账号吗？');">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= e((string) $item['id']) ?>">
                                        <button type="submit" class="px-3 py-1.5 rounded-md border border-red-200 text-red-600 hover:bg-red-50">删除</button>
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

<section id="logs-page" class="page-section <?= $view === 'logs' ? '' : 'hidden' ?>">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">操作日志</h2>
        <p class="text-gray-600 mt-1">记录登录日志和平台增删改查行为</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">时间</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">账号</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">动作</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">模块</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">说明</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e(format_datetime($log['created_at'])) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($log['display_name'] ?: ($log['username'] ?: '-')) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($log['action_type']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($log['module_name']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= e($log['description']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($log['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php if ($previewingProject): ?>
    <div id="project-preview-modal" class="fixed inset-0 z-40 bg-gray-900/45 px-4 py-8 overflow-y-auto">
        <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">项目预览</h3>
                    <p class="text-sm text-gray-500 mt-1">查看当前项目的基本信息、任务和附件</p>
                </div>
                <a href="index.php?<?= http_build_query($projectListQuery) ?>#projects" class="text-gray-400 hover:text-gray-600 text-xl">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><div class="text-sm text-gray-500">项目类型</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['project_type'] ?: '-') ?></div></div>
                <div><div class="text-sm text-gray-500">项目名称</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['project_name']) ?></div></div>
                <div><div class="text-sm text-gray-500">项目区域</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['project_region']) ?></div></div>
                <div><div class="text-sm text-gray-500">项目重要程度</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['project_priority'] ?: '普通') ?></div></div>
                <div><div class="text-sm text-gray-500">项目销售</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['project_sales']) ?></div></div>
                <div><div class="text-sm text-gray-500">支撑事业部</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['support_department'] ?: '-') ?></div></div>
                <div><div class="text-sm text-gray-500">支撑岗位</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['support_role']) ?></div></div>
                <div><div class="text-sm text-gray-500">支撑人员</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['support_personnel']) ?></div></div>
                <div><div class="text-sm text-gray-500">跨部门协调</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['cross_department'] ?: '-') ?></div></div>
                <div><div class="text-sm text-gray-500">开始 / 结束</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e(format_datetime($previewingProject['start_at'] ?? $previewingProject['start_date'])) ?> / <?= e(format_datetime($previewingProject['end_at'] ?? $previewingProject['end_date'])) ?></div></div>
                <div><div class="text-sm text-gray-500">项目工时</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($numberLabel($previewingProject['project_hours'] ?? 0)) ?> 绩效</div></div>
                <div class="md:col-span-2"><div class="text-sm text-gray-500">工作任务</div><div class="mt-1 text-sm leading-6 text-gray-900 whitespace-pre-wrap"><?= e($previewingProject['task_summary']) ?></div></div>
                <div><div class="text-sm text-gray-500">反馈标签</div><div class="mt-1">
                    <?php
                    $previewFeedbackKey = $previewingProject['feedback_tag'] ?? \App\Services\ProjectService::FEEDBACK_NORMAL;
                    $previewFeedbackLabel = $feedbackTagOptions[$previewFeedbackKey] ?? '正常';
                    $previewFeedbackClass = $feedbackTagStyles[$previewFeedbackKey] ?? $feedbackTagStyles[\App\Services\ProjectService::FEEDBACK_NORMAL];
                    ?>
                    <span class="inline-flex px-3 py-1 text-xs rounded-full <?= e($previewFeedbackClass) ?>"><?= e($previewFeedbackLabel) ?></span>
                </div></div>
                <div class="md:col-span-2"><div class="text-sm text-gray-500">销售评价</div><div class="mt-1 text-sm leading-6 text-gray-900 whitespace-pre-wrap"><?= e($previewingProject['completion_feedback'] ?? '') ?></div></div>
            </div>
            <div class="px-6 py-5 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-900 mb-3">项目文件</h4>
                <div class="space-y-3">
                    <?php if ($previewProjectDocuments === []): ?>
                        <div class="text-sm text-gray-500">当前项目暂无文件</div>
                    <?php else: ?>
                        <?php foreach ($previewProjectDocuments as $doc): ?>
                            <div class="rounded-lg border border-gray-200 px-4 py-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?= e($doc['original_name']) ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><?= e($doc['category']) ?> / v<?= e((string) $doc['version_no']) ?> / <?= e(format_bytes((int) $doc['file_size'])) ?></div>
                                </div>
                                <div class="flex items-center gap-3 text-sm">
                                    <a class="text-blue-600 hover:text-blue-800" href="index.php?action=download_document&id=<?= e((string) $doc['id']) ?>">下载</a>
                                    <?php if (in_array($doc['extension'], ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true)): ?>
                                        <a class="text-blue-600 hover:text-blue-800" target="_blank" href="index.php?action=preview_document&id=<?= e((string) $doc['id']) ?>">预览文件</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="add-project-modal" class="fixed inset-0 z-50 <?= $openProjectModal ? '' : 'hidden' ?> bg-gray-900/50 px-4 py-8 overflow-y-auto">
    <div class="max-w-5xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden project-modal-shell" data-project-modal-card>
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center project-modal-header">
            <div>
                <h3 class="text-lg font-semibold text-gray-900"><?= $projectForm['id'] ? '编辑项目' : '新增项目' ?></h3>
                <p class="text-sm text-gray-500 mt-1">按需录入项目销售、区域、人员、周期、任务、附件和销售评价</p>
            </div>
            <button id="close-modal" type="button" class="text-gray-400 hover:text-gray-600 text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="px-6 py-6 project-modal-body">
            <form method="post" enctype="multipart/form-data" id="project-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_project">
                <input type="hidden" name="project_id" value="<?= e((string) $projectForm['id']) ?>">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">项目类型</label>
                        <input type="text" name="project_type" value="<?= e($projectForm['project_type'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="如：本周计划 / 临时支撑">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">项目销售 <span class="text-red-500">*</span></label>
                        <input type="text" name="project_sales" value="<?= e($projectForm['project_sales']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">项目名称 <span class="text-red-500">*</span></label>
                        <input type="text" name="project_name" value="<?= e($projectForm['project_name']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">项目区域 <span class="text-red-500">*</span></label>
                        <input type="text" name="project_region" value="<?= e($projectForm['project_region']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">项目重要程度</label>
                        <input type="text" name="project_priority" value="<?= e($projectForm['project_priority'] ?? '普通') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="普通 / 重要 / 紧急">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">支撑事业部</label>
                        <input type="text" name="support_department" value="<?= e($projectForm['support_department'] ?? '技术支撑事业部') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">跨部门协调</label>
                        <input type="text" name="cross_department" value="<?= e($projectForm['cross_department'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="无则留空">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">支撑岗位 <span class="text-red-500">*</span></label>
                        <select name="support_role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="售前" <?= $projectForm['support_role'] === '售前' ? 'selected' : '' ?>>售前</option>
                            <option value="实施" <?= $projectForm['support_role'] === '实施' ? 'selected' : '' ?>>实施</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">支撑人员 <span class="text-red-500">*</span></label>
                        <input type="text" name="support_personnel" value="<?= e($projectForm['support_personnel']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="多人可用 / 或 , 分隔" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">开始时间 <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="start_at" value="<?= e(format_datetime_local($projectForm['start_at'] ?? (($projectForm['start_date'] ?? '') !== '' ? ($projectForm['start_date'] . ' 09:00:00') : ''))) ?>" data-duration-start class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <p class="text-xs text-gray-500 mt-1"><?= e($weekdayOf($datePartOf($projectForm['start_at'] ?? $projectForm['start_date']))) ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">结束时间 <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="end_at" value="<?= e(format_datetime_local($projectForm['end_at'] ?? (($projectForm['end_date'] ?? '') !== '' ? ($projectForm['end_date'] . ' 18:00:00') : ''))) ?>" data-duration-end class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <p class="text-xs text-gray-500 mt-1"><?= e($weekdayOf($datePartOf($projectForm['end_at'] ?? $projectForm['end_date']))) ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">项目工时</label>
                        <input type="text" value="<?= e($numberLabel($projectForm['project_hours'] ?? 0)) ?>" data-duration-output class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">工作任务简介 <span class="text-red-500">*</span></label>
                        <textarea name="task_summary" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required><?= e($projectForm['task_summary']) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">技术完成回执单</label>
                        <input type="file" name="receipt_file" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">项目附件</label>
                        <input type="file" name="attachment_file" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">附件说明</label>
                        <input type="text" name="attachment_description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="项目过程文档 / 培训资料 / 交付清单">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">销售评价</label>
                        <textarea name="completion_feedback" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= e($projectForm['completion_feedback']) ?></textarea>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">反馈标签</label>
                        <div class="flex flex-wrap gap-3">
                            <?php foreach ($feedbackTagOptions as $feedbackKey => $feedbackLabel): ?>
                                <?php $feedbackClass = $feedbackTagStyles[$feedbackKey] ?? 'bg-slate-100 text-slate-700 border border-slate-200'; ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="feedback_tag" value="<?= e($feedbackKey) ?>" class="sr-only peer" <?= ($projectForm['feedback_tag'] ?? \App\Services\ProjectService::FEEDBACK_NORMAL) === $feedbackKey ? 'checked' : '' ?>>
                                    <span class="inline-flex items-center rounded-full px-4 py-2 text-sm peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-blue-500 <?= e($feedbackClass) ?>"><?= e($feedbackLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">工单标签状态</label>
                        <div class="flex flex-wrap gap-3">
                            <?php foreach ($workOrderStatuses as $statusKey => $statusLabel): ?>
                                <?php $statusClass = $workOrderStatusStyles[$statusKey] ?? 'bg-white text-gray-700 border border-gray-300'; ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="work_order_status" value="<?= e($statusKey) ?>" class="sr-only peer" <?= ($projectForm['work_order_status'] ?? \App\Services\ProjectService::STATUS_SALES_TASK) === $statusKey ? 'checked' : '' ?>>
                                    <span class="inline-flex items-center rounded-full px-4 py-2 text-sm peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-blue-500 <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </form>

            <?php if ($editingProject): ?>
                <div class="mt-6 border-t border-gray-200 pt-6">
                    <h4 class="text-md font-semibold text-gray-900 mb-4">当前项目文件</h4>
                    <div class="space-y-3">
                        <?php if ($projectDocuments === []): ?>
                            <div class="text-sm text-gray-500">当前项目还没有上传文件。</div>
                        <?php else: ?>
                            <?php foreach ($projectDocuments as $doc): ?>
                                <div class="rounded-lg border border-gray-200 px-4 py-3 flex justify-between items-center gap-4">
                                    <div>
                                        <strong class="block text-gray-900"><?= e($doc['original_name']) ?></strong>
                                        <div class="text-sm text-gray-500"><?= e($doc['category']) ?> / v<?= e((string) $doc['version_no']) ?> / <?= e(format_bytes((int) $doc['file_size'])) ?></div>
                                    </div>
                                    <div class="text-sm flex gap-3">
                                        <a class="text-blue-600 hover:text-blue-800" href="index.php?action=download_document&id=<?= e((string) $doc['id']) ?>">下载</a>
                                        <?php if (in_array($doc['extension'], ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true)): ?>
                                            <a class="text-blue-600 hover:text-blue-800" target="_blank" href="index.php?action=preview_document&id=<?= e((string) $doc['id']) ?>">预览</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3 project-modal-footer">
            <a id="cancel-project" href="index.php?view=projects#projects" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                取消
            </a>
            <button id="save-project" type="submit" form="project-form" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                保存项目
            </button>
        </div>
    </div>
</div>




