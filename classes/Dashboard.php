<?php
declare(strict_types=1);

namespace Grav\Plugin\CwCookie;

use Grav\Common\Grav;

/**
 * Uebersicht fuer die Admin-Seite /admin/cw-cookie: was ist eingebunden,
 * was fehlt, wo droht Doppelmessung. PHP rechnet, Twig zeigt.
 */
final class Dashboard
{
    /**
     * @return array<string,mixed>
     */
    public static function build(): array
    {
        $k = Konfiguration::laden();
        $g = $k['google'];

        $hinweise = [];
        if ($g['gtm_ungueltig']) {
            $hinweise[] = 'Die GTM-Container-ID hat nicht die Form GTM-XXXXXXX und wird nicht eingebunden.';
        }
        if ($g['ga4_ungueltig']) {
            $hinweise[] = 'Die GA4-Mess-ID hat nicht die Form G-XXXXXXXXXX und wird nicht eingebunden.';
        }
        if ($g['ga4_doppelt']) {
            $hinweise[] = 'GTM und GA4 sind beide eingetragen. GA4 wird deshalb NICHT direkt eingebunden (sonst doppelte Messung) – richten Sie GA4 im Tag Manager ein.';
        }
        if (!$g['consent_mode'] && $g['gtm_id'] !== '') {
            $hinweise[] = 'Consent Mode ist aus: der Tag Manager lädt erst nach der Zustimmung.';
        }

        $dienste = 0;
        foreach ($k['kategorien'] as $kat) {
            $dienste += count($kat['dienste']);
        }

        return [
            'aktiv'      => (bool) Grav::instance()['config']->get('plugins.cw-cookie.enabled'),
            'version'    => $k['version'],
            'laufzeit'   => $k['laufzeit'],
            'gtm'        => $g['gtm_id'] !== '' ? $g['gtm_id'] . ' (' . ($g['gtm_laden'] === 'immer' ? 'Consent Mode' : 'nach Zustimmung') . ')' : '–',
            'ga4'        => $g['ga4_aktiv'] ? $g['ga4_id'] . ' (' . ($g['ga4_laden'] === 'immer' ? 'Consent Mode' : 'nach Zustimmung') . ')' : '–',
            'kategorien' => $k['kategorien'],
            'dienste'    => $dienste,
            'hinweise'   => $hinweise,
        ];
    }
}
