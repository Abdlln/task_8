<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: trash.php');
    exit;
}

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);
    $disk->cleanTrash();
    usleep(500000);

    header('Location: trash.php?success=Корзина очищена');
    exit;
} catch (Exception $e) {
    header('Location: trash.php?error=' . urlencode('Ошибка очистки: ' . $e->getMessage()));
    exit;
}