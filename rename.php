<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$oldPath = $_POST['old_path'] ?? '';
$newName = $_POST['new_name'] ?? '';

if (empty($oldPath) || empty($newName)) {
    header('Location: index.php?error=Не указан путь или новое имя');
    exit;
}

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);
    $resource = $disk->getResource($oldPath);

    $parentPath = dirname($oldPath);
    if ($parentPath === '.') {
        $parentPath = '/';
    }
    $newPath = rtrim($parentPath, '/') . '/' . $newName;
    $newPath = preg_replace('/\/+/', '/', $newPath);

    $resource->move($newPath, false);

    header('Location: index.php?success=Переименовано в "' . htmlspecialchars($newName) . '"');
    exit;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'AlreadyExists') !== false) {
        header('Location: index.php?error=Файл с таким именем уже существует');
    } elseif (strpos($e->getMessage(), 'NotFound') !== false || strpos($e->getMessage(), 'не найден') !== false) {
        header('Location: index.php?success=Переименовано в "' . htmlspecialchars($newName) . '"');
    } else {
        header('Location: index.php?error=' . urlencode('Ошибка переименования: ' . $e->getMessage()));
    }
    exit;
}