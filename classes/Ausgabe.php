<?php
declare(strict_types=1);

namespace Grav\Plugin\CwCookie;

/**
 * Baut die Schnipsel, die cw-cookie in die Seite schreibt.
 *
 * Grundmuster fuer alles, was erst nach einer Zustimmung laufen darf:
 *
 *     <script type="text/plain" data-cw-cookie-kategorie="statistik">...</script>
 *     <script type="text/plain" data-cw-cookie-kategorie="marketing" data-src="https://..."></script>
 *     <iframe data-cw-cookie-kategorie="medien" data-src="https://..."></iframe>
 *
 * Der Browser fuehrt ein Script vom Typ text/plain nicht aus und laedt auch
 * keine Adresse dafuer. assets/js/cw-cookie.js tauscht es nach der Zustimmung
 * gegen ein echtes Script aus.
 */
final class Ausgabe
{
    private const JSON = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Inline-Script fuer den Anfang des <head>: dataLayer, gtag(),
     * Consent-Mode-Vorgabe und -- bei gespeicherter Zustimmung -- sofort das
     * Update. So sieht jedes Google-Tag schon beim ersten Befehl den
     * richtigen Stand. Bewusst ES5 und ohne Abhaengigkeit.
     *
     * @param array<string,mixed> $k Konfiguration::laden()
     */
    public static function kopfSkript(array $k): string
    {
        $js  = "window.dataLayer=window.dataLayer||[];\n";
        $js .= "function gtag(){dataLayer.push(arguments);}\n";
        $js .= 'window.cwCookieKonfig=' . self::json(self::frontendKonfig($k)) . ";\n";

        if ($k['google']['consent_mode']) {
            $js .= "gtag('consent','default'," . self::json(Konfiguration::standardZustimmung($k)) . ");\n";
            if ($k['google']['ads_data_redaction']) {
                $js .= "gtag('set','ads_data_redaction',true);\n";
            }
            if ($k['google']['url_passthrough']) {
                $js .= "gtag('set','url_passthrough',true);\n";
            }
        }

        // Gespeicherte Zustimmung sofort anwenden (nur bei passender Version).
        $js .= <<<'JS'
(function(w,d){var K=w.cwCookieKonfig,z=null,m;
try{m=d.cookie.match(new RegExp('(?:^|;\\s*)'+K.cookie+'=([^;]*)'));if(m){z=JSON.parse(decodeURIComponent(m[1]));}}catch(e){z=null;}
if(!z||!z.k||String(z.v)!==String(K.version)){return;}
var u={},s=K.signale,i,j,an;
for(i in s){if(Object.prototype.hasOwnProperty.call(s,i)){an=K.pflicht.indexOf(i)>-1||z.k[i]===true;for(j=0;j<s[i].length;j++){if(an||u[s[i][j]]!=='granted'){u[s[i][j]]=an?'granted':'denied';}}}}
if(K.google.consentMode){gtag('consent','update',u);}
w.dataLayer.push({event:'cw_consent_update',cw_consent:z.k,cw_consent_version:String(K.version),cw_consent_quelle:'gespeichert'});
})(window,document);
JS;

        return $js;
    }

    /**
     * Offizieller GTM-Container-Code (Google, Stand 2024), nur die ID ersetzt.
     */
    public static function gtmSkript(string $id): string
    {
        return "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});"
            . "var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';"
            . "j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
            . "})(window,document,'script','dataLayer'," . self::json($id) . ');';
    }

    /**
     * GA4 direkt (gtag.js). anonymize_ip braucht es bei GA4 nicht mehr: GA4
     * speichert keine IP-Adressen. Die Befehle landen zuerst nur im
     * dataLayer; gemessen wird erst, wenn gtag.js geladen ist.
     */
    public static function ga4Skript(string $id): string
    {
        return "(function(d,i){var j=d.createElement('script');j.async=true;"
            . "j.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(i);d.head.appendChild(j);"
            . "gtag('js',new Date());gtag('config',i);})(document," . self::json($id) . ');';
    }

    /** <noscript>-Teil des GTM-Codes, direkt nach <body>. */
    public static function gtmNoscript(string $id): string
    {
        $src = 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode($id);

        return '<noscript><iframe src="' . self::attr($src) . '" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>';
    }

    /**
     * Gesperrtes Script: text/plain + Kategorie, erst nach Zustimmung aktiv.
     */
    public static function gesperrt(string $kategorie, string $code = '', string $src = '', string $dienst = '', bool $async = false): string
    {
        $html = '<script type="text/plain" data-cw-cookie-kategorie="' . self::attr($kategorie) . '"';
        if ($dienst !== '') {
            $html .= ' data-cw-cookie-dienst="' . self::attr($dienst) . '"';
        }
        if ($src !== '') {
            $html .= ' data-src="' . self::attr($src) . '"';
            if ($async) {
                $html .= ' async';
            }
        }

        return $html . '>' . self::scriptInhalt($code) . "</script>\n";
    }

    /**
     * Alle Scripts der weiteren Dienste fuer eine Position (head/body).
     *
     * Im Feld "Inline-Code" darf die Redaktion auch den fertigen Schnipsel
     * eines Anbieters einfuegen, samt <script>-Tags: sie werden hier
     * zerlegt, <noscript>-Teile fallen weg (die liefen ohne Zustimmung).
     *
     * @param list<array<string,mixed>> $dienste
     */
    public static function dienste(array $dienste, string $position): string
    {
        $html = '';
        foreach ($dienste as $d) {
            if ($d['position'] !== $position) {
                continue;
            }
            if ($d['script_url'] !== '') {
                $html .= self::gesperrt($d['kategorie'], '', $d['script_url'], $d['name'], true);
            }
            foreach (self::zerlegen((string) $d['code']) as $teil) {
                $html .= self::gesperrt($d['kategorie'], $teil['code'], $teil['src'], $d['name'], $teil['async']);
            }
        }

        return $html;
    }

    /**
     * Shortcode [cw-einbettung]: gesperrtes iframe mit festem Seitenverhaeltnis.
     *
     * @param array<string,string> $a
     * @param array<string,mixed> $k
     */
    public static function einbettung(array $a, array $k): string
    {
        $src = trim($a['src'] ?? '');
        if (!preg_match('#^https://#i', $src)) {
            return '<!-- cw-einbettung: src fehlt oder ist keine https-Adresse -->';
        }
        $keys = array_column($k['kategorien'], 'key');
        $kat  = trim($a['kategorie'] ?? 'medien');
        if (!in_array($kat, $keys, true)) {
            $kat = in_array('medien', $keys, true) ? 'medien' : (string) end($keys);
        }
        $titel    = trim($a['titel'] ?? '') ?: Konfiguration::t('PLATZHALTER_TITEL');
        $anbieter = trim($a['anbieter'] ?? '') ?: (string) parse_url($src, PHP_URL_HOST);
        $stil     = '';
        if (preg_match('/^(\d{1,4})[:\/](\d{1,4})$/', trim($a['verhaeltnis'] ?? '16:9'), $m) && (int) $m[2] > 0) {
            $stil = 'aspect-ratio:' . (int) $m[1] . '/' . (int) $m[2];
        }
        if (preg_match('/^\d{2,4}$/', trim($a['hoehe'] ?? ''))) {
            $stil = 'height:' . (int) $a['hoehe'] . 'px';
        }
        $allow = trim($a['erlaube'] ?? 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen');

        return '<div class="cw-cookie-einbettung"' . ($stil !== '' ? ' style="' . self::attr($stil) . '"' : '') . '>'
            . '<iframe data-cw-cookie-kategorie="' . self::attr($kat) . '" data-cw-cookie-anbieter="' . self::attr($anbieter) . '"'
            . ' data-src="' . self::attr($src) . '" title="' . self::attr($titel) . '" loading="lazy"'
            . ' allow="' . self::attr($allow) . '" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>'
            . '</div>';
    }

    /** Knopf "Cookie-Einstellungen" fuer Fusszeile oder Datenschutzseite. */
    public static function link(string $text, string $klasse = ''): string
    {
        $klasse = trim('cw-cookie-link ' . preg_replace('/[^A-Za-z0-9_\- ]/', '', $klasse));

        return '<button type="button" class="' . self::attr($klasse) . '" data-cw-cookie-oeffnen>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</button>';
    }

    // --------------------------------------------------------------------

    /**
     * Was das JavaScript braucht -- nicht mehr (steht in jeder Seite).
     *
     * @param array<string,mixed> $k
     * @return array<string,mixed>
     */
    private static function frontendKonfig(array $k): array
    {
        $kategorien = [];
        foreach ($k['kategorien'] as $kat) {
            $kategorien[] = ['key' => $kat['key'], 'titel' => $kat['titel'], 'notwendig' => $kat['notwendig']];
        }
        $t = $k['texte'];

        return [
            'cookie'     => Konfiguration::COOKIE,
            'version'    => $k['version'],
            'laufzeit'   => $k['laufzeit'],
            'kategorien' => $kategorien,
            'pflicht'    => $k['pflicht'],
            'signale'    => (object) $k['signale'],
            'loeschen'   => (object) $k['loeschen'],
            'google'     => ['consentMode' => $k['google']['consent_mode']],
            'farbschema' => $k['darstellung']['farbschema'],
            'datenschutz' => $k['datenschutz'],
            'texte'      => [
                'platzhalterTitel' => $t['platzhalter_titel'],
                'platzhalterText'  => $t['platzhalter_text'],
                'laden'            => $t['platzhalter_laden'],
                'immer'            => $t['platzhalter_immer'],
                'datenschutz'      => $t['datenschutz'],
            ],
        ];
    }

    /**
     * @return list<array{code:string,src:string,async:bool}>
     */
    private static function zerlegen(string $code): array
    {
        $code = trim((string) preg_replace('#<noscript\b.*?</noscript>#is', '', $code));
        if ($code === '') {
            return [];
        }
        if (stripos($code, '<script') === false) {
            return [['code' => $code, 'src' => '', 'async' => false]];
        }
        $teile = [];
        if (preg_match_all('#<script\b([^>]*)>(.*?)</script\s*>#is', $code, $treffer, PREG_SET_ORDER)) {
            foreach ($treffer as $t) {
                $src = '';
                if (preg_match('#\bsrc\s*=\s*(["\']?)([^"\'\s>]+)\1#i', $t[1], $s)) {
                    $src = html_entity_decode($s[2], ENT_QUOTES, 'UTF-8');
                }
                $teile[] = [
                    'code'  => $src === '' ? trim($t[2]) : '',
                    'src'   => $src,
                    'async' => (bool) preg_match('#\basync\b#i', $t[1]),
                ];
            }
        }

        return $teile;
    }

    private static function scriptInhalt(string $code): string
    {
        // "</script" im Code wuerde das Element vorzeitig schliessen.
        return str_ireplace('</script', '<\/script', $code);
    }

    private static function json($wert): string
    {
        return (string) json_encode($wert, self::JSON);
    }

    private static function attr(string $wert): string
    {
        return htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
    }
}
