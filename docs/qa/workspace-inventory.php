<?php

use Illuminate\Http\Request;
use Tests\TestCase;

// Source/route inventory only; boots with the isolated test configuration.
require __DIR__.'/../../vendor/autoload.php';
$runner = new class('audit') extends TestCase {};
$app = $runner->createApplication();
$root = dirname(__DIR__, 2);
$routes = [];
$issues = [];
$families = [];
$controllers = [];
foreach ($app['router']->getRoutes() as $route) {
    $action = $route->getActionName();
    $source = null;
    $line = null;
    try {
        if ($action !== 'Closure' && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
            $ref = new ReflectionMethod($class, $method);
            $source = str_replace('\\', '/', substr($ref->getFileName(), strlen($root) + 1));
            $line = $ref->getStartLine();
            $controllers[$source] = true;
        }
    } catch (Throwable $e) {
        $issues[] = ['uri' => $route->uri(), 'action' => $action, 'error' => $e->getMessage()];
    }
    $family = explode('/', $route->uri())[0];
    $families[$family] = ($families[$family] ?? 0) + 1;
    $routes[] = ['methods' => $route->methods(), 'uri' => $route->uri(), 'name' => $route->getName(), 'action' => $action, 'middleware' => $route->gatherMiddleware(), 'file' => $source, 'line' => $line];
}
$links = [];
$pages = [];
$viewFiles = [$root.'/resources/js/navigation/workspaces.js'];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js', FilesystemIterator::SKIP_DOTS)) as $viewFile) {
    if ($viewFile->getExtension() === 'vue') {
        $viewFiles[] = $viewFile->getPathname();
    }
}
foreach ($viewFiles as $file) {
    $pattern = str_ends_with($file, 'workspaces.js') ? '~[\'"](/[^\'"\s]+)[\'"]~' : '~href=[\'"](/[^\'"<>`{}\s]*)[\'"]~';
    preg_match_all($pattern, file_get_contents($file), $matches);
    foreach (array_unique($matches[1]) as $path) {
        if (str_contains($path, '$')) {
            continue;
        }
        try {
            $matched = $app['router']->getRoutes()->match(Request::create($path));
            $valid = true;
        } catch (Throwable $e) {
            $valid = false;
        }
        $links[] = ['file' => str_replace('\\', '/', substr($file, strlen($root) + 1)), 'path' => $path, 'get_route_exists' => $valid];
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http/Controllers', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    preg_match_all('/Inertia::render\(\s*[\'\"]([^\'\"]+)[\'\"]/', file_get_contents($file->getPathname()), $matches);
    foreach ($matches[1] as $page) {
        if (! file_exists($root.'/resources/js/Pages/'.$page.'.vue')) {
            $pages[] = ['controller' => str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)), 'missing_page' => $page];
        }
    }
}
$result = ['project' => 'JK Michaels Professional Mastery Platform', 'base_commit' => 'a1eba2381311ae98a2d7bf2e44b45f1c45caff1f', 'generated_at' => gmdate('c'), 'route_count' => count($routes), 'routed_controller_files' => count($controllers), 'families' => $families, 'unresolved_actions' => $issues, 'missing_inertia_pages' => $pages, 'layout_links' => $links, 'routes' => $routes];
file_put_contents(__DIR__.'/workspace-inventory-2026-10-05.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode(['routes' => count($routes), 'controllers' => count($controllers), 'unresolved_actions' => $issues, 'missing_pages' => $pages, 'broken_layout_links' => array_values(array_filter($links, fn ($link) => ! $link['get_route_exists']))], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
