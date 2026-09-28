<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Domain;
use App\Notifications\CertificateExpiringNotification;
use App\Services\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CertificateMonitorService
{
    /**
     * List active domains grouped by certificate health.
     *
     * @return array{expired: Collection, expiring_soon: Collection, expiring_later: Collection, no_ssl: Collection}
     */
    public function scanExpiring(int $days = 14): array
    {
        $domains = Domain::with('sslCertificate')
            ->where('is_active', true)
            ->get();

        $result = [
            'expired'       => collect(),
            'expiring_soon' => collect(),
            'expiring_later'=> collect(),
            'no_ssl'        => collect(),
        ];

        foreach ($domains as $domain) {
            $expiresAt = $domain->ssl_expires_at;

            if (! $expiresAt) {
                $result['no_ssl']->push($domain);
                continue;
            }

            if ($expiresAt->isPast()) {
                $result['expired']->push($domain);
            } elseif ($expiresAt->lte(now()->addDays($days))) {
                $result['expiring_soon']->push($domain);
            } else {
                $result['expiring_later']->push($domain);
            }
        }

        // Soonest expiry first within each group.
        foreach (['expired', 'expiring_soon', 'expiring_later'] as $group) {
            $result[$group] = $result[$group]
                ->sortBy(fn (Domain $d) => $d->ssl_expires_at?->timestamp)
                ->values();
        }

        return $result;
    }

    /**
     * Alert admins about expiring / expired certificates, avoiding duplicate
     * alerts within the same expiry window. Returns the number of alerts sent.
     */
    public function sendAlerts(int $days = 14): int
    {
        $scan = $this->scanExpiring($days);
        $targets = $scan['expired']->merge($scan['expiring_soon']);

        if (! app()->isProduction()) {
            Log::info('CertificateMonitorService: modo dev, alertas SSL simuladas.', [
                'candidates' => $targets->count(),
            ]);
            AuditLog::record('ssl.certificate_scan', 'Simulación en entorno de desarrollo', [
                'days'       => $days,
                'candidates' => $targets->count(),
            ]);
            return 0;
        }

        $alerted = 0;

        foreach ($targets as $domain) {
            $cert = $domain->sslCertificate;
            if (! $cert || ! $cert->expires_at) {
                continue;
            }

            if ($this->wasAlertedForWindow($domain, $cert->expires_at)) {
                continue;
            }

            $daysLeft = (int) now()->diffInDays($cert->expires_at, absolute: false);
            $expired = $daysLeft < 0;

            Notifier::send(new CertificateExpiringNotification(
                $domain,
                max($daysLeft, 0),
                $expired,
            ));

            $domain->update(['last_ssl_alerted_at' => now()]);

            AuditLog::record(
                'ssl.certificate_alert',
                $domain->name,
                [
                    'expires_at' => $cert->expires_at->toDateTimeString(),
                    'days_left'  => $daysLeft,
                    'severity'   => $expired ? 'expired' : 'expiring_soon',
                ],
                $expired ? 'warning' : 'info',
            );

            $alerted++;
        }

        AuditLog::record('ssl.certificate_scan', 'Escaneo SSL completado', [
            'days'   => $days,
            'alerted'=> $alerted,
        ]);

        return $alerted;
    }

    /**
     * Only alert once per expiry window: after the certificate has been
     * flagged, subsequent scans skip it as long as it still carries the same
     * (soon-to-expire) certificate. Renewing the certificate replaces the
     * expires_at value and opens a fresh window; an already-expired cert keeps
     * being re-flagged since it is a persisting critical issue.
     */
    public function wasAlertedForWindow(Domain $domain, mixed $expiresAt): bool
    {
        $lastAlertedAt = $domain->last_ssl_alerted_at;

        if (! $lastAlertedAt) {
            return false;
        }

        return $expiresAt->greaterThanOrEqualTo($lastAlertedAt);
    }
}