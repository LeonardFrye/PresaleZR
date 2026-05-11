<section class="section-stack">
    <div class="hero-grid three">
        <div class="metric-card">
            <span>总工时</span>
            <strong><?= e((string) $dashboard['metrics']['total_hours']) ?></strong>
            <small>按工期天数 * 8 小时估算</small>
        </div>
        <div class="metric-card mint">
            <span>平均工期</span>
            <strong><?= e((string) $dashboard['metrics']['avg_days']) ?> 天</strong>
            <small>所有项目平均持续时间</small>
        </div>
        <div class="metric-card amber">
            <span>转接延续</span>
            <strong><?= e((string) $dashboard['metrics']['transfer_count']) ?></strong>
            <small>用于跟踪项目跨阶段延续</small>
        </div>
    </div>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>报表导出</h3>
                <p>导出结构按 `project_plan.xlsx` 形式生成单 Sheet Excel</p>
            </div>
            <a class="primary-btn" href="index.php?action=export_report">导出项目报表</a>
        </div>
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>序号</th>
                    <th>项目区域</th>
                    <th>项目名称</th>
                    <th>项目销售</th>
                    <th>支撑岗位</th>
                    <th>支撑人员</th>
                    <th>开始时间</th>
                    <th>结束时间</th>
                    <th>回执单</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $index => $item): ?>
                    <tr>
                        <td><?= e((string) ($index + 1)) ?></td>
                        <td><?= e($item['project_region']) ?></td>
                        <td><?= e($item['project_name']) ?></td>
                        <td><?= e($item['project_sales']) ?></td>
                        <td><?= e($item['support_role']) ?></td>
                        <td><?= e($item['support_personnel']) ?></td>
                        <td><?= e($item['start_date']) ?></td>
                        <td><?= e($item['end_date']) ?></td>
                        <td><?= e($item['receipt_name'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

