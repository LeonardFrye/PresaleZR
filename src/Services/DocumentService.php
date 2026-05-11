<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DocumentService
{
    private $pdo;
    private $auth;
    private $projectService;

    public function __construct(PDO $pdo, AuthService $auth, ProjectService $projectService)
    {
        $this->pdo = $pdo;
        $this->auth = $auth;
        $this->projectService = $projectService;
    }

    public function list(array $filters = []): array
    {
        $sql = 'SELECT d.*, p.project_name, p.project_region, u.display_name AS uploader_name
                FROM documents d
                LEFT JOIN projects p ON p.id = d.project_id
                LEFT JOIN users u ON u.id = d.uploaded_by
                WHERE 1=1';
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= ' AND d.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        if (!empty($filters['category'])) {
            $sql .= ' AND d.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= ' AND (d.original_name LIKE ? OR COALESCE(d.description, \'\') LIKE ? OR COALESCE(p.project_name, \'\') LIKE ?)';
            $keyword = '%' . trim((string) $filters['keyword']) . '%';
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $sql .= ' ORDER BY d.created_at DESC, d.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        return $doc ?: null;
    }

    public function upload(array $file, int $projectId, string $category, string $description, array $user, string $ipAddress): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('请选择要上传的文件。');
        }

        $originalName = (string) $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = app_config('uploads.allowed_extensions', []);
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('当前文件格式不支持上传。');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size > (int) app_config('uploads.max_size', 0)) {
            throw new RuntimeException('上传文件超过大小限制。');
        }

        $project = $this->projectService->find($projectId);
        if (!$project) {
            throw new RuntimeException('项目不存在，无法绑定附件。');
        }

        $directory = app_config('storage.uploads');
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('文件保存失败，请检查 storage/uploads 目录权限。');
        }

        $version = $this->nextVersion($projectId, $category);
        $stmt = $this->pdo->prepare('INSERT INTO documents
            (project_id, category, original_name, stored_name, extension, mime_type, file_size, version_no, description, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $projectId,
            $category,
            $originalName,
            $storedName,
            $extension,
            $this->guessMime($extension),
            $size,
            $version,
            $description,
            (int) $user['id'],
        ]);
        $documentId = (int) $this->pdo->lastInsertId();

        if ($category === 'receipt') {
            $this->pdo->prepare('UPDATE projects SET receipt_document_id = ?, updated_by = ? WHERE id = ?')
                ->execute([$documentId, (int) $user['id'], $projectId]);
        }

        $this->auth->log((int) $user['id'], 'upload', 'documents', '上传文档：' . $originalName, $ipAddress, [
            'project_id' => $projectId,
            'document_id' => $documentId,
            'category' => $category,
        ]);

        return $documentId;
    }

    public function stream(int $id, bool $inline): void
    {
        $document = $this->find($id);
        if (!$document) {
            http_response_code(404);
            exit('文件不存在。');
        }

        $path = rtrim(app_config('storage.uploads'), '/\\') . DIRECTORY_SEPARATOR . $document['stored_name'];
        if (!is_file($path)) {
            http_response_code(404);
            exit('文件已丢失。');
        }

        $disposition = $inline ? 'inline' : 'attachment';
        header('Content-Type: ' . ($document['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($document['original_name']) . '"');
        readfile($path);
        exit;
    }

    private function nextVersion(int $projectId, string $category): int
    {
        $stmt = $this->pdo->prepare('SELECT MAX(version_no) FROM documents WHERE project_id = ? AND category = ?');
        $stmt->execute([$projectId, $category]);
        return (int) $stmt->fetchColumn() + 1;
    }

    private function guessMime(string $extension): string
    {
        $map = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt' => 'text/plain; charset=utf-8',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
        ];

        return $map[$extension] ?? 'application/octet-stream';
    }
}
