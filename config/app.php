<?php

return [
    'app_name' => '技术支撑事业部项目管理平台',
    'timezone' => 'Asia/Shanghai',
    'base_url' => '',
    'db' => [
        'host' => '',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
        'auto_create_database' => true,
    ],
    'storage' => [
        'uploads' => __DIR__ . '/../storage/uploads',
    ],
    'uploads' => [
        'max_size' => 20 * 1024 * 1024,
        'allowed_extensions' => [
            'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'zip', 'rar',
        ],
    ],
    'roles' => [
        'admin' => '系统管理员',
        'editor' => '普通用户',
    ],
    'default_icons' => [
        'dashboard' => 'fa-solid fa-chart-line',
        'projects' => 'fa-solid fa-diagram-project',
        'personnel' => 'fa-solid fa-users',
        'attendance' => 'fa-solid fa-calendar-check',
        'documents' => 'fa-solid fa-folder-open',
        'reports' => 'fa-solid fa-file-export',
        'settings' => 'fa-solid fa-gear',
        'logs' => 'fa-solid fa-clock-rotate-left',
    ],
];