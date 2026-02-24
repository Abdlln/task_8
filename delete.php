<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;
use Arhitector\Yandex\Client\Exception\NotFoundException;

if (!isset($_POST['path'])) {
    header('Location: index.php?error=Не указан путь к файлу');
    exit;
}

$filePath = $_POST['path'];

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);
    $resource = $disk->getResource($filePath);

    if (!$resource->has()) {
        throw new NotFoundException('Файл не найден');
    }

    $resource->delete();

    header('Location: index.php?success=Файл успешно удален');
    exit;
} catch (NotFoundException $e) {
    header('Location: index.php?error=Файл не найден на Яндекс Диске');
    exit;
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('Ошибка удаления: ' . $e->getMessage()));
    exit;
}