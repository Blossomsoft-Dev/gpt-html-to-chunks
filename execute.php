<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Adjust these paths as needed
$conversations_file = "source/conversations.json";  // Path to your conversations.json
$output_dir = "dest/";              // Folder for split conversation HTML files
$css_relative_path = "style/style.css";                   // How your HTML files will reference the CSS file

// Ensure output directory exists
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0777, true);
}

// Read and decode the JSON
$json_str = file_get_contents($conversations_file);
$conversations_data = json_decode($json_str, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Failed to decode conversations.json: " . json_last_error_msg() . "\n");
}

// Helper function to replicate logic from getConversationMessages()
function getConversationMessages($conversation) {
    $messages = [];
    $mapping = $conversation['mapping'];
    $currentNode = $conversation['current_node'];

    while ($currentNode !== null) {
        $node = $mapping[$currentNode];
        if (isset($node['message']) && isset($node['message']['content'])) {
            $msg = $node['message'];
            $content = $msg['content'];

            if ($content['content_type'] === 'text' &&
                isset($content['parts'][0]) &&
                strlen($content['parts'][0]) > 0) {
                
                $author = $msg['author']['role'];
                $metadata = $msg['metadata'];

                // Match the logic from your JS:
                //  - "assistant" -> "ChatGPT"
                //  - "system" (unless is_user_system_message) -> skip
                //  - "system" + is_user_system_message -> "Custom user info"
                if ($author === "assistant") {
                    $author = "ChatGPT";
                } elseif ($author === "system" && !empty($metadata['is_user_system_message'])) {
                    $author = "Custom user info";
                }

                // Exclude pure system messages
                if ($author !== "system" || !empty($metadata['is_user_system_message'])) {
                    $messages[] = [
                        'author' => $author,
                        'text'   => $content['parts'][0]
                    ];
                }
            }
        }
        $currentNode = $node['parent'];
    }
    // Reverse to restore chronological order
    return array_reverse($messages);
}

// Generate one file per conversation
foreach ($conversations_data as $conversation) {
    $title = $conversation['title'] ?? "Untitled Conversation";
    $messages = getConversationMessages($conversation);

    // Build the HTML
    $html  = "<html>\n<head>\n";
    $html .= "  <meta charset='UTF-8'>\n";
    $html .= "  <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n";
    $html .= "  <link rel='stylesheet' type='text/css' href='{$css_relative_path}'>\n";
    $html .= "</head>\n<body>\n";
    // Replicate structure with #root and .conversation
    $html .= "  <div id='root'>\n";
    $html .= "    <div class='conversation'>\n";
    $html .= "      <h4>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h4>\n";

    foreach ($messages as $msg) {
        $author = htmlspecialchars($msg['author'], ENT_QUOTES, 'UTF-8');
        // Convert newlines to <br> for HTML; also escape HTML special chars
        $text = nl2br(htmlspecialchars($msg['text'], ENT_QUOTES, 'UTF-8'));

        $html .= "      <pre class='message'>"
               . "<div class='author'>{$author}</div>"
               . "<div>{$text}</div></pre>\n";
    }

    $html .= "    </div>\n";
    $html .= "  </div>\n";
    $html .= "</body>\n</html>";

    // Sanitize the title for a filename
    $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $title) . ".html";
    $filepath = $output_dir . DIRECTORY_SEPARATOR . $filename;

    // Save the file
    if (file_put_contents($filepath, $html) === false) {
        echo "Failed to save: {$filepath}\n";
    } else {
        echo "Saved: {$filepath}\n";
    }
}