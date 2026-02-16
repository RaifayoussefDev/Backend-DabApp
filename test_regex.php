<?php

function processContentImages($content)
{
    // Pattern matches: src="data:image/[type];base64,[data]" or src='...'
    $pattern = '/src=["\'](data:image\/(\w+);base64,([^"\']+))["\']/';

    // Mock handleImageUpload
    $handleImageUpload = function ($fullSrc) {
        return 'https://example.com/uploaded_image.jpg';
    };

    return preg_replace_callback($pattern, function ($matches) use ($handleImageUpload) {
        $fullSrc = $matches[1];
        $url = $handleImageUpload($fullSrc);
        return 'src="' . $url . '"';
    }, $content);
}

$testCases = [
    'Double quotes' => '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=">',
    'Single quotes' => "<img src='data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQcL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQcL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwH7/9k='>",
    'Mixed content' => '<p>Test</p><img src="data:image/png;base64,abc"><br><img src=\'data:image/jpg;base64,def\'>',
    'Already URL' => '<img src="https://example.com/image.jpg">',
];

$outputString = "Running tests...\n";

foreach ($testCases as $name => $input) {
    $outputString .= "Test: $name\n";
    $processed = processContentImages($input);
    $outputString .= "Input: " . substr($input, 0, 50) . "...\n";
    $outputString .= "Output: " . $processed . "\n";
    $outputString .= "----------------------------------------\n";
}

file_put_contents('test_results_final.txt', $outputString);
