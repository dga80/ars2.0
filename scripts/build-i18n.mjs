#!/usr/bin/env node
/**
 * Generates static SEO-indexable /es/index.html and /en/index.html
 * from the root index.html (Catalan) and the translations object in app.js.
 *
 * Usage: node scripts/build-i18n.mjs
 */

import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');

const indexHtml = fs.readFileSync(path.join(rootDir, 'index.html'), 'utf8');
const appJs = fs.readFileSync(path.join(rootDir, 'app.js'), 'utf8');

// Extract `translations` object from app.js safely via VM sandbox
const translationsMatch = appJs.match(/const translations = (\{[\s\S]*?\n\});/);
if (!translationsMatch) {
  throw new Error('Could not find `const translations = {...};` in app.js');
}
const translations = vm.runInNewContext(`(${translationsMatch[1]})`);

const metaByLang = {
  es: {
    url: 'https://www.arsadvocats.com/es/',
    title: 'ARS Advocats i Assessors | Despacho de abogados en Granollers',
    description:
      'ARS Advocats i Assessors. Despacho de abogados y asesoramiento legal integral en el centro de Granollers. Derecho Civil, Familia, Penal, Mercantil, Laboral e Inmobiliario.',
    keywords:
      'ARS Advocats, abogados Granollers, derecho civil Granollers, derecho penal, divorcios, herencias, mercantil, laboral, Roger Subirana, Àlex Reus, Anna Camps, Dana Costa',
    ogDescription:
      'Rigor, compromiso y excelencia jurídica en el centro de Granollers. Asesoramiento integral para particulares y empresas.',
    ogImageAlt: 'ARS Advocats i Assessors Despacho de abogados en Granollers',
    ogLocale: 'es_ES',
    ogLocaleAlternates: ['ca_ES', 'en_GB'],
    twitterDescription:
      'Rigor, compromiso y excelencia jurídica en el centro de Granollers. Asesoramiento integral en Derecho Civil, Familia, Penal, Mercantil y Laboral.',
    orgDescription:
      'Despacho de abogados y asesoramiento legal integral en el centro de Granollers. Especialistas en Derecho Civil, Matrimonial y Familia, Sucesiones, Penal, Mercantil, Laboral, Inmobiliario, Bancario y Ley de Segunda Oportunidad.',
    catalogName: 'Áreas de Especialización Jurídica',
    jobTitles: {
      roger: 'Socio Director',
      alex: 'Socio Director',
      anna: 'Abogada Colaboradora',
      dana: 'Asistente legal · Derecho Penal',
    },
    knowsAbout: {
      roger: ['Derecho Civil', 'Derecho de Familia', 'Sucesiones y Herencias', 'Derecho Penal', 'Litigación'],
      alex: ['Derecho Mercantil y Societario', 'Derecho Laboral y Seguridad Social', 'Incapacidades Laborales', 'Ley de Segunda Oportunidad', 'Corporate Compliance'],
      anna: ['Derecho Inmobiliario', 'Desahucios y Arrendamientos', 'Derecho Civil', 'Derecho Penal'],
      dana: ['Derecho Penal', 'Derecho Procesal Penal', 'Ciencias Penales'],
    },
    waMessage: 'Hola, quería solicitar información sobre una consulta jurídica en ARS Advocats.',
    waLabel: 'Contactar por WhatsApp',
    mapLang: 'es',
    modalTitle: 'Información Legal',
  },
  en: {
    url: 'https://www.arsadvocats.com/en/',
    title: 'ARS Advocats i Assessors | Law Firm in Granollers (Barcelona)',
    description:
      'ARS Advocats i Assessors. Comprehensive law firm in downtown Granollers (Barcelona). Civil, Family, Criminal, Corporate, Employment, and Real Estate Law.',
    keywords:
      'ARS Advocats, lawyers Granollers, English speaking lawyer Granollers, Barcelona law firm, family law, inheritance, criminal defense, corporate law, employment law, Roger Subirana, Àlex Reus, Anna Camps, Dana Costa',
    ogDescription:
      'Rigour, commitment, and legal excellence in downtown Granollers. Comprehensive legal counsel for individuals and companies.',
    ogImageAlt: 'ARS Advocats i Assessors Law Firm in Granollers',
    ogLocale: 'en_GB',
    ogLocaleAlternates: ['ca_ES', 'es_ES'],
    twitterDescription:
      'Rigour, commitment, and legal excellence in downtown Granollers. Comprehensive legal counsel in Civil, Family, Criminal, Corporate, and Employment Law.',
    orgDescription:
      'Full-service law firm in downtown Granollers (Barcelona). Specialists in Civil, Family, Inheritance, Criminal, Corporate, Employment, Real Estate, Banking, and Second Chance Law.',
    catalogName: 'Legal Practice Areas',
    jobTitles: {
      roger: 'Managing Partner',
      alex: 'Managing Partner',
      anna: 'Associate Attorney',
      dana: 'Legal Assistant · Criminal Law',
    },
    knowsAbout: {
      roger: ['Civil Law', 'Family Law', 'Inheritance and Wills', 'Criminal Law', 'Litigation'],
      alex: ['Corporate and Commercial Law', 'Employment and Social Security Law', 'Disability Claims', 'Second Chance Law', 'Corporate Compliance'],
      anna: ['Real Estate Law', 'Evictions and Leases', 'Civil Law', 'Criminal Law'],
      dana: ['Criminal Law', 'Criminal Procedural Law', 'Criminal Sciences'],
    },
    waMessage: 'Hello, I would like to request information regarding legal advice at ARS Advocats.',
    waLabel: 'Contact via WhatsApp',
    mapLang: 'en',
    modalTitle: 'Legal Information',
  },
};

function escapeAttr(str) {
  return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

function buildLangPage(lang) {
  const dict = translations[lang];
  const meta = metaByLang[lang];
  if (!dict || !meta) throw new Error(`Unsupported language: ${lang}`);

  let html = indexHtml;

  // 1. <html lang="..."> and <body data-lang="...">
  html = html.replace('<html lang="ca">', `<html lang="${lang}">`);
  html = html.replace('<body data-lang="ca">', `<body data-lang="${lang}">`);

  // 2. Head metadata (title, description, keywords, canonical, OG, Twitter)
  html = html.replace(/<title>[\s\S]*?<\/title>/, `<title>${meta.title}</title>`);
  html = html.replace(
    /<meta name="description" content="[^"]*">/,
    `<meta name="description" content="${escapeAttr(meta.description)}">`
  );
  html = html.replace(
    /<meta name="keywords" content="[^"]*">/,
    `<meta name="keywords" content="${escapeAttr(meta.keywords)}">`
  );
  html = html.replace(
    /<link rel="canonical" href="[^"]*">/,
    `<link rel="canonical" href="${meta.url}">`
  );
  html = html.replace(
    /<meta property="og:title" content="[^"]*">/,
    `<meta property="og:title" content="${escapeAttr(meta.title)}">`
  );
  html = html.replace(
    /<meta property="og:description" content="[^"]*">/,
    `<meta property="og:description" content="${escapeAttr(meta.ogDescription)}">`
  );
  html = html.replace(
    /<meta property="og:url" content="[^"]*">/,
    `<meta property="og:url" content="${meta.url}">`
  );
  html = html.replace(
    /<meta property="og:image:alt" content="[^"]*">/,
    `<meta property="og:image:alt" content="${escapeAttr(meta.ogImageAlt)}">`
  );
  html = html.replace(
    /<meta property="og:locale" content="[^"]*">\s*<meta property="og:locale:alternate" content="[^"]*">\s*<meta property="og:locale:alternate" content="[^"]*">/,
    `<meta property="og:locale" content="${meta.ogLocale}">\n  <meta property="og:locale:alternate" content="${meta.ogLocaleAlternates[0]}">\n  <meta property="og:locale:alternate" content="${meta.ogLocaleAlternates[1]}">`
  );
  html = html.replace(
    /<meta name="twitter:title" content="[^"]*">/,
    `<meta name="twitter:title" content="${escapeAttr(meta.title)}">`
  );
  html = html.replace(
    /<meta name="twitter:description" content="[^"]*">/,
    `<meta name="twitter:description" content="${escapeAttr(meta.twitterDescription)}">`
  );

  // 3. Localize JSON-LD @graph cleanly
  html = html.replace(
    /(<script type="application\/ld\+json">\s*)([\s\S]*?)(\s*<\/script>)/,
    (_, openTag, jsonRaw, closeTag) => {
      const ld = JSON.parse(jsonRaw);
      const graph = ld['@graph'];
      for (const node of graph) {
        if (node['@type'] === 'WebPage') {
          node['@id'] = `${meta.url}#webpage`;
          node.url = meta.url;
          node.name = meta.title;
          node.description = meta.description;
          node.inLanguage = lang;
        } else if (Array.isArray(node['@type']) && node['@type'].includes('LegalService')) {
          node.description = meta.orgDescription;
          if (node.hasOfferCatalog) {
            node.hasOfferCatalog.name = meta.catalogName;
            node.hasOfferCatalog.itemListElement = Array.from({ length: 15 }, (_, idx) => {
              const n = idx + 1;
              return {
                '@type': 'Offer',
                itemOffered: {
                  '@type': 'Service',
                  name: dict[`sp_${n}_title`],
                  description: dict[`sp_${n}_desc`],
                },
              };
            });
          }
        } else if (node['@id'] === 'https://www.arsadvocats.com/#roger-subirana') {
          node.jobTitle = meta.jobTitles.roger;
          node.knowsAbout = meta.knowsAbout.roger;
        } else if (node['@id'] === 'https://www.arsadvocats.com/#alex-reus') {
          node.jobTitle = meta.jobTitles.alex;
          node.knowsAbout = meta.knowsAbout.alex;
        } else if (node['@id'] === 'https://www.arsadvocats.com/#anna-camps') {
          node.jobTitle = meta.jobTitles.anna;
          node.knowsAbout = meta.knowsAbout.anna;
        } else if (node['@id'] === 'https://www.arsadvocats.com/#dana-costa') {
          node.jobTitle = meta.jobTitles.dana;
          node.knowsAbout = meta.knowsAbout.dana;
        }
      }
      const formatted = JSON.stringify(ld, null, 2)
        .split('\n')
        .map((line) => `  ${line}`)
        .join('\n');
      return `${openTag}\n${formatted}\n  ${closeTag.trim()}`;
    }
  );

  // 4. Translate all elements with data-i18n="key">...inner...</tag>
  // Match leaf/simple tags cleanly (handles nested inline tags like <strong> and <a> inside <p>/<span>)
  html = html.replace(
    /(<([a-z0-9]+)\b[^>]*\bdata-i18n="([^"]+)"[^>]*>)([\s\S]*?)(<\/\2>)/gi,
    (match, openTag, tagName, key, _inner, closeTag) => {
      if (dict[key] === undefined) return match;
      return `${openTag}${dict[key]}${closeTag}`;
    }
  );

  // 5. Translate placeholder attributes with data-i18n-placeholder="key"
  html = html.replace(
    /(<(?:input|textarea)\b[^>]*\bplaceholder=")([^"]*)("[^>]*\bdata-i18n-placeholder="([^"]+)"[^>]*>)/gi,
    (match, before, _oldPh, after, key) => {
      if (dict[key] === undefined) return match;
      return `${before}${escapeAttr(dict[key])}${after}`;
    }
  );

  // 6. Active language button state
  html = html.replace(/class="lang-btn active"/g, 'class="lang-btn"');
  html = html.replace(
    new RegExp(`class="lang-btn" data-switch-lang="${lang}"`, 'g'),
    `class="lang-btn active" data-switch-lang="${lang}"`
  );

  // 7. Prefix relative paths with ../ for subdirectory (/es/ and /en/)
  html = html
    .replace(/href="favicon\.ico"/g, 'href="../favicon.ico"')
    .replace(/href="assets\//g, 'href="../assets/')
    .replace(/src="assets\//g, 'src="../assets/')
    .replace(/href="styles\.css/g, 'href="../styles.css')
    .replace(/href="chatbot\.css/g, 'href="../chatbot.css')
    .replace(/src="app\.js/g, 'src="../app.js')
    .replace(/src="chatbot\.js/g, 'src="../chatbot.js')
    .replace(/action="contact\.php"/g, 'action="../contact.php"');

  // 8. WhatsApp floating button default message & aria-label
  html = html.replace(
    /href="https:\/\/wa\.me\/34668556252\?text=[^"]*"([^>]*)aria-label="[^"]*"/,
    `href="https://wa.me/34668556252?text=${encodeURIComponent(meta.waMessage)}"$1aria-label="${escapeAttr(meta.waLabel)}"`
  );

  // 9. Google Maps embed language & modal default title
  html = html.replace('!2sca!2ses', `!2s${meta.mapLang}!2ses`);
  html = html.replace('<h3 id="modalTitle">Informació Legal</h3>', `<h3 id="modalTitle">${meta.modalTitle}</h3>`);

  const outDir = path.join(rootDir, lang);
  fs.mkdirSync(outDir, { recursive: true });
  const outFile = path.join(outDir, 'index.html');
  fs.writeFileSync(outFile, html, 'utf8');
  console.log(`Generated ${path.relative(rootDir, outFile)} (${html.length} bytes)`);
}

buildLangPage('es');
buildLangPage('en');
