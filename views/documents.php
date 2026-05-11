<section class="section-stack">
    <form class="filter-panel" method="get">
        <input type="hidden" name="view" value="documents">
        <label>
            <span>项目</span>
            <select name="project_id">
                <option value="">全部项目</option>
                <?php foreach ($projectService->list() as $project): ?>
                    <option value="<?= e((string) $project['id']) ?>" <?= (string) ($_GET['project_id'] ?? '') === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['project_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>类别</span>
            <select name="category">
                <option value="">全部</option>
                <option value="receipt" <?= ($_GET['category'] ?? '') === 'receipt' ? 'selected' : '' ?>>技术完成回执单</option>
                <option value="attachment" <?= ($_GET['category'] ?? '') === 'attachment' ? 'selected' : '' ?>>项目附件</option>
            </select>
        </label>
        <div class="filter-actions">
            <button class="primary-btn" type="submit">筛选</button>
        </div>
    </form>

    <div class="split-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>文档列表</h3>
                    <p>支持上传、下载、预览、修订（通过新版本上传）</p>
                </div>
            </div>
            <div class="list-stack">
                <?php if ($documents === []): ?>
                    <div class="empty-box">暂时没有文档记录。</div>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="doc-row large">
                            <div>
                                <strong><?= e($doc['original_name']) ?></strong>
                                <span><?= e($doc['project_name'] ?: '未绑定项目') ?> / <?= e($doc['category']) ?> / v<?= e((string) $doc['version_no']) ?> / <?= e(format_bytes((int) $doc['file_size'])) ?></span>
                                <small>上传人：<?= e($doc['uploader_name'] ?: '-') ?>，时间：<?= e(format_datetime($doc['created_at'])) ?></small>
                            </div>
                            <div class="badge-row">
                                <?php if (in_array($doc['extension'], ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true)): ?>
                                    <a class="inline-link" target="_blank" href="index.php?action=preview_document&id=<?= e((string) $doc['id']) ?>">预览</a>
                                <?php endif; ?>
                                <a class="inline-link" href="index.php?action=download_document&id=<?= e((string) $doc['id']) ?>">下载</a>
                                <a class="inline-link" href="index.php?view=projects&edit=<?= e((string) $doc['project_id']) ?>">转到项目</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>上传 / 修订文档</h3>
                    <p>选择同一项目和类别再次上传，即形成新的版本记录</p>
                </div>
            </div>
            <form class="form-grid" method="post" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_document">
                <label>
                    <span>所属项目</span>
                    <select name="project_id" required>
                        <option value="">请选择项目</option>
                        <?php foreach ($projectService->list() as $project): ?>
                            <option value="<?= e((string) $project['id']) ?>" <?= (string) ($_GET['project_id'] ?? '') === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>文档类别</span>
                    <select name="category" required>
                        <option value="attachment">项目附件</option>
                        <option value="receipt">技术完成回执单</option>
                    </select>
                </label>
                <label class="wide"><span>文档说明</span><input type="text" name="description" placeholder="例如：二次修订版 / 交付材料"></label>
                <label class="wide"><span>选择文件</span><input type="file" name="document_file" required></label>
                <div class="form-actions wide">
                    <button class="primary-btn" type="submit">上传文档</button>
                </div>
            </form>
        </section>
    </div>
</section>

