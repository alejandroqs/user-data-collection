/** Optional isolated Chromium check; requires Playwright. Generate fixtures: php tests/forms.php --preview */
const { chromium } = require('playwright');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn } = require('node:child_process');
(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const origin = 'http://127.0.0.1:18761';
    const server = spawn('php', ['-S', '127.0.0.1:18761', 'tests/fixtures/forms-router.php'], {cwd: path.join(__dirname, '..'), windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
    try {
        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('PHP test server did not start')), 5000);
            server.once('error', reject);
            server.stderr.on('data', chunk => { if (chunk.toString().includes('started')) { clearTimeout(timer); resolve(); } });
            server.once('exit', code => { clearTimeout(timer); reject(new Error('PHP test server exited: ' + code)); });
        });
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const preview = fs.readFileSync(path.join(os.tmpdir(), 'udc-forms-preview.html'), 'utf8').replaceAll('https://example.test/wp-admin/admin-post.php', origin + '/download');
        await page.setContent(preview);
        const duplicateIds = await page.locator('[id]').evaluateAll(elements => elements.length - new Set(elements.map(el => el.id)).size);
        if (duplicateIds) throw new Error('Duplicate HTML IDs across the two form types');
        for (const width of [320, 390, 782, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
            if (overflow) throw new Error('Horizontal overflow at ' + width);
            if (await page.locator('select').count() !== 11) throw new Error('Expected ten health selects and one category select');
            for (const form of await page.locator('form').all()) {
                if (await form.evaluate(el => el.checkValidity())) throw new Error('Empty form must not pass browser validation');
            }
            const newForm = page.locator('.udc-piercing-form');
            await newForm.locator('select[name=udc_health_allergies]').selectOption('2');
            if (await newForm.locator('select[name=udc_health_allergies]').inputValue() !== '2') throw new Error('Discuss option failed');
            await newForm.locator('select[name=udc_health_allergies]').selectOption('');
            const summary = newForm.locator('summary').first();
            await summary.focus(); await page.keyboard.press('Enter');
            if (!(await newForm.locator('details').first().evaluate(el => el.open))) throw new Error('Keyboard disclosure failed');
            await page.keyboard.press('Enter');
            if (width === 390) {
                await newForm.evaluate(el => el.scrollIntoView({block:'start'}));
                await page.screenshot({path:path.join(os.tmpdir(),'udc-piercing-top.png')});
                await newForm.locator('button[type=submit]').scrollIntoViewIfNeeded();
                await page.screenshot({path:path.join(os.tmpdir(),'udc-piercing-bottom.png')});
            }
            const unlabelled = await page.locator('input:not([type=hidden]),select,button').evaluateAll(elements => elements.filter(el => el.tagName !== 'BUTTON' && !el.labels?.length).length);
            if (unlabelled) throw new Error('Found unlabelled controls');
            const markerErrors = await page.locator('input:not([type=hidden]),select').evaluateAll(elements => elements.filter(el => {
                const label = Array.from(el.labels || []).map(item => item.textContent.trim()).join(' ');
                return el.required ? !label.endsWith('*') : label.endsWith('*');
            }).map(el => el.name));
            if (markerErrors.length) throw new Error('Required/optional markers disagree: ' + markerErrors.join(', '));
            for (const form of await page.locator('.udc-form,.udc-piercing-form').all()) {
                for (const field of ['address','zip']) {
                    if (await form.locator('[name=udc_'+field+']').evaluate(el => el.required)) throw new Error('Optional field is required: '+field);
                }
            }
            await page.screenshot({ path: path.join(os.tmpdir(), 'udc-forms-' + width + '.png'), fullPage: true });
            console.log('PASS: ' + width + 'px layout, labels, explicit answers and keyboard disclosures');
        }
        const careResponse = await page.request.get(origin + '/download?action=udc_piercing_care&category=oral&language=fr_FR');
        if (careResponse.status() !== 200 || !careResponse.headers()['content-disposition']?.includes('piercing-care-oral-fr_FR.pdf') || careResponse.headers()['content-type'] !== 'application/pdf' || careResponse.headers()['x-content-type-options'] !== 'nosniff') throw new Error('Care download headers/status failed');
        if (!(await careResponse.body()).subarray(0, 5).equals(Buffer.from('%PDF-'))) throw new Error('Care endpoint emitted unexpected output');
        const generic = await page.request.get(origin + '/download?action=udc_download_document&document=piercing-care&category=oral&language=fr_FR');
        if (generic.status() !== 200 || generic.headers()['content-type'] !== 'application/pdf') throw new Error('Reusable document endpoint failed');
        if ((await page.request.get(origin + '/download?action=udc_download_document&document=unknown&language=fr_FR')).status() !== 400) throw new Error('Unknown public provider accepted');
        for (const query of ['category=invalid&language=fr_FR', 'category=oral&language=invalid']) {
            if ((await page.request.get(origin + '/download?' + query)).status() !== 400) throw new Error('Invalid download request was not rejected');
        }
        const localized = await page.request.get(origin + '/download?category=oral&language=en&simulate_polylang=1');
        if (localized.status() !== 200 || !localized.headers()['content-disposition']?.includes('piercing-care-oral-en.pdf') || !(await localized.body()).subarray(0, 5).equals(Buffer.from('%PDF-'))) throw new Error('Localized PDF download failed');
        console.log('PASS: Care download maps a Polylang slug to the requested gettext locale (synthetic)');
        console.log('PASS: Synthetic PHP download endpoint returns attachment PDF and rejects invalid category/language');
        const downloadEvent = page.waitForEvent('download');
        await page.locator('.udc-piercing-form details').first().evaluate(el => el.open = true);
        await page.locator('.udc-piercing-form details a').first().click();
        const download = await downloadEvent;
        if (download.suggestedFilename() !== 'piercing-care-lobe-fr_FR.pdf') throw new Error('Unexpected download filename');
        await download.saveAs(path.join(os.tmpdir(), 'udc-downloaded-care.pdf'));
        console.log('PASS: Browser downloads the selected standalone care document');
        const tattooDownloadEvent = page.waitForEvent('download');
        await page.locator('.udc-form .udc-care-instructions a').click();
        const tattooDownload = await tattooDownloadEvent;
        if (tattooDownload.suggestedFilename() !== 'tattoo-care-fr_FR.pdf') throw new Error('Unexpected tattoo download filename');
        await tattooDownload.saveAs(path.join(os.tmpdir(), 'udc-downloaded-tattoo-care.pdf'));
        console.log('PASS: Browser downloads tattoo care from the original form');
        const newForm = page.locator('.udc-piercing-form');
        for (const [name, value] of Object.entries({last_name:'Example',first_name:'Synthetic',dob:'1990-01-01',address:'Test address',city:'Test city',zip:'1000',phone:'5550100',appointment_date:'2026-11-01',appointment_time:'12:30',piercing_location:'Synthetic oral piercing'})) {
            await newForm.locator('[name=udc_' + name + ']').fill(value);
        }
        await newForm.locator('[name=udc_dob]').fill('2999-01-01');
        if (!(await newForm.locator('[name=udc_dob]').evaluate(el => el.validity.rangeOverflow))) throw new Error('Piercing DOB lacks its upper bound');
        await newForm.locator('[name=udc_dob]').fill('1990-01-01');
        await newForm.locator('[name=udc_address]').fill('');
        await newForm.locator('[name=udc_zip]').fill('');
        for (const select of await newForm.locator('select[name^=udc_health_]').all()) await select.selectOption('2');
        await newForm.locator('[name=udc_piercing_category]').selectOption('oral');
        for (const checkbox of await newForm.locator('input[type=checkbox]').all()) await checkbox.check();
        if (!(await newForm.evaluate(el => el.checkValidity()))) throw new Error('Complete synthetic form fails browser validation');
        const submitState = await newForm.evaluate(form => {
            const first = form.dispatchEvent(new Event('submit', {bubbles:true,cancelable:true}));
            const second = form.dispatchEvent(new Event('submit', {bubbles:true,cancelable:true}));
            const busy = form.getAttribute('aria-busy') === 'true' && form.querySelector('button[type=submit]').disabled;
            window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted:true}));
            return {first,second,busy,reset:!form.querySelector('button[type=submit]').disabled && !form.hasAttribute('aria-busy')};
        });
        if (!submitState.first || submitState.second || !submitState.busy || !submitState.reset) throw new Error('Submit locking or Back restoration failed');
        console.log('PASS: Duplicate browser submits are blocked and Back navigation restores the controls');
        let posts = 0;
        await page.route(origin + '/download', async route => {
            if (route.request().method() === 'POST') {
                posts++;
                const body = new URLSearchParams(route.request().postData());
                if (body.get('udc_health_allergies') !== '2') throw new Error('Explicit health value was not submitted');
                await route.fulfill({status:200,contentType:'text/html',body:'<p>Synthetic browser submission received</p>'});
            } else await route.continue();
        });
        await Promise.all([
            page.waitForNavigation({waitUntil:'load'}),
            newForm.evaluate(form => { form.requestSubmit(); form.requestSubmit(); })
        ]);
        await page.waitForLoadState('load');
        if (posts !== 1) throw new Error('Piercing duplicate request reached the network');
        await page.setContent(preview);
        const original = page.locator('.udc-form');
        for (const [name,value] of Object.entries({last_name:'Example',first_name:'Synthetic',dob:'1990-01-01',address:'Test address',city:'Test city',zip:'1000',phone:'5550100',appointment_date:'2026-11-01',appointment_time:'12:30',piercing_location:'Synthetic service'})) await original.locator('[name=udc_'+name+']').fill(value);
        for (const select of await original.locator('select').all()) await select.selectOption('2');
        await original.locator('[name=udc_address]').fill('');
        await original.locator('[name=udc_zip]').fill('');
        await original.locator('input[type=checkbox]').check();
        if (!(await original.evaluate(form => form.checkValidity()))) throw new Error('Complete original form fails browser validation');
        await original.locator('[name=udc_dob]').fill('2999-01-01');
        if (!(await original.locator('[name=udc_dob]').evaluate(el => el.validity.rangeOverflow))) throw new Error('Original DOB lacks its upper bound');
        await original.locator('[name=udc_dob]').fill('1990-01-01');
        await Promise.all([
            page.waitForNavigation({waitUntil:'load'}),
            original.evaluate(form => { form.requestSubmit(); form.requestSubmit(); })
        ]);
        await page.waitForLoadState('load');
        if (posts !== 2) throw new Error('Original form duplicate request reached the network');
        console.log('PASS: Both completed forms send exactly one browser POST despite repeated submit attempts');
        await page.setContent(fs.readFileSync(path.join(os.tmpdir(), 'udc-forms-design-preview.html'), 'utf8'));
        for (const width of [320,390,1280]) {
            await page.setViewportSize({width,height:900});
            if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw new Error('Styled original forms overflow at '+width);
            await page.locator('input[name=udc_last_name]').first().focus();
            await page.keyboard.press('Tab');
            const focusVisible = await page.evaluate(() => { const style=getComputedStyle(document.activeElement); return style.outlineStyle !== 'none' && parseFloat(style.outlineWidth)>0; });
            if (!focusVisible) throw new Error('Styled original form hides keyboard focus');
        }
        const repeatedIds = await page.locator('[id]').evaluateAll(elements => elements.length-new Set(elements.map(el=>el.id)).size);
        if (repeatedIds) throw new Error('Repeated original shortcodes duplicate IDs');
        await page.screenshot({path:path.join(os.tmpdir(),'udc-review-styled.png'),fullPage:true});
        console.log('PASS: Repeated styled original forms retain unique IDs and keyboard focus at mobile/desktop widths');
        if (errors.length) throw new Error(errors.join('\n'));
        console.log('PASS: Complete synthetic piercing form satisfies browser validation');
        await page.setContent(fs.readFileSync(path.join(os.tmpdir(), 'udc-care-oral-preview.html'), 'utf8'));
        await page.setViewportSize({width:320,height:900});
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw new Error('Care download overflows');
        await page.screenshot({path:path.join(os.tmpdir(),'udc-care-oral.png'),fullPage:true});
        console.log('PASS: Standalone care document renders at 320px without page errors');
    } finally { server.kill(); await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
