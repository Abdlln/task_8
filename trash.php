<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

$disk = new Disk(YANDEX_DISK_TOKEN);
$trashItems = [];
$error = null;

try {
    $trashCollection = $disk->getTrashResources();
    foreach ($trashCollection as $item) {
        try {
            $itemData = $item->toArray();
            if (isset($itemData['name'])) {
                $trashItems[] = $itemData;
            }
        } catch (Exception $itemException) {
            continue;
        }
    }
} catch (Exception $e) {
    $error = 'Ошибка загрузки корзины: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; color: #333; }
        h1 { font-size: 20px; font-weight: 500; margin: 0 0 20px; color: #222; }
        h2 { font-size: 16px; font-weight: 500; margin: 0 0 15px; color: #333; }
        a { color: #333; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .box { background: #fff; padding: 15px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #ddd; }
        .trash-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .trash-item:last-child { border-bottom: none; }
        .item-info { flex-grow: 1; }
        .item-name { font-weight: 500; margin-bottom: 5px; }
        .item-meta { color: #888; font-size: 13px; }
        .item-actions { display: flex; gap: 8px; }
        .btn { padding: 6px 12px; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; font-size: 13px; background: #fff; color: #333; display: inline-block; }
        .btn:hover { background: #f0f0f0; }
        .btn-danger { color: #d93025; border-color: transparent; }
        .btn-danger:hover { background: #ffebee; }
        .btn-primary { background: #333; color: #fff; border-color: #333; }
        .btn-primary:hover { background: #000; }
        .alert { padding: 10px; border-radius: 3px; margin-bottom: 15px; font-size: 13px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        .trash-info { background: #fff3cd; color: #856404; padding: 10px; border-radius: 3px; margin-bottom: 15px; border: 1px solid #ffeeba; }
    </style>
</head>
<body>
    <h1>🗑️ Корзина</h1>
    <a href="index.php" class="btn" style="margin-bottom: 20px;">← Назад к файлам</a>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="trash-info">
        <strong>ℹ️ Информация:</strong> Файлы в корзине будут автоматически удалены через 30 дней.
        <form action="clean-trash.php" method="post" style="display: inline; margin-left: 20px;">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Очистить всю корзину? Это действие необратимо!')">🗑️ Очистить корзину</button>
        </form>
    </div>
    <div class="box">
        <h2>📂 Удалённые файлы (<?php echo count($trashItems); ?>)</h2>
        <?php if (empty($trashItems)): ?>
            <p style="color: #888;">Корзина пуста</p>
        <?php else: ?>
            <?php foreach ($trashItems as $item): ?>
                <?php
                $hasSize = isset($item['size']) && $item['size'] !== null;
                $hasDeleted = isset($item['deleted']) && $item['deleted'] !== null;
                $hasType = isset($item['type']) && $item['type'] !== null;
                ?>
                <div class="trash-item">
                    <div class="item-info">
                        <div class="item-name">
                            <?php echo $hasType && $item['type'] === 'dir' ? '📁' : '📄'; ?>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </div>
                        <div class="item-meta">
                            Тип: <?php echo $hasType ? htmlspecialchars($item['type']) : '—'; ?> |
                            <?php if ($hasSize): ?>Размер: <?php echo round($item['size'] / 1024, 1); ?> KB |<?php else: ?>Размер: — |<?php endif; ?>
                            <?php if ($hasDeleted): ?>Удалён: <?php echo date('d.m.Y H:i', strtotime($item['deleted'])); ?><?php else: ?>Удалён: —<?php endif; ?>
                        </div>
                    </div>
                    <div class="item-actions">
                        <form action="restore.php" method="post" style="display: inline;">
                            <input type="hidden" name="trash_path" value="<?php echo htmlspecialchars($item['path']); ?>">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Восстановить файл?')">↩️ Восстановить</button>
                        </form>
                        <form action="delete-permanent.php" method="post" style="display: inline;">
                            <input type="hidden" name="trash_path" value="<?php echo htmlspecialchars($item['path']); ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Удалить навсегда? Это действие необратимо!')">🗑️ Удалить</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>