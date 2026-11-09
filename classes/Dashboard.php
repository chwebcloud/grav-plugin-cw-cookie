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
            $hinweise[] = self::text('HINWEIS_GTM_UNGUELTIG');
        }
        if ($g['ga4_ungueltig']) {
            $hinweise[] = self::text('HINWEIS_GA4_UNGUELTIG');
        }
        if ($g['ga4_doppelt']) {
            $hinweise[] = self::text('HINWEIS_GA4_DOPPELT');
        }
        if (!$g['consent_mode'] && $g['gtm_id'] !== '') {
            $hinweise[] = self::text('HINWEIS_CONSENT_MODE_AUS');
        }

        $dienste = 0;
        foreach ($k['kategorien'] as $kat) {
            $dienste += count($kat['dienste']);
        }

        return [
            'aktiv'      => (bool) Grav::instance()['config']->get('plugins.cw-cookie.enabled'),
            'version'    => $k['version'],
            'laufzeit'   => $k['laufzeit'],
            'gtm'        => $g['gtm_id'] !== '' ? $g['gtm_id'] . ' (' . ($g['gtm_laden'] === 'immer' ? 'Consent Mode' : self::text('NACH_ZUSTIMMUNG')) . ')' : '–',
            'ga4'        => $g['ga4_aktiv'] ? $g['ga4_id'] . ' (' . ($g['ga4_laden'] === 'immer' ? 'Consent Mode' : self::text('NACH_ZUSTIMMUNG')) . ')' : '–',
            'kategorien' => $k['kategorien'],
            'dienste'    => $dienste,
            'hinweise'   => $hinweise,
        ];
    }

    /**
     * Text in der Sprache der Website. Ohne languages.supported ist keine
     * Sprache aktiv und Grav faellt auf Englisch zurueck -- dann gilt die
     * Standardsprache der Website, zuletzt Deutsch.
     */
    private static function text(string $schluessel): string
    {
        $grav = Grav::instance();
        $sprache = $grav['language'];
        $code = $sprache->getActive()
            ?: $grav['config']->get('system.languages.default_lang')
            ?: $grav['config']->get('site.default_lang')
            ?: 'de';

        return (string) $sprache->translate('PLUGIN_CW_COOKIE.' . $schluessel, array_unique([$code, 'de']));
    }
}
