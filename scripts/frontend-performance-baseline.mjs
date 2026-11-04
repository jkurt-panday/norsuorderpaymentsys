#!/usr/bin/env node

import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = (process.env.PERF_BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const outputPath = resolve(process.env.PERF_OUTPUT || 'tmp/performance/frontend-baseline.json');
const staffEmail = process.env.PERF_STAFF_EMAIL;
const staffPassword = process.env.PERF_STAFF_PASSWORD;
const sampleTarget = 3;
const maxAttempts = 5;
const settleMs = 750;

const routes = [
    { key: 'login', path: '/login', auth: false, ready: ['Sign in'], interaction: 'password-visibility' },
    { key: 'public-order-form', path: '/public/opform', auth: false, ready: ['NORSU Order of Payment', 'Contact Information'], interaction: 'student-tab' },
    { key: 'staff-dashboard', path: '/staff/staffdashboard', auth: true, ready: ['Dashboard', 'OP Requests by Status'], interaction: 'sidebar-toggle' },
    { key: 'assessments-dashboard', path: '/staff/assessments/dashboard', auth: true, ready: ['Assessments Dashboard', 'Assessment Requests'], interaction: 'sidebar-toggle' },
    { key: 'law-ledger', path: '/law-ledger', auth: true, ready: ['Law School Ledger', 'Transaction Ledger'], interaction: 'filters' },
];

const round = (value) => Number.isFinite(value) ? Math.round(value * 100) / 100 : null;
const sleep = (milliseconds) => new Promise((done) => setTimeout(done, milliseconds));

function median(values) {
    const sorted = values.filter(Number.isFinite).toSorted((a, b) => a - b);
    if (!sorted.length) return null;
    const middle = Math.floor(sorted.length / 2);
    return sorted.length % 2 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
}

function summary(values) {
    const valid = values.filter(Number.isFinite);
    return valid.length ? {
        median: round(median(valid)),
        min: round(Math.min(...valid)),
        max: round(Math.max(...valid)),
    } : { median: null, min: null, max: null };
}

function categoryFor(resource) {
    const mime = resource.mimeType || '';
    const type = resource.type || '';
    if (type === 'Document') return 'document';
    if (type === 'Script' || /javascript|ecmascript/.test(mime)) return 'js';
    if (type === 'Stylesheet' || /css/.test(mime)) return 'css';
    if (type === 'Image' || mime.startsWith('image/')) return 'image';
    if (type === 'Font' || /font|woff/.test(mime)) return 'font';
    if (['XHR', 'Fetch'].includes(type)) return 'xhr';
    return 'other';
}

async function installObservers(page) {
    await page.evaluateOnNewDocument(() => {
        window.__perfBaseline = { lcp: null, cls: 0, longTasks: [], events: [] };
        const observe = (type, callback, options = {}) => {
            try {
                new PerformanceObserver((list) => callback(list.getEntries())).observe({ type, buffered: true, ...options });
            } catch { /* unsupported entry type */ }
        };
        observe('largest-contentful-paint', (entries) => {
            const last = entries.at(-1);
            if (last) window.__perfBaseline.lcp = last.startTime;
        });
        observe('layout-shift', (entries) => {
            for (const entry of entries) if (!entry.hadRecentInput) window.__perfBaseline.cls += entry.value;
        });
        observe('longtask', (entries) => {
            window.__perfBaseline.longTasks.push(...entries.map(({ startTime, duration }) => ({ startTime, duration })));
        });
        observe('event', (entries) => {
            window.__perfBaseline.events.push(...entries.filter((entry) => entry.duration > 0).map(({ name, startTime, duration, interactionId }) => ({ name, startTime, duration, interactionId }))); 
        }, { durationThreshold: 16 });
    });
}

async function waitForText(page, texts, timeout = 20_000) {
    await page.waitForFunction(
        (needles) => needles.every((needle) => document.body?.innerText.includes(needle)),
        { timeout },
        texts,
    );
}

async function login(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    await waitForText(page, ['Sign in']);
    await page.type('#email', staffEmail);
    await page.type('#password', staffPassword);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30_000 }),
        page.click('button[type="submit"]'),
    ]);
    if (new URL(page.url()).pathname === '/login') throw new Error('Staff authentication was denied; verify PERF_STAFF_EMAIL and PERF_STAFF_PASSWORD.');
}

async function runInteraction(page, route) {
    const started = await page.evaluate(() => performance.now());
    if (route.interaction === 'password-visibility') {
        await page.click('button[aria-controls="password"]');
        await page.waitForFunction(() => document.querySelector('#password')?.getAttribute('type') === 'text');
    } else if (route.interaction === 'student-tab') {
        await page.evaluate(() => [...document.querySelectorAll('button')].find((node) => node.textContent?.trim() === 'Student')?.click());
        await page.waitForFunction(() => document.querySelector('[role="tab"][data-active]')?.textContent?.includes('Student') || document.body.innerText.includes('Student Number'));
    } else if (route.interaction === 'sidebar-toggle') {
        const before = await page.$eval('[data-slot="sidebar"]', (sidebar) => sidebar.getAttribute('data-state'));
        await page.click('[data-sidebar="trigger"]');
        await page.waitForFunction((previous) => {
            const current = document.querySelector('[data-slot="sidebar"]')?.getAttribute('data-state');
            return current !== previous;
        }, {}, before);
    } else if (route.interaction === 'filters') {
        await page.evaluate(() => [...document.querySelectorAll('button')].find((node) => node.textContent?.includes('Filters'))?.click());
        await page.waitForFunction(() => [...document.querySelectorAll('button')].some((node) => node.textContent?.includes('Filters') && node.getAttribute('aria-expanded') === 'true'));
    }
    return page.evaluate((start) => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve(performance.now() - start)))), started);
}

async function measure(browser, route, attempt) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1 });
    await installObservers(page);

    const consoleErrors = [];
    const failedRequests = [];
    const httpErrors = [];

    try {
        if (route.auth) await login(page);
        page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
        page.on('requestfailed', (request) => failedRequests.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));
        page.on('response', (response) => { if (response.status() >= 400) httpErrors.push({ url: response.url(), status: response.status() }); });
        const client = await page.createCDPSession();
        await client.send('Network.enable');
        await client.send('Network.setCacheDisabled', { cacheDisabled: true });
        const resources = new Map();
        let loadTimestamp = null;
        client.on('Network.responseReceived', ({ requestId, response, type, timestamp }) => {
            resources.set(requestId, {
                url: response.url,
                status: response.status,
                mimeType: response.mimeType,
                type,
                fromDiskCache: response.fromDiskCache,
                fromServiceWorker: response.fromServiceWorker,
                responseTimestamp: timestamp,
                encodedDataLength: response.encodedDataLength || 0,
            });
        });
        client.on('Network.loadingFinished', ({ requestId, encodedDataLength, timestamp }) => {
            const resource = resources.get(requestId);
            if (resource) Object.assign(resource, { encodedDataLength, finishedTimestamp: timestamp });
        });
        client.on('Page.loadEventFired', ({ timestamp }) => { loadTimestamp = timestamp; });
        await client.send('Page.enable');

        await page.goto(`${baseUrl}${route.path}`, { waitUntil: 'load', timeout: 30_000 });
        const routeStarted = await page.evaluate(() => performance.timeOrigin);
        await waitForText(page, route.ready);
        const readyAt = await page.evaluate((origin) => performance.timeOrigin + performance.now() - origin, routeStarted);
        await sleep(settleMs);
        const interactionMs = await runInteraction(page, route);
        await sleep(100);

        const browserMetrics = await page.evaluate(() => {
            const navigation = performance.getEntriesByType('navigation')[0];
            const state = window.__perfBaseline;
            return {
                navigation: navigation ? {
                    domContentLoadedMs: navigation.domContentLoadedEventEnd,
                    loadMs: navigation.loadEventEnd,
                    transferSize: navigation.transferSize,
                    encodedBodySize: navigation.encodedBodySize,
                } : null,
                lcpMs: state.lcp,
                cls: state.cls,
                longTasks: state.longTasks,
                eventTimings: state.events,
            };
        });

        const resourceList = [...resources.values()].map((resource) => ({
            ...resource,
            category: categoryFor(resource),
            encodedDataLength: resource.encodedDataLength || 0,
            initial: loadTimestamp === null || resource.responseTimestamp <= loadTimestamp,
        }));
        const bytesByCategory = Object.fromEntries(['document', 'js', 'css', 'image', 'font', 'xhr', 'other'].map((category) => [category, resourceList.filter((item) => item.category === category).reduce((sum, item) => sum + item.encodedDataLength, 0)]));
        const finalPath = new URL(page.url()).pathname;
        const viteErrors = [...consoleErrors, ...failedRequests.map((item) => item.url)].filter((value) => /vite|@react-refresh|failed to fetch dynamically imported/i.test(value));
        const errors = [];
        if (finalPath !== route.path) errors.push(`Unexpected final path: ${finalPath}`);
        if (failedRequests.length) errors.push(`${failedRequests.length} failed request(s)`);
        if (httpErrors.length) errors.push(`${httpErrors.length} HTTP error response(s)`);
        if (viteErrors.length) errors.push('Vite asset failure detected');

        const result = {
            attempt,
            valid: errors.length === 0,
            invalidReasons: errors,
            finalUrl: page.url(),
            requestCount: resourceList.length,
            initialRequestCount: resourceList.filter((item) => item.initial).length,
            transferBytes: { ...bytesByCategory, total: resourceList.reduce((sum, item) => sum + item.encodedDataLength, 0) },
            lcpMs: round(browserMetrics.lcpMs),
            cls: round(browserMetrics.cls),
            domContentLoadedMs: round(browserMetrics.navigation?.domContentLoadedMs),
            loadMs: round(browserMetrics.navigation?.loadMs),
            routeReadyMs: round(readyAt),
            interactionMs: round(interactionMs),
            longTaskCount: browserMetrics.longTasks.length,
            longTaskTotalMs: round(browserMetrics.longTasks.reduce((sum, task) => sum + task.duration, 0)),
            eventTimingSupported: browserMetrics.eventTimings.length > 0,
            failedRequests,
            httpErrors,
            consoleErrors,
            resources: resourceList.toSorted((a, b) => b.encodedDataLength - a.encodedDataLength),
        };
        await client.detach();
        return result;
    } catch (error) {
        return {
            attempt,
            valid: false,
            invalidReasons: [error instanceof Error ? error.message : String(error)],
            finalUrl: page.url(),
            failedRequests,
            httpErrors,
            consoleErrors,
        };
    } finally {
        await context.close();
    }
}

function summarize(samples) {
    const valid = samples.filter((sample) => sample.valid);
    const fields = ['requestCount', 'initialRequestCount', 'lcpMs', 'cls', 'domContentLoadedMs', 'loadMs', 'routeReadyMs', 'interactionMs', 'longTaskCount', 'longTaskTotalMs'];
    const result = Object.fromEntries(fields.map((field) => [field, summary(valid.map((sample) => sample[field]))]));
    for (const category of ['js', 'css', 'image', 'total']) result[`${category}Bytes`] = summary(valid.map((sample) => sample.transferBytes?.[category]));
    return result;
}

async function warmRoute(browser, route) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    try {
        if (route.auth) await login(page);
        await page.goto(`${baseUrl}${route.path}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
        await waitForText(page, route.ready);
        return { ok: true };
    } catch (error) {
        return { ok: false, reason: error instanceof Error ? error.message : String(error) };
    } finally {
        await context.close();
    }
}

const report = {
    schemaVersion: 1,
    generatedAt: new Date().toISOString(),
    baseUrl,
    outputPath,
    protocol: {
        samplesPerRoute: sampleTarget,
        maxAttemptsPerRoute: maxAttempts,
        viewport: { width: 1440, height: 900, deviceScaleFactor: 1 },
        cache: 'disabled via CDP for measured navigation',
        throttling: 'none',
        settleMs,
        serverMode: 'Laravel development server with connected Vite development server',
        note: 'Development-server lab baseline; not production performance or real-user monitoring.',
    },
    environment: { node: process.version, platform: `${process.platform} ${process.arch}` },
    auth: { credentialsProvided: Boolean(staffEmail && staffPassword), passwordSerialized: false },
    routes: {},
    blockers: [],
};

let browser;
let userDataDir;
try {
    userDataDir = await mkdtemp(join(tmpdir(), 'frontend-performance-'));
    browser = await puppeteer.launch({ headless: true, userDataDir });
    report.environment.chromium = await browser.version();
    for (const route of routes) {
        if (route.auth && !(staffEmail && staffPassword)) {
            report.routes[route.key] = { path: route.path, status: 'blocked', blocker: 'Set PERF_STAFF_EMAIL and PERF_STAFF_PASSWORD.', samples: [], summary: summarize([]) };
            continue;
        }
        const warmup = await warmRoute(browser, route);
        if (!warmup.ok) {
            report.routes[route.key] = { path: route.path, status: 'blocked', warmup, samples: [], summary: summarize([]) };
            report.blockers.push(`${route.path}: ${warmup.reason}`);
            continue;
        }
        const samples = [];
        for (let attempt = 1; attempt <= maxAttempts && samples.filter((sample) => sample.valid).length < sampleTarget; attempt += 1) {
            samples.push(await measure(browser, route, attempt));
        }
        const validCount = samples.filter((sample) => sample.valid).length;
        report.routes[route.key] = { path: route.path, status: validCount === sampleTarget ? 'complete' : 'invalid', warmup, validCount, samples, summary: summarize(samples) };
        if (validCount < sampleTarget) report.blockers.push(`${route.path}: only ${validCount}/${sampleTarget} valid samples.`);
    }
} catch (error) {
    report.blockers.push(error instanceof Error ? error.message : String(error));
} finally {
    if (browser) await browser.close();
    if (userDataDir) await rm(userDataDir, { recursive: true, force: true });
    await mkdir(dirname(outputPath), { recursive: true });
    await writeFile(outputPath, `${JSON.stringify(report, null, 2)}\n`);
}

console.log(`Frontend performance baseline written to ${outputPath}`);
for (const [key, route] of Object.entries(report.routes)) console.log(`${key}: ${route.status}${route.validCount !== undefined ? ` (${route.validCount}/${sampleTarget} valid)` : ''}`);
if (report.blockers.length) {
    console.error(`Blocked: ${report.blockers.join(' | ')}`);
    process.exitCode = 1;
}
