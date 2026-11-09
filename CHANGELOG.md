# v1.1.1
## 09.11.2026

1. [](#improved)
    * **Der Menuepunkt im klassischen Admin fragt nicht mehr nach einem anderen Plugin.** Bisher trat er zurueck, sobald eine bestimmte Zentrale eingeschaltet war -- das Plugin musste deren Namen kennen. Jetzt laeuft er als letzter und traegt sich nur ein, wenn die Route noch frei ist. Gleiches Verhalten, ohne Vorwissen ueber die Umgebung: fuer eine Installation ohne diese Zentrale aendert sich nichts.

# v1.1.0
## 09.11.2026

1. [](#improved)
    * **Die Beschriftungen im Admin sprechen vier Sprachen.** 131 Beschriftungen, Hilfetexte, Platzhalter und Auswahlwerte standen fest verdrahtet auf Deutsch in den Blueprints; jetzt stehen sie als Sprachmarken in `languages/de|en|fr|it.yaml`. Auf einer deutschen Installation aendert sich nichts Sichtbares.
    * Die Angaben am Dateianfang (Name, Beschreibung, Autor) bleiben bewusst Text: Das Grav-Verzeichnis liest sie woertlich, eine Sprachmarke stuende dort im Katalog.

# v1.0.1
## 09.11.2026

1. [](#improved)
    * **Fuer die Anmeldung bei getgrav.org vorbereitet.** Beschreibung, Schlagworte und README auf Englisch; die deutsche Fassung steht als `README.de.md` daneben. Der Verweis auf den CW-PLUGIN-STANDARD ist aus dem oeffentlichen README genommen -- er zeigt in das Monorepo.

# v1.0.0
## 30.09.2026

1. [](#new)
    * **Erste Fassung**, Neufassung des alten `chweb-cookie` (ein OK-Knopf ohne Wahl) nach CW-Plugin-Standard.
    * **Banner** mit drei gleichwertigen Knöpfen «Alle akzeptieren», «Nur notwendige», «Einstellungen» (kein Dark Pattern), Links auf Datenschutzerklärung und Impressum; als Leiste oder Kasten unten links/rechts.
    * **Einstellungsdialog** mit Schalter je Kategorie (notwendig, Statistik, Marketing, Externe Medien und Komfort; im Admin erweiterbar) und aufklappbarer Dienstliste mit Anbieter, Cookies und Datenschutz-Link. Barrierefrei: `role=dialog`, `aria-modal`, Fokusfalle, Escape, Fokus zurück zum Auslöser, `role=switch`.
    * **Google Consent Mode v2**: Inline-Script als erstes Script im `<head>` setzt `gtag('consent','default', …)` (alles verweigert ausser functionality/security_storage, `wait_for_update: 500`), bei gespeicherter Zustimmung sofort das Update; nach jeder Wahl `gtag('consent','update', …)` und das dataLayer-Ereignis `cw_consent_update`.
    * **Google Tag Manager** (Container-ID, «immer laden» mit Consent Mode oder «erst nach Zustimmung»), `<noscript>`-Teil direkt nach `<body>`.
    * **Google Analytics 4 direkt** (Vorgabe: erst nach Zustimmung); wird bei gesetztem GTM ausgelassen (Doppelmessung).
    * **Weitere Dienste** im Admin (Script-Adresse und/oder Inline-Code, Kopf oder Seitenende) als gesperrtes `<script type="text/plain" data-cw-cookie-kategorie="…">`.
    * **Eigene Einbettungen sperren** mit `data-cw-cookie-kategorie` + `data-src` und Platzhalter «Inhalt laden» / «immer erlauben»; Shortcode `[cw-einbettung]` für Seiteninhalte.
    * **Zustimmung** nur im Cookie `cw_consent` (Version, Kategorien, Zeitpunkt), Laufzeit einstellbar (Vorgabe 180 Tage); höhere Version im Admin = Banner erscheint erneut. Beim Widerruf werden die Cookies der Kategorie gelöscht und die Seite neu geladen. Nichts auf dem Server.
    * Twig-Funktion `cw_cookie_link()`, Selektor `[data-cw-cookie-oeffnen]`, optional schwebender Knopf; JavaScript-Schnittstelle `window.cwCookie`.
    * Ausnahme-Pfade (Vorgabe `/admin`, `/api`) und `cw_cookie: false` im Seitenkopf.
    * Texte in Deutsch, Französisch, Italienisch und Englisch; Leitfarbe mit automatisch lesbarer Schriftfarbe; kein CDN, kein jQuery.
