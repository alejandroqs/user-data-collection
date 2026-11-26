/** Synthetic browser checks. Generate the script with php tests/forms.php --preview. */
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
(async () => {
    const html = fs.readFileSync(path.join(os.tmpdir(), 'udc-forms-preview.html'), 'utf8');
    const script = html.match(/function udcShowResult\(\)[\s\S]*?(?=<\/script>)/)[0];
    const browser = await chromium.launch({channel: 'msedge', headless: true});
    const page = await browser.newPage();
    let requests = [], posts = [], mode = 'success', otherNonce;
    await page.route('https://example.test/**', async route => {
        const request = route.request();
        if (request.url().endsWith('/ajax')) {
            requests.push(request.postData());
            otherNonce = await page.locator(`[data-udc-type=${request.postData().includes('form_type=tattoo') ? 'piercing' : 'tattoo'}] [name=udc_form_nonce]`).inputValue();
            if (mode === 'network') return route.abort();
            if (mode === 'html') return route.fulfill({status: 200, contentType: 'text/html', body: '<html></html>'});
            return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify({success: true, data: {nonce: 'fresh'}})});
        }
        if (request.url().endsWith('/post')) { posts.push(request.postData()); return route.fulfill({status: 200, body: 'ok'}); }
        return route.fulfill({status: 200, contentType: 'text/html', body: '<html></html>'});
    });
    async function setup() {
        await page.goto('https://example.test/');
        await page.setContent(['tattoo','piercing'].map(type => `<form class="${type === 'tattoo' ? 'udc-form' : 'udc-piercing-form'}" data-udc-type="${type}" data-udc-ajax="https://example.test/ajax" data-udc-error="Retry" action="https://example.test/post" method="post"><input name="udc_form_nonce" value="invalid"><input name="answer" value="kept" required><input type="checkbox" name="consent" value="1" checked><button type="submit">Send</button></form>`).join(''));
        await page.addScriptTag({content: script});
        requests = []; posts = [];
    }
    try {
        for (const type of ['tattoo', 'piercing']) {
            mode = 'success'; await setup();
            const form = page.locator(`[data-udc-type=${type}]`);
            await form.locator('button').dblclick({timeout: 3000}).catch(() => {});
            await page.waitForURL('**/post');
            if (requests.length !== 1 || posts.length !== 1 || otherNonce !== 'invalid' || !requests[0].includes('form_type='+type) || requests[0].includes('answer') || !posts[0].includes('udc_form_nonce=fresh') || !posts[0].includes('answer=kept')) throw new Error('Successful submission failed: '+type);
        }
        for (const failure of ['network', 'html', 'timeout']) {
            mode = failure; await setup();
            if (failure === 'timeout') await page.evaluate(() => {
                const oldTimeout = window.setTimeout; window.setTimeout = (fn, ms) => oldTimeout(fn, ms === 10000 ? 30 : ms);
                window.originalFetch = window.fetch;
                window.fetch = (url, options) => new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('aborted'))));
            });
            const form = page.locator('[data-udc-type=tattoo]');
            await form.locator('input[name=answer]').focus(); await page.keyboard.press('Enter');
            await form.locator('[role=alert]').getByText('Retry').waitFor();
            if (posts.length || await form.locator('[name=answer]').inputValue() !== 'kept' || !await form.locator('[name=consent]').isChecked() || await form.locator('button').isDisabled()) throw new Error('Failure state: '+failure);
            if (failure === 'timeout') await page.evaluate(() => { window.fetch = window.originalFetch; });
            mode = 'success'; await form.locator('button').click(); await page.waitForURL('**/post'); if (posts.length !== 1) throw new Error('Retry failed');
        }
        mode = 'network'; await setup();
        await page.evaluate(() => { const f=document.querySelector('form'); f.dataset.udcSubmitting='sent'; f.setAttribute('aria-busy','true'); const b=f.querySelector('button'); b.disabled=true; b.dataset.udcDisabled='1'; window.dispatchEvent(new Event('pageshow')); });
        if (await page.locator('form').first().locator('button').isDisabled()) throw new Error('Back restoration failed');
        console.log('PASS: both forms, duplicate activation, keyboard, failure, timeout, retry and Back restoration');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
