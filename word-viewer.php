<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

require_once 'vendor/autoload.php';
require_once 'config.php';

use Arhitector\Yandex\Disk;
use PhpOffice\PhpWord\IOFactory;

$filePath = $_GET['path'] ?? '';
$cleanPath = '';
$resource = null;
$htmlContent = '';

if (empty($filePath)) {
    http_response_code(400); 
    echo "Ошибка: не указан путь к файлу"; 
    exit;
}

try {
    $disk = new Disk(YANDEX_DISK_TOKEN);
    
    $cleanPath = preg_replace('/\?.*$/s', '', $filePath) ?: '';
    $cleanPath = preg_replace('/\/+/', '/', $cleanPath) ?: '';
    if (strpos($cleanPath, 'disk:/') !== 0) {
        $cleanPath = 'disk:/' . ltrim($cleanPath, '/');
    }
    
    $resource = $disk->getResource($cleanPath);
    
    if (!$resource->has()) {
        throw new Exception("Файл не найден на Яндекс.Диске");
    }
    
    $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('word_') . '.docx';
    $resource->download($tempFile);
    
    if (!file_exists($tempFile) || filesize($tempFile) === 0) {
        throw new Exception("Не удалось скачать файл или он пустой");
    }
    
    $images = extractImagesFromDocx($tempFile);
    
    $phpWordError = null;
    try {
        $phpWord = IOFactory::load($tempFile);
        
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
        ob_start();
        $htmlWriter->save('php://output');
        $htmlContent = ob_get_clean();
        
        if (!empty($images)) {
            $htmlContent = fixImagePaths($htmlContent, $images);
        }
        
    } catch (Exception $e) {
        $phpWordError = $e;
    }
    
    if ($phpWordError !== null) {
        $htmlContent = extractContentWithStyles($tempFile, $images);
    }
    
    @unlink($tempFile);
    
    if (empty($htmlContent)) {
        throw new Exception("Не удалось извлечь содержимое документа");
    }
    
} catch (Exception $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="color:red;padding:20px;">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

function extractImagesFromDocx($docxPath) {
    $images = [];
    $zip = new ZipArchive();
    
    if ($zip->open($docxPath) !== true) return $images;
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        
        if (preg_match('/\.(png|jpg|jpeg|gif|bmp)$/i', $name)) {
            $imageData = $zip->getFromName($name);
            if ($imageData !== false) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mimeTypes = ['png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'gif'=>'image/gif', 'bmp'=>'image/bmp'];
                $mimeType = $mimeTypes[$ext] ?? 'image/png';
                $base64 = base64_encode($imageData);
                $images[basename($name)] = [
                    'data' => "data:$mimeType;base64,$base64",
                    'path' => $name,
                    'mimeType' => $mimeType
                ];
            }
        }
    }
    $zip->close();
    return $images;
}

function fixImagePaths($html, $images) {
    if (empty($images)) return $html;
    
    foreach ($images as $baseName => $imgInfo) {
        $dataUrl = $imgInfo['data'];
        $html = str_replace('word//media/' . $baseName, $dataUrl, $html);
        $html = str_replace('word/media/' . $baseName, $dataUrl, $html);
        $html = str_replace('media/' . $baseName, $dataUrl, $html);
        
        $html = preg_replace(
            '/src=["\'][^"\']*' . preg_quote($baseName, '/') . '["\']/i',
            'src="' . $dataUrl . '"',
            $html
        );
    }
    
    return $html;
}

function extractContentWithStyles($filePath, $images) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return '<p>Не удалось открыть файл</p>';
    }
    
    $styles = [];
    if ($zip->locateName('word/styles.xml') !== false) {
        $stylesXml = $zip->getFromName('word/styles.xml');
        $styles = parseStyles($stylesXml);
    }
    
    $html = '<div class="word-content">';
    
    if ($zip->locateName('word/document.xml') !== false) {
        $xml = $zip->getFromName('word/document.xml');
        if ($xml) {
            $html .= parseDocumentXml($xml, $images, $styles);
        }
    }
    
    $zip->close();
    return $html . '</div>';
}

function parseStyles($xml) {
    $styles = [];
    
    if (preg_match_all('/<w:style[^>]*w:styleId=["\']([^"\']+)["\'][^>]*>(.*?)<\/w:style>/s', $xml, $styleMatches)) {
        foreach ($styleMatches[1] as $i => $styleId) {
            $styleContent = $styleMatches[2][$i];
            
            $style = [];
            
            if (strpos($styleContent, '<w:b') !== false || strpos($styleContent, '<w:bCs') !== false) {
                $style['bold'] = true;
            }
            
            if (strpos($styleContent, '<w:i') !== false || strpos($styleContent, '<w:iCs') !== false) {
                $style['italic'] = true;
            }
            
            if (preg_match('/<w:u[^>]*w:val=["\']([^"\']+)["\']/', $styleContent, $uMatch)) {
                $style['underline'] = $uMatch[1] !== 'none';
            }
            
            if (preg_match('/<w:sz[^>]*w:val=["\'](\d+)["\']/', $styleContent, $szMatch)) {
                $style['fontSize'] = $szMatch[1] / 2; 
            }
            
            if (preg_match('/<w:color[^>]*w:val=["\']([^"\']+)["\']/', $styleContent, $colorMatch)) {
                $style['color'] = '#' . $colorMatch[1];
            }
            
            if (preg_match('/<w:jc[^>]*w:val=["\']([^"\']+)["\']/', $styleContent, $jcMatch)) {
                $style['align'] = $jcMatch[1];
            }
            
            $styles[$styleId] = $style;
        }
    }
    
    return $styles;
}

function parseDocumentXml($xml, $images, $styles) {
    $html = '';
    
    if (preg_match_all('/<w:p[^>]*>(.*?)<\/w:p>/s', $xml, $paragraphs)) {
        foreach ($paragraphs[1] as $pContent) {
            $paragraphHtml = parseParagraph($pContent, $images, $styles);
            if (!empty(trim($paragraphHtml))) {
                $html .= $paragraphHtml;
            }
        }
    }
    
    if (preg_match_all('/<w:tbl[^>]*>(.*?)<\/w:tbl>/s', $xml, $tables)) {
        foreach ($tables[1] as $tableContent) {
            $tableHtml = parseTable($tableContent, $images, $styles);
            if (!empty(trim($tableHtml))) {
                $html .= $tableHtml;
            }
        }
    }
    
    return $html;
}

function parseParagraph($pContent, $images, $styles) {
    $paragraphStyle = '';
    
    if (preg_match('/<w:pStyle[^>]*w:val=["\']([^"\']+)["\']/', $pContent, $pStyleMatch)) {
        $styleId = $pStyleMatch[1];
        if (isset($styles[$styleId])) {
            $paragraphStyle = $styles[$styleId];
        }
        
        if (strpos($styleId, 'Heading1') !== false || strpos($styleId, 'Heading_1') !== false || strpos($styleId, 'Заголовок1') !== false) {
            $tag = 'h1';
        } elseif (strpos($styleId, 'Heading2') !== false || strpos($styleId, 'Heading_2') !== false || strpos($styleId, 'Заголовок2') !== false) {
            $tag = 'h2';
        } elseif (strpos($styleId, 'Heading3') !== false || strpos($styleId, 'Heading_3') !== false || strpos($styleId, 'Заголовок3') !== false) {
            $tag = 'h3';
        } elseif (strpos($styleId, 'Title') !== false || strpos($styleId, 'Заголовок') !== false) {
            $tag = 'h2';
        } else {
            $tag = 'p';
        }
    } else {
        $tag = 'p';
    }
    
    $isList = false;
    if (preg_match('/<w:numPr[^>]*>(.*?)<\/w:numPr>/s', $pContent, $numMatch)) {
        $isList = true;
    }
    
    $text = extractTextWithFormatting($pContent, $images, $styles);
    
    if (empty(trim($text))) {
        return '';
    }
    
    $styleAttr = '';
    if (!empty($paragraphStyle)) {
        if (!empty($paragraphStyle['align'])) {
            $alignMap = ['both' => 'justify', 'center' => 'center', 'right' => 'right', 'left' => 'left'];
            $styleAttr .= 'text-align: ' . ($alignMap[$paragraphStyle['align']] ?? 'left') . ';';
        }
        if (!empty($paragraphStyle['fontSize'])) {
            $styleAttr .= 'font-size: ' . $paragraphStyle['fontSize'] . 'pt;';
        }
        if (!empty($paragraphStyle['color'])) {
            $styleAttr .= 'color: ' . $paragraphStyle['color'] . ';';
        }
    }
    
    if ($isList) {
        return '<li style="' . $styleAttr . '">' . $text . '</li>';
    } elseif ($tag !== 'p') {
        return '<' . $tag . ' style="' . $styleAttr . '">' . $text . '</' . $tag . '>';
    } else {
        return '<p style="' . $styleAttr . '">' . $text . '</p>';
    }
}

function extractTextWithFormatting($pContent, $images, $styles) {
    $text = '';
    
    if (preg_match_all('/<w:r[^>]*>(.*?)<\/w:r>/s', $pContent, $runs)) {
        foreach ($runs[1] as $runContent) {
            $runText = '';
            $runStyles = [];
            
            if (preg_match('/<w:rPr[^>]*>(.*?)<\/w:rPr>/s', $runContent, $rprMatch)) {
                $rprContent = $rprMatch[1];
                
                if (strpos($rprContent, '<w:b') !== false || strpos($rprContent, '<w:bCs') !== false) {
                    $runStyles['bold'] = true;
                }
                
                if (strpos($rprContent, '<w:i') !== false || strpos($rprContent, '<w:iCs') !== false) {
                    $runStyles['italic'] = true;
                }
                
                if (preg_match('/<w:u[^>]*w:val=["\']([^"\']+)["\']/', $rprContent, $uMatch)) {
                    $runStyles['underline'] = $uMatch[1] !== 'none';
                }
                
                if (strpos($rprContent, '<w:strike') !== false || strpos($rprContent, '<w:dstrike') !== false) {
                    $runStyles['strike'] = true;
                }
                
                if (preg_match('/<w:vertAlign[^>]*w:val=["\']superscript["\']/', $rprContent)) {
                    $runStyles['superscript'] = true;
                }
                
                if (preg_match('/<w:vertAlign[^>]*w:val=["\']subscript["\']/', $rprContent)) {
                    $runStyles['subscript'] = true;
                }
                
                if (preg_match('/<w:sz[^>]*w:val=["\'](\d+)["\']/', $rprContent, $szMatch)) {
                    $runStyles['fontSize'] = $szMatch[1] / 2;
                }
                
                if (preg_match('/<w:color[^>]*w:val=["\']([A-Fa-f0-9]+)["\']/', $rprContent, $colorMatch)) {
                    $runStyles['color'] = '#' . $colorMatch[1];
                }
                
                if (preg_match('/<w:highlight[^>]*w:val=["\']([^"\']+)["\']/', $rprContent, $hlMatch)) {
                    $highlightColors = [
                        'yellow' => '#FFFF00',
                        'green' => '#00FF00',
                        'cyan' => '#00FFFF',
                        'magenta' => '#FF00FF',
                        'blue' => '#0000FF',
                        'red' => '#FF0000',
                        'darkBlue' => '#00008B',
                        'darkGreen' => '#008000',
                        'darkRed' => '#8B0000',
                        'darkCyan' => '#008B8B',
                        'darkMagenta' => '#8B008B',
                        'darkYellow' => '#8B8B00',
                        'lightGray' => '#D3D3D3',
                        'darkGray' => '#A9A9A9',
                        'black' => '#000000',
                        'white' => '#FFFFFF'
                    ];
                    $runStyles['highlight'] = $highlightColors[$hlMatch[1]] ?? '#FFFF00';
                }
            }
            
            if (preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $runContent, $textMatches)) {
                foreach ($textMatches[1] as $t) {
                    $runText .= html_entity_decode($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                }
            }
            
            if (preg_match('/<w:drawing[^>]*>(.*?)<\/w:drawing>/s', $runContent, $drawMatch) ||
                preg_match('/<w:pict[^>]*>(.*?)<\/w:pict>/s', $runContent, $pictMatch)) {
                
                foreach ($images as $baseName => $imgInfo) {
                    if (strpos($baseName, 'image') !== false) {
                        $text .= '<img src="' . $imgInfo['data'] . '" alt="' . htmlspecialchars($baseName) . '" style="max-width:100%;height:auto;display:block;margin:10px 0;">';
                        break;
                    }
                }
            }
            
            if (!empty($runText)) {
                $formatted = htmlspecialchars($runText);
                
                if (!empty($runStyles['bold'])) $formatted = '<strong>' . $formatted . '</strong>';
                if (!empty($runStyles['italic'])) $formatted = '<em>' . $formatted . '</em>';
                if (!empty($runStyles['underline'])) $formatted = '<u>' . $formatted . '</u>';
                if (!empty($runStyles['strike'])) $formatted = '<s>' . $formatted . '</s>';
                if (!empty($runStyles['superscript'])) $formatted = '<sup>' . $formatted . '</sup>';
                if (!empty($runStyles['subscript'])) $formatted = '<sub>' . $formatted . '</sub>';
                $inlineStyle = '';
                if (!empty($runStyles['color'])) $inlineStyle .= 'color: ' . $runStyles['color'] . ';';
                if (!empty($runStyles['fontSize'])) $inlineStyle .= 'font-size: ' . $runStyles['fontSize'] . 'pt;';
                if (!empty($runStyles['highlight'])) $inlineStyle .= 'background-color: ' . $runStyles['highlight'] . ';';
                
                if (!empty($inlineStyle)) {
                    $formatted = '<span style="' . $inlineStyle . '">' . $formatted . '</span>';
                }
                
                $text .= $formatted;
            }
        }
    }
    
    $text = str_replace('</w:t><w:t>', '', $text);
    
    return $text;
}

function parseTable($tableContent, $images, $styles) {
    $html = '<table style="border-collapse:collapse;width:100%;margin:15px 0;">';
    
    if (preg_match_all('/<w:tr[^>]*>(.*?)<\/w:tr>/s', $tableContent, $rows)) {
        foreach ($rows[1] as $i => $rowContent) {
            $tag = ($i === 0) ? 'th' : 'td';
            $rowStyle = ($i === 0) ? 'background:#f5f5f5;font-weight:bold;' : '';
            $html .= '<tr>';            
            if (preg_match_all('/<w:tc[^>]*>(.*?)<\/w:tc>/s', $rowContent, $cells)) {
                foreach ($cells[1] as $cellContent) {
                    $cellText = extractTextWithFormatting($cellContent, $images, $styles);
                    $html .= '<' . $tag . ' style="border:1px solid #ddd;padding:8px;' . $rowStyle . '">' . $cellText . '</' . $tag . '>';
                }
            }
            
            $html .= '</tr>';
        }
    }
    
    $html .= '</table>';
    return $html;
}

$fileName = basename($cleanPath);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($fileName); ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; background: #f5f5f5; max-width: 900px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h2 { margin: 0 0 10px 0; color: #333; font-size: 20px; }
        .info { color: #666; font-size: 14px; }
        .content { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .content p { margin: 10px 0; line-height: 1.8; }
        .content h1 { font-size: 24pt; color: #333; margin: 20px 0 10px 0; font-weight: bold; }
        .content h2 { font-size: 18pt; color: #444; margin: 18px 0 8px 0; font-weight: bold; }
        .content h3 { font-size: 14pt; color: #555; margin: 15px 0 6px 0; font-weight: bold; }
        .content img { max-width: 100%; height: auto; display: block; margin: 20px 0; border-radius: 4px; }
        .content table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        .content td, .content th { border: 1px solid #ddd; padding: 8px; }
        .content th { background: #f5f5f5; font-weight: bold; }
        .content ul, .content ol { margin: 10px 0; padding-left: 30px; }
        .content li { margin: 5px 0; }
        .content strong { font-weight: bold; }
        .content em { font-style: italic; }
        .content u { text-decoration: underline; }
        .content s { text-decoration: line-through; }
        .btn-print { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #1a73e8; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-print:hover { background: #0d47a1; }
        @media print { .header, .btn-print { display: none; } .content { box-shadow: none; padding: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h2>📘 <?php echo htmlspecialchars($fileName); ?></h2>
        <div class="info">
            Размер: <?php echo round($resource->size / 1024, 1); ?> KB | 
            Изменён: <?php echo date('d.m.Y H:i', strtotime($resource->modified)); ?>
        </div>
    </div>
    
    <div class="content"><?php echo $htmlContent; ?></div>
    
    <button class="btn-print" onclick="window.print()">🖨️ Печать</button>
</body>
</html>