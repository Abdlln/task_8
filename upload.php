<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: index.php?error=Файл не был загружен');
    exit;
}

if (!file_exists(TEMP_UPLOAD_DIR)) {
    mkdir(TEMP_UPLOAD_DIR, 0777, true);
}

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$dest_path = TEMP_UPLOAD_DIR . uniqid() . '_' . $fileName;

if (!move_uploaded_file($fileTmpPath, $dest_path)) {
    header('Location: index.php?error=Ошибка при сохранении файла');
    exit;
}

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);

    $currentPath = $_POST['path'] ?? '';
    if (empty($currentPath)) {
        $currentPath = YANDEX_DISK_PATH;
    }

    $currentPath = preg_replace('/\/+/', '/', $currentPath);

    if (strpos($currentPath, 'disk:/') === 0) {
        $uploadPath = rtrim($currentPath, '/') . '/' . $fileName;
    } else {
        $cleanPath = ltrim($currentPath, '/');
        $uploadPath = 'disk:/' . $cleanPath . '/' . $fileName;
    }

    $uploadPath = preg_replace('/\/+/', '/', $uploadPath);

    $resource = $disk->getResource($uploadPath);
    $resource->upload($dest_path);

    unlink($dest_path);

    $redirectPath = !empty($currentPath) ? '?path=' . urlencode($currentPath) . '&' : '?';
    header('Location: index.php' . $redirectPath . 'success=Файл успешно загружен');
    exit;
} catch (Exception $e) {
    if (file_exists($dest_path)) {
        unlink($dest_path);
    }
    $redirectPath = !empty($currentPath) ? '&path=' . urlencode($currentPath) : '';
    header('Location: index.php?error=' . urlencode('Ошибка загрузки: ' . $e->getMessage()) . $redirectPath);
    exit;
}