const puppeteer = require('puppeteer');

(async () => {
  const base = process.env.TEST_BASE_URL || 'http://localhost';
  const appBase = process.env.TEST_APP_BASE || '/';
  const host = base.replace(/\/$/, '');
  const app = (appBase === '/') ? '' : appBase.replace(/\/$/, '');
  const studentsUrl = host + app + '/teacher/dashboard/students';
  console.log('Fetching URL:', studentsUrl);

  const browser = await puppeteer.launch({ args: ['--no-sandbox','--disable-setuid-sandbox'] });
  const page = await browser.newPage();

  try {
    await page.goto(studentsUrl, { waitUntil: 'networkidle2' });
    const url = page.url();
    console.log('Final URL after navigation:', url);
    const html = await page.content();
    console.log('HTML length:', html.length);
    console.log('HTML snippet (first 2000 chars):\n', html.slice(0,2000));
  } catch (err) {
    console.error('Error fetching page:', err);
  } finally {
    await browser.close();
  }
})();
