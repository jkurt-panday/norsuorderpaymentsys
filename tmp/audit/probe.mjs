import puppeteer from 'puppeteer';
import fs from 'node:fs';

const base = 'http://localhost:8000';
const out = 'tmp/audit';
const pages = process.argv.slice(2).length ? process.argv.slice(2) : ['/login'];

const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox'],
    userDataDir: `${process.env.TEMP}/impeccable-audit-${Date.now()}`,
});
const report = {};

for (const path of pages) {
    const page = await browser.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    const failed = [];
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', (e) => pageErrors.push(String(e)));
    page.on('requestfailed', (r) => failed.push(`${r.url()} :: ${r.failure()?.errorText}`));

    await page.setViewport({ width: 1440, height: 900 });
    let status = null;
    try {
        const res = await page.goto(base + path, { waitUntil: 'networkidle2', timeout: 45000 });
        status = res?.status();
    } catch (e) {
        pageErrors.push('NAV: ' + String(e));
    }
    await new Promise((r) => setTimeout(r, 1200));

    const slug = (path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'root');
    await page.screenshot({ path: `${out}/${slug}-desktop.png`, fullPage: true });

    const desktop = await page.evaluate(() => {
        const cs = getComputedStyle(document.documentElement);
        const body = getComputedStyle(document.body);
        return {
            title: document.title,
            url: location.href,
            h1: [...document.querySelectorAll('h1')].map((h) => h.textContent.trim()),
            bodyBg: body.backgroundColor,
            bodyColor: body.color,
            bodyFont: body.fontFamily,
            bodySize: body.fontSize,
            tokens: {
                background: cs.getPropertyValue('--background'),
                foreground: cs.getPropertyValue('--foreground'),
                primary: cs.getPropertyValue('--primary'),
                mutedForeground: cs.getPropertyValue('--muted-foreground'),
                border: cs.getPropertyValue('--border'),
                radius: cs.getPropertyValue('--radius'),
            },
            textLen: document.body.innerText.length,
            horizontalOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
    });

    await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await new Promise((r) => setTimeout(r, 600));
    const mobileOverflow = await page.evaluate(() => {
        const de = document.documentElement;
        const offenders = [];
        document.querySelectorAll('*').forEach((el) => {
            const r = el.getBoundingClientRect();
            if (r.width && r.right > de.clientWidth + 1) {
                offenders.push({ tag: el.tagName.toLowerCase(), cls: (el.className || '').toString().slice(0, 120), right: Math.round(r.right), width: Math.round(r.width) });
            }
        });
        return { scrollW: de.scrollWidth, clientW: de.clientWidth, offenders: offenders.slice(0, 12) };
    });
    await page.screenshot({ path: `${out}/${slug}-mobile.png`, fullPage: true });

    const smallTargets = await page.evaluate(() => {
        const els = [...document.querySelectorAll('a,button,input,select,textarea,[role="button"],[role="checkbox"],[role="tab"],[role="combobox"]')];
        return els.filter((el) => {
            const r = el.getBoundingClientRect();
            const s = getComputedStyle(el);
            return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && (r.width < 24 || r.height < 24);
        }).slice(0, 15).map((el) => {
            const r = el.getBoundingClientRect();
            return { tag: el.tagName.toLowerCase(), text: (el.textContent || '').trim().slice(0, 40), w: Math.round(r.width), h: Math.round(r.height) };
        });
    });

    report[path] = { status, desktop, mobileOverflow, smallTargets, consoleErrors: consoleErrors.slice(0, 10), pageErrors: pageErrors.slice(0, 10), failed: failed.slice(0, 10) };
    await page.close();
}

await browser.close();
fs.writeFileSync(`${out}/probe-report.json`, JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
