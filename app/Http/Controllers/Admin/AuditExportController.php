<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date'],
            'severity' => ['nullable', 'string', 'in:info,warning,error,critical'],
            'format'   => ['nullable', 'string', 'in:csv,json'],
            'limit'    => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $format = $data['format'] ?? 'csv';
        $limit = min((int) ($data['limit'] ?? 5000), 10000);

        AuditLog::record('audit.exported', 'audit_logs', [
            'format' => $format,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'severity' => $data['severity'] ?? null,
            'limit' => $limit,
        ], 'warning');

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when(!empty($data['from']), fn ($query) => $query->whereDate('created_at', '>=', $data['from']))
            ->when(!empty($data['to']), fn ($query) => $query->whereDate('created_at', '<=', $data['to']))
            ->when(!empty($data['severity']), fn ($query) => $query->where('severity', $data['severity']))
            ->latest()
            ->limit($limit)
            ->cursor();

        $fileName = 'larapanel-audit-' . now()->format('Y-m-d-His') . '.' . $format;

        if ($format === 'json') {
            return response()->streamDownload(function () use ($logs) {
                echo '[';
                $first = true;
                foreach ($logs as $log) {
                    echo $first ? '' : ',';
                    $first = false;
                    echo json_encode([
                        'id' => $log->id,
                        'created_at' => optional($log->created_at)->toISOString(),
                        'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : null,
                        'action' => $log->action,
                        'subject' => $log->subject,
                        'severity' => $log->severity,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'meta' => $log->meta,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                echo ']';
            }, $fileName, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id', 'created_at', 'user_id', 'user_name', 'user_email',
                'action', 'subject', 'severity', 'ip_address', 'user_agent', 'meta',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    optional($log->created_at)->format('Y-m-d H:i:s'),
                    $log->user_id,
                    $this->csvValue($log->user?->name),
                    $this->csvValue($log->user?->email),
                    $this->csvValue($log->action),
                    $this->csvValue($log->subject),
                    $log->severity,
                    $this->csvValue($log->ip_address),
                    $this->csvValue($log->user_agent),
                    $this->csvValue(json_encode($log->meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) $value;

        if (preg_match('/^\s*[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
