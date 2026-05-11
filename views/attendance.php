<section class="section-stack">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>本周出勤台账</h3>
                <p>自动识别项目管理中登记的开始/结束时间和支撑人员</p>
            </div>
        </div>
        <div class="table-shell attendance">
            <table>
                <thead>
                <tr>
                    <th>人员 / 日期</th>
                    <?php foreach ($attendanceBoard['days'] as $day): ?>
                        <th><?= e($day['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($attendanceBoard['rows'] as $row): ?>
                    <tr>
                        <td class="row-title"><?= e($row['name']) ?></td>
                        <?php foreach ($row['days'] as $cell): ?>
                            <td>
                                <span class="status-pill <?= e($cell['status']) ?>"><?= e($cell['text']) ?></span>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

