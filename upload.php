<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configurare
$uploadDir = 'uploads/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 10 * 1024 * 1024; // 10MB
$maxFiles = 20;

// Funcție pentru a genera un nume sigur de fișier
function generateSafeFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    
    // Curăță numele fișierului
    $baseName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $baseName);
    $baseName = trim($baseName, '_-');
    
    // Generează un timestamp și un random string pentru unicitate
    $timestamp = date('Y-m-d_H-i-s');
    $randomString = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
    
    return $baseName . '_' . $timestamp . '_' . $randomString . '.' . strtolower($extension);
}

// Funcție pentru a crea directorul dacă nu există
function createUploadDirectory($dir) {
    // Încearcă să creeze directorul cu permisiuni maxime
    if (!file_exists($dir)) {
        $oldUmask = umask(0);
        $result = mkdir($dir, 0777, true);
        umask($oldUmask);
        
        if (!$result) {
            // Încearcă cu permisiuni diferite
            if (!mkdir($dir, 0755, true) && !mkdir($dir, 0644, true)) {
                return false;
            }
        }
        
        // Verifică dacă directorul chiar există acum
        if (!is_dir($dir)) {
            return false;
        }
        
        // Încearcă să seteze permisiunile din nou
        @chmod($dir, 0755);
        
        // Creează un fișier .htaccess pentru securitate doar dacă se poate scrie
        if (is_writable($dir)) {
            $htaccessContent = "Options -Indexes\n";
            $htaccessContent .= "Order deny,allow\n";
            $htaccessContent .= "Allow from all\n";
            $htaccessContent .= "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)\$\">\n";
            $htaccessContent .= "Order deny,allow\n";
            $htaccessContent .= "Deny from all\n";
            $htaccessContent .= "</FilesMatch>\n";
            
            @file_put_contents($dir . '.htaccess', $htaccessContent);
        }
    }
    
    // Verifică dacă directorul este writable
    return is_dir($dir) && is_writable($dir);
}

// Funcție pentru a valida imaginea (cu fallback dacă GD nu e disponibil)
function validateImage($tmpPath, $fileType) {
    // Dacă GD nu este disponibil, verifică doar extensia și MIME type-ul
    if (!extension_loaded('gd')) {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($fileType, $allowedMimes) && is_uploaded_file($tmpPath);
    }
    
    // Verifică dacă este într-adevăr o imagine folosind GD
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return false;
    }
    
    // Verifică tipul MIME din fișier vs ce declară browserul
    $detectedMime = $imageInfo['mime'];
    return $detectedMime === $fileType;
}

// Funcție pentru a optimiza imaginea (cu fallback dacă GD nu e disponibil)
function optimizeImage($sourcePath, $destPath, $quality = 85) {
    // Dacă GD nu este disponibil, copiază simplu fișierul
    if (!extension_loaded('gd')) {
        return copy($sourcePath, $destPath);
    }
    
    $imageInfo = @getimagesize($sourcePath);
    if (!$imageInfo) {
        return copy($sourcePath, $destPath);
    }
    
    $mime = $imageInfo['mime'];
    
    try {
        switch ($mime) {
            case 'image/jpeg':
                if (function_exists('imagecreatefromjpeg')) {
                    $image = @imagecreatefromjpeg($sourcePath);
                    if ($image) {
                        $result = @imagejpeg($image, $destPath, $quality);
                        @imagedestroy($image);
                        return $result;
                    }
                }
                break;
            case 'image/png':
                if (function_exists('imagecreatefrompng')) {
                    $image = @imagecreatefrompng($sourcePath);
                    if ($image) {
                        $result = @imagepng($image, $destPath, (int)(9 - ($quality / 10)));
                        @imagedestroy($image);
                        return $result;
                    }
                }
                break;
            case 'image/gif':
                if (function_exists('imagecreatefromgif')) {
                    $image = @imagecreatefromgif($sourcePath);
                    if ($image) {
                        $result = @imagegif($image, $destPath);
                        @imagedestroy($image);
                        return $result;
                    }
                }
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($sourcePath);
                    if ($image) {
                        $result = @imagewebp($image, $destPath, $quality);
                        @imagedestroy($image);
                        return $result;
                    }
                }
                break;
        }
    } catch (Exception $e) {
        // Fallback la copy simplu
    }
    
    // Fallback: copiază fișierul fără procesare
    return copy($sourcePath, $destPath);
}

// Funcție principală de upload
function handleUpload() {
    global $uploadDir, $allowedTypes, $maxFileSize, $maxFiles;
    
    try {
        // Verifică metoda de request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Metoda de request nu este permisă');
        }
        
        // Verifică dacă există fișiere
        if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
            throw new Exception('Nu au fost găsite fișiere pentru upload');
        }
        
        // Verifică dacă directorul există și este writable
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            throw new Exception("Directorul '$uploadDir' nu există sau nu este writable. Permisiuni găsite: " . 
                              (file_exists($uploadDir) ? substr(sprintf('%o', fileperms($uploadDir)), -3) : 'nu există'));
        }
        
        // Creează directorul de upload dacă nu există
        if (!createUploadDirectory($uploadDir)) {
            $error = "Nu s-a putut crea/accesa directorul de upload: $uploadDir";
            if (!is_writable(dirname($uploadDir))) {
                $error .= " (directorul părinte nu este writable)";
            }
            if (function_exists('posix_getuid') && posix_getuid() !== fileowner('.')) {
                $error .= " (permisiuni insuficiente)";
            }
            $error .= "\n\nSoluții:\n1. Creează manual folderul 'uploads' cu chmod 755\n2. Sau contactează administratorul serverului pentru permisiuni";
            throw new Exception($error);
        }
        
        $files = $_FILES['photos'];
        $fileCount = count($files['name']);
        
        // Verifică numărul de fișiere
        if ($fileCount > $maxFiles) {
            throw new Exception("Numărul maxim de fișiere permise este $maxFiles");
        }
        
        $uploadedFiles = [];
        $errors = [];
        
        // Procesează fiecare fișier
        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $files['name'][$i];
            $fileTmpPath = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileType = $files['type'][$i];
            $fileError = $files['error'][$i];
            
            // Verifică erorile de upload
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = "Eroare la upload pentru $fileName: cod eroare $fileError";
                continue;
            }
            
            // Verifică mărimea fișierului
            if ($fileSize > $maxFileSize) {
                $errors[] = "$fileName este prea mare. Mărimea maximă permisă este " . ($maxFileSize / 1024 / 1024) . "MB";
                continue;
            }
            
            // Verifică tipul de fișier
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "$fileName nu este un tip de fișier permis";
                continue;
            }
            
            // Validează imaginea
            if (!validateImage($fileTmpPath, $fileType)) {
                $errors[] = "$fileName nu este o imagine validă";
                continue;
            }
            
            // Generează numele nou al fișierului
            $newFileName = generateSafeFileName($fileName);
            $destPath = $uploadDir . $newFileName;
            
            // Verifică dacă fișierul nu există deja
            while (file_exists($destPath)) {
                $newFileName = generateSafeFileName($fileName);
                $destPath = $uploadDir . $newFileName;
            }
            
            // Mută fișierul și optimizează-l
            if (optimizeImage($fileTmpPath, $destPath)) {
                $uploadedFiles[] = [
                    'original_name' => $fileName,
                    'new_name' => $newFileName,
                    'path' => $destPath,
                    'size' => filesize($destPath),
                    'url' => $uploadDir . $newFileName
                ];
            } else {
                $errors[] = "Nu s-a putut salva $fileName";
            }
        }
        
        // Pregătește răspunsul
        $response = [
            'success' => count($uploadedFiles) > 0,
            'uploaded' => count($uploadedFiles),
            'total' => $fileCount,
            'files' => $uploadedFiles
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        if (count($uploadedFiles) > 0) {
            $response['message'] = count($uploadedFiles) . ' fișiere au fost uploadate cu succes';
        } else {
            $response['message'] = 'Nu s-au putut uploada fișiere';
            $response['success'] = false;
        }
        
        return $response;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'uploaded' => 0,
            'total' => 0
        ];
    }
}

// Execută upload-ul și returnează răspunsul JSON
$result = handleUpload();
echo json_encode($result, JSON_UNESCAPED_UNICODE);

// Log pentru debugging (opțional)
if (defined('UPLOAD_DEBUG') && UPLOAD_DEBUG) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'result' => $result
    ];
    file_put_contents('upload_log.txt', json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
}
?>
