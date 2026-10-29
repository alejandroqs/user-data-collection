/** Real isolated WordPress only; launched by upgrade-package.py --browser. */
const {chromium} = require('playwright');
const path = require('node:path');
const os = require('node:os');
(async function () {
    const [origin, pageId] = process.argv.slice(2);
    if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(origin)) throw new Error('Only an isolated loopback site is allowed');
    const browser = await chromium.launch({channel:'msedge',headless:true});
    try {
        const admin = await browser.newPage();
        await admin.goto(origin+'/wp-login.php');
        await admin.locator('#user_login').fill('synthetic_admin');
        await admin.locator('#user_pass').fill('synthetic-local-only-password');
        await Promise.all([admin.waitForNavigation(),admin.locator('#wp-submit').click()]);
        await admin.goto(origin+'/wp-admin/admin.php?page=udc-settings&tab=general');
        if (await admin.locator('.udc-media-setting').count() !== 5) throw new Error('General settings must contain five language selectors');
        await admin.locator('.udc-media-select').first().click();
        await admin.locator('.media-modal').waitFor();
        const libraryTab=admin.getByRole('tab',{name:'Media Library',exact:true});
        if (await libraryTab.count()) await libraryTab.click();
        await admin.locator('.media-modal .attachment').first().click();
        await admin.locator('.media-modal .media-button-select').click();
        await Promise.all([admin.waitForNavigation(),admin.locator('#submit').click()]);
        if (!(await admin.locator('[name=udc_guardian_document_fr]').inputValue()).match(/^[1-9]\d*$/)) throw new Error('Native media selection did not persist');
        await admin.screenshot({path:path.join(os.tmpdir(),'udc-general-native.png'),fullPage:true});
        console.log('PASS: Real WordPress General settings, media modal and Settings API save');
        const context=await browser.newContext();
        const page=await context.newPage();
        const errors=[]; page.on('pageerror',error=>errors.push(error.message));
        for (const selector of ['.udc-form','.udc-piercing-form']) {
            await page.goto(origin+'/?page_id='+pageId);
            const form=page.locator(selector);
            for (const [field,value] of Object.entries({last_name:'Synthetic',first_name:'Browser',dob:'2010-01-01',phone:'5550100',appointment_date:'2026-11-01',appointment_time:'12:30',piercing_location:'Synthetic service'})) await form.locator('[name=udc_'+field+']').fill(value);
            for (const select of await form.locator('select[name^=udc_health_]').all()) await select.selectOption('2');
            if (selector.includes('piercing')) await form.locator('[name=udc_piercing_category]').selectOption('oral');
            for (const checkbox of await form.locator('input[type=checkbox][required]').all()) await checkbox.check();
            await form.locator('[name=udc_dob]').dispatchEvent('change');
            if (await form.evaluate(el=>el.checkValidity())) throw new Error('Native minor passed without declaration');
            const errorGap=await form.evaluate(el=>el.querySelector('.udc-minor').nextElementSibling.getBoundingClientRect().top-el.querySelector('.udc-minor-error').getBoundingClientRect().bottom);
            if(errorGap<23.9) throw new Error('Minor error lacks separation before the health heading: '+errorGap);
            if (await form.locator('button[type=submit]').isDisabled()) throw new Error('Native rejection left submit disabled');
            await form.locator('[name=udc_minor_declared]').check();
            if (!(await form.locator('.udc-minor-notice').isVisible())) throw new Error('Native guardian notice did not expand');
            const downloading=page.waitForEvent('download');
            await form.locator('.udc-minor-notice .udc-download').click();
            const download=await downloading;
            if (download.suggestedFilename()!=='guardian-authorization-en.pdf') throw new Error('Native guardian download filename mismatch');
            const response=await page.request.get(await form.locator('.udc-download').getAttribute('href'));
            if (response.status()!==200 || response.headers()['content-type']!=='application/pdf' || response.headers()['x-content-type-options']!=='nosniff' || !(await response.body()).subarray(0,5).equals(Buffer.from('%PDF-'))) throw new Error('Native document response is invalid');
            await Promise.all([page.waitForNavigation(),form.locator('button[type=submit]').click()]);
            if (!page.url().includes('status=success')) throw new Error('Native form was not saved: '+page.url());
            console.log('PASS: Native anonymous '+selector+' minor validation, original document download and submission');
        }
        if (errors.length) throw new Error(errors.join('\n'));
        for(const slug of ['udc-submissions','udc-piercings']) {
            await admin.goto(origin+'/wp-admin/admin.php?page='+slug);
            if(await admin.locator('#udc-filter thead th').count()!==9 || (await admin.locator('#udc-filter thead th').last().innerText()).trim()!=='Is a minor') throw new Error('Native table does not end with the minority column');
            const row=admin.locator('#the-list tr').filter({has:admin.locator('.udc-minority-yes')}).first();
            if(await row.locator('.udc-minority-yes').innerText()!=='Yes') throw new Error('Saved native minor lacks a Yes badge');
            for(const width of [390,1280]) {
                await admin.setViewportSize({width,height:900});
                if(width===390) await row.locator('.toggle-row').click();
                if(!(await row.locator('.udc-minority-yes').isVisible())) throw new Error('Minority badge is not accessible in native mobile row expansion');
                if(await admin.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw new Error('Minority column introduced horizontal overflow');
                await admin.screenshot({path:path.join(os.tmpdir(),'udc-minority-'+slug+'-'+width+'.png'),fullPage:true});
            }
            const style=await row.locator('.udc-minority-yes').evaluate(el=>getComputedStyle(el).backgroundColor);
            if(style!=='rgb(166, 27, 27)') throw new Error('Minor badge is not red');
            if(slug==='udc-submissions' && await admin.locator('#the-list .udc-minority-no').count()<1) throw new Error('Legacy adult lacks its calculated No badge');
            await row.hover();
            await row.locator('a[href*="action=view"]').click();
            const dob=admin.locator('tr').filter({has:admin.getByRole('cell',{name:'2010-01-01',exact:true})});
            const minorityRow=dob.locator('xpath=following-sibling::tr[1]');
            if(!(await minorityRow.innerText()).includes('Is a minor:') || !(await minorityRow.locator('.udc-minority-yes').count())) throw new Error('Native detail minority badge is not below date of birth');
            console.log('PASS: Native '+slug+' ninth column, red/green badges, mobile row expansion and detail placement');
        }
        await context.close();
    } finally { await browser.close(); }
}()).catch(error=>{console.error(error);process.exitCode=1;});
