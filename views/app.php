<?php
$metrics = $dashboard['metrics'];
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
$openProjectModal = $editingProject !== null || array_key_exists('edit', $_GET);
$weekdayOf = static function (?string $date): string {
    return $date ? weekday_label($date) : '';
};
$scoreLabel = static function (float $value): string {
    $formatted = number_format($value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
};
$numberLabel = static function ($value): string {
    $formatted = number_format((float) $value, 1, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
};
$personnelWeekdays = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
?>
<section id="dashboard-page" class="page-section <?= $view === 'dashboard' ? '' : 'hidden' ?>">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">数据概览</h2>
        <p class="text-gray-600 mt-1">技术支撑事业部项目管理平台数据统计与分析</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <a href="index.php?view=projects#projects" class="card-hover bg-white rounded-lg shadow p-6 border border-gray-100">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-project-diagram text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">项目总数</p>
                    <p class="text-2xl font-bold text-gray-900"><?= e((string) $metrics['total_projects']) ?><span class="ml-1 text-base font-medium text-gray-500">个</span></p>
                </div>
            </div>
        </a>
        <a href="index.php?view=reports#reports" class="card-hover bg-white rounded-lg shadow p-6 border border-gray-100">
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-clock text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">总工期</p>
                    <p class="text-2xl font-bold text-gray-900"><?= e((string) $metrics['total_hours']) ?><span class="ml-1 text-base font-medium text-gray-500">小时</span></p>
                    <p class="mt-1 text-sm text-gray-500"><?= e($numberLabel($metrics['total_days'])) ?> 天</p>
                </div>
            </div>
        </a>
        <a href="index.php?view=attendance#attendance" class="card-hover bg-white rounded-lg shadow p-6 border border-gray-100">
            <div class="flex items-center">
                <div class="bg-purple-100 p-3 rounded-full">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
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
                                <?= $isDone ? '&#24050;&#23436;&#25104;' : '&#36827;&#34892;&#20013;' ?>
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
            <p class="text-gray-600 mt-1">支持批量选择、预览、编辑、删除和分页查看项目</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="post" enctype="multipart/form-data" class="inline-flex">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="import_projects">
                <label class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center cursor-pointer">
                    <i class="fas fa-file-import mr-2"></i> 导入项目
                    <input type="file" name="import_excel_file" accept=".xlsx,.csv" class="hidden" required onchange="if (this.files.length) { this.form.submit(); }">
                </label>
            </form>
            <button id="add-project-btn" type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> 新增项目
            </button>
        </div>
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
            <form method="post" class="flex flex-col gap-3 lg:flex-row lg:items-center" id="project-batch-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="batch_delete_projects">
                <?php foreach ($projectListQuery as $queryKey => $queryValue): ?>
                    <input type="hidden" name="<?= e($queryKey) ?>" value="<?= e((string) $queryValue) ?>">
                <?php endforeach; ?>
                <div class="flex flex-wrap items-center gap-3">
                    <select name="batch_action" id="project-batch-action" class="min-w-[12rem] px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">批量操作</option>
                        <option value="delete">删除选中项目</option>
                    </select>
                    <button type="submit" id="project-batch-submit" class="px-4 py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50" disabled>执行</button>
                    <div class="text-sm text-gray-500">已选 <span id="project-selected-count" class="font-semibold text-gray-900">0</span> 项</div>
                </div>
            </form>

            <div class="flex flex-wrap justify-end gap-3">
                <button type="submit" form="project-filter-form" class="inline-flex h-12 items-center gap-2 rounded-full bg-blue-600 px-5 text-sm font-semibold text-white shadow-[0_12px_24px_rgba(37,99,235,0.22)] transition hover:-translate-y-0.5 hover:bg-blue-700">
                    <i class="fas fa-filter text-sm"></i>
                    <span>筛选项目</span>
                </button>
                <a href="index.php?view=projects#projects" class="inline-flex h-12 items-center gap-2 rounded-full border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-[0_8px_18px_rgba(15,23,42,0.06)] transition hover:-translate-y-0.5 hover:bg-slate-50">
                    <i class="fas fa-rotate-left text-sm"></i>
                    <span>重置</span>
                </a>
                <a href="index.php?action=export_report&<?= http_build_query(array_merge(['view' => 'projects'], $filters)) ?>" class="inline-flex h-12 items-center gap-2 rounded-full bg-emerald-600 px-5 text-sm font-semibold text-white shadow-[0_12px_24px_rgba(5,150,105,0.2)] transition hover:-translate-y-0.5 hover:bg-emerald-700">
                    <i class="fas fa-file-excel text-sm"></i>
                    <span>导出 Excel</span>
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
                        'duration' => '工期',
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <input type="checkbox" id="projects-select-all" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th data-project-col="sequence" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">序号</th>
                    <th data-project-col="project_name" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">项目名称</th>
                    <th data-project-col="region" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">区域</th>
                    <th data-project-col="sales" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">销售</th>
                    <th data-project-col="role" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">岗位</th>
                    <th data-project-col="personnel" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">支撑人员</th>
                    <th data-project-col="date_range" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">开始 / 结束</th>
                    <th data-project-col="duration" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">工期</th>
                    <th data-project-col="tag" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">标签</th>
                    <th data-project-col="feedback" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">反馈标签</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="12" data-project-empty class="px-6 py-10 text-center text-sm text-gray-500">暂无符合条件的项目</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($projects as $index => $item): ?>
                    <tr>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                            <input type="checkbox" name="project_ids[]" value="<?= e((string) $item['id']) ?>" form="project-batch-form" class="project-row-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td data-project-col="sequence" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e((string) ((($projectPagination['page'] - 1) * $projectPagination['per_page']) + $index + 1)) ?></td>
                        <td data-project-col="project_name" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= e($item['project_name']) ?></td>
                        <td data-project-col="region" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['project_region']) ?></td>
                        <td data-project-col="sales" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['project_sales']) ?></td>
                        <td data-project-col="role" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['support_role']) ?></td>
                        <td data-project-col="personnel" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['support_personnel']) ?></td>
                        <td data-project-col="date_range" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['start_date']) ?> / <?= e($item['end_date']) ?></td>
                        <td data-project-col="duration" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e((string) $item['duration_days']) ?> 天</td>
                        <td data-project-col="tag" class="px-6 py-4 text-sm text-gray-500">
                            <?php
                            $statusKey = $item['work_order_status'] ?? \App\Services\ProjectService::STATUS_SALES_TASK;
                            $statusLabel = $workOrderStatuses[$statusKey] ?? '销售发布任务';
                            $statusClass = $workOrderStatusStyles[$statusKey] ?? $workOrderStatusStyles[\App\Services\ProjectService::STATUS_SALES_TASK];
                            ?>
                            <span class="inline-flex px-3 py-1 text-xs rounded-full <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                        </td>
                        <td data-project-col="feedback" class="px-6 py-4 text-sm text-gray-500">
                            <?php
                            $feedbackKey = $item['feedback_tag'] ?? \App\Services\ProjectService::FEEDBACK_NORMAL;
                            $feedbackLabel = $feedbackTagOptions[$feedbackKey] ?? '正常';
                            $feedbackClass = $feedbackTagStyles[$feedbackKey] ?? $feedbackTagStyles[\App\Services\ProjectService::FEEDBACK_NORMAL];
                            ?>
                            <span class="inline-flex px-3 py-1 text-xs rounded-full <?= e($feedbackClass) ?>"><?= e($feedbackLabel) ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="index.php?<?= http_build_query(array_merge($projectListQuery, ['preview' => $item['id']])) ?>#projects" class="text-slate-600 hover:text-slate-800 mr-3">
                                <i class="fas fa-eye mr-1"></i> 预览
                            </a>
                            <a href="index.php?<?= http_build_query(array_merge($projectListQuery, ['edit' => $item['id']])) ?>#projects" class="text-blue-600 hover:text-blue-800 mr-3">
                                <i class="fas fa-edit mr-1"></i> 编辑
                            </a>
                            <form method="post" class="inline" onsubmit="return confirm('确认删除该项目吗？');">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_project">
                                <input type="hidden" name="project_id" value="<?= e((string) $item['id']) ?>">
                                <?php foreach ($projectListQuery as $queryKey => $queryValue): ?>
                                    <input type="hidden" name="<?= e($queryKey) ?>" value="<?= e((string) $queryValue) ?>">
                                <?php endforeach; ?>
                                <button class="text-red-600 hover:text-red-800" type="submit">
                                    <i class="fas fa-trash mr-1"></i> 删除
                                </button>
                            </form>
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
    <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">人员绩效</h2>
        </div>
        <form class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm md:flex-row md:items-end" method="get">
            <input type="hidden" name="view" value="personnel">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">时间维度</label>
                <div class="flex gap-2">
                    <?php foreach (['year' => '年', 'month' => '月', 'week' => '周'] as $periodKey => $periodLabel): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="personnel_period" value="<?= e($periodKey) ?>" class="sr-only peer" <?= $personnelBoard['period'] === $periodKey ? 'checked' : '' ?>>
                            <span class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-700"><?= e($periodLabel) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">参考日期</label>
                <input type="date" name="personnel_date" value="<?= e($personnelBoard['anchor_date']) ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">切换</button>
                <a href="index.php?view=personnel#personnel" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50">重置</a>
                <a href="index.php?action=export_personnel_performance&personnel_period=<?= e($personnelBoard['period']) ?>&personnel_date=<?= e($personnelBoard['anchor_date']) ?>" class="rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">一键生成绩效</a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($personnelBoard['cards'] as $card): ?>
            <a href="index.php?view=personnel&personnel_period=<?= e($personnelBoard['period']) ?>&personnel_date=<?= e($personnelBoard['anchor_date']) ?>&personnel_person=<?= urlencode($card['name']) ?>#personnel" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                <div class="text-lg font-semibold text-gray-900"><?= e($card['name']) ?></div>
                <div class="mt-4 text-sm text-gray-500">总绩效</div>
                <div class="mt-1 text-3xl font-bold text-blue-700"><?= e($scoreLabel((float) $card['total_score'])) ?></div>
                <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
                    <span>有项目天数</span>
                    <span class="font-medium text-gray-700"><?= e((string) $card['active_days']) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($personnelDetail): ?>
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 px-4 py-6">
            <div class="mx-auto max-w-7xl rounded-2xl bg-white shadow-2xl">
                <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900"><?= e($personnelDetail['person_name']) ?> 绩效明细</h3>
                        <p class="mt-1 text-sm text-gray-500"><?= e($personnelDetail['label']) ?>，活跃天数 <?= e((string) $personnelDetail['active_days']) ?> 天，总绩效 <?= e($scoreLabel((float) $personnelDetail['total_score'])) ?></p>
                        <?php if ($auth->can('manage_performance')): ?>
                            <p class="mt-2 text-xs text-blue-700">管理员可直接修改每天的绩效数值，默认有项目时为 1。</p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if ($auth->can('manage_performance')): ?>
                            <button type="submit" form="personnel-performance-form" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">保存绩效</button>
                        <?php endif; ?>
                        <a href="index.php?action=export_personnel_detail_performance&personnel_period=<?= e($personnelDetail['period']) ?>&personnel_date=<?= e($personnelDetail['anchor_date']) ?>&person_name=<?= urlencode($personnelDetail['person_name']) ?>" class="rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">一键生成个人绩效</a>
                        <a href="index.php?view=personnel&personnel_period=<?= e($personnelBoard['period']) ?>&personnel_date=<?= e($personnelBoard['anchor_date']) ?>#personnel" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50">关闭</a>
                    </div>
                </div>

                <form id="personnel-performance-form" method="post" class="px-6 py-6">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_personnel_performance">
                    <input type="hidden" name="person_name" value="<?= e($personnelDetail['person_name']) ?>">
                    <input type="hidden" name="personnel_period" value="<?= e($personnelDetail['period']) ?>">
                    <input type="hidden" name="personnel_date" value="<?= e($personnelDetail['anchor_date']) ?>">

                    <?php foreach ($personnelDetail['calendar_groups'] as $group): ?>
                        <div class="mb-8 last:mb-0">
                            <div class="mb-4 flex items-center justify-between">
                                <h4 class="text-lg font-semibold text-gray-900"><?= e($group['title']) ?></h4>
                            </div>
                            <div class="grid grid-cols-7 gap-3 mb-3">
                                <?php foreach ($personnelWeekdays as $weekday): ?>
                                    <div class="rounded-lg bg-gray-100 px-3 py-2 text-center text-sm font-medium text-gray-600"><?= e($weekday) ?></div>
                                <?php endforeach; ?>
                            </div>

                            <div class="<?= $group['compact'] ? 'space-y-3' : 'space-y-4' ?>">
                                <?php foreach ($group['weeks'] as $week): ?>
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-7">
                                        <?php foreach ($week as $cell): ?>
                                            <?php
                                            $isCurrentMonth = $cell['in_current_month'] ?? true;
                                            $cellClass = $isCurrentMonth ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-100';
                                            $taskClass = $cell['has_task'] ? 'text-gray-700' : 'text-gray-400';
                                            ?>
                                            <div class="min-h-[180px] rounded-2xl border p-4 <?= $cellClass ?>">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div>
                                                        <div class="text-sm font-semibold text-gray-900"><?= e($cell['month_number'] . '/' . $cell['day_number']) ?></div>
                                                        <div class="text-xs text-gray-500"><?= e($cell['weekday']) ?></div>
                                                    </div>
                                                    <?php if ($cell['has_task']): ?>
                                                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700">有项目</span>
                                                    <?php else: ?>
                                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-500">无任务</span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mt-4 space-y-2 text-xs <?= $taskClass ?>">
                                                    <?php if ($cell['has_task']): ?>
                                                        <?php foreach ($cell['tasks'] as $task): ?>
                                                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                                                <div class="font-semibold text-slate-700"><?= e($task['project_name']) ?></div>
                                                                <div class="mt-1 text-slate-500"><?= e($task['task_summary']) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="pt-6 text-center">当天没有项目安排</div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mt-4 border-t border-gray-100 pt-3">
                                                    <div class="text-xs text-gray-500">绩效</div>
                                                    <?php if ($auth->can('manage_performance') && $cell['has_task']): ?>
                                                        <input type="number" step="0.1" min="0" name="scores[<?= e($cell['date']) ?>]" value="<?= e((string) $cell['score']) ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <?php else: ?>
                                                        <div class="mt-2 text-xl font-bold text-blue-700"><?= e($scoreLabel((float) $cell['score'])) ?></div>
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
        <p class="text-gray-600 mt-1">每日自动识别项目安排，动态刷新本周出勤台账<?= $auth->can('manage_performance') ? '，管理员可手动调整为调休' : '' ?></p>
    </div>
    <div class="grid grid-cols-1 gap-8">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">本周出勤台账</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="attendance-table">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">人员 / 出勤</th>
                        <?php foreach ($attendanceBoard['days'] as $day): ?>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider"><?= e($day['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($attendanceBoard['rows'] as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= e($row['name']) ?></td>
                            <?php foreach ($row['days'] as $cell): ?>
                                <?php
                                $statusClass = 'bg-green-100 text-green-800';
                                if ($cell['status'] === 'busy' || $cell['status'] === 'conflict') {
                                    $statusClass = 'bg-blue-100 text-blue-800';
                                } elseif ($cell['status'] === 'rest') {
                                    $statusClass = 'bg-amber-100 text-amber-800';
                                }
                                ?>
                                <td class="px-4 py-4 text-center align-top">
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
    <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold text-gray-900">项目导出预览</h3>
            <a href="index.php?action=export_report" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">导出 Excel</a>
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
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500"><?= e($item['start_date']) ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500"><?= e($item['end_date']) ?></td>
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
        <p class="text-gray-600 mt-1">支持背景替换、图标配置和账号权限管理</p>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">外观设置</h3>
            <form class="grid grid-cols-1 md:grid-cols-2 gap-4" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_settings">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">鑳屾櫙鍥剧墖 URL</label>
                    <input type="text" name="appearance_background" value="<?= e($settings['appearance_background'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">平台副标题</label>
                    <input type="text" name="brand_subtitle" value="<?= e($settings['brand_subtitle'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <?php foreach (app_config('default_icons', []) as $key => $defaultIcon): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($pageTitleMap[$key] ?? $key) ?> 图标</label>
                        <input type="text" name="icon_<?= e($key) ?>" value="<?= e($icons[$key] ?? $defaultIcon) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                <?php endforeach; ?>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">保存设置</button>
                </div>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">账号权限</h3>
            <form class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="user_id" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">账号名</label>
                    <input type="text" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">显示名称</label>
                    <input type="text" name="display_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">角色</label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="editor">普通用户</option>
                        <option value="auditor">审核员</option>
                        <option value="admin">管理员</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                    <input type="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="md:col-span-2 flex items-center justify-between">
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1" checked class="mr-2"> 启用账号
                    </label>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">创建账号</button>
                </div>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">账号</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">名称</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">角色</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['username']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($item['display_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e(role_label($item['role'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= (int) $item['is_active'] === 1 ? '启用' : '停用' ?></td>
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
                <div><div class="text-sm text-gray-500">项目名称</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['project_name']) ?></div></div>
                <div><div class="text-sm text-gray-500">项目区域</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['project_region']) ?></div></div>
                <div><div class="text-sm text-gray-500">项目销售</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['project_sales']) ?></div></div>
                <div><div class="text-sm text-gray-500">支撑岗位</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['support_role']) ?></div></div>
                <div><div class="text-sm text-gray-500">支撑人员</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['support_personnel']) ?></div></div>
                <div><div class="text-sm text-gray-500">开始 / 结束</div><div class="mt-1 text-sm font-medium text-gray-900"><?= e($previewingProject['start_date']) ?> / <?= e($previewingProject['end_date']) ?></div></div>
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
    <div class="max-w-5xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden" data-project-modal-card>
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-900"><?= $projectForm['id'] ? '编辑项目' : '新增项目' ?></h3>
                <p class="text-sm text-gray-500 mt-1">按需录入项目销售、区域、人员、周期、任务、附件和销售评价</p>
            </div>
            <button id="close-modal" type="button" class="text-gray-400 hover:text-gray-600 text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="px-6 py-6">
            <form method="post" enctype="multipart/form-data" id="project-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_project">
                <input type="hidden" name="project_id" value="<?= e((string) $projectForm['id']) ?>">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                        <input type="date" name="start_date" value="<?= e($projectForm['start_date']) ?>" data-duration-start class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <p class="text-xs text-gray-500 mt-1"><?= e($weekdayOf($projectForm['start_date'])) ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">结束时间 <span class="text-red-500">*</span></label>
                        <input type="date" name="end_date" value="<?= e($projectForm['end_date']) ?>" data-duration-end class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <p class="text-xs text-gray-500 mt-1"><?= e($weekdayOf($projectForm['end_date'])) ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">工期总天数</label>
                        <input type="text" value="<?= e((string) (((strtotime($projectForm['end_date']) - strtotime($projectForm['start_date'])) / 86400) + 1)) ?>" data-duration-output class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" readonly>
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
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
            <a id="cancel-project" href="index.php?view=projects#projects" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                取消
            </a>
            <button id="save-project" type="submit" form="project-form" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                保存项目
            </button>
        </div>
    </div>
</div>




