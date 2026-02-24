<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;

function formatSize($bytes)
{
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i) * 100) / 100 . ' ' . $sizes[$i];
}

$disk = new Disk(YANDEX_DISK_TOKEN);
$path = $_GET['path'] ?? YANDEX_DISK_PATH ?: '/';

try {
    $resource = $disk->getResource($path);
    if ($resource->has()) {
        $files = $resource->items->toArray();
    } else {
        $files = [];
        $disk->getResource($path)->create('dir');
    }
} catch (Exception $e) {
    $files = [];
    $error = 'Ошибка: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Яндекс Диск</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; color: #333; }
        h1 { font-size: 20px; font-weight: 500; margin: 0 0 20px; color: #222; }
        h2 { font-size: 16px; font-weight: 500; margin: 0 0 15px; color: #333; }
        a { color: #333; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .box { background: #fff; padding: 15px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #ddd; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .file-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .file-info { flex-grow: 1; }
        .file-name { font-weight: 500; margin-bottom: 5px; }
        .file-name a { color: #333; cursor: pointer; }
        .file-name a:hover { text-decoration: underline; }
        .file-meta { color: #888; font-size: 13px; }
        .file-actions { display: flex; gap: 8px; }
        .btn { padding: 6px 12px; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; font-size: 13px; background: #fff; color: #333; display: inline-block; }
        .btn:hover { background: #f0f0f0; }
        .btn-danger { color: #d93025; border-color: transparent; }
        .btn-danger:hover { background: #ffebee; }
        .btn-primary { background: #333; color: #fff; border-color: #333; }
        .btn-primary:hover { background: #000; }
        .alert { padding: 10px; border-radius: 3px; margin-bottom: 15px; font-size: 13px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); }
        .modal-content { background: #fff; margin: 15% auto; padding: 20px; border-radius: 4px; width: 400px; max-width: 90%; position: relative; }
        .modal-content h3 { margin-top: 0; font-size: 16px; }
        .modal-content input, .modal-content select { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box; }
        .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 15px; }
        .close { position: absolute; right: 15px; top: 10px; font-size: 24px; cursor: pointer; color: #999; }
        .close:hover { color: #333; }
        #previewModal .modal-content { margin: 5% auto; padding: 10px; width: 80%; max-width: 900px; background: transparent; box-shadow: none; }
        #previewContent { background: #fff; padding: 20px; border-radius: 4px; text-align: center; }
        #previewContent img { max-width: 100%; max-height: 70vh; display: block; margin: 0 auto; }
        .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #333; border-radius: 50%; width: 30px; height: 30px; margin: 20px auto; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <h1>📁 Яндекс Диск</h1>
    <div class="box">
        <strong>Файлы:</strong> <a href="index.php">Корневая папка</a>
        <?php
        $pathParts = explode('/', trim($path, '/'));
        $currentPath = '';
        foreach ($pathParts as $part):
            if (!empty($part)):
                $currentPath .= '/' . $part;
        ?>
                / <a href="index.php?path=<?php echo urlencode($currentPath); ?>"><?php echo htmlspecialchars($part); ?></a>
        <?php
            endif;
        endforeach;
        ?>
    </div>
    <div class="toolbar">
        <button onclick="showCreateFolderModal()" class="btn btn-primary">📁 Новая папка</button>
        <a href="trash.php" class="btn">🗑️ Корзина</a>
        <button onclick="location.reload()" class="btn">🔄 Обновить</button>
        <?php if ($path !== YANDEX_DISK_PATH && $path !== '/'): ?>
            <a href="index.php?path=<?php echo urlencode(dirname($path)); ?>" class="btn">⬅️ Наверх</a>
        <?php endif; ?>
    </div>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateFolderModal()">&times;</span>
            <h3>📁 Создать папку</h3>
            <form action="create-folder.php" method="post">
                <input type="hidden" name="parent_path" value="<?php echo htmlspecialchars($path); ?>">
                <input type="text" name="folder_name" placeholder="Название папки" required>
                <div class="modal-actions">
                    <button type="button" onclick="closeCreateFolderModal()" class="btn">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать</button>
                </div>
            </form>
        </div>
    </div>
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRenameModal()">&times;</span>
            <h3>✏️ Переименовать</h3>
            <form action="rename.php" method="post">
                <input type="hidden" name="old_path" id="rename_old_path">
                <input type="text" name="new_name" id="rename_new_name" required>
                <div class="modal-actions">
                    <button type="button" onclick="closeRenameModal()" class="btn">Отмена</button>
                    <button type="submit" class="btn btn-primary">Переименовать</button>
                </div>
            </form>
        </div>
    </div>
    <div id="moveModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeMoveModal()">&times;</span>
            <h3>📦 Переместить в папку</h3>
            <form action="move.php" method="post">
                <input type="hidden" name="file_path" id="move_file_path">
                <label style="display: block; margin-bottom: 5px;">Выберите папку назначения:</label>
                <select name="destination_path" required id="move_destination">
                    <option value="<?php echo htmlspecialchars(YANDEX_DISK_PATH); ?>">📁 <?php echo htmlspecialchars(YANDEX_DISK_PATH); ?> (корневая)</option>
                    <?php foreach ($files as $f): ?>
                        <?php if ($f['type'] === 'dir'): ?>
                            <option value="<?php echo htmlspecialchars($f['path']); ?>">📁 <?php echo htmlspecialchars($f['name']); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="modal-actions">
                    <button type="button" onclick="closeMoveModal()" class="btn">Отмена</button>
                    <button type="submit" class="btn btn-primary">Переместить</button>
                </div>
            </form>
        </div>
    </div>
    <div class="box">
        <h2>📤 Загрузить файл</h2>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
            <input type="file" name="file" required style="margin-bottom: 10px;">
            <button type="submit" class="btn btn-primary">Загрузить на Яндекс Диск</button>
        </form>
    </div>
    <div class="box">
        <h2>📂 Файлы в папке: <?php echo htmlspecialchars(basename($path) ?: 'Корневая'); ?></h2>
        <?php if (empty($files)): ?>
            <p style="color: #888;">Нет файлов в папке "<?php echo htmlspecialchars($path); ?>"</p>
        <?php else: ?>
            <?php foreach ($files as $file): ?>
                <?php
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $extension = strtolower($extension);
                $hasSize = isset($file['size']) && $file['size'] !== null;
                $hasModified = isset($file['modified']) && $file['modified'] !== null;
                $downloadLink = isset($file['file']) && !empty($file['file']) ? $file['file'] : 'download.php?path=' . urlencode($file['path']);
                ?>
                <div class="file-item">
                    <div class="file-info">
                        <div class="file-name">
                            <?php if ($file['type'] === 'file'): ?>
                                <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])): ?>
                                    <a href="javascript:void(0)" onclick="openImagePreview('<?php echo htmlspecialchars($downloadLink); ?>', '<?php echo htmlspecialchars($file['name']); ?>')"><?php echo htmlspecialchars($file['name']); ?></a>
                                <?php elseif ($extension === 'pdf'): ?>
                                    <a href="javascript:void(0)" onclick="openPdfPreview('<?php echo htmlspecialchars($file['path']); ?>', '<?php echo htmlspecialchars($file['name']); ?>')"><?php echo htmlspecialchars($file['name']); ?></a>
                                <?php elseif (in_array($extension, ['doc', 'docx'])): ?>
                                    <a href="javascript:void(0)" onclick="openWordPreview('<?php echo htmlspecialchars($file['path']); ?>', '<?php echo htmlspecialchars($file['name']); ?>')"><?php echo htmlspecialchars($file['name']); ?></a>
                                <?php elseif ($extension === 'txt'): ?>
                                    <a href="view-text.php?path=<?php echo urlencode($file['path']); ?>" target="_blank"><?php echo htmlspecialchars($file['name']); ?></a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($downloadLink); ?>" target="_blank"><?php echo htmlspecialchars($file['name']); ?></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="index.php?path=<?php echo urlencode($file['path']); ?>" title="Открыть папку">📁 <?php echo htmlspecialchars($file['name']); ?></a>
                            <?php endif; ?>
                        </div>
                        <div class="file-meta">
                            Тип: <?php echo htmlspecialchars($file['type']); ?> |
                            <?php if ($hasSize): ?>Размер: <?php echo formatSize($file['size']); ?> |<?php endif; ?>
                            <?php if ($hasModified): ?>Изменен: <?php echo date('d.m.Y H:i', strtotime($file['modified'])); ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="file-actions">
                        <?php if ($file['type'] === 'file'): ?>
                            <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="btn" target="_blank">💾 Скачать</a>
                            <button onclick="showRenameModal('<?php echo htmlspecialchars($file['path']); ?>', '<?php echo htmlspecialchars($file['name']); ?>')" class="btn">✏️</button>
                            <button onclick="showMoveModal('<?php echo htmlspecialchars($file['path']); ?>')" class="btn">📦</button>
                            <form action="delete.php" method="post" style="display: inline;">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars($file['path']); ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Переместить в корзину?')">🗑️</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePreview()">&times;</span>
            <div id="previewContent"></div>
        </div>
    </div>
    <script>
        function showCreateFolderModal() { document.getElementById('createFolderModal').style.display = 'block'; }
        function closeCreateFolderModal() { document.getElementById('createFolderModal').style.display = 'none'; }
        function showRenameModal(oldPath, currentName) {
            document.getElementById('rename_old_path').value = oldPath;
            document.getElementById('rename_new_name').value = currentName;
            document.getElementById('renameModal').style.display = 'block';
        }
        function closeRenameModal() { document.getElementById('renameModal').style.display = 'none'; }
        function showMoveModal(filePath) {
            document.getElementById('move_file_path').value = filePath;
            document.getElementById('moveModal').style.display = 'block';
        }
        function closeMoveModal() { document.getElementById('moveModal').style.display = 'none'; }
        function openImagePreview(fileUrl, fileName) {
            const previewContent = document.getElementById('previewContent');
            document.getElementById('previewModal').style.display = 'block';
            previewContent.innerHTML = '<div style="padding: 40px;"><div class="spinner"></div><p>Загрузка...</p></div>';
            const img = new Image();
            img.onload = function() { previewContent.innerHTML = `<img src="${fileUrl}" alt="${fileName}" style="max-width: 100%; max-height: 70vh;">`; };
            img.onerror = function() { previewContent.innerHTML = '<p style="color: red;">Ошибка загрузки</p>'; };
            img.src = fileUrl;
        }
        function openPdfPreview(filePath, fileName) {
            const link = document.createElement('a');
            link.href = `preview.php?path=${encodeURIComponent(filePath)}`;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        function openWordPreview(filePath, fileName) {
            const link = document.createElement('a');
            link.href = `word-viewer.php?path=${encodeURIComponent(filePath)}`;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
            document.getElementById('previewContent').innerHTML = '';
        }
        window.onclick = function(event) {
            ['createFolderModal', 'renameModal', 'moveModal', 'previewModal'].forEach(function(modalId) {
                const modal = document.getElementById(modalId);
                if (modal && event.target == modal) modal.style.display = 'none';
            });
        }
    </script>
</body>
</html>