<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: trash.php');
    exit;
}

$trashPath = $_POST['trash_path'] ?? '';

if (empty($trashPath)) {
    header('Location: trash.php?error=Не указан путь к файлу');
    exit;
}

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);
    $cleanPath = str_replace('trash:/', '', $trashPath);
    $resource = $disk->getTrashResource($cleanPath);
    $resource->restore($cleanPath, false);

    header('Location: trash.php?success=Файл восстановлен');
    exit;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'AlreadyExists') !== false) {
        header('Location: trash.php?error=Файл с таким именем уже существует. Переименуйте его в корзине или удалите существующий.');
    } else {
        header('Location: trash.php?error=' . urlencode('Ошибка восстановления: ' . $e->getMessage()));
    }
    exit;
}