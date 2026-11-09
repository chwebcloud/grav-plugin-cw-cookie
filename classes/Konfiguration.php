<?php
declare(strict_types=1);

namespace Grav\Plugin\CwCookie;

use Grav\Common\Grav;

/**
 * Bereitet die Konfiguration plugins.cw-cookie einmal je Anfrage auf.
 *
 * Alles, was aus dem Admin kommt, wird hier geprueft und in eine feste Form
 * gebracht: Kategorien mit gueltigem Schluessel, Dienste mit ihrer Kategorie,
 * Google-IDs nur im erwarteten Muster, eine Leitfarbe nur als Hex-Wert. Die
 * Ausgabe (Ausgabe.php, Twig, JavaScript) arbeitet nur noch mit diesem Stand
 * und muss nichts mehr pruefen.
 *
 * Leere Texte fallen auf die Uebersetzung PLUGIN_CW_COOKIE.* in der Sprache
 * der Seite zurueck -- so bleibt eine mehrsprachige Seite mehrsprachig, bis
 * jemand einen Text im Admin bewusst ueberschreibt.
 */
final class Konfiguration
{
    public const COOKIE = 'cw_consent';

    /** Alle Signale des Google Consent Mode v2. */
    public const SIGNALE = [
        'ad_storage',
        'ad_user_data',
        'ad_personalization',
        'analytics_storage',
        'functionality_storage',
        'personalization_storage',
        'security_storage',
    ];

    /** Ohne Zustimmung erlaubt: technisch notwendig, keine Auswertung. */
    private const IMMER_ERLAUBT = ['functionality_storage', 'security_storage'];

    private const GOOGLE_DATENSCHUTZ = 'https://policies.google.com/privacy';
    private const GOOGLE_ANBIETER = 'Google Ireland Limited';

    /** Cookies, die Google-Dienste setzen; beim Widerruf geloescht. */
    private const GOOGLE_COOKIES_STATISTIK = ['_ga', '_ga_*', '_gid', '_gat*'];
    private const GOOGLE_COOKIES_MARKETING = ['_gcl_*', '_gac_*'];

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string,mixed>
     */
    public static function laden(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $grav = Grav::instance();
        $roh  = (array) $grav['config']->get('plugins.cw-cookie', []);

        $kategorien = self::kategorien((array) ($roh['kategorien'] ?? []));
        $keys       = array_column($kategorien, 'key');
        $pflicht    = array_values(array_column(array_filter($kategorien, static fn ($k) => $k['notwendig']), 'key'));
        $google     = self::google((array) ($roh['google'] ?? []), $keys);
        $dienste    = self::dienste((array) ($roh['dienste'] ?? []), $keys);

        // Google-Dienste erscheinen in der Dienstliste des Dialogs wie jeder
        // andere Dienst -- die Besucherin soll sehen, wem sie zustimmt.
        $googleDienste = [];
        if ($google['gtm_id'] !== '') {
            $googleDienste[] = self::googleDienst(
                'gtm',
                'Google Tag Manager',
                $google['gtm_kategorie'],
                self::t('DIENST_GTM'),
                $google['gtm_kategorie'] === 'marketing' ? self::GOOGLE_COOKIES_MARKETING : self::GOOGLE_COOKIES_STATISTIK
            );
        }
        if ($google['ga4_aktiv']) {
            $googleDienste[] = self::googleDienst(
                'ga4',
                'Google Analytics 4',
                $google['ga4_kategorie'],
                self::t('DIENST_GA4'),
                self::GOOGLE_COOKIES_STATISTIK
            );
        }
        $alleDienste = array_merge($googleDienste, $dienste);

        foreach ($kategorien as &$kat) {
            $kat['dienste'] = array_values(array_filter($alleDienste, static fn ($d) => $d['kategorie'] === $kat['key']));
        }
        unset($kat);

        // Signale je Kategorie und die Liste der zu loeschenden Cookies.
        $signale  = [];
        $loeschen = [];
        foreach ($kategorien as $kat) {
            $signale[$kat['key']] = $kat['google'];
            $namen = [];
            foreach ($kat['dienste'] as $dienst) {
                $namen = array_merge($namen, $dienst['cookies']);
            }
            if ($namen) {
                $loeschen[$kat['key']] = array_values(array_unique($namen));
            }
        }

        $darstellung = self::darstellung((array) ($roh['darstellung'] ?? []));

        self::$cache = [
            'version'     => self::version($roh['version'] ?? 1),
            'laufzeit'    => max(1, min(730, (int) ($roh['laufzeit_tage'] ?? 180))),
            'datenschutz' => self::url((string) ($roh['datenschutz_url'] ?? '/datenschutz')),
            'impressum'   => self::url((string) ($roh['impressum_url'] ?? '/impressum')),
            'ausnahmen'   => self::ausnahmen($roh['ausnahmen'] ?? ['/admin', '/api']),
            'texte'       => self::texte((array) ($roh['texte'] ?? [])),
            'kategorien'  => $kategorien,
            'pflicht'     => $pflicht,
            'dienste'     => $dienste,
            'signale'     => $signale,
            'loeschen'    => $loeschen,
            'google'      => $google,
            'darstellung' => $darstellung,
        ];

        return self::$cache;
    }

    /** Fuer Tests und nach dem Speichern im Admin. */
    public static function vergessen(): void
    {
        self::$cache = null;
    }

    /**
     * Zustand des Consent Mode vor jeder Zustimmung: alles verweigert, was
     * auswertet oder personalisiert; technisch Notwendiges erlaubt.
     *
     * @return array<string,string|int>
     */
    public static function standardZustimmung(array $k): array
    {
        $out = [];
        foreach (self::SIGNALE as $signal) {
            $out[$signal] = in_array($signal, self::IMMER_ERLAUBT, true) ? 'granted' : 'denied';
        }
        $out['wait_for_update'] = (int) $k['google']['wait_for_update'];

        return $out;
    }

    /**
     * Uebersetzung aus languages/<sprache>.yaml.
     */
    public static function t(string $schluessel, array $werte = []): string
    {
        $key = 'PLUGIN_CW_COOKIE.' . $schluessel;
        $txt = (string) Grav::instance()['language']->translate([$key]);
        if ($txt === $key) {
            $txt = '';
        }
        foreach ($werte as $name => $wert) {
            $txt = str_replace('{' . $name . '}', (string) $wert, $txt);
        }

        return $txt;
    }

    // --------------------------------------------------------------------

    /**
     * @param array<int,mixed> $liste
     * @return list<array<string,mixed>>
     */
    private static function kategorien(array $liste): array
    {
        $out  = [];
        $seen = [];
        foreach ($liste as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }
            $key = self::schluessel((string) ($eintrag['key'] ?? ''));
            if ($key === '' || isset($seen[$key]) || array_key_exists('aktiv', $eintrag) && !$eintrag['aktiv']) {
                continue;
            }
            $seen[$key] = true;
            $gross      = strtoupper(str_replace('-', '_', $key));
            $titel      = trim((string) ($eintrag['titel'] ?? ''));
            $text       = trim((string) ($eintrag['beschreibung'] ?? ''));
            $google     = array_values(array_intersect(self::SIGNALE, array_map('strval', (array) ($eintrag['google'] ?? []))));

            $out[] = [
                'key'          => $key,
                'titel'        => $titel !== '' ? $titel : (self::t('KAT_' . $gross . '_TITEL') ?: ucfirst($key)),
                'beschreibung' => $text !== '' ? $text : self::t('KAT_' . $gross . '_TEXT'),
                'notwendig'    => (bool) ($eintrag['notwendig'] ?? false),
                'google'       => $google,
                'dienste'      => [],
            ];
        }

        // Notwendige Kategorien stehen im Dialog immer zuoberst.
        usort($out, static fn ($a, $b) => (int) $b['notwendig'] <=> (int) $a['notwendig']);

        return $out;
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    private static function google(array $g, array $keys): array
    {
        $gtm = strtoupper(trim((string) ($g['gtm_id'] ?? '')));
        $ga4 = strtoupper(trim((string) ($g['ga4_id'] ?? '')));
        $consentMode = (bool) ($g['consent_mode'] ?? true);

        $gtmGueltig = (bool) preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm);
        $ga4Gueltig = (bool) preg_match('/^G-[A-Z0-9]{4,16}$/', $ga4);

        $kat = static function ($wert) use ($keys): string {
            $wert = (string) $wert;
            return in_array($wert, $keys, true) ? $wert : (in_array('statistik', $keys, true) ? 'statistik' : (string) ($keys[0] ?? 'statistik'));
        };

        // Ohne Consent Mode gibt es kein "vorsorglich verweigert" -- dann
        // darf ein Google-Tag erst nach der Zustimmung laden.
        $gtmLaden = ($g['gtm_laden'] ?? 'immer') === 'zustimmung' || !$consentMode ? 'zustimmung' : 'immer';
        $ga4Laden = ($g['ga4_laden'] ?? 'zustimmung') === 'immer' && $consentMode ? 'immer' : 'zustimmung';

        return [
            'consent_mode'       => $consentMode,
            'wait_for_update'    => max(0, min(10000, (int) ($g['wait_for_update'] ?? 500))),
            'ads_data_redaction' => (bool) ($g['ads_data_redaction'] ?? true),
            'url_passthrough'    => (bool) ($g['url_passthrough'] ?? false),
            'gtm_id'             => $gtmGueltig ? $gtm : '',
            'gtm_ungueltig'      => $gtm !== '' && !$gtmGueltig,
            'gtm_laden'          => $gtmLaden,
            'gtm_kategorie'      => $kat($g['gtm_kategorie'] ?? 'statistik'),
            'gtm_noscript'       => (bool) ($g['gtm_noscript'] ?? true) && $gtmLaden === 'immer',
            'ga4_id'             => $ga4Gueltig ? $ga4 : '',
            'ga4_ungueltig'      => $ga4 !== '' && !$ga4Gueltig,
            // Ist GTM gesetzt, laeuft GA4 dort -- eine zweite, direkte
            // Einbindung zaehlte jeden Seitenaufruf doppelt.
            'ga4_aktiv'          => $ga4Gueltig && !$gtmGueltig,
            'ga4_doppelt'        => $ga4Gueltig && $gtmGueltig,
            'ga4_laden'          => $ga4Laden,
            'ga4_kategorie'      => $kat($g['ga4_kategorie'] ?? 'statistik'),
        ];
    }

    /**
     * @param array<int,mixed> $liste
     * @param list<string> $keys
     * @return list<array<string,mixed>>
     */
    private static function dienste(array $liste, array $keys): array
    {
        $out = [];
        $nr  = 0;
        foreach ($liste as $eintrag) {
            if (!is_array($eintrag) || array_key_exists('aktiv', $eintrag) && !$eintrag['aktiv']) {
                continue;
            }
            $name = trim((string) ($eintrag['name'] ?? ''));
            $kat  = (string) ($eintrag['kategorie'] ?? '');
            if ($name === '' || !in_array($kat, $keys, true)) {
                continue;
            }
            $nr++;
            $url = trim((string) ($eintrag['script_url'] ?? ''));
            if ($url !== '' && !preg_match('#^(https?:)?//#i', $url) && !str_starts_with($url, '/')) {
                $url = '';
            }
            $out[] = [
                'id'           => 'dienst-' . $nr,
                'name'         => $name,
                'kategorie'    => $kat,
                'anbieter'     => trim((string) ($eintrag['anbieter'] ?? '')),
                'beschreibung' => trim((string) ($eintrag['beschreibung'] ?? '')),
                'datenschutz'  => self::externeUrl((string) ($eintrag['datenschutz_url'] ?? '')),
                'cookies'      => self::cookieNamen((string) ($eintrag['cookies'] ?? '')),
                'script_url'   => $url,
                'code'         => trim((string) ($eintrag['inline_code'] ?? '')),
                'position'     => ($eintrag['position'] ?? 'head') === 'body' ? 'body' : 'head',
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $cookies
     * @return array<string,mixed>
     */
    private static function googleDienst(string $id, string $name, string $kat, string $text, array $cookies): array
    {
        return [
            'id'           => $id,
            'name'         => $name,
            'kategorie'    => $kat,
            'anbieter'     => self::GOOGLE_ANBIETER,
            'beschreibung' => $text,
            'datenschutz'  => self::GOOGLE_DATENSCHUTZ,
            'cookies'      => $cookies,
            'script_url'   => '',
            'code'         => '',
            'position'     => 'head',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function darstellung(array $d): array
    {
        $farbe = trim((string) ($d['leitfarbe'] ?? ''));
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $farbe)) {
            $farbe = '#434fbf';
        }
        $position = (string) ($d['position'] ?? 'leiste');
        if (!in_array($position, ['leiste', 'box-links', 'box-rechts'], true)) {
            $position = 'leiste';
        }

        return [
            'leitfarbe'       => $farbe,
            'textfarbe'       => self::kontrastfarbe($farbe),
            'position'        => $position,
            'farbschema'      => ($d['farbschema'] ?? 'hell') === 'auto' ? 'auto' : 'hell',
            'radius'          => max(0, min(32, (int) ($d['radius'] ?? 10))),
            'schwebend'       => (bool) ($d['schwebender_knopf'] ?? false),
            'schwebend_seite' => ($d['schwebender_knopf_seite'] ?? 'links') === 'rechts' ? 'rechts' : 'links',
            'css_laden'       => (bool) ($d['css_laden'] ?? true),
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function texte(array $t): array
    {
        $felder = [
            'titel'        => 'TITEL',
            'text'         => 'TEXT',
            'alle'         => 'ALLE',
            'notwendige'   => 'NOTWENDIGE',
            'einstellungen' => 'EINSTELLUNGEN',
            'dialog_titel' => 'DIALOG_TITEL',
            'dialog_text'  => 'DIALOG_TEXT',
            'speichern'    => 'SPEICHERN',
            'link'         => 'LINK',
        ];
        $out = [];
        foreach ($felder as $feld => $key) {
            $wert        = trim((string) ($t[$feld] ?? ''));
            $out[$feld]  = $wert !== '' ? $wert : self::t($key);
        }
        foreach (['SCHLIESSEN', 'IMMER_AKTIV', 'DIENSTE_ZEIGEN', 'ANBIETER', 'COOKIES', 'DATENSCHUTZ', 'IMPRESSUM',
                  'DATENSCHUTZ_ANBIETER', 'PLATZHALTER_TITEL', 'PLATZHALTER_TEXT', 'PLATZHALTER_LADEN',
                  'PLATZHALTER_IMMER', 'SCHWEBEND'] as $key) {
            $out[strtolower($key)] = self::t($key);
        }

        return $out;
    }

    private static function version($wert): string
    {
        $v = preg_replace('/[^0-9A-Za-z._-]/', '', (string) $wert);

        return $v !== '' ? $v : '1';
    }

    private static function schluessel(string $wert): string
    {
        return trim((string) preg_replace('/[^a-z0-9_-]/', '', strtolower($wert)), '-_');
    }

    /**
     * @return list<string>
     */
    private static function cookieNamen(string $wert): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $wert) ?: [] as $name) {
            if ($name !== '' && preg_match('/^[A-Za-z0-9_.\-]+\*?$/', $name)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * @param mixed $wert
     * @return list<string>
     */
    private static function ausnahmen($wert): array
    {
        $out = [];
        foreach ((array) $wert as $eintrag) {
            $pfad = is_array($eintrag) ? (string) (reset($eintrag) ?: '') : (string) $eintrag;
            $pfad = '/' . trim(trim($pfad), '/');
            if ($pfad !== '/') {
                $out[] = $pfad;
            }
        }

        return array_values(array_unique($out));
    }

    /** Seiten-Pfad oder volle Adresse; ein Pfad bekommt die Basis der Installation. */
    private static function url(string $wert): string
    {
        $wert = trim($wert);
        if ($wert === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $wert) || str_starts_with($wert, '#') || str_starts_with($wert, 'mailto:')) {
            return $wert;
        }
        $basis = rtrim((string) Grav::instance()['base_url_relative'], '/');

        return $basis . '/' . ltrim($wert, '/');
    }

    private static function externeUrl(string $wert): string
    {
        $wert = trim($wert);

        return preg_match('#^https?://#i', $wert) ? $wert : '';
    }

    /** Weiss oder fast Schwarz auf der Leitfarbe -- was lesbarer ist (WCAG). */
    private static function kontrastfarbe(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $kanal = static function (string $teil): float {
            $c = hexdec($teil) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $kanal(substr($hex, 0, 2)) + 0.7152 * $kanal(substr($hex, 2, 2)) + 0.0722 * $kanal(substr($hex, 4, 2));
        $weiss   = 1.05 / ($l + 0.05);
        $schwarz = ($l + 0.05) / 0.0625; // gegen #1d1f2b (L ~ 0.0125)

        return $weiss >= $schwarz ? '#ffffff' : '#1d1f2b';
    }
}
