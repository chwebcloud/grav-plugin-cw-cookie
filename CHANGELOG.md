# v1.2.0
## 2026-11-09

1. [](#improved)
    * **Visitors can withdraw their consent again.** `darstellung.schwebender_knopf` now defaults to on. It used to be off, and without it there was no way back into the settings unless the theme supplied `cw_cookie_link()` or a `[data-cw-cookie-oeffnen]` element — in a consent plugin, withdrawing has to be as easy as giving. Sites with their own link switch the button off.
    * **The Tag Manager waits for consent by default.** `google.gtm_laden` now defaults to `zustimmung` instead of `immer`. With `immer`, Google is contacted on the very first page view, before any choice, and the `noscript` iframe cannot be held back at all. Advanced Consent Mode is a legitimate setup and gives better modelled data, but it has to be chosen knowingly rather than shipped. The README explains the trade-off.
    * `compatibility` now lists `1.7` and `2.0`, matching the dependency and the README. It said `2.0` only.
    * **The README said more than the plugin does.** "Any embedded content loads only after consent" was true for elements carrying `data-cw-cookie-kategorie` and for the `[cw-einbettung]` shortcode — a raw iframe, the YouTube plugin, a map or a theme web font were never touched. Now it says so. The configuration example used `zustimmung_version`; the key is `version`. And `shortcode-core` is named as what it is: needed for the shortcode, not for anything else.
    * The remaining hardcoded German in the admin — five notice blocks, two "add" buttons and the dashboard's hints — now comes from the language files, in German, English, French and Italian.
    * The fallbacks in `Konfiguration.php` still carried the old defaults (`?? 'immer'`, `?? false`). A fallback that disagrees with the shipped default is a second, invisible setting; both now say the same thing.

    All of the above comes from Andy Miller's review of the directory submission, [getgrav/grav#4396](https://github.com/getgrav/grav/issues/4396).

# v1.1.1
## 2026-11-09

1. [](#improved)
    * **The menu entry in the classic admin no longer asks about another plugin.** It used to stand back as soon as a particular hub plugin was enabled, which meant knowing that plugin's name. It now runs last and only registers itself if the route is still free. Same behaviour, no knowledge of its surroundings; nothing changes for an installation without that hub.

# v1.1.0
## 2026-11-09

1. [](#improved)
    * **The admin labels speak four languages.** 131 labels, help texts, placeholders and option labels were hardcoded German in the blueprints; they are language keys in `languages/de|en|fr|it.yaml` now. Nothing visible changes on a German installation.
    * The header fields (name, description, author) stay plain text on purpose: the Grav directory reads them literally, and a language key would end up in the catalogue.

# v1.0.1
## 2026-11-09

1. [](#improved)
    * **Prepared for the getgrav.org directory.** Description, keywords and README in English; the German README sits beside it as `README.de.md`.

# v1.0.0
## 2026-09-30

1. [](#new)
    * **First release**, a rewrite of the old `chweb-cookie` (an OK button without a choice).
    * **Banner** with three equal buttons – accept all, necessary only, settings (no dark pattern) – and links to the privacy policy and the imprint; as a bar or as a box, bottom left or right.
    * **Settings dialog** with a switch per category (necessary, statistics, marketing, external media, convenience; extendable in the admin) and an expandable service list with provider, cookies and privacy link. Accessible: `role=dialog`, `aria-modal`, focus trap, Escape, focus returned to the trigger, `role=switch`.
    * **Google Consent Mode v2**: an inline script as the first script in `<head>` sets `gtag('consent','default', …)` with everything denied except functionality and security storage (`wait_for_update: 500`); with a stored decision, the update follows immediately. Every choice sends `gtag('consent','update', …)` and the dataLayer event `cw_consent_update`.
    * **Google Tag Manager** (container ID, loaded always with Consent Mode or only after consent), with the `<noscript>` part right after `<body>`.
    * **Google Analytics 4 directly** (after consent by default); skipped when a Tag Manager is configured, to avoid counting every visit twice.
    * **Further services** in the admin (script URL and/or inline code, head or end of page) as a blocked `<script type="text/plain" data-cw-cookie-kategorie="…">`.
    * **Blocking your own embeds** with `data-cw-cookie-kategorie` + `data-src` and a placeholder ("load content" / "always allow"); the `[cw-einbettung]` shortcode for page content.
    * **Consent** lives in the `cw_consent` cookie only (version, categories, time), with a configurable lifetime (180 days by default); raising the version in the admin shows the banner again. On withdrawal the category's cookies are deleted and the page reloads. Nothing on the server.
    * Twig function `cw_cookie_link()`, selector `[data-cw-cookie-oeffnen]`, optional floating button, JavaScript interface `window.cwCookie`.
    * Excluded paths (`/admin`, `/api` by default) and `cw_cookie: false` in the page header.
    * Texts in German, French, Italian and English; an accent colour with an automatically readable text colour; no CDN, no jQuery.
