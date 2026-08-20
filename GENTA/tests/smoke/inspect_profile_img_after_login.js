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
  page.setDefaultTimeout(20000);

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
    console.log('Submitting');
    await Promise.all([
      page.click('button[type="submit"], input[type="submit"]'),
      page.waitForNavigation({ timeout: 15000 }).catch(() => null),
    ]);
    console.log('After login, final URL', page.url());
    // Wait a bit for sidebar to render (support older puppeteer versions)
    if (typeof page.waitForTimeout === 'function') {
      await page.waitForTimeout(800);
    } else {
      await new Promise((r) => setTimeout(r, 800));
    }
    const imgInfo = await page.evaluate(() => {
      const img = document.querySelector('#sidebar .nav-profile-image img');
      if (!img) return { found: false };
      return { found: true, attr: img.getAttribute('src'), resolved: img.src, outer: img.outerHTML };
    });
    console.log('Profile image info:', imgInfo);
  } catch (err) {
    console.error('Error during inspect:', err);
  } finally {
    await browser.close();
  }
})();
