<?php

// Define the path to save the notes, ideally outside the webroot for security.
$save_path = '/var/www/_tmp';

// Disable caching.
header('Cache-Control: no-store');

// Get the 'note' parameter from the URL, defaulting to an empty string if not set.
$note = $_GET['note'] ?? '';

// If the note name is invalid or missing, generate a new name and redirect.
if (!$note || strlen($note) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/', $note)) {
    $generated_name = substr(str_shuffle('234579abcdefghjkmnpqrstwxyz'), 0, 5);
    header("Location: /$generated_name");
    exit;
}

// Define the full path for the note file.
$path = $save_path . '/' . $note;

// Handle saving the note when a POST request is made.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the content from the POST request.
    $text = $_POST['text'] ?? file_get_contents("php://input");

    if (!empty($text)) {
        // Save content to the file.
        file_put_contents($path, $text);
    } elseif (is_file($path)) {
        // If the text is empty, delete the file if it exists.
        unlink($path);
    }
    exit;
}

// Serve the raw content for curl, wget, or if 'raw' is set in the query.
if (isset($_GET['raw']) || strpos($_SERVER['HTTP_USER_AGENT'], 'curl') === 0 || strpos($_SERVER['HTTP_USER_AGENT'], 'Wget') === 0) {
    if (is_file($path)) {
        header('Content-type: text/plain');
        readfile($path);
    } else {
        header('HTTP/1.0 404 Not Found');
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($note); ?></title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<style>
body {
    margin: 0;
    background-color: #ebeef1;
}

.container {
    position: absolute;
    top: 20px;
    right: 20px;
    bottom: 20px;
    left: 20px;
}

#content {
    margin: 0;
    padding: 20px;
    overflow-y: auto;
    resize: none;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    border: 1px solid #ddd;
    outline: none;
}

#printable {
    display: none;
}

@media (prefers-color-scheme: dark) {
    body {
        background-color: #333b4d;
    }

    #content {
        background-color: #24262b;
        color: #ffffff;
        border-color: #495265;
    }
}

@media print {
    .container {
        display: none;
    }

    #printable {
        display: block;
        white-space: pre-wrap;
        word-break: break-word;
    }
}
</style>
</head>
<body>
<div class="container">
    <textarea id="content"><?php echo is_file($path) ? htmlspecialchars(file_get_contents($path), ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
</div>
<pre id="printable"></pre>
<script>
const textarea = document.getElementById('content');
const printable = document.getElementById('printable');
let content = textarea.value;

// Initialize the printable area.
printable.textContent = content;

async function uploadContent() {
    const newContent = textarea.value;
    if (newContent !== content) {
        content = newContent;
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: 'text=' + encodeURIComponent(newContent)
            });
            if (response.ok) {
                printable.textContent = newContent;
            }
        } catch {
            // Retry on failure after 1 second.
            setTimeout(uploadContent, 1000);
        }
    } else {
        setTimeout(uploadContent, 1000);
    }
}

textarea.focus();
uploadContent();
</script>
</body>
</html>
