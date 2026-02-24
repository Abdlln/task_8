<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

$filePath = $_GET['path'] ?? '';

if (empty($filePath)) {
    http_response_code(400);
    echo "Ошибка: не указан путь к файлу";
    exit;
}

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);

    $cleanPath = preg_replace('/\?.*$/s', '', $filePath);
    $cleanPath = preg_replace('/\/+/', '/', $cleanPath);

    if (strpos($cleanPath, 'disk:/') !== 0) {
        $cleanPath = 'disk:/' . ltrim($cleanPath, '/');
    }

    $resource = $disk->getResource($cleanPath);

    if (!$resource->has()) {
        http_response_code(404);
        echo "Файл не найден";
        exit;
    }

    if (ob_get_level()) {
        ob_end_clean();
    }

    $extension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
    $contentTypes = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];
    $contentType = $contentTypes[$extension] ?? 'application/octet-stream';

    header("Content-Type: " . $contentType);
    header("Content-Length: " . $resource->size);
    header("Content-Disposition: inline; filename=\"" . basename($cleanPath) . "\"");
    header("Cache-Control: private, max-age=0, must-revalidate");
    header("Access-Control-Allow-Origin: *");
    header("X-Frame-Options: SAMEORIGIN");

    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . DIRECTORY_SEPARATOR . uniqid('yandex_') . '_' . basename($cleanPath);

    $resource->download($tempFile);

    if (!file_exists($tempFile)) {
        throw new Exception("Не удалось сохранить временный файл");
    }

    readfile($tempFile);
    @unlink($tempFile);
    exit;
} catch (Exception $e) {
    if (headers_sent()) {
        exit;
    }

    http_response_code(500);
    echo "Ошибка: " . htmlspecialchars($e->getMessage());
    exit;
}