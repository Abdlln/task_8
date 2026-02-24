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
    $resource = $disk->getTrashResource($trashPath);
    $resource->delete();

    header('Location: trash.php?success=Файл удалён навсегда');
    exit;
} catch (Exception $e) {
    header('Location: trash.php?error=' . urlencode('Ошибка удаления: ' . $e->getMessage()));
    exit;
}