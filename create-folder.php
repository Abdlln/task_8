<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$folderName = $_POST['folder_name'] ?? '';
$parentPath = $_POST['parent_path'] ?? YANDEX_DISK_PATH;

if (empty($folderName)) {
    header('Location: index.php?error=Введите название папки');
    exit;
}

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);

    $newPath = rtrim($parentPath, '/') . '/' . $folderName;
    $newPath = preg_replace('/\/+/', '/', $newPath);

    $resource = $disk->getResource($newPath);
    $resource->create('dir');

    header('Location: index.php?success=Папка "' . htmlspecialchars($folderName) . '" создана');
    exit;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'AlreadyExists') !== false) {
        header('Location: index.php?error=Папка с таким именем уже существует');
    } else {
        header('Location: index.php?error=' . urlencode('Ошибка создания: ' . $e->getMessage()));
    }
    exit;
}