<?php $metrics = $dashboard['metrics']; ?>
<section class="section-stack">
    <div class="hero-grid">
        <a class="metric-card" href="index.php?view=projects">
            <span>项目总数</span>
            <strong><?= e((string) $metrics['total_projects']) ?></strong>
            <small>点击查看全部项目详情</small>
        </a>
        <a class="metric-card mint" href="index.php?view=projects&tag=conflict">
            <span>人员冲突</span>
            <strong><?= e((string) $metrics['conflict_count']) ?></strong>
            <small>下钻到冲突项目及日志</small>
        </a>
        <a class="metric-card amber" href="index.php?view=projects&tag=completion">
            <span>已完成项目</span>
            <strong><?= e((string) $metrics['completion_count']) ?></strong>
            <small>查看完成评价与回执</small>
        </a>
        <a class="metric-card ink" href="index.php?view=attendance">
            <span>总体出勤率</span>
            <strong><?= e((string) $metrics['attendance_rate']) ?>%</strong>
            <small>进入本周出勤台账</small>
        </a>
    </div>

    <div class="panel-grid two">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>项目区域分布</h3>
                    <p>关联项目详情表，点击可切换到项目管理筛选</p>
                </div>
            </div>
            <canvas id="region-chart" class="chart-box"></canvas>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>项目角色统计</h3>
                    <p>售前 / 实施支持项目数量占比</p>
                </div>
            </div>
            <canvas id="role-chart" class="chart-box"></canvas>
        </section>
    </div>

    <div class="panel-grid two">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>月度项目趋势</h3>
                    <p>看板与项目管理数据实时联动</p>
                </div>
            </div>
            <canvas id="monthly-chart" class="chart-box"></canvas>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>销售项目分布</h3>
                    <p>展示项目销售维度的支持工作分布</p>
                </div>
            </div>
            <canvas id="sales-chart" class="chart-box"></canvas>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>项目甘特图</h3>
                <p>展示项目时间跨度与执行窗口</p>
            </div>
        </div>
        <div class="gantt-list">
            <?php foreach ($dashboard['gantt'] as $item): ?>
                <div class="gantt-row">
                    <div class="gantt-meta">
                        <strong><?= e($item['name']) ?></strong>
                        <span><?= e($item['person']) ?> / <?= e($item['start']) ?> - <?= e($item['end']) ?></span>
                    </div>
                    <div class="gantt-track">
                        <div class="gantt-bar <?= $item['role'] === '售前' ? 'pre' : 'impl' ?>" style="left: <?= e((string) $item['offset']) ?>%; width: <?= e((string) $item['width']) ?>%;">
                            <?= e($item['role']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="panel-grid two">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>冲突日志下钻</h3>
                    <p>人员时间重叠项目自动识别</p>
                </div>
                <a class="inline-link" href="index.php?view=projects&tag=conflict">查看全部</a>
            </div>
            <div class="list-stack">
                <?php if ($dashboard['conflicts'] === []): ?>
                    <div class="empty-box">当前没有发现人员冲突项目。</div>
                <?php else: ?>
                    <?php foreach ($dashboard['conflicts'] as $item): ?>
                        <a class="log-card" href="index.php?view=projects&edit=<?= e((string) $item['id']) ?>">
                            <strong><?= e($item['project_name']) ?></strong>
                            <span><?= e($item['conflict_note']) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>近期项目</h3>
                    <p>快速跳转到项目详情继续编辑</p>
                </div>
                <a class="inline-link" href="index.php?view=projects">进入管理</a>
            </div>
            <div class="table-shell compact">
                <table>
                    <thead>
                    <tr>
                        <th>项目</th>
                        <th>区域</th>
                        <th>支撑人员</th>
                        <th>周期</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dashboard['recent_projects'] as $item): ?>
                        <tr>
                            <td><a href="index.php?view=projects&edit=<?= e((string) $item['id']) ?>"><?= e($item['project_name']) ?></a></td>
                            <td><?= e($item['project_region']) ?></td>
                            <td><?= e($item['support_personnel']) ?></td>
                            <td><?= e($item['start_date']) ?> ~ <?= e($item['end_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>

