<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
foreach($files as $file) {
    if($file->getExtension() == 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Match bg-gradient-to-[dir] from-[color]-[shade] [via-...] to-[color]-[shade]
        // Including opacity modifiers like /50
        $pattern = '/bg-gradient-to-[a-z]+\s+from-([a-z]+)-(\d+)(?:\/\d+)?(?:\s+via-[a-z]+-\d+(?:\/\d+)?)?\s+to-[a-z]+-\d+(?:\/\d+)?/';
        
        $newContent = preg_replace_callback($pattern, function($m) {
            // Replace with solid color based on the 'from' color
            return 'bg-' . $m[1] . '-' . $m[2];
        }, $content);
        
        // Also clean up some transparent gradients that didn't match the standard color-number format
        $newContent = preg_replace('/bg-gradient-to-[a-z]+\s+from-[a-z]+-\d+\/\d+\s+(?:via-[a-z]+-\d+\/\d+\s+)?to-transparent/', 'bg-slate-100', $newContent);
        
        // Remove hover gradients and replace with a standard solid hover
        $newContent = preg_replace('/hover:from-[a-z]+-\d+\s+hover:to-[a-z]+-\d+/', 'hover:brightness-110', $newContent);
        
        // Remove dark mode gradients
        $newContent = preg_replace('/dark:from-[a-z]+-\d+(?:\/\d+)?(?:\s+dark:via-[a-z]+-\d+(?:\/\d+)?)?\s+dark:to-[a-z]+-\d+(?:\/\d+)?/', '', $newContent);

        if ($newContent !== $content) {
            file_put_contents($file->getPathname(), $newContent);
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}
