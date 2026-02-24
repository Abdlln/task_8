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

    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . DIRECTORY_SEPARATOR . uniqid('yandex_') . '_' . basename($cleanPath);
    $resource->download($tempFile);

    if (!file_exists($tempFile)) {
        throw new Exception("Не удалось сохранить временный файл");
    }

    $content = file_get_contents($tempFile);
    $fileName = basename($cleanPath);

    @unlink($tempFile);
} catch (Exception $e) {
    $content = "Ошибка загрузки: " . $e->getMessage();
    $fileName = "Ошибка";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($fileName); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; background: #f5f5f5; color: #333; max-width: 1200px; margin: 0 auto; }
        .box { background: #fff; padding: 20px; border-radius: 4px; border: 1px solid #ddd; margin-bottom: 15px; }
        .content { background: #fff; padding: 20px; border-radius: 4px; border: 1px solid #ddd; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; font-size: 14px; }
        .btn { padding: 6px 12px; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; font-size: 13px; background: #fff; color: #333; display: inline-block; text-decoration: none; }
        .btn:hover { background: #f0f0f0; }
        .btn-print { position: fixed; top: 20px; right: 20px; }
        h2 { font-size: 16px; font-weight: 500; margin: 0 0 15px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>📄 <?php echo htmlspecialchars($fileName); ?></h2>
    </div>
    <div class="content"><?php echo htmlspecialchars($content); ?></div>
    <button class="btn btn-print" onclick="window.print()">🖨️ Печать</button>
</body>
</html>