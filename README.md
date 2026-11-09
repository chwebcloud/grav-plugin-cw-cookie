# CHWEB Cookie Consent (cw-cookie)

A cookie banner with a settings dialog for Grav, built for the GDPR and the
Swiss revDSG. Prepared for **Google Consent Mode v2**, **Google Tag Manager**
and **Google Analytics 4**; any further service and any embedded content loads
only after consent. No CDN, no jQuery, nothing stored on your server.

The most-seen element of many websites is a box nobody wants to see. That it is
there at all has one good reason and one bad one: the good one is asking before
anything loads. The bad one is the box that appears while the services are
already running – at that point it is decoration, and a visit to the developer
tools proves it. This plugin does the serious version: before any other script,
the default is "not allowed". Consent is what switches it on, per category.

## What it does

- **Banner and settings dialog** – accept, essential only, or choose per
  category. All three are equally reachable; there is no hidden reject button.
- **Four categories** out of the box – necessary, statistics, marketing, media
  – each with its own title and description, and you can add your own.
- **Google Consent Mode v2**, Tag Manager and Analytics 4 are prepared: before
  the decision every signal is set to denied, afterwards to whatever was chosen.
- **Any further service** as a list entry: name, category, provider,
  description, privacy link, the script and where it goes. Nothing of it loads
  before consent.
- **Embedded content stays blocked** until its category is allowed. Instead of
  a video that quietly loads, the visitor sees a placeholder with a button.
- **Your own texts and your own look** in four languages (de/en/fr/it): accent
  colour, position, colour scheme, corner radius, floating button.
- **A consent version**: raise it and everyone is asked again – the honest way
  when a new service is added.

## What it does not do

- It does not load anything from a foreign network, and it stores nothing on
  the server. The decision lives in the visitor's browser.
- It does not count how many people accept. There is no provider behind it who
  could.
- It does not decide for you whether you need a banner at all. A site without
  statistics, without embedded videos and without maps is better off without
  one – faster and friendlier. Look at what your site actually loads first.

## Installation

```bash
bin/gpm install cw-cookie
```

Or copy the folder to `user/plugins/cw-cookie` and run `bin/grav clear-cache`.

## Configuration

Admin → **Plugins → CHWEB Cookie**, or directly in
`user/config/plugins/cw-cookie.yaml`:

```yaml
enabled: true
datenschutz_url: /privacy
impressum_url: /imprint
zustimmung_version: 1        # raise it and everyone is asked again
laufzeit_tage: 180
google:
  consent_mode: true
  gtm_id: GTM-XXXXXXX        # Tag Manager container
  ga4_id: G-XXXXXXXXXX       # only without Tag Manager
darstellung:
  leitfarbe: '#434fbf'
```

Leave a text empty and the built-in text for the language of the page is used.

### Order in the `<head>`

The consent defaults are written into the asset group `head` at position
`before` – ahead of the bundle and ahead of every other script:

1. `dataLayer`, `gtag()` and `gtag('consent', 'default', …)` with every signal
   denied (`wait_for_update: 500`); with a stored decision, an immediate
   `gtag('consent', 'update', …)`.
2. Google Tag Manager.
3. GA4 directly – only when no Tag Manager is configured.

This is the uncomfortable order technically, and the only one that keeps what
the box promises.

### Blocking an embed

Mark the element with its category and it stays inert until consent is given:

```html
<iframe data-cw-cookie-kategorie="medien" data-src="https://…"></iframe>
```

A link or button with `data-cw-cookie-oeffnen` reopens the dialog from
anywhere – put one in the footer, next to the privacy policy.

## Requirements

Grav 1.7 or newer, PHP 8.1 or newer. No further dependencies.

## Licence

MIT – see [LICENSE](LICENSE). Built and maintained by
[CHWEB GmbH](https://chweb.ch), Huttwil, Switzerland.
