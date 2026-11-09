/*
 * CHWEB-Cookie -- Zustimmung zu Cookies und externen Diensten.
 *
 * ES2019, ohne jQuery, ohne fremde Dateien. Die Konfiguration steht in
 * window.cwCookieKonfig (vom Inline-Script im <head>, das auch den Google
 * Consent Mode vorbelegt).
 *
 * Gesperrte Inhalte erkennt das Script am Attribut data-cw-cookie-kategorie:
 *   <script type="text/plain" data-cw-cookie-kategorie="statistik">...</script>
 *   <script type="text/plain" data-cw-cookie-kategorie="marketing" data-src="https://..."></script>
 *   <iframe data-cw-cookie-kategorie="medien" data-src="https://..."></iframe>
 * Mehrere Kategorien mit Leerzeichen: geladen wird erst, wenn ALLE erlaubt sind.
 *
 * Schnittstelle: window.cwCookie.oeffnen(), .erlaubt('statistik'),
 * .zustimmung(), .setzen({statistik: true}), .widerrufen(), .aktivieren();
 * Ereignis 'cw-cookie:aenderung' auf document.
 */
(function () {
  'use strict';

  const K = window.cwCookieKonfig;
  if (!K || window.cwCookie) {
    return;
  }
  const d = document;
  const ATTR = 'data-cw-cookie-kategorie';
  const FOKUSSIERBAR = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

  let zustand = gueltig(lesen());
  let banner = null;
  let dialog = null;
  let fenster = null;
  let ausloeser = null;

  // ------------------------------------------------------------ Zustimmung

  function lesen() {
    const m = d.cookie.match(new RegExp('(?:^|;\\s*)' + K.cookie + '=([^;]*)'));
    if (!m) {
      return null;
    }
    try {
      return JSON.parse(decodeURIComponent(m[1]));
    } catch (e) {
      return null;
    }
  }

  function gueltig(z) {
    return z && typeof z.k === 'object' && z.k !== null && String(z.v) === String(K.version) ? z : null;
  }

  function istPflicht(kat) {
    return K.pflicht.indexOf(kat) > -1;
  }

  function erlaubt(kat) {
    return istPflicht(kat) || Boolean(zustand && zustand.k[kat] === true);
  }

  function erlaubtAlle(liste) {
    const teile = String(liste || '').split(/[\s,]+/).filter(Boolean);
    return teile.length > 0 && teile.every(erlaubt);
  }

  function titelVon(kat) {
    const eintrag = K.kategorien.find((k) => k.key === kat);
    return eintrag ? eintrag.titel : kat;
  }

  /**
   * Speichert die Auswahl, meldet sie an Google und an den dataLayer,
   * aktiviert gesperrte Inhalte. Wurde etwas entzogen, werden die Cookies
   * dieser Kategorie geloescht und die Seite neu geladen -- ein einmal
   * gestartetes Script laesst sich nicht mehr anhalten.
   */
  function speichern(auswahl, quelle) {
    const vorher = zustand ? zustand.k : null;
    const k = {};
    K.kategorien.forEach((kat) => {
      k[kat.key] = kat.notwendig ? true : auswahl[kat.key] === true;
    });
    zustand = { v: K.version, k: k, t: new Date().toISOString() };

    let eintrag = K.cookie + '=' + encodeURIComponent(JSON.stringify(zustand)) +
      '; path=/; max-age=' + (K.laufzeit * 86400) + '; SameSite=Lax';
    if (location.protocol === 'https:') {
      eintrag += '; Secure';
    }
    d.cookie = eintrag;

    melden(quelle);
    d.dispatchEvent(new CustomEvent('cw-cookie:aenderung', { detail: { kategorien: Object.assign({}, k), quelle: quelle } }));

    const entzogen = vorher ? Object.keys(vorher).filter((key) => vorher[key] === true && k[key] !== true) : [];
    if (entzogen.length) {
      entzogen.forEach(cookiesLoeschen);
      location.reload();
      return;
    }
    aktivieren();
    dialogZu(true);
    bannerZeigen(false);
  }

  function melden(quelle) {
    const signale = {};
    Object.keys(K.signale).forEach((kat) => {
      const an = erlaubt(kat);
      K.signale[kat].forEach((signal) => {
        if (an || signale[signal] !== 'granted') {
          signale[signal] = an ? 'granted' : 'denied';
        }
      });
    });
    if (K.google.consentMode && typeof window.gtag === 'function') {
      window.gtag('consent', 'update', signale);
    }
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'cw_consent_update',
      cw_consent: Object.assign({}, zustand.k),
      cw_consent_version: String(K.version),
      cw_consent_quelle: quelle
    });
  }

  function alleAuswahl(wert) {
    const a = {};
    K.kategorien.forEach((kat) => {
      a[kat.key] = wert;
    });
    return a;
  }

  // -------------------------------------------------------------- Cookies

  function cookiesLoeschen(kat) {
    const muster = (K.loeschen && K.loeschen[kat]) || [];
    if (!muster.length) {
      return;
    }
    const namen = d.cookie.split(';').map((c) => c.split('=')[0].trim()).filter(Boolean);
    const teile = location.hostname.split('.');
    const domains = [''];
    for (let i = 0; i < teile.length - 1; i++) {
      const dom = teile.slice(i).join('.');
      domains.push('; domain=' + dom, '; domain=.' + dom);
    }
    namen.forEach((name) => {
      if (name === K.cookie) {
        return;
      }
      const trifft = muster.some((m) => (m.slice(-1) === '*' ? name.indexOf(m.slice(0, -1)) === 0 : name === m));
      if (trifft) {
        domains.forEach((dom) => {
          d.cookie = name + '=; path=/; max-age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT' + dom;
        });
      }
    });
  }

  // ------------------------------------------------- gesperrte Inhalte

  function aktivieren() {
    d.querySelectorAll('script[type="text/plain"][' + ATTR + ']').forEach((alt) => {
      if (!erlaubtAlle(alt.getAttribute(ATTR))) {
        return;
      }
      const neu = d.createElement('script');
      Array.from(alt.attributes).forEach((a) => {
        if (a.name !== 'type' && a.name !== 'data-src' && a.name !== 'data-type' && a.name !== 'src') {
          neu.setAttribute(a.name, a.value);
        }
      });
      if (alt.getAttribute('data-type')) {
        neu.type = alt.getAttribute('data-type');
      }
      neu.setAttribute('data-cw-cookie-aktiv', '');
      const src = alt.getAttribute('data-src') || alt.getAttribute('src');
      if (src) {
        if (!alt.hasAttribute('async')) {
          neu.async = false;
        }
        neu.src = src;
      } else {
        neu.text = alt.text;
      }
      alt.parentNode.replaceChild(neu, alt);
    });

    d.querySelectorAll('[' + ATTR + '][data-src]:not(script)').forEach((el) => {
      if (el.hasAttribute('data-cw-cookie-geladen')) {
        return;
      }
      if (erlaubtAlle(el.getAttribute(ATTR))) {
        laden(el);
      } else {
        platzhalter(el);
      }
    });
  }

  function laden(el) {
    const p = platzhalterVon(el);
    if (p) {
      p.parentNode.removeChild(p);
    }
    el.setAttribute('data-cw-cookie-geladen', '');
    el.src = el.getAttribute('data-src');
    el.hidden = false;
  }

  function platzhalterVon(el) {
    const v = el.previousElementSibling;
    return v && v.classList.contains('cw-cookie-platzhalter') ? v : null;
  }

  /** {name} in einem Text ersetzen, jedes Vorkommen (ohne replaceAll, ES2019). */
  function ersetzen(text, werte) {
    return Object.keys(werte).reduce((t, name) => t.split('{' + name + '}').join(werte[name]), String(text));
  }

  function element(tag, klasse, text) {
    const e = d.createElement(tag);
    if (klasse) {
      e.className = klasse;
    }
    if (text) {
      e.textContent = text;
    }
    return e;
  }

  /** Ersatz fuer ein gesperrtes iframe: Erklaerung und zwei Knoepfe. */
  function platzhalter(el) {
    if (platzhalterVon(el) || el.tagName !== 'IFRAME') {
      return;
    }
    const liste = String(el.getAttribute(ATTR)).split(/[\s,]+/).filter(Boolean);
    const offen = liste.filter((kat) => !erlaubt(kat));
    const kategorie = offen.map(titelVon).join(', ');
    let anbieter = el.getAttribute('data-cw-cookie-anbieter') || '';
    if (!anbieter) {
      try {
        anbieter = new URL(el.getAttribute('data-src'), location.href).hostname;
      } catch (e) {
        anbieter = '';
      }
    }
    const p = element('div', 'cw-cookie-platzhalter' + (K.farbschema === 'auto' ? ' cw-cookie-schema-auto' : ''));
    p.setAttribute('role', 'group');
    p.setAttribute('aria-label', el.getAttribute('title') || K.texte.platzhalterTitel);
    if (el.parentNode && el.parentNode.classList && el.parentNode.classList.contains('cw-cookie-einbettung')) {
      p.classList.add('cw-cookie-platzhalter--fuellend');
    } else if (el.getAttribute('height')) {
      p.style.minHeight = parseInt(el.getAttribute('height'), 10) + 'px';
    }
    p.appendChild(element('p', 'cw-cookie-platzhalter__titel', el.getAttribute('title') || K.texte.platzhalterTitel));
    p.appendChild(element('p', 'cw-cookie-platzhalter__text', ersetzen(K.texte.platzhalterText, { anbieter: anbieter, kategorie: kategorie })));

    const knoepfe = element('div', 'cw-cookie-platzhalter__knoepfe');
    const einmal = element('button', 'cw-cookie__knopf', K.texte.laden);
    einmal.type = 'button';
    einmal.addEventListener('click', () => {
      laden(el);
      el.focus();
    });
    const immer = element('button', 'cw-cookie__knopf cw-cookie__knopf--zweit', ersetzen(K.texte.immer, { kategorie: kategorie }));
    immer.type = 'button';
    immer.addEventListener('click', () => {
      const a = zustand ? Object.assign({}, zustand.k) : {};
      offen.forEach((kat) => {
        a[kat] = true;
      });
      speichern(a, 'platzhalter');
    });
    knoepfe.appendChild(einmal);
    knoepfe.appendChild(immer);
    p.appendChild(knoepfe);

    if (K.datenschutz) {
      const hinweis = element('p', 'cw-cookie-platzhalter__link');
      const a = element('a', '', K.texte.datenschutz);
      a.href = K.datenschutz;
      hinweis.appendChild(a);
      p.appendChild(hinweis);
    }

    el.hidden = true;
    el.parentNode.insertBefore(p, el);
  }

  // ---------------------------------------------------- Banner und Dialog

  function bannerZeigen(zeigen) {
    if (banner) {
      banner.hidden = !zeigen;
    }
    d.querySelectorAll('[data-cw-cookie-schwebend]').forEach((k) => {
      k.hidden = zeigen || !zustand || Boolean(dialog && !dialog.hidden);
    });
  }

  function dialogAuf(von) {
    if (!dialog) {
      return;
    }
    ausloeser = von || d.activeElement;
    dialog.querySelectorAll('[data-cw-cookie-schalter]').forEach((schalter) => {
      const kat = schalter.getAttribute('data-cw-cookie-schalter');
      schalter.checked = istPflicht(kat) || erlaubt(kat);
    });
    dialog.hidden = false;
    d.documentElement.classList.add('cw-cookie-gesperrt');
    bannerZeigen(false);
    fenster.scrollTop = 0;
    fenster.focus();
  }

  function dialogZu(zurueck) {
    if (!dialog || dialog.hidden) {
      return;
    }
    dialog.hidden = true;
    d.documentElement.classList.remove('cw-cookie-gesperrt');
    if (!zustand) {
      // Noch keine Entscheidung: zurueck zum Banner.
      bannerZeigen(true);
      const k = banner && banner.querySelector('[data-cw-cookie-aktion="einstellungen"]');
      if (zurueck && k) {
        k.focus();
      }
      return;
    }
    bannerZeigen(false);
    if (zurueck && ausloeser && d.contains(ausloeser) && typeof ausloeser.focus === 'function') {
      ausloeser.focus();
    }
  }

  function auswahlImDialog() {
    const a = {};
    dialog.querySelectorAll('[data-cw-cookie-schalter]').forEach((schalter) => {
      a[schalter.getAttribute('data-cw-cookie-schalter')] = schalter.checked;
    });
    return a;
  }

  /** Tab bleibt im Dialog, Escape schliesst ihn. */
  function tastatur(ev) {
    if (!dialog || dialog.hidden) {
      return;
    }
    if (ev.key === 'Escape' || ev.key === 'Esc') {
      ev.preventDefault();
      dialogZu(true);
      return;
    }
    if (ev.key !== 'Tab') {
      return;
    }
    const ziele = Array.from(fenster.querySelectorAll(FOKUSSIERBAR))
      .filter((el) => el.offsetWidth > 0 || el.offsetHeight > 0 || el === d.activeElement);
    if (!ziele.length) {
      ev.preventDefault();
      fenster.focus();
      return;
    }
    const erstes = ziele[0];
    const letztes = ziele[ziele.length - 1];
    if (ev.shiftKey && (d.activeElement === erstes || d.activeElement === fenster)) {
      ev.preventDefault();
      letztes.focus();
    } else if (!ev.shiftKey && d.activeElement === letztes) {
      ev.preventDefault();
      erstes.focus();
    } else if (!fenster.contains(d.activeElement)) {
      ev.preventDefault();
      erstes.focus();
    }
  }

  function klick(ev) {
    const ziel = ev.target.closest('[data-cw-cookie-aktion], [data-cw-cookie-oeffnen]');
    if (!ziel) {
      if (dialog && ev.target === dialog) {
        dialogZu(true);
      }
      return;
    }
    if (ziel.hasAttribute('data-cw-cookie-oeffnen')) {
      ev.preventDefault();
      dialogAuf(ziel);
      return;
    }
    switch (ziel.getAttribute('data-cw-cookie-aktion')) {
      case 'alle':
        speichern(alleAuswahl(true), 'alle');
        break;
      case 'notwendige':
        speichern(alleAuswahl(false), 'notwendige');
        break;
      case 'speichern':
        speichern(auswahlImDialog(), 'auswahl');
        break;
      case 'einstellungen':
        dialogAuf(ziel);
        break;
      case 'schliessen':
        dialogZu(true);
        break;
      default:
    }
  }

  function start() {
    banner = d.getElementById('cw-cookie');
    dialog = d.getElementById('cw-cookie-dialog');
    fenster = dialog ? dialog.querySelector('.cw-cookie-dialog__fenster') : null;
    d.addEventListener('click', klick);
    d.addEventListener('keydown', tastatur);
    aktivieren();
    bannerZeigen(!zustand);
  }

  window.cwCookie = {
    oeffnen: () => dialogAuf(null),
    erlaubt: erlaubt,
    zustimmung: () => (zustand ? JSON.parse(JSON.stringify(zustand)) : null),
    setzen: (auswahl) => speichern(Object.assign({}, zustand ? zustand.k : {}, auswahl || {}), 'api'),
    widerrufen: () => speichern(alleAuswahl(false), 'widerruf'),
    aktivieren: aktivieren
  };

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
