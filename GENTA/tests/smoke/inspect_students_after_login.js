const puppeteer = require('puppeteer');

(async () => {
  const base = process.env.TEST_BASE_URL || 'http://localhost';
  const appBase = process.env.TEST_APP_BASE || '/';
  const host = base.replace(/\/$/, '');
  const app = (appBase === '/') ? '' : appBase.replace(/\/$/, '');
  const studentsUrl = host + app + '/teacher/dashboard/students';
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

  try {
    console.log('Logging in at', loginUrl);
    await page.goto(loginUrl, { waitUntil: 'networkidle2' });
    await page.waitForSelector('#email');
    await page.type('#email', testUser, { delay: 20 });
    await page.type('#password', testPass, { delay: 20 });
    await Promise.all([
      page.click('button[type="submit"], input[type="submit"]'),
      page.waitForNavigation({ timeout: 10000 }).catch(() => null),
    ]);

    console.log('Visiting students page', studentsUrl);
    await page.goto(studentsUrl, { waitUntil: 'networkidle2' });
    const finalUrl = page.url();
    console.log('Final URL:', finalUrl);
    const html = await page.content();
    console.log('HTML length:', html.length);
    // print a slice that includes where the button would be
    const idx = html.indexOf('btn-add-student');
    if (idx === -1) {
      console.log('btn-add-student not present in HTML. Printing first 4000 chars for inspection:');
      console.log(html.slice(0, 4000));
    } else {
      console.log('btn-add-student found at index', idx);
      console.log(html.slice(Math.max(0, idx-200), idx+800));
    }
  } catch (err) {
    console.error('Error:', err);
  } finally {
    await browser.close();
  }
})();
