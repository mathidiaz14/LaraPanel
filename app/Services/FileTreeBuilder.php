<?php

namespace App\Services;

/**
 * FileTreeBuilder — Construye el árbol lateral del Administrador de Archivos.
 *
 * Lógica pura extraída del componente Livewire FileManager para poder
 * testearla y reutilizarla de forma aislada.
 */
class FileTreeBuilder
{
    /** Profundidad máxima de expansión automática del árbol. */
    public const MAX_DEPTH = 12;

    /** Carpeta reservada de la Papelera, oculta del árbol. */
    public const TRASH_FOLDER = '.larapanel-trash';

    /**
     * Construye un nivel (recursivo) del árbol de directorios.
     *
     * @return array<int, array{name:string, path:string, isExpanded:bool, children:array}>
     */
    public static function build(string $rootPath, array $expandedPaths, FileService $fileService, int $depth = 0): array
    {
        $tree = [];
        try {
            $items = $fileService->listDirectory($rootPath);
        } catch (\Throwable) {
            return [];
        }

        foreach ($items as $item) {
            if ($item['name'] === self::TRASH_FOLDER) {
                continue;
            }
            if ($item['is_dir']) {
                $itemPath = $rootPath === '' ? $item['name'] : $rootPath . '/' . $item['name'];
                $node = [
                    'name' => $item['name'],
                    'path' => $itemPath,
                    'isExpanded' => self::normalizeExpandedResolution($itemPath, $expandedPaths),
                    'children' => [],
                ];

                if ($node['isExpanded'] && $depth < self::MAX_DEPTH) {
                    $node['children'] = self::build($itemPath, $expandedPaths, $fileService, $depth + 1);
                }

                $tree[] = $node;
            }
        }

        usort($tree, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $tree;
    }

    /**
     * Normaliza un conjunto de rutas expandidas: limpia separadores iniciales/
     * finales, descarta entradas no-strings y elimina duplicados conservando
     * el orden de aparición.
     */
    public static function normalizeExpandedPaths(array $expandedPaths): array
    {
        $clean = [];
        foreach ($expandedPaths as $path) {
            if (! is_string($path)) {
                continue;
            }
            $path = trim($path, '/');
            $clean[$path] = true;
        }
        return array_keys($clean);
    }

    /**
     * Resuelve si una ruta concreta debe considerarse expandida contra el
     * conjunto de rutas expandidas (la raíz siempre se considera expandida).
     */
    public static function normalizeExpandedResolution(string $path, array $expandedPaths): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return true;
        }
        return in_array($path, self::normalizeExpandedPaths($expandedPaths), true);
    }
}