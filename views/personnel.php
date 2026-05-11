<section class="section-stack">
    <div class="hero-grid three">
        <div class="metric-card">
            <span>本周人数</span>
            <strong><?= e((string) count($attendanceBoard['rows'])) ?></strong>
            <small>基于项目支撑人员自动汇总</small>
        </div>
        <div class="metric-card mint">
            <span>总体出勤率</span>
            <strong><?= e((string) $attendanceBoard['attendance_rate']) ?>%</strong>
            <small>忙碌天数 / 总人天</small>
        </div>
        <div class="metric-card amber">
            <span>冲突人天</span>
            <strong><?= e((string) array_sum(array_column($attendanceBoard['rows'], 'conflict_days'))) ?></strong>
            <small>可点击出勤台账继续排查</small>
        </div>
    </div>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>人员负载看板</h3>
                <p>每周根据项目开始/结束时间自动生成</p>
            </div>
            <a class="inline-link" href="index.php?view=attendance">查看出勤台账</a>
        </div>
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>人员</th>
                    <th>本周占用天数</th>
                    <th>冲突天数</th>
                    <th>状态概览</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($attendanceBoard['rows'] as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e((string) $row['load_days']) ?></td>
                        <td><?= e((string) $row['conflict_days']) ?></td>
                        <td>
                            <div class="badge-row">
                                <?php if ($row['conflict_days'] > 0): ?><span class="badge danger">有冲突</span><?php endif; ?>
                                <?php if ($row['load_days'] === 0): ?><span class="badge">空闲</span><?php else: ?><span class="badge ok">已排班</span><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

