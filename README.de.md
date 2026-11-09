# CHWEB-Cookie

Cookie-Banner mit Einstellungsdialog für Grav, nach DSGVO und revDSG.
Vorbereitet für **Google Consent Mode v2**, **Google Tag Manager** und
**Google Analytics 4**; beliebige weitere Dienste und eigene Einbettungen
laden erst nach der Zustimmung. Kein CDN, kein jQuery, nichts auf dem Server.

## Einrichten

Admin → **Plugins → CHWEB-Cookie**. Sechs Reiter:

| Reiter | Inhalt |
|---|---|
| Allgemein | Ein/aus, **Version der Zustimmung** (erhöhen = Banner erscheint bei allen erneut), Laufzeit (Vorgabe 180 Tage), Datenschutz- und Impressum-Seite, Pfade ohne Banner |
| Texte | Eigene Texte für Banner und Dialog; leer = Standardtext in der Sprache der Seite (de/fr/it/en) |
| Kategorien | notwendig, statistik, marketing, medien – Titel, Beschreibung, welche Consent-Mode-Signale die Kategorie erlaubt; eigene Kategorien möglich |
| Google | Consent Mode, **GTM-Container-ID**, **GA4-Mess-ID** |
| Weitere Dienste | Name, Kategorie, Anbieter, Beschreibung, Datenschutz-Link, Cookies, Script-Adresse und/oder Inline-Code, Position |
| Darstellung | Leitfarbe (Vorgabe `#434fbf`), Position, Farbschema, Rundung, schwebender Knopf |

Oder direkt in `user/config/plugins/cw-cookie.yaml`:

```yaml
enabled: true
datenschutz_url: /datenschutz
impressum_url: /impressum
google:
  gtm_id: GTM-XXXXXXX
darstellung:
  leitfarbe: '#434fbf'
```

## Google

**Reihenfolge im `<head>`** (Asset-Gruppe `head`, Position `before`, also vor
der Bündelung und vor jedem anderen Script):

1. `dataLayer`, `gtag()`, `gtag('consent','default',{ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied', analytics_storage:'denied', functionality_storage:'granted', personalization_storage:'denied', security_storage:'granted', wait_for_update:500})` – und bei gespeicherter Zustimmung sofort `gtag('consent','update', …)`.
2. Google Tag Manager
3. GA4 direkt (nur ohne GTM)

**Google Tag Manager** – Container-ID eintragen. «Tag Manager laden»:

* **Immer** (Vorgabe): GTM startet sofort, der Consent Mode hält alle Google-Tags
  zurück, bis zugestimmt ist. Der `<noscript>`-Teil steht direkt nach `<body>`.
* **Erst nach Zustimmung**: GTM ist als `text/plain` gesperrt und startet erst,
  wenn die gewählte Kategorie (Vorgabe Statistik) erlaubt ist. Kein `<noscript>`.

Nach jeder Wahl (und bei jedem Seitenaufruf mit gespeicherter Wahl) steht im
dataLayer:

```js
{ event: 'cw_consent_update',
  cw_consent: { notwendig: true, statistik: true, marketing: false, medien: false },
  cw_consent_version: '1',
  cw_consent_quelle: 'alle' }   // alle | notwendige | auswahl | platzhalter | api | gespeichert
```

Im Tag Manager: Trigger «Benutzerdefiniertes Ereignis» `cw_consent_update`,
Datenschichtvariable `cw_consent.statistik`. Google-Tags selbst brauchen keinen
eigenen Trigger – sie lesen den Consent Mode.

**Google Analytics 4 direkt** – Mess-ID `G-…` eintragen, nur wenn es **keinen**
Tag Manager gibt. Ist beides gesetzt, wird GA4 nicht direkt eingebunden
(Doppelmessung) und die Seite trägt einen HTML-Kommentar dazu. Vorgabe «Erst
nach Zustimmung»: gtag.js lädt erst mit erlaubter Statistik. `anonymize_ip`
braucht es bei GA4 nicht mehr.

## Weitere Dienste

Im Reiter «Weitere Dienste» eintragen. Das Plugin gibt aus:

```html
<script type="text/plain" data-cw-cookie-kategorie="statistik" data-cw-cookie-dienst="Matomo">…</script>
<script type="text/plain" data-cw-cookie-kategorie="statistik" data-src="https://…/matomo.js" async></script>
```

Der Browser führt `text/plain` nicht aus und lädt keine Adresse dafür. Nach der
Zustimmung ersetzt `cw-cookie.js` das Element durch ein echtes Script. Im Feld
Inline-Code darf der ganze Einbettungscode des Anbieters stehen, samt
`<script>`-Tags; `<noscript>`-Teile fallen weg.

Die unter «Cookies» genannten Namen (`_pk_id*` = beginnt mit) erscheinen im
Dialog und werden beim Widerruf gelöscht; danach lädt die Seite neu, weil ein
gestartetes Script nicht mehr anzuhalten ist.

## Eigene Einbettungen sperren (YouTube, Karten, Kalender)

Im Theme oder in eigenem HTML: `src` durch `data-src` ersetzen und die
Kategorie angeben.

```html
<iframe data-cw-cookie-kategorie="medien" data-cw-cookie-anbieter="YouTube"
        data-src="https://www.youtube-nocookie.com/embed/VIDEO-ID"
        title="Video: …" width="560" height="315"></iframe>
```

An seiner Stelle erscheint ein Platzhalter mit «Inhalt laden» (nur dieses
Mal) und «… immer erlauben» (speichert die Kategorie). `img`, `video`,
`audio` mit `data-src` werden ebenfalls erst nach Zustimmung geladen, ohne
Platzhalter. Scripts: `<script type="text/plain" data-cw-cookie-kategorie="marketing">`.
Mehrere Kategorien mit Leerzeichen: geladen wird erst, wenn **alle** erlaubt sind.

Grav 2 verweigert `<iframe>` im Seiteninhalt (Security, `dangerous_tags`).
Für die Redaktion gibt es deshalb den Shortcode (braucht `shortcode-core`):

```
[cw-einbettung src="https://www.youtube-nocookie.com/embed/VIDEO-ID" anbieter="YouTube" titel="Video: Käsemarkt 2026"]
[cw-einbettung src="https://www.google.com/maps/embed?pb=…" anbieter="Google Maps" verhaeltnis="4:3"]
[cw-einbettung src="https://…" kategorie="marketing" hoehe="500"]
```

Nur `https://`-Adressen. `kategorie` Vorgabe `medien`, `verhaeltnis` Vorgabe `16:9`.

## Link «Cookie-Einstellungen»

Der Widerruf muss so einfach sein wie die Zustimmung. Im Theme, z. B. im Fuss:

```twig
{{ cw_cookie_link() }}                              {# Standardtext #}
{{ cw_cookie_link('Cookies verwalten', 'fuss-link') }}
```

oder irgendein Element mit `data-cw-cookie-oeffnen`:

```html
<a href="#" data-cw-cookie-oeffnen>Cookie-Einstellungen</a>
```

Ohne solchen Link den **schwebenden Knopf** einschalten (Reiter Darstellung).

## JavaScript

```js
cwCookie.oeffnen();               // Dialog öffnen
cwCookie.erlaubt('statistik');    // true/false
cwCookie.zustimmung();            // {v, k, t} oder null
cwCookie.setzen({ medien: true });
cwCookie.widerrufen();            // nur notwendige
cwCookie.aktivieren();            // nachgeladenen Inhalt erneut prüfen
document.addEventListener('cw-cookie:aenderung', (e) => console.log(e.detail.kategorien));
```

## Gespeichert wird

Nur im Browser, Cookie `cw_consent` (first-party, `SameSite=Lax`, bei https
`Secure`):

```json
{"v":"1","k":{"notwendig":true,"statistik":false,"marketing":false,"medien":false},"t":"2026-09-30T12:00:00.000Z"}
```

Keine Protokollierung auf dem Server, keine Personendaten. Bevor zugestimmt
ist, setzt das Plugin kein anderes Cookie.

## Ausnahmen

Pfade ohne Banner und ohne Google (Vorgabe `/admin`, `/api`) im Reiter
Allgemein; einzelne Seiten mit `cw_cookie: false` im Seitenkopf. Ausgaben
ausser HTML (JSON, RSS) bleiben ohnehin unberührt.

## Theme anpassen

`templates/partials/cw-cookie.html.twig` im Theme überschreibt Banner und
Dialog. Farben über CSS-Variablen (`--cw-cookie-farbe`, `--cw-cookie-farbe-text`,
`--cw-cookie-radius`); mit «Eigenes CSS des Plugins laden = Nein» gestaltet
das Theme alles selbst.
