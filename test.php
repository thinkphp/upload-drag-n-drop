<?php
header('Content-Type: text/plain; charset=utf-8');

echo "🔍 TEST RAPID UPLOAD SYSTEM\n";
echo "========================\n\n";

$uploadDir = 'uploads/';

// 1. Verifică existența și permisiunile folderului
echo "1. VERIFICARE FOLDER:\n";
if (is_dir($uploadDir)) {
    $perms = substr(sprintf('%o', fileperms($uploadDir)), -3);
    echo "✅ Folderul '$uploadDir' există\n";
    echo "📋 Permisiuni: $perms\n";
    
    if (is_readable($uploadDir)) {
        echo "✅ Readable: DA\n";
    } else {
        echo "❌ Readable: NU\n";
    }
    
    if (is_writable($uploadDir)) {
        echo "✅ Writable: DA\n";
    } else {
        echo "❌ Writable: NU\n";
    }
    
    if (is_executable($uploadDir)) {
        echo "✅ Executable: DA\n";
    } else {
        echo "❌ Executable: NU\n";
    }
} else {
    echo "❌ Folderul '$uploadDir' NU există\n";
    echo "💡 Rulează: mkdir uploads && chmod 775 uploads\n";
}

echo "\n";

// 2. Test de scriere
echo "2. TEST SCRIERE:\n";
$testFile = $uploadDir . 'test_' . date('YmdHis') . '.txt';
$testContent = "Test upload - " . date('Y-m-d H:i:s') . "\nServer: " . $_SERVER['HTTP_HOST'];

if (file_put_contents($testFile, $testContent)) {
    echo "✅ Scriere reușită: $testFile\n";
    echo "📄 Mărime fișier: " . filesize($testFile) . " bytes\n";
    
    // Șterge fișierul test
    if (unlink($testFile)) {
        echo "✅ Ștergere fișier test reușită\n";
    } else {
        echo "⚠️  Nu s-a putut șterge fișierul test\n";
    }
} else {
    echo "❌ Nu s-a putut scrie în folder\n";
    echo "💡 Verifică permisiunile cu: ls -la $uploadDir\n";
}

echo "\n";

// 3. Verifică configurarea PHP
echo "3. CONFIGURARE PHP:\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "s\n";

echo "\n";

// 4. Verifică extensiile PHP pentru imagini
echo "4. EXTENSII IMAGINI PHP:\n";
echo "GD Extension: " . (extension_loaded('gd') ? "✅ DA" : "❌ NU") . "\n";
echo "JPEG Support: " . (function_exists('imagejpeg') ? "✅ DA" : "❌ NU") . "\n";
echo "PNG Support: " . (function_exists('imagepng') ? "✅ DA" : "❌ NU") . "\n";
echo "GIF Support: " . (function_exists('imagegif') ? "✅ DA" : "❌ NU") . "\n";
echo "WebP Support: " . (function_exists('imagewebp') ? "✅ DA" : "❌ NU") . "\n";

echo "\n";

// 5. Informații sistem
echo "5. INFO SISTEM:\n";
echo "PHP User: " . get_current_user() . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Directory curent: " . getcwd() . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";

if (function_exists('posix_getuid')) {
    echo "Process UID: " . posix_getuid() . "\n";
    echo "Process GID: " . posix_getgid() . "\n";
}

echo "\n";

// 6. Status final
echo "6. STATUS FINAL:\n";
if (is_dir($uploadDir) && is_writable($uploadDir)) {
    echo "🎉 TOTUL PERFECT! Sistemul de upload ar trebui să funcționeze!\n";
    echo "🚀 Poți folosi upload-ul acum cu încredere.\n";
} else {
    echo "⚠️  PROBLEME DETECTATE!\n";
    echo "🔧 Rulează: chmod 775 uploads\n";
    echo "📞 Sau contactează administratorul serverului.\n";
}
?>
