<?php
/**
 * Markdown Reader - Simple PHP application to read and display markdown files
 * 
 * URL Structure:
 * - www.domain.com/file-name-example -> displays file-name-example.md
 * - www.domain.com/folder-name/file-name -> displays folder-name/file-name.md
 */

// Get the requested path
$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Remove leading slash
$requestPath = ltrim($requestPath, '/');

// Remove trailing slash if present
$requestPath = rtrim($requestPath, '/');

// Remove index.php from path if present (for PHP built-in server)
$requestPath = preg_replace('#^index\.php/?#', '', $requestPath);

// If empty path, show a list of available files and folders
if (empty($requestPath)) {
    // List directories and markdown files in root
    $directories = [];
    $files = [];
    
    $iterator = new DirectoryIterator('.');
    
    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }
        
        // Skip hidden files and system files
        if (substr($item->getFilename(), 0, 1) === '.') {
            continue;
        }
        
        // Skip cgi-bin folder
        if ($item->isDir() && strtolower($item->getFilename()) === 'cgi-bin') {
            continue;
        }
        
        // Skip PHP files and other non-markdown files
        if ($item->isFile() && $item->getExtension() !== 'md') {
            continue;
        }
        
        $relativePath = str_replace(['\\', './'], ['/', ''], $item->getPathname());
        
        if ($item->isDir()) {
            $directories[] = [
                'url' => '/' . $relativePath,
                'name' => $item->getFilename(),
                'type' => 'folder'
            ];
        } elseif ($item->isFile() && $item->getExtension() === 'md') {
            $urlPath = str_replace('.md', '', $relativePath);
            $files[] = [
                'url' => '/' . $urlPath,
                'name' => $item->getFilename(),
                'path' => $relativePath,
                'type' => 'file'
            ];
        }
    }
    
    // Sort alphabetically
    usort($directories, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    usort($files, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Markdown Reader - Available Files</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                max-width: 900px;
                margin: 50px auto;
                padding: 20px;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 {
                color: #2c3e50;
                margin-bottom: 30px;
            }
            h2 {
                color: #34495e;
                font-size: 1.2em;
                margin-top: 30px;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 2px solid #ecf0f1;
            }
            ul {
                list-style: none;
                padding: 0;
            }
            li {
                padding: 12px;
                margin-bottom: 8px;
                background: #f8f9fa;
                border-radius: 4px;
                border-left: 3px solid #e67e22;
            }
            li.folder {
                border-left-color: #e67e22;
            }
            a {
                color: #e67e22;
                text-decoration: none;
                font-weight: 500;
            }
            li.folder a {
                color: #e67e22;
                text-decoration: none;
                font-weight: 500;
            }
            a:hover {
                text-decoration: underline;
            }
            li.folder a:hover {
                text-decoration: underline;
            }
            .path {
                color: #666;
                font-size: 0.85em;
                margin-left: 10px;
            }
            .empty {
                color: #999;
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Markdown Reader</h1>
            <?php if (empty($directories) && empty($files)): ?>
                <p class="empty">No markdown files or folders found. Create a <code>.md</code> file or folder to get started.</p>
            <?php else: ?>
                <?php if (!empty($directories)): ?>
                    <h2>Folders</h2>
                    <ul>
                        <?php foreach ($directories as $dir): ?>
                            <li class="folder">
                                <a href="<?php echo htmlspecialchars($dir['url']); ?>">
                                    📁 <?php echo htmlspecialchars($dir['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (!empty($files)): ?>
                    <h2>Files</h2>
                    <ul>
                        <?php foreach ($files as $file): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($file['url']); ?>">
                                    <?php echo htmlspecialchars($file['name']); ?>
                                </a>
                                <span class="path"><?php echo htmlspecialchars($file['path']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Security: Prevent directory traversal attacks
if (strpos($requestPath, '..') !== false || strpos($requestPath, '//') !== false) {
    http_response_code(403);
    die('Forbidden: Invalid path');
}

// Check if the requested path is a directory
$requestedPath = $requestPath;
$isDirectory = is_dir($requestedPath);

if ($isDirectory) {
    // Security: Ensure directory is within document root
    $realPath = realpath($requestedPath);
    $documentRoot = realpath(__DIR__);
    
    if ($realPath === false || strpos($realPath, $documentRoot) !== 0) {
        http_response_code(403);
        die('Forbidden: Invalid directory path');
    }
    
    // Show list of subdirectories and markdown files in this directory
    $directories = [];
    $files = [];
    
    try {
        $iterator = new DirectoryIterator($requestedPath);
    } catch (Exception $e) {
        http_response_code(403);
        die('Forbidden: Cannot access directory');
    }
    
    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }
        
        // Skip hidden files and system files
        if (substr($item->getFilename(), 0, 1) === '.') {
            continue;
        }
        
        // Skip cgi-bin folder
        if ($item->isDir() && strtolower($item->getFilename()) === 'cgi-bin') {
            continue;
        }
        
        if ($item->isDir()) {
            $dirName = $item->getFilename();
            $urlPath = $requestPath . '/' . $dirName;
            $directories[] = [
                'url' => '/' . $urlPath,
                'name' => $dirName,
                'type' => 'folder'
            ];
        } elseif ($item->isFile() && $item->getExtension() === 'md') {
            $fileName = $item->getFilename();
            $urlPath = $requestPath . '/' . str_replace('.md', '', $fileName);
            $files[] = [
                'url' => '/' . $urlPath,
                'name' => $fileName,
                'path' => $requestPath . '/' . $fileName,
                'type' => 'file'
            ];
        }
    }
    
    // Sort alphabetically
    usort($directories, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    usort($files, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    // Get folder name for display
    $folderName = basename($requestPath);
    $folderName = str_replace(['-', '_'], ' ', $folderName);
    $folderName = ucwords($folderName);
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title><?php echo htmlspecialchars($folderName); ?> - Markdown Reader</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                max-width: 1100px;
                margin: 30px auto;
                padding: 20px;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
            }
            .container {
                background: white;
                padding: 20px;
                border-radius: 8px;
            }
            h1 {
                color: #2c3e50;
                margin-bottom: 10px;
            }
            .breadcrumb {
                color: #666;
                margin-bottom: 30px;
                font-size: 0.9em;
            }
            h2 {
                color: #34495e;
                font-size: 1.2em;
                margin-top: 30px;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 2px solid #ecf0f1;
            }
            ul {
                list-style: none;
                padding: 0;
            }
            li {
                padding: 12px;
                margin-bottom: 8px;
                background: #f8f9fa;
                border-radius: 4px;
                border-left: 3px solid #e67e22;
            }
            li.folder {
                border-left-color: #e67e22;
            }
            a {
                color: #e67e22;
                text-decoration: none;
                font-weight: 500;
            }
            li.folder a {
                color: #e67e22;
                text-decoration: none;
                font-weight: 500;
            }
            a:hover {
                text-decoration: underline;
            }
            li.folder a:hover {
                text-decoration: underline;
            }
            .path {
                color: #666;
                font-size: 0.85em;
                margin-left: 10px;
            }
            .empty {
                color: #999;
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1><?php echo htmlspecialchars($folderName); ?></h1>
            <div class="breadcrumb">
                <a href="/">Home</a> / <?php echo htmlspecialchars($folderName); ?>
            </div>
            <?php if (empty($directories) && empty($files)): ?>
                <p class="empty">No markdown files or folders found in this directory.</p>
            <?php else: ?>
                <?php if (!empty($directories)): ?>
                    <h2>Folders</h2>
                    <ul>
                        <?php foreach ($directories as $dir): ?>
                            <li class="folder">
                                <a href="<?php echo htmlspecialchars($dir['url']); ?>">
                                    📁 <?php echo htmlspecialchars($dir['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (!empty($files)): ?>
                    <h2>Files</h2>
                    <ul>
                        <?php foreach ($files as $file): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($file['url']); ?>">
                                    <?php echo htmlspecialchars($file['name']); ?>
                                </a>
                                <span class="path"><?php echo htmlspecialchars($file['path']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Map URL path to markdown file
$markdownFile = $requestPath . '.md';

// Security: Ensure file is within the document root using realpath
$realPath = realpath($markdownFile);
$documentRoot = realpath(__DIR__);

// Check if file exists and is within document root
if ($realPath === false || strpos($realPath, $documentRoot) !== 0 || !file_exists($markdownFile)) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>404 - File Not Found</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                line-height: 1.6;
                color: #333;
            }
            h1 { color: #e74c3c; }
        </style>
    </head>
    <body>
        <h1>404 - File Not Found</h1>
        <p>The requested markdown file <code><?php echo htmlspecialchars($markdownFile); ?></code> could not be found.</p>
        <p><a href="/">← Back to home</a></p>
    </body>
    </html>
    <?php
    exit;
}

// Load Parsedown library (single file, no Composer needed)
require_once __DIR__ . '/Parsedown.php';

// Function to create URL-friendly slug from text
function createHeadingId($text) {
    // Remove HTML tags
    $text = strip_tags($text);
    // Convert to lowercase
    $text = strtolower($text);
    // Replace spaces and underscores with hyphens
    $text = preg_replace('/[\s_]+/', '-', $text);
    // Remove special characters, keep only alphanumeric and hyphens
    $text = preg_replace('/[^a-z0-9\-]/', '', $text);
    // Remove multiple consecutive hyphens
    $text = preg_replace('/-+/', '-', $text);
    // Remove leading/trailing hyphens
    $text = trim($text, '-');
    return $text;
}

// Parse markdown
$parsedown = new Parsedown();
$markdownContent = file_get_contents($markdownFile);
$htmlContent = $parsedown->text($markdownContent);

// Add IDs to all headings (h1-h6) and handle duplicates
$usedIds = [];
$htmlContent = preg_replace_callback(
    '/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i',
    function($matches) use (&$usedIds) {
        $level = $matches[1];
        $content = $matches[2];
        $baseId = createHeadingId($content);
        
        // Handle duplicate IDs
        $id = $baseId;
        $counter = 1;
        while (isset($usedIds[$id])) {
            $id = $baseId . '-' . $counter;
            $counter++;
        }
        $usedIds[$id] = true;
        
        return '<h' . $level . ' id="' . htmlspecialchars($id) . '">' . $content . '</h' . $level . '>';
    },
    $htmlContent
);

// Wrap tables in a scrollable container
$htmlContent = preg_replace(
    '/<table[^>]*>/i',
    '<div class="table-wrapper"><table',
    $htmlContent
);
$htmlContent = preg_replace(
    '/<\/table>/i',
    '</table></div>',
    $htmlContent
);

// Extract title from first h1 or use filename
$title = 'Markdown Reader';
if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $htmlContent, $matches)) {
    $title = strip_tags($matches[1]);
} else {
    // Use filename as title
    $title = basename($requestPath);
    $title = str_replace(['-', '_'], ' ', $title);
    $title = ucwords($title);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($title); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.7;
            color: #333;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        h1, h2, h3, h4, h5, h6 {
            scroll-margin-top: 20px;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            font-size: 2em;
            line-height: 1.2;
        }
        
        h2 {
            color: #34495e;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.5em;
            line-height: 1.2;
        }
        
        h3 {
            color: #555;
            margin-top: 25px;
            margin-bottom: 12px;
            font-size: 1.2em;
            line-height: 1.2;
        }
        
        p {
            margin-bottom: 15px;
        }
        
        ul, ol {
            margin-bottom: 15px;
            padding-left: 30px;
        }
        
        li {
            margin-bottom: 8px;
        }
        
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.9em;
            color: #e83e8c;
        }
        
        pre {
            background-color: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        pre code {
            background-color: transparent;
            color: inherit;
            padding: 0;
        }
        
        blockquote {
            border-left: 4px solid #3498db;
            padding-left: 20px;
            margin-left: 0;
            color: #666;
            font-style: italic;
        }
        
        a {
            color: #3498db;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .table-wrapper {
            overflow-x: auto;
            margin-bottom: 20px;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        hr {
            border: none;
            border-top: 2px solid #eee;
            margin: 30px 0;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
                overflow-x: hidden;
            }

            .table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                min-width: 600px;
            }
            
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php echo $htmlContent; ?>
    </div>
</body>
</html>
