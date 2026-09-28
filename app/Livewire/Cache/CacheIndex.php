<?php

namespace App\Livewire\Cache;

use App\Services\CacheService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CacheIndex extends Component
{
    public array $redis = [];
    public array $memcached = [];

    public int $redisPage = 0;
    public int $redisPerPage = 200;

    public string $successMessage = '';
    public string $errorMessage   = '';

    public function mount(CacheService $service): void
    {
        $this->refresh($service);
    }

    public function refresh(CacheService $service): void
    {
        $this->clearMessages();

        try {
            $this->redis     = $service->redisSnapshot();
            $this->memcached = $service->memcachedSnapshot();
            $this->redisPage = 0;
        } catch (\Throwable $e) {
            Log::error('CacheIndex: error refrescando caché', ['error' => $e->getMessage()]);
            $this->errorMessage = 'No se pudo obtener el estado del caché: ' . $e->getMessage();
        }
    }

    public function prevRedisPage(): void
    {
        $this->redisPage = max(0, $this->redisPage - 1);
    }

    public function nextRedisPage(): void
    {
        $keys = $this->redis['keys'] ?? [];

        if (($this->redisPage + 1) * $this->redisPerPage < count($keys)) {
            $this->redisPage++;
        }
    }

    public function deleteKey(string $key, CacheService $service): void
    {
        $this->clearMessages();

        try {
            if ($service->redisDeleteKey($key)) {
                $this->successMessage = "Clave «{$key}» eliminada.";
            } else {
                $this->errorMessage = "No se pudo eliminar la clave «{$key}».";
            }

            $this->redis = $service->redisSnapshot();

            $total = count($this->redis['keys'] ?? []);
            $lastPage = max(0, intdiv(max(0, $total - 1), $this->redisPerPage));
            $this->redisPage = min($this->redisPage, $lastPage);
        } catch (\Throwable $e) {
            Log::error('CacheIndex: deleteKey falló', ['error' => $e->getMessage()]);
            $this->errorMessage = $e->getMessage();
        }
    }

    public function flushRedis(CacheService $service): void
    {
        $this->clearMessages();

        try {
            if ($service->redisFlush()) {
                $this->redis = $service->redisSnapshot();
                $this->redisPage = 0;
                $this->successMessage = 'Redis vaciado por completo.';
            } else {
                $this->errorMessage = 'No se pudo vaciar Redis. Revisa los logs del servidor.';
            }
        } catch (\Throwable $e) {
            Log::error('CacheIndex: flushRedis falló', ['error' => $e->getMessage()]);
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render()
    {
        $redisKeys = $this->redis['keys'] ?? [];
        $totalKeys = count($redisKeys);

        $pageKeys   = array_slice($redisKeys, $this->redisPage * $this->redisPerPage, $this->redisPerPage);
        $hasPrev    = $this->redisPage > 0;
        $hasNext    = ($this->redisPage + 1) * $this->redisPerPage < $totalKeys;
        $redisStart = $totalKeys > 0 ? $this->redisPage * $this->redisPerPage + 1 : 0;
        $redisEnd   = min(($this->redisPage + 1) * $this->redisPerPage, $totalKeys);

        return view('livewire.cache.cache-index', compact(
            'pageKeys', 'totalKeys', 'hasPrev', 'hasNext', 'redisStart', 'redisEnd',
        ))->layout('layouts.app', [
            'title'      => 'Gestor de Caché',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Cache</strong>',
        ]);
    }
}