const puppeteer = require('puppeteer');

(async () => {
  // Use TEST_BASE_URL for the host (e.g. 'http://192.168.68.97') and
  // TEST_APP_BASE for the app subpath (e.g. '/GENTA'). Prefer an explicit
  // env var; fall back to '/' so tests run in root-deployed setups too.
  const base = process.env.TEST_BASE_URL || 'http://localhost';
  const appBase = process.env.TEST_APP_BASE || '/';
  // Normalise and join parts safely
  const host = base.replace(/\/$/, '');
  const app = (appBase === '/') ? '' : appBase.replace(/\/$/, '');
  const studentsUrl = host + app + '/teacher/dashboard/students';
  console.log('Starting headless smoke test for idle-expiry behavior');
  console.log('Target students URL:', studentsUrl);

  const browser = await puppeteer.launch({ args: ['--no-sandbox','--disable-setuid-sandbox'] });
  const page = await browser.newPage();
  page.setDefaultTimeout(10000);

  // Support automated login if credentials are supplied via env vars
  const testUser = process.env.TEST_USER_EMAIL || '';
  const testPass = process.env.TEST_USER_PASSWORD || '';

  if (!testUser || !testPass) {
    console.error('Missing TEST_USER_EMAIL and/or TEST_USER_PASSWORD environment variables.');
    console.error('To run end-to-end, set these and re-run. Example (PowerShell):');
    console.error("$env:TEST_BASE_URL='http://192.168.0.107'; $env:TEST_APP_BASE='/GENTA'; $env:TEST_USER_EMAIL='test@example.com'; $env:TEST_USER_PASSWORD='secret'; node tests/smoke/idle-expiry-smoke.js");
    await browser.close();
    process.exit(4);
  }

  try {
    // Log in first
    const loginUrl = host + app + '/users/login';
    console.log('Logging in at:', loginUrl);
    await page.goto(loginUrl, { waitUntil: 'networkidle2' });

    // Fill the login form — the form uses 'email' and 'password' ids
    await page.waitForSelector('#email');
    await page.type('#email', testUser, { delay: 20 });
    await page.type('#password', testPass, { delay: 20 });
    // Submit and wait for navigation back to app
    await Promise.all([
      page.click('button[type="submit"], input[type="submit"]'),
      page.waitForNavigation({ timeout: 10000 }).catch(() => null),
    ]);

    // Now navigate to the students page as an authenticated user
    console.log('Visiting students page as authenticated user:', studentsUrl);
    await page.goto(studentsUrl, { waitUntil: 'networkidle2' });
    console.log('Loaded students page');

    // Ensure the Add Student button exists
    const addSel = 'a.btn-add-student';
    await page.waitForSelector(addSel, { timeout: 5000 });
    console.log('Add Student button found — clicking it to open modal (simulating AJAX modal open while authenticated)');

    // Listen for navigation — the client code reloads the page when login HTML is returned
    const navPromise = page.waitForNavigation({ timeout: 5000 }).catch(() => null);

    await page.click(addSel);

    const navigation = await navPromise;
    if (navigation) {
      console.log('Navigation detected — page reloaded as expected (session expiry handling).');
      console.log('New URL:', page.url());
      await browser.close();
      process.exit(0);
    }

    // If no navigation, inspect modal body content to see if login form was injected (bad)
    const modalBodySel = '#studentModal .modal-body';
    await page.waitForSelector(modalBodySel, { timeout: 5000 });
    const modalHtml = await page.$eval(modalBodySel, el => el.innerHTML);

    if (/form[^>]+action=["'][^"']*\/users?\/login["']/i.test(modalHtml) || /name=["']?username["']?/i.test(modalHtml) || /name=["']?password["']?/i.test(modalHtml)) {
      console.error('Login form detected inside modal — client did NOT reload top-level page (FAILED).');
      console.error('Modal HTML snippet:', modalHtml.slice(0, 400));
      await browser.close();
      process.exit(2);
    }

    console.log('No navigation and no login form detected inside modal. Modal content appears to have loaded normally.');
    await browser.close();
    process.exit(0);
  } catch (err) {
    console.error('Smoke test encountered an error:', err);
    try { await browser.close(); } catch(e){}
    process.exit(3);
  }
})();
