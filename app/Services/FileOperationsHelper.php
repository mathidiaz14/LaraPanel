<?php

namespace App\Services;

/**
 * FileOperationsHelper — Operaciones puras sobre rutas y nombres de archivo.
 *
 * Lógica extraída del componente Livewire FileManager para poder testearla
 * de forma aislada y reutilizarla desde varios servicios.
 */
class FileOperationsHelper
{
    /**
     * Normaliza un nombre de archivo/carpeta: recorta espacios (o los
     * caracteres indicados) y resuelve los separadores a "/". Se devuelve
     * la parte final de la ruta (basename) para descartar rutas completas.
     */
    public static function normalizeName(string $name, ?string $trimChars = null): string
    {
        $trimmed = $trimChars === null ? trim($name) : trim($name, $trimChars);

        return basename(FileService::normalizeSeparators($trimmed));
    }

    /**
     * Construye la lista de rutas absolutas del portapapeles para los nombres
     * seleccionados en la carpeta actual. Se omiten entradas vacías, los
     * pseudo-directorios "." y ".." y la carpeta reservada de la Papelera.
     *
     * @param string[] $names Rutas relativas (o nombres) dentro de la carpeta.
     * @return string[]
     */
    public static function buildClipboardPaths(array $names, string $currentPath): array
    {
        $paths = [];
        foreach ($names as $name) {
            $name = (string) $name;
            if ($name === '' || $name === '.' || $name === '..' || $name === self::TRASH_FOLDER) {
                continue;
            }
            $paths[] = ltrim($currentPath . '/' . $name, '/');
        }

        return array_values(array_unique($paths));
    }

    /** Carpeta reservada de la Papelera, alineada con FileTreeBuilder. */
    private const TRASH_FOLDER = '.larapanel-trash';

    /**
     * Calcula un nombre de destino único: si el destino ya existe se añade
     * un sufijo " (N)" antes de la extensión. Se comprueba siempre contra el
     * sistema de archivos real (incluida la Papelera).
     *
     * @throws \RuntimeException Si no se encuentra un nombre libre tras 9999 iteraciones.
     */
    public static function uniqueDestinationName(FileService $fileService, string $destParent, string $name): string
    {
        $destParent = trim($destParent, '/');
        $prefix = $destParent === '' ? '' : $destParent . '/';

        if (! file_exists($fileService->resolvePath($prefix . $name))) {
            return $name;
        }

        $dotParts = explode('.', $name);
        $ext = count($dotParts) > 1 && $dotParts[count($dotParts) - 1] !== '' ? '.' . array_pop($dotParts) : '';
        $base = implode('.', $dotParts);
        if ($base === '') {
            $base = $name;
            $ext = '';
        }

        for ($i = 2; $i <= 9999; $i++) {
            $candidate = $base . ' (' . $i . ')' . $ext;
            if (! file_exists($fileService->resolvePath($prefix . $candidate))) {
                return $candidate;
            }
        }

        throw new \RuntimeException("No se pudo generar un nombre libre para '{$name}' en el destino.");
    }

    /**
     * Determina si mover/copiar el destino dentro del propio origen crearía
     * un ciclo (directorio dentro de sí mismo o de un subdirectorio suyo).
     */
    public static function wouldNestDestination(string $destPath, string $sourcePath): bool
    {
        $dest = rtrim($destPath, '/');
        $source = rtrim($sourcePath, '/');

        return $dest === $source || str_starts_with($dest . '/', $source . '/');
    }
}