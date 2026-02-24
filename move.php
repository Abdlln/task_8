<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$filePath = $_POST['file_path'] ?? '';
$destinationPath = $_POST['destination_path'] ?? '';

if (empty($filePath) || empty($destinationPath)) {
    header('Location: index.php?error=Не указан файл или папка назначения');
    exit;
}

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);
    $resource = $disk->getResource($filePath);

    if (!$resource->has()) {
        throw new Exception('Файл или папка не найдены');
    }

    $fileName = basename($filePath);
    $fullDestPath = rtrim($destinationPath, '/') . '/' . $fileName;
    $fullDestPath = preg_replace('/\/+/', '/', $fullDestPath);

    $resource->move($fullDestPath, false);

    header('Location: index.php?success=Файл перемещён');
    exit;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'AlreadyExists') !== false) {
        header('Location: index.php?error=Файл с таким именем уже существует в папке назначения');
    } else {
        header('Location: index.php?error=' . urlencode('Ошибка перемещения: ' . $e->getMessage()));
    }
    exit;
}