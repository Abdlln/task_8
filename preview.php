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

    $extension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));

    $contentTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];

    $contentType = $contentTypes[$extension] ?? 'application/octet-stream';

    if (ob_get_level()) {
        ob_end_clean();
    }

    header("Content-Type: " . $contentType);
    header("Content-Length: " . $resource->size);
    header("Content-Disposition: inline; filename=\"" . basename($cleanPath) . "\"");
    header("Cache-Control: private, max-age=0, must-revalidate");
    header("Accept-Ranges: bytes");

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