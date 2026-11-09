<?php
declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Utils;
use Grav\Plugin\CwCookie\Ausgabe;
use Grav\Plugin\CwCookie\Dashboard;
use Grav\Plugin\CwCookie\Konfiguration;

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Grav\\Plugin\\CwCookie\\', 21) === 0) {
        $file = __DIR__ . '/classes/' . strtr(substr($class, 21), '\\', '/') . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

/**
 * CHWEB-Cookie -- Zustimmung zu Cookies und externen Diensten.
 *
 * Reihenfolge im <head> (Asset-Gruppe head, Position "before", also vor der
 * Buendelung und vor jedem anderen Script):
 *
 *   1. dataLayer + gtag() + gtag('consent','default', alles verweigert)
 *      + sofortiges Update, wenn schon eine Zustimmung gespeichert ist
 *   2. Google Tag Manager (Consent Mode: immer; sonst als text/plain)
 *   3. GA4 direkt (nur ohne GTM; Vorgabe: erst nach Zustimmung)
 *
 * Banner und Einstellungsdialog stehen direkt nach <body> (erste Station
 * fuer die Tastatur), gezeigt werden sie von assets/js/cw-cookie.js nur,
 * solange keine gueltige Zustimmung vorliegt. Weitere Dienste und eigene
 * iframes sperrt das Attribut data-cw-cookie-kategorie (siehe README).
 *
 * Auf dem Server wird nichts protokolliert: die Zustimmung lebt allein im
 * Cookie cw_consent im Browser.
 */
class CwCookiePlugin extends Plugin
{
    public $features = ['blueprints' => 1000];

    /** Gilt fuer diese Anfrage: Banner und Google-Einbindung ausgeben. */
    private bool $aktiv = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            // shortcode-core feuert das Ereignis in onThemeInitialized.
            'onShortcodeHandlers'  => ['onShortcodeHandlers', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            $this->enable([
                // Niedrige Prioritaet: laeuft NACH allen anderen, damit der
                // Doppelpunkt-Test unten jeden schon gesetzten Eintrag sieht.
                'onAdminMenu'         => ['onAdminMenu', -100],
                'onTwigTemplatePaths' => ['onTwigAdminTemplatePaths', 0],
                'onTwigSiteVariables' => ['onTwigAdminVariables', 0],
            ]);
            return;
        }

        $this->enable([
            'onPageInitialized'   => ['onPageInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigInitialized'   => ['onTwigInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onOutputGenerated'   => ['onOutputGenerated', -100],
        ]);
    }

    // ------------------------------------------------------------ Frontend --

    /**
     * Entscheidet einmal je Anfrage, ob cw-cookie hier arbeitet: nicht auf
     * Ausnahme-Pfaden, nicht bei JSON/RSS, nicht auf Seiten mit
     * `cw_cookie: false` im Kopf.
     */
    public function onPageInitialized(): void
    {
        $page = $this->grav['page'] ?? null;
        if (!$page || $page->templateFormat() !== 'html') {
            return;
        }
        $header = (array) $page->header();
        if (array_key_exists('cw_cookie', $header) && !$header['cw_cookie']) {
            return;
        }
        $pfad = '/' . trim((string) $this->grav['uri']->path(), '/');
        foreach (Konfiguration::laden()['ausnahmen'] as $ausnahme) {
            if ($pfad === $ausnahme || str_starts_with($pfad, $ausnahme . '/')) {
                return;
            }
        }
        $this->aktiv = true;
    }

    public function onTwigTemplatePaths(): void
    {
        // Hinten anfuegen: ein Theme kann partials/cw-cookie.html.twig ueberschreiben.
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Twig: {{ cw_cookie_link() }} oder {{ cw_cookie_link('Cookies', 'meine-klasse') }}.
     */
    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFunction(new \Twig\TwigFunction(
            'cw_cookie_link',
            static function (?string $text = null, string $klasse = ''): string {
                $text = ($text !== null && trim($text) !== '') ? $text : Konfiguration::laden()['texte']['link'];
                return Ausgabe::link($text, $klasse);
            },
            ['is_safe' => ['html']]
        ));
    }

    public function onTwigSiteVariables(): void
    {
        if (!$this->aktiv) {
            return;
        }
        $k      = Konfiguration::laden();
        $g      = $k['google'];
        $d      = $k['darstellung'];
        $assets = $this->grav['assets'];

        // 1. Consent-Vorgabe: vor der Buendelung und vor allen anderen Scripts.
        $assets->addInlineJs(Ausgabe::kopfSkript($k), ['group' => 'head', 'position' => 'before', 'priority' => 1000]);

        // 2. Google Tag Manager
        if ($g['gtm_id'] !== '') {
            $this->kopfScript(Ausgabe::gtmSkript($g['gtm_id']), $g['gtm_laden'] === 'immer' ? '' : $g['gtm_kategorie'], 999);
        }

        // 3. GA4 direkt (nie zusammen mit GTM, siehe Konfiguration::google)
        if ($g['ga4_aktiv']) {
            $this->kopfScript(Ausgabe::ga4Skript($g['ga4_id']), $g['ga4_laden'] === 'immer' ? '' : $g['ga4_kategorie'], 998);
        }

        if ($d['css_laden']) {
            $assets->addCss('plugin://cw-cookie/assets/css/cw-cookie.css');
        }
        $assets->addInlineCss(sprintf(
            ':root{--cw-cookie-farbe:%s;--cw-cookie-farbe-text:%s;--cw-cookie-radius:%dpx}',
            $d['leitfarbe'],
            $d['textfarbe'],
            $d['radius']
        ));
        $assets->addJs('plugin://cw-cookie/assets/js/cw-cookie.js', ['group' => 'bottom', 'loading' => 'defer']);
    }

    /**
     * Banner, Dialog, noscript-Teil von GTM und die Scripts der weiteren
     * Dienste in die fertige Seite setzen. Gesucht wird jeweils nur das
     * erste <body> bzw. das letzte </head> und </body>.
     */
    public function onOutputGenerated(): void
    {
        if (!$this->aktiv) {
            return;
        }
        $html = $this->grav->output;
        if (!is_string($html) || !preg_match('/<body\b[^>]*>/i', $html, $body, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $k = Konfiguration::laden();

        try {
            $banner = $this->grav['twig']->processTemplate('partials/cw-cookie.html.twig', ['cw_cookie' => $this->ansicht($k)]);
        } catch (\Throwable $e) {
            $this->grav['log']->error('cw-cookie: Banner nicht darstellbar: ' . $e->getMessage());
            return;
        }

        $nachBody = "\n";
        if ($k['google']['gtm_id'] !== '' && $k['google']['gtm_noscript']) {
            $nachBody .= Ausgabe::gtmNoscript($k['google']['gtm_id']) . "\n";
        }
        if ($k['google']['ga4_doppelt']) {
            $nachBody .= "<!-- cw-cookie: GA4 nicht direkt eingebunden, weil GTM gesetzt ist (Doppelmessung) -->\n";
        }
        $nachBody .= $banner . "\n";

        $offset = $body[0][1] + strlen($body[0][0]);
        $html   = substr($html, 0, $offset) . $nachBody . substr($html, $offset);

        $html = $this->vorLetztem($html, '</head>', Ausgabe::dienste($k['dienste'], 'head'));
        $html = $this->vorLetztem($html, '</body>', Ausgabe::dienste($k['dienste'], 'body'));

        $this->grav->output = $html;
    }

    /**
     * Shortcode [cw-einbettung src="https://..." kategorie="medien" anbieter="YouTube" titel="..."]
     * -- ein iframe, das erst nach Zustimmung laedt. Nur mit shortcode-core.
     */
    public function onShortcodeHandlers(): void
    {
        if (!isset($this->grav['shortcode'])) {
            return;
        }
        $this->grav['shortcode']->getHandlers()->add('cw-einbettung', static function ($sc): string {
            return Ausgabe::einbettung(array_map('strval', (array) $sc->getParameters()), Konfiguration::laden());
        });
    }

    // --------------------------------------------------- klassischer Admin --

    public function onAdminMenu(): void
    {
        $twig = $this->grav['twig'];

        // Fuehrt eine uebergeordnete Oberflaeche diesen Punkt schon, nicht
        // doppelt eintragen. Geprueft wird die Route, nicht der Name des
        // Eintrags -- der kann dort anders lauten.
        foreach ((array) ($twig->plugins_hooked_nav ?? []) as $eintrag) {
            if (is_array($eintrag) && ($eintrag['route'] ?? '') === 'cw-cookie') {
                return;
            }
        }

        $twig->plugins_hooked_nav['PLUGIN_CW_COOKIE.DASHBOARD'] = [
            'route'     => 'cw-cookie',
            'icon'      => 'fa-shield',
            'authorize' => ['admin.super'],
            'priority'  => 2,
        ];
    }

    public function onTwigAdminTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
    }

    public function onTwigAdminVariables(): void
    {
        $route = trim((string) $this->grav['uri']->path(), '/');
        if (!str_ends_with($route, 'cw-cookie')) {
            return;
        }
        $this->grav['twig']->twig_vars['cw_cookie_dashboard'] = Dashboard::build();
    }

    /**
     * Auswahl der Kategorien fuer den Admin (data-options@ im Blueprint).
     *
     * @return array<string,string>
     */
    public static function kategorieOptionen(): array
    {
        $out = [];
        foreach (Konfiguration::laden()['kategorien'] as $kat) {
            $out[$kat['key']] = $kat['titel'];
        }

        return $out ?: ['statistik' => 'Statistik', 'marketing' => 'Marketing', 'medien' => 'Externe Medien und Komfort'];
    }

    // ------------------------------------------------------------- intern --

    /**
     * Inline-Script in den Kopf, direkt hinter die Consent-Vorgabe. Mit
     * Kategorie wird es als text/plain gesperrt und erst nach Zustimmung
     * von cw-cookie.js ausgefuehrt.
     */
    private function kopfScript(string $code, string $kategorie, int $prioritaet): void
    {
        $optionen = ['group' => 'head', 'position' => 'before', 'priority' => $prioritaet];
        if ($kategorie !== '') {
            $optionen['type']                    = 'text/plain';
            $optionen['data-cw-cookie-kategorie'] = $kategorie;
        }
        $this->grav['assets']->addInlineJs($code, $optionen);
    }

    /**
     * Daten fuer templates/partials/cw-cookie.html.twig.
     *
     * @param array<string,mixed> $k
     * @return array<string,mixed>
     */
    private function ansicht(array $k): array
    {
        $texte = $k['texte'];
        $texte['text_html']        = Utils::processMarkdown($texte['text'], true);
        $texte['dialog_text_html'] = Utils::processMarkdown($texte['dialog_text'], true);

        return [
            'texte'       => $texte,
            'kategorien'  => $k['kategorien'],
            'datenschutz' => $k['datenschutz'],
            'impressum'   => $k['impressum'],
            'darstellung' => $k['darstellung'],
        ];
    }

    private function vorLetztem(string $html, string $tag, string $einschub): string
    {
        if ($einschub === '') {
            return $html;
        }
        $pos = strripos($html, $tag);

        return $pos === false ? $html : substr($html, 0, $pos) . $einschub . substr($html, $pos);
    }
}
