const puppeteer = require('puppeteer');

(async () => {
  const base = process.env.TEST_BASE_URL || 'http://localhost';
  const appBase = process.env.TEST_APP_BASE || '/';
  const host = base.replace(/\/$/, '');
  const app = (appBase === '/') ? '' : appBase.replace(/\/$/, '');
  const loginUrl = host + app + '/users/login';
  const testUser = process.env.TEST_USER_EMAIL || '';
  const testPass = process.env.TEST_USER_PASSWORD || '';
  if (!testUser || !testPass) {
    console.error('Missing TEST_USER_EMAIL/TEST_USER_PASSWORD');
    process.exit(2);
  }

  const browser = await puppeteer.launch({ args: ['--no-sandbox','--disable-setuid-sandbox'] });
  const page = await browser.newPage();
  page.setDefaultTimeout(15000);

  page.on('request', req => {
    console.log('REQ:', req.method(), req.url());
  });
  page.on('response', res => {
    console.log('RES:', res.status(), res.url());
  });

  try {
    console.log('Opening login page', loginUrl);
    await page.goto(loginUrl, { waitUntil: 'networkidle2' });
    await page.waitForSelector('#email');
    await page.type('#email', testUser, { delay: 20 });
    await page.type('#password', testPass, { delay: 20 });
      // Log the form action attribute vs resolved action
      try {
        const formAttr = await page.$eval('form', f => f.getAttribute('action'));
        const formResolved = await page.$eval('form', f => f.action);
        console.log('Form action attribute:', formAttr);
        console.log('Form resolved action:', formResolved);
      } catch (e) { console.error('Failed to read form action', e); }
    console.log('Click submit');
    await Promise.all([
      page.click('button[type="submit"], input[type="submit"]'),
      page.waitForNavigation({ timeout: 10000 }).catch(() => null),
    ]);
    console.log('Done, final URL', page.url());
  } catch (err) {
    console.error('Error during trace:', err);
  } finally {
    await browser.close();
  }
})();
