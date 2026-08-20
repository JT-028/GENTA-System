// Lightweight presence marker and initial debug log so we can detect whether the file was loaded.
try { window.__mascotScriptPresent = true; if (typeof console !== 'undefined' && console.info) console.info('[mascot] script file loaded'); } catch (e) {}

(function(){
  // Combined loader + interactivity for the mascot
  // Note: we intentionally do NOT create separate pupil circles for this mascot.
  // The "eyes" in the provided SVG are rounded rectangles (`left_eye` / `right_eye`)
  // and should themselves be translated slightly to simulate pupil movement.

  function initInteractivity() {
  if (window.__gentaMascotInitialized) return; // idempotent
  window.__gentaMascotInitialized = true;
  try { console.info('[mascot] initInteractivity start'); } catch(e){}

  var email = document.getElementById('email');
    var password = document.getElementById('password');
  var firstName = document.getElementById('first_name');
  var lastName = document.getElementById('last_name');
    try { console.info('[mascot] inputs found:', { email: !!email, firstName: !!firstName, lastName: !!lastName, password: !!password }); } catch(e){}
    var container = document.getElementById('genta-mascot-container');
    if (!container) return;

    var svg = container.querySelector('svg');
    if (!svg) return;
  try { console.info('[mascot] svg element present in container'); } catch(e){}

  // eye groups present in mascot_head.svg
  var openGroup = svg.querySelector('#open_eyes');
  var closedGroup = svg.querySelector('#closed_eyes');
  var peakGroup = svg.querySelector('#peak_eyes');
  // optional group for a dedicated wrong-password expression
  var wrongGroup = svg.querySelector('#wrong_pass_eyes');
  // optional group for happy expression (used on successful register/login)
  var happyGroup = svg.querySelector('#happy_eyes');
  // optional group for pending expression (used when account is unverified/pending)
  var pendingGroup = svg.querySelector('#pending_eyes');

  // Helper: ensure a pending_eyes group exists inside the loaded SVG. If missing,
  // create one with three circles so the pending animation is always available.
  function ensurePendingGroup() {
    try {
      if (pendingGroup && pendingGroup.parentNode) return pendingGroup;
      // try to locate again in case of timing
      pendingGroup = svg.querySelector('#pending_eyes');
      if (pendingGroup && pendingGroup.parentNode) return pendingGroup;
      // create group and three circles positioned roughly where the eyes are in the SVG
      var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
      g.setAttribute('id', 'pending_eyes');
      g.setAttribute('data-name', 'pending eyes');
  // Increased spacing between dots for better legibility
  var cx1 = 117.06, cx2 = 139.06, cx3 = 161.06, cy = 140, r = 6.5;
      var c1 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      c1.setAttribute('class', 'pending-dot'); c1.setAttribute('cx', cx1); c1.setAttribute('cy', cy); c1.setAttribute('r', r);
      var c2 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      c2.setAttribute('class', 'pending-dot'); c2.setAttribute('cx', cx2); c2.setAttribute('cy', cy); c2.setAttribute('r', r);
      var c3 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      c3.setAttribute('class', 'pending-dot'); c3.setAttribute('cx', cx3); c3.setAttribute('cy', cy); c3.setAttribute('r', r);
      g.appendChild(c1); g.appendChild(c2); g.appendChild(c3);
      // Append near the face group if present, otherwise append to svg root
      var face = svg.querySelector('#face') || svg;
      face.appendChild(g);
      pendingGroup = g;
      return pendingGroup;
    } catch (e) { return pendingGroup; }
  }

  // Diagnostic logger (disabled by default). Enable by setting window.__mascotDebug = true
  // or appending ?mascotDebug=1 to the URL. Logs are kept in window.__mascotEventLog (capped).
  (function(){
    try {
      var debugOn = false;
      try {
        if (window.__mascotDebug) debugOn = true;
        else if (location && location.search && /[?&]mascotDebug=1/.test(location.search)) debugOn = true;
      } catch(e) {}
      window.__mascotEventLog = window.__mascotEventLog || [];
      window.__mascotLog = function() {
        if (!debugOn) return;
        try {
          var args = Array.prototype.slice.call(arguments);
          var entry = { t: Date.now(), s: (new Date()).toISOString(), args: args };
          window.__mascotEventLog.push(entry);
          if (window.__mascotEventLog.length > 200) window.__mascotEventLog.shift();
          console.debug.apply(console, ['[mascot]'].concat(args));
        } catch (e) {}
      };
      window.__dumpMascotLog = function() { return (window.__mascotEventLog || []).slice(); };
    } catch (e) { window.__mascotLog = function(){}; window.__dumpMascotLog = function(){ return []; }; }
  })();

    // open eyes rects (to support subtle caret-following)
    var openLeft = null;
    var openRight = null;
    try {
      if (openGroup) {
        // Try the most specific IDs first, then more generic patterns, then fall back to children
        openLeft = openGroup.querySelector('#left_eye-3') || openGroup.querySelector('#left_eye') || openGroup.querySelector('[id^="left_eye"]') || openGroup.querySelector('[id*="left_eye"]');
        openRight = openGroup.querySelector('#right_eye-3') || openGroup.querySelector('#right_eye') || openGroup.querySelector('[id^="right_eye"]') || openGroup.querySelector('[id*="right_eye"]');
        // If still not found, try to use the first two visual children of the openGroup
        if (!openLeft || !openRight) {
          try {
            var kids = Array.prototype.slice.call(openGroup.children || []);
            if (!openLeft && kids.length >= 1) openLeft = kids[0];
            if (!openRight && kids.length >= 2) openRight = kids[1] || kids[0];
            if ((!openLeft || !openRight) && kids.length === 1) {
              // if there's only one child, use it for both eyes (best-effort)
              openLeft = openLeft || kids[0];
              openRight = openRight || kids[0];
            }
          } catch (ee) {}
        }
      }
    } catch (e) {}

    // Intercept registration form to check password confirmation client-side
    // NOTE: Password match checking is now handled by register.php with real-time validation
    // This form submit handler is disabled because custom masking uses bullets (•) in field values
    // The actual password comparison is done in register.php before form submission
    try {
      var registerForm = document.querySelector('form[action*="/Users/register"], form[action*="/users/register"]');
      if (registerForm) {
        // Form validation is handled by HTML5 validation and register.php's real-time checker
        // No need to check password match here since we can't access actual password values
      }
    } catch (e) {}
    try { console.info('[mascot] eye groups:', { openGroup: !!openGroup, closedGroup: !!closedGroup, peakGroup: !!peakGroup, openLeft: !!openLeft, openRight: !!openRight }); } catch(e){}

    // helper: translate the open eye rects to simulate pupils moving inside the rounded squares
    function setPupils(x, y) {
      try {
        var max = 6; // px
        var xx = Math.max(-max, Math.min(max, x));
        var yy = Math.max(-max, Math.min(max, y));
        if (openLeft) {
          try { openLeft.setAttribute('transform', 'translate(' + xx + ',' + yy + ')'); } catch (e) {}
          try { openLeft.style && (openLeft.style.transform = 'translate(' + xx + 'px,' + yy + 'px)'); } catch (e) {}
        }
        if (openRight) {
          try { openRight.setAttribute('transform', 'translate(' + xx + ',' + yy + ')'); } catch (e) {}
          try { openRight.style && (openRight.style.transform = 'translate(' + xx + 'px,' + yy + 'px)'); } catch (e) {}
        }
      } catch (e) {}
    }

    function resetOpenEyes() { setPupils(0,0); }

    // compute caret pixel position in the input (best-effort)
    // Uses a persistent hidden <span> mirror to measure text width up to the caret,
    // includes paddingLeft and page scroll offsets so the returned coordinates are page-accurate.
    function getCaretPixelPos(input) {
      try {
        var style = window.getComputedStyle(input);
        var inputRect = input.getBoundingClientRect();
        var value = input.value || '';
        var pos = (typeof input.selectionStart === 'number') ? input.selectionStart : value.length;

        // Persistent hidden span used as a mirror
        var span = window.__mascotMirrorSpan;
        if (!span) {
          span = document.createElement('span');
          span.id = '__mascot_caret_mirror_span';
          span.setAttribute('aria-hidden', 'true');
          span.style.position = 'absolute';
          span.style.visibility = 'hidden';
          span.style.whiteSpace = 'pre';
          span.style.pointerEvents = 'none';
          span.style.left = '0px';
          span.style.top = '0px';
          span.style.zIndex = '-9999';
          document.body.appendChild(span);
          window.__mascotMirrorSpan = span;
        }

        // Copy font properties so measurement matches the input
        try {
          var mprops = ['fontSize','fontFamily','fontWeight','fontStyle','lineHeight','letterSpacing','textTransform','fontVariant','textIndent'];
          mprops.forEach(function(p){ try { span.style[p] = style[p]; } catch(e){} });
        } catch (e) {}

        // Use a zero-width char to ensure width > 0 when pos === 0
        var text = value.substring(0, pos) || '\u200B';
        // Preserve spaces
        span.textContent = text;

        var width = span.getBoundingClientRect().width;
        var paddingLeft = parseFloat(style.paddingLeft) || 0;
        var scrollLeft = input.scrollLeft || 0;
        var pageX = (window.pageXOffset !== undefined) ? window.pageXOffset : (document.documentElement && document.documentElement.scrollLeft) || 0;
        var pageY = (window.pageYOffset !== undefined) ? window.pageYOffset : (document.documentElement && document.documentElement.scrollTop) || 0;

        var caretX = Math.round(inputRect.left + pageX + paddingLeft + width - scrollLeft);
        var caretY = Math.round(inputRect.top + pageY + inputRect.height / 2);
        return { x: caretX, y: caretY };
      } catch (e) {
        try {
          var fallbackRect = input.getBoundingClientRect();
          var pageX2 = (window.pageXOffset !== undefined) ? window.pageXOffset : (document.documentElement && document.documentElement.scrollLeft) || 0;
          var pageY2 = (window.pageYOffset !== undefined) ? window.pageYOffset : (document.documentElement && document.documentElement.scrollTop) || 0;
          return { x: Math.round(fallbackRect.left + pageX2 + fallbackRect.width / 2), y: Math.round(fallbackRect.top + pageY2 + fallbackRect.height / 2) };
        } catch (ee) {
          return null;
        }
      }
    }

    // email caret follow
    function onEmailInput() {
      window.__mascotLog('onEmailInput');
      if (!openLeft || !openRight || !email) return;
      var c = getCaretPixelPos(email);
      if (!c) { resetOpenEyes(); return; }
      var leftRect = openLeft.getBoundingClientRect();
      var rightRect = openRight.getBoundingClientRect();
      var centerL = { x: leftRect.left + leftRect.width / 2, y: leftRect.top + leftRect.height / 2 };
      var centerR = { x: rightRect.left + rightRect.width / 2, y: rightRect.top + rightRect.height / 2 };
      var dxL = (c.x - centerL.x) / 12;
      var dyL = (c.y - centerL.y) / 12;
      var dxR = (c.x - centerR.x) / 12;
      var dyR = (c.y - centerR.y) / 12;
      setPupils((dxL + dxR) / 2, (dyL + dyR) / 2);
      }

      // generic caret follow for any text input
      function followCaretFor(input) {
        try {
          if (!openLeft || !openRight || !input) return;
          var c = getCaretPixelPos(input);
          // diagnostic: log the first caret measurement to help debug if eyes don't move
          try {
            if (!window.__mascotFirstCaretLogged) {
              window.__mascotFirstCaretLogged = true;
              console.info('[mascot] first caret event for', input && input.id, 'caret=', c);
            }
          } catch (ee) {}
          if (!c) { resetOpenEyes(); return; }
          var leftRect = openLeft.getBoundingClientRect();
          var rightRect = openRight.getBoundingClientRect();
          var centerL = { x: leftRect.left + leftRect.width / 2, y: leftRect.top + leftRect.height / 2 };
          var centerR = { x: rightRect.left + rightRect.width / 2, y: rightRect.top + rightRect.height / 2 };
          var dxL = (c.x - centerL.x) / 12;
          var dyL = (c.y - centerL.y) / 12;
          var dxR = (c.x - centerR.x) / 12;
          var dyR = (c.y - centerR.y) / 12;
          setPupils((dxL + dxR) / 2, (dyL + dyR) / 2);
        } catch (e) {}
      }

      // helper to attach caret-follow behavior to an input element
      function attachCaretFollower(input) {
        if (!input) return;
        try {
          try { console.info('[mascot] attachCaretFollower called for', input && input.id); } catch(e){}
          // mark attached for diagnostics
          try { input.dataset.mascotAttached = '1'; } catch(e){}
          // Force-open the eyes when typing/focusing name/email fields so the
          // mascot follows caret immediately even if a recent wrong_pass lock
          // is still active. This prevents the 'double' effect where wrong_pass
          // lingers until the toast times out while the user is typing elsewhere.
          input.addEventListener('focus', function () { showEyes('open', true); followCaretFor(input); });
          input.addEventListener('input', function () { showEyes('open', true); followCaretFor(input); });
          input.addEventListener('keyup', function () { followCaretFor(input); });
          input.addEventListener('click', function () { followCaretFor(input); });
          input.addEventListener('blur', function () { showEyes('open'); resetOpenEyes(); });
          // Ensure single-click focuses reliably: preempt pointerdown to show open eyes and schedule caret follow
          input.addEventListener('pointerdown', function (e) {
            try {
              window.__mascotLog && window.__mascotLog('input.pointerdown', input && input.id);
              if (document.activeElement !== input) {
                showEyes('open', true);
                setTimeout(function(){ followCaretFor(input); }, 0);
              }
            } catch (err) {}
          });
        } catch (e) {}
      }

  var __mascotSuppressOpenUntil = 0;
  var __mascotForcePeakUntil = 0; // kept for compatibility but not used for persistent peak
  var __mascotToggleInProgress = false;
  var __mascotCurrentEyeState = null;
  var __mascotStateLockUntil = 0; // when set, prevent other states from overriding (used for pending)
    // prepare cross-fade transitions for the eye groups
    var __eyeHideTimers = {};
    function prepareGroupForFade(g) {
      try {
        if (!g) return;
        g.style.transition = 'opacity 220ms ease';
        g.style.opacity = '0';
        g.style.visibility = 'hidden';
        g.style.willChange = 'opacity';
      } catch (e) {}
    }
  prepareGroupForFade(openGroup);
  prepareGroupForFade(closedGroup);
  prepareGroupForFade(peakGroup);
  // ensure wrong-pass group (if present) is hidden initially so it doesn't show beneath others
  prepareGroupForFade(wrongGroup);
  // ensure happy group (if present) is hidden initially
  prepareGroupForFade(happyGroup);
  // ensure pending group (if present) is hidden initially
  prepareGroupForFade(pendingGroup);
    
    // helper: show a minimal in-page toast when Swal (SweetAlert2) is not available
    function _showFallbackToast(txt, type) {
      try {
        var t = document.createElement('div');
        t.className = 'genta-toast';
        t.textContent = txt;
        t.setAttribute('role', 'status');
        var bg = type === 'error' ? '#fee2e2' : (type === 'success' ? '#dcfce7' : '#eef2ff');
        var color = '#0f172a';
        Object.assign(t.style, {
          position: 'fixed',
          top: '16px',
          right: '16px',
          zIndex: 11000,
          background: bg,
          color: color,
          padding: '10px 14px',
          borderRadius: '8px',
          boxShadow: '0 6px 18px rgba(2,6,23,0.08)',
          maxWidth: '340px',
          fontSize: '0.95rem'
        });
        document.body.appendChild(t);
        // auto-dismiss
        setTimeout(function(){ try{ t.style.transition='opacity 300ms'; t.style.opacity='0'; setTimeout(function(){ try{ t.remove(); }catch(e){} }, 300); }catch(e){} }, 3600);
      } catch (e) {}
    }

    // Detect server-side flash elements and show toasts (Swal if available, fallback otherwise).
    function detectAndHandleFlash() {
      try {
  var flashEls = document.querySelectorAll('.alert.alert-danger, .flash-error, .message.error, .alert-danger, .alert.alert-success, .flash-success, .message.success, .alert-success');
        var foundError = false;
        var foundAny = false;
        var SwalToast = null;
        try {
              if (typeof Swal !== 'undefined' && Swal && typeof Swal.mixin === 'function') {
                // Match default toast duration for pending alerts (4000ms)
                SwalToast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true });
              }
        } catch (e) { SwalToast = null; }

        flashEls.forEach(function(el){
          try {
            var txt = (el.textContent || '').trim();
            if (!txt) return;
            foundAny = true;
            var isError = /invalid email|invalid password|invalid email or password|not registered|please register first|error/i.test(txt) || el.classList.contains('alert-danger') || el.classList.contains('flash-error');
            // detect confirm-password mismatch messages specifically
            var isConfirmMismatch = /confirm.*password|password.*confirm|confirm password did not match|passwords do not match/i.test(txt);
            if (isConfirmMismatch) isError = true;
            var isSuccess = el.classList.contains('alert-success') || el.classList.contains('flash-success') || /success|registered|created|saved/i.test(txt);
            if (SwalToast) {
              try {
                // Use the same options as other site toasts (profile page) so appearance matches exactly
                Swal.fire({
                  icon: isError ? 'error' : (isSuccess ? 'success' : 'info'),
                  toast: true,
                  position: 'top-end',
                  showConfirmButton: false,
                  timer: isError ? 3000 : 1800,
                  title: txt,
                });
              } catch (e) {}
            } else {
              _showFallbackToast(txt, isError ? 'error' : (isSuccess ? 'success' : 'info'));
            }
            // If this is a success flash (e.g., registration or profile saved), show happy eyes
            // But don't show happy if wrong_pass was triggered OR if it's an unregistered account message
            var isUnregistered = /not registered|please register first/i.test(txt);
            var isRegistrationSuccess = isSuccess && /registered|you successfully registered|successfully registered a new account/i.test(txt);
            if (isSuccess && !window.__mascotWrongPassTriggered && !isUnregistered) {
              try {
                // For registration-specific success messages, set a flag so
                // initialization won't immediately reset the mascot to 'open'.
                if (isRegistrationSuccess) {
                  try { window.__mascotHappyFlash = true; } catch(e) {}
                }
                try { showEyes('happy', true); } catch(e) {}
              } catch(e) {}
            }
            // Detect pending/unverified account messages and show pending animation
            // Do NOT show pending when the success flash is actually the registration confirmation
            var isPending = /not active|pending admin approval|pending approval|account is not active|awaiting approval/i.test(txt);
            if (isPending && !isRegistrationSuccess) {
              // mark that we found a pending flash so initialization won't override it
              try { window.__mascotPendingFlash = true; } catch(e){}
              try { if (SwalToast) { Swal.fire({ icon: 'info', toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, title: txt }); } else { _showFallbackToast(txt, 'info'); } } catch(e){}
              try { showEyes('pending', true); } catch(e){}
            }
            // hide the server-rendered flash to avoid duplicate messages on-screen
            try { el.style.display = 'none'; } catch (e) {}
            if (isError) {
              // if it's a confirm-mismatch, favor wrong_pass eyes
              if (isConfirmMismatch) {
                try { showEyes('wrong_pass', true); } catch(e){}
              }
              // Check for unregistered account message - force wrong_pass and mark as error
              if (/not registered|please register first/i.test(txt)) {
                try { 
                  showEyes('wrong_pass', true);
                  // Set flag to prevent happy eyes from showing
                  window.__mascotWrongPassTriggered = true;
                } catch(e){}
                foundError = true;
              }
              if (/invalid email|invalid password|invalid email or password/i.test(txt)) {
                try { showEyes('wrong_pass', true); } catch(e){}
                foundError = true;
              }
            }
          } catch(e){}
        });

        // Removed body text scanning as it incorrectly triggers on JavaScript code
        // that contains error message strings. Flash messages are already handled above.
        return foundError;
      } catch (e) { return false; }
    }
    function _showGroup(g) {
      try {
        window.__mascotLog('_showGroup', g && g.getAttribute && g.getAttribute('id'));
        if (!g) return;
        // clear any pending hide timer
        var id = g.getAttribute && g.getAttribute('id');
        if (id && __eyeHideTimers[id]) { clearTimeout(__eyeHideTimers[id]); __eyeHideTimers[id] = null; }
        g.style.visibility = 'visible';
        g.style.opacity = '1';
      } catch (e) {}
    }
    function _hideGroup(g) {
      try {
        window.__mascotLog                                                                          ('_hideGroup', g && g.getAttribute && g.getAttribute('id'));
        if (!g) return;
        g.style.opacity = '0';
        // after transition, hide to remove from a11y and pointer events
        var id = g.getAttribute && g.getAttribute('id');
        if (id) {
          if (__eyeHideTimers[id]) clearTimeout(__eyeHideTimers[id]);
          __eyeHideTimers[id] = setTimeout(function(){ try{ g.style.visibility='hidden'; __eyeHideTimers[id]=null; }catch(e){} }, 260);
        }
      } catch (e) {}
    }
    function showEyes(state, force) {
      try {
        window.__mascotLog('showEyes', state, !!force, Date.now());
        var now = Date.now();
  // If a state lock is active, do not change to a different state until it expires.
        // Allow forced overrides (force === true). While locked, do NOT allow other
        // states (including 'open') to override the locked state. This ensures
        // expressions like 'wrong_pass' remain visible for the full duration.
        if (now < __mascotStateLockUntil && state !== __mascotCurrentEyeState) {
          if (!force) {
            window.__mascotLog && window.__mascotLog('state locked, ignoring', state);
            return;
          }
        }
  // No persistent force-peak window. Peak is shown immediately when toggled
        // or when the password input is focused and revealed. Do not persist peak
        // after the user moves focus to another field.
        // If a temporary suppression is active, ignore requests to show 'open'
        if (state === 'open' && now < __mascotSuppressOpenUntil && !force) return;

        // If caller forced an 'open' state, proactively hide any wrong_pass group
        // and cancel the state lock so typing in name/email immediately restores
        // the following/open eyes even while an error toast may still be visible.
        if (state === 'open' && !!force) {
          try {
            if (wrongGroup) _hideGroup(wrongGroup);
            if (container && container.classList) container.classList.remove('mascot-wrong');
            // clear the lock so other states behave normally after the forced open
            __mascotStateLockUntil = 0;
          } catch (e) {}
        }

        // avoid redundant transitions
        if (!force && __mascotCurrentEyeState === state) return;

        // No DOM overlay cleanup needed here; SVG-based pending eyes are used when available.

        // show/hide with cross-fade (support extra 'wrong_pass' group if present)
        if (state === 'wrong_pass') {
            // If the SVG contains a dedicated wrong-pass group, show it briefly.
            var wrongG = wrongGroup;
            if (wrongG) {
              // hide others, show wrongGroup
              _hideGroup(openGroup); _hideGroup(closedGroup); _hideGroup(peakGroup);
              _showGroup(wrongG);
              // lock other states for the duration so 'open' won't override prematurely
              __mascotStateLockUntil = Date.now() + 3000;
              // revert to open after a timeout matching error toasts (3000ms)
              setTimeout(function(){ try{ _hideGroup(wrongG); showEyes('open'); }catch(e){} }, 3000);
              __mascotCurrentEyeState = state;
              return;
            }
          // fallback: show closed eyes and a brief shake animation on the mascot container
          try {
            _hideGroup(openGroup); _hideGroup(peakGroup); _showGroup(closedGroup);
            if (container) container.classList.add('mascot-wrong');
            // lock other states for the duration so the fallback remains visible
            __mascotStateLockUntil = Date.now() + 3000;
            setTimeout(function(){ try{ if (container) container.classList.remove('mascot-wrong'); showEyes('open'); }catch(e){} }, 3000);
            __mascotCurrentEyeState = state;
            return;
          } catch (e) {
            /* ignore */
          }
        }

        // pending expression for unverified / awaiting approval accounts
        if (state === 'pending') {
          try {
            // Lock other state changes for the duration of pending animation
            __mascotStateLockUntil = Date.now() + 4000;
            var p = ensurePendingGroup();
            if (p) {
              _hideGroup(openGroup); _hideGroup(closedGroup); _hideGroup(peakGroup);
              _showGroup(p);
              // revert to open after a timeout matching the pending toast (keep in sync)
              setTimeout(function(){ try{ _hideGroup(p); showEyes('open'); }catch(e){} }, 4000);
              __mascotCurrentEyeState = state;
              return;
            }
            // Fallback: show closed eyes (do NOT use peak as a fallback for pending)
            _hideGroup(openGroup); _hideGroup(peakGroup); _showGroup(closedGroup);
            setTimeout(function(){ try{ _hideGroup(closedGroup); showEyes('open'); }catch(e){} }, 4000);
            __mascotCurrentEyeState = state;
            return;
          } catch (e) {}
        }

        // peak expression: show the peak_eyes group (used for password reveal)
        if (state === 'peak') {
          try {
            var pg = peakGroup;
            if (pg) {
              _hideGroup(openGroup); _hideGroup(closedGroup); _hideGroup(pendingGroup);
              _showGroup(pg);
              __mascotCurrentEyeState = state;
              return;
            }
            // Fallback: show open eyes if peak group is not present
            _hideGroup(closedGroup); _hideGroup(pendingGroup); _showGroup(openGroup);
            __mascotCurrentEyeState = state;
            return;
          } catch (e) {}
        }

        // happy expression for success cases
        if (state === 'happy') {
          try {
            var h = happyGroup;
            if (h) {
              _hideGroup(openGroup); _hideGroup(closedGroup); _hideGroup(peakGroup);
              _showGroup(h);
              // revert to open after a short timeout
              setTimeout(function(){ try{ _hideGroup(h); showEyes('open'); }catch(e){} }, 900);
              __mascotCurrentEyeState = state;
              return;
            }
            // fallback: briefly show peak
            _hideGroup(closedGroup); _hideGroup(openGroup); _showGroup(peakGroup);
            setTimeout(function(){ try{ showEyes('open'); }catch(e){} }, 900);
            __mascotCurrentEyeState = state;
            return;
          } catch (e) {}
        }

        // Default behaviour for open/closed/peak
  if (state === 'open') { _showGroup(openGroup); } else { _hideGroup(openGroup); }
  if (state === 'closed') { _showGroup(closedGroup); } else { _hideGroup(closedGroup); }
  if (state === 'peak') { _showGroup(peakGroup); } else { _hideGroup(peakGroup); }
  // Ensure any dedicated wrong_pass (or other special groups) are hidden
  // when showing standard eye states so groups don't visually overlap.
  try { _hideGroup(wrongGroup); _hideGroup(happyGroup); _hideGroup(pendingGroup); } catch (e) {}

        __mascotCurrentEyeState = state;
      } catch (e) {
        // silent
      }
    }

    // NOTE: DOM overlay fallback removed -- prefer SVG #pending_eyes vector group.

    // If there's a server-side login failure, show wrong_pass immediately and suppress the brief open flash
    var __initialError = detectAndHandleFlash();
    if (__initialError || window.__mascotWrongPassTriggered) {
      // show wrong_pass immediately; it will revert to open after a short delay
      try { showEyes('wrong_pass', true); } catch(e) { showEyes('open'); }
    } else {
      // Prefer pending animation when present, otherwise prefer a registration-success
      // happy expression (if detected). Only fall back to open if neither applies.
      if (window.__mascotPendingFlash) {
        try { showEyes('pending', true); } catch(e) { showEyes('open'); }
        try { window.__mascotPendingFlash = false; } catch(e) {}
      } else if (window.__mascotHappyFlash && !window.__mascotWrongPassTriggered) {
        try { showEyes('happy', true); } catch(e) { showEyes('open'); }
        try { window.__mascotHappyFlash = false; } catch(e) {}
      } else {
        // initialize to open eyes
        showEyes('open');
      }
    }

    if (email) attachCaretFollower(email);

    // attach to first_name and last_name if present
    if (firstName) attachCaretFollower(firstName);
    if (lastName) attachCaretFollower(lastName);

    // Global focusin listener to ensure state matches focused element immediately
    var confirmPassword = document.getElementById('confirm_password');
    
    document.addEventListener('focusin', function (ev) {
      try {
        window.__mascotLog('focusin', ev.target && ev.target.id);
        if (ev.target === email || ev.target === firstName || ev.target === lastName) {
          showEyes('open', true);
          if (ev.target === email) onEmailInput(); else followCaretFor(ev.target);
        } else if (ev.target === password || ev.target === confirmPassword) {
          passwordStateRefresh(true);
        }
      } catch (e) {}
    });

    function passwordStateRefresh(force) {
      window.__mascotLog('passwordStateRefresh', force, password && password.type, document.activeElement && document.activeElement.id);
      if (!password) return;
      var now = Date.now();
      var activeEl = document.activeElement;
      // Check ONLY the global flag for password reveal state (supports custom masking with text input)
      // Do NOT check password.type because custom masking uses type="text"
      var isRevealed = window.__passwordRevealed === true;
      // show peak only if password/confirm is focused and revealed
      if (isRevealed && (activeEl === password || activeEl === confirmPassword)) {
        showEyes('peak', !!force);
      } else if (activeEl === password || activeEl === confirmPassword) {
        // password/confirm focused and not revealed
        showEyes('closed', !!force);
      } else {
        // otherwise, prefer open for other fields
        showEyes('open', !!force);
      }
    }
    
    // Expose passwordStateRefresh globally for toggle buttons in other scripts
    window.passwordStateRefresh = passwordStateRefresh;

    if (password) {
      password.addEventListener('focus', passwordStateRefresh);
      password.addEventListener('input', passwordStateRefresh);
      password.addEventListener('blur', function () { showEyes('open'); });

      // Observe attribute changes to detect type toggles (password <-> text)
      try {
        var mo = new MutationObserver(function (muts) {
          muts.forEach(function (m) {
            try { window.__mascotLog('mutation', m.attributeName, m.oldValue); } catch(e){}
            if (m.attributeName === 'type') passwordStateRefresh();
          });
        });
        mo.observe(password, { attributes: true });
      } catch (e) { /* ignore */ }
    }
    
    if (confirmPassword) {
      confirmPassword.addEventListener('focus', passwordStateRefresh);
      confirmPassword.addEventListener('input', passwordStateRefresh);
      confirmPassword.addEventListener('blur', function () { showEyes('open'); });
    }

    if (password) {
      // Password visibility toggle button (if present)
      // NOTE: Toggle functionality is handled by register.php for custom character-by-character masking
      // mascot.js only handles the pointerdown event for eye state transitions
      var toggleBtn = document.getElementById('toggle-password-visibility');
      if (toggleBtn) {
        // pointerdown used to preempt focus/change events so we can suppress transient open flashes
        // fallback to mousedown/touchstart for older browsers
        var addPointerDown = function(fn) {
          if (window.PointerEvent) {
            toggleBtn.addEventListener('pointerdown', fn);
          } else {
            toggleBtn.addEventListener('mousedown', fn);
            toggleBtn.addEventListener('touchstart', fn);
          }
        };

        addPointerDown(function () {
          try {
            window.__mascotLog('toggle.pointerdown');
            __mascotToggleInProgress = true;
            setTimeout(function(){ __mascotToggleInProgress = false; }, 150);
            // also set a short suppression window immediately
            __mascotSuppressOpenUntil = Date.now() + 300;
            // ensure peak is shown immediately while toggle interaction is happening
            // showEyes('peak', true); // Removed - handled by register.php
          } catch (e) {}
        });

        // Click handler removed - now handled by register.php for custom masking support
      }
    }

    // Password match indicator logic is now handled by register.php
    // with custom masking support and proper validation of actual password values

    // Flash handling is managed earlier via detectAndHandleFlash()

    // Expose a helper so other scripts can trigger the wrong-password expression programmatically
    try { window.triggerMascotWrongPassword = function() { try{ showEyes('wrong_pass', true); } catch(e){} }; } catch(e) {}

    window.addEventListener('pageshow', function () { showEyes('open'); });
    
    // Login form AJAX handling has been moved to login.php template
    // Mascot expressions are now triggered by that handler via helper functions
  }

  function loadMascotAndInit() {
    var container = document.getElementById('genta-mascot-container');
    if (!container) return;
  // Build the mascot SVG URL from the application's base so we don't accidentally
  // request duplicate subpaths like '/GENTA/GENTA/...'. Prefer window.APP_BASE
  // (injected by the layout). Fall back to document.baseURI or '/' when missing.
  var svgUrl;
  try {
    var appBase = '/';
    if (typeof window.APP_BASE !== 'undefined' && window.APP_BASE) {
      appBase = String(window.APP_BASE);
    } else if (document && document.baseURI) {
      try { appBase = new URL(document.baseURI).pathname || '/'; } catch (ee) { appBase = '/'; }
    }
    if (appBase.slice(-1) !== '/') appBase += '/';
    // If appBase contains an absolute origin (unlikely), strip it to get a root-relative path
    try {
      var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
      if (appBase.indexOf(origin) === 0) appBase = appBase.slice(origin.length) || '/';
    } catch (ee) {}
    // Ensure leading slash
    if (appBase.charAt(0) !== '/') appBase = '/' + appBase;
    svgUrl = appBase.replace(/\/\/+$/, '/') + 'assets/images/mascot_head.svg';
  } catch (e) {
    svgUrl = '/assets/images/mascot_head.svg';
  }
    // Global error hook to surface runtime errors to console for debugging (kept minimal)
    try {
      window.addEventListener && window.addEventListener('error', function(evt){
        try { console.error && console.error('[mascot] runtime error', evt && evt.error ? evt.error : evt.message); } catch(e){}
      });
    } catch (e) {}
    function insertSvgText(text) {
      try {
        try { window.__mascotLog('insertSvgText', text && text.length); } catch (e) {}
        var parser = new DOMParser();
        var doc = parser.parseFromString(text, 'image/svg+xml');
        var svg = doc.documentElement;
        container.innerHTML = '';
        svg = document.importNode(svg, true);
        container.appendChild(svg);
      } catch (e) {
        container.innerHTML = '';
      }
      // Small timeout to ensure elements are in the DOM
      setTimeout(initInteractivity, 30);
    }

    if (typeof fetch === 'function') {
      fetch(svgUrl).then(function(res){
        if (!res.ok) throw new Error('SVG not found: ' + res.status);
        return res.text();
      }).then(function(text){
        insertSvgText(text);
      }).catch(function(err){
        console.warn('Could not load mascot SVG via fetch:', err);
        // fallback to XHR
        try {
          var xhr = new XMLHttpRequest();
          xhr.open('GET', svgUrl);
          xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
              if (xhr.status >= 200 && xhr.status < 300) insertSvgText(xhr.responseText);
              else {
                console.warn('Could not load mascot SVG via XHR:', xhr.status);
                setTimeout(initInteractivity, 30);
              }
            }
          };
          xhr.send();
        } catch (e) {
          setTimeout(initInteractivity, 30);
        }
      });
    } else {
      // fetch not available: use XHR
      try {
        var xhr2 = new XMLHttpRequest();
        xhr2.open('GET', svgUrl);
        xhr2.onreadystatechange = function() {
          if (xhr2.readyState === 4) {
            if (xhr2.status >= 200 && xhr2.status < 300) insertSvgText(xhr2.responseText);
            else { console.warn('Could not load mascot SVG via XHR:', xhr2.status); setTimeout(initInteractivity, 30); }
          }
        };
        xhr2.send();
      } catch (e) {
        setTimeout(initInteractivity, 30);
      }
    }
  }

  // Start on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadMascotAndInit);
  } else {
    loadMascotAndInit();
  }
})();
