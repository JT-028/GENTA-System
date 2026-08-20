// shepherd-init.js
// Map existing WalkthroughSystem.tours into Shepherd tours when Shepherd is available.
(function(){
    function whenShepherdReady(cb){
        if(window.Shepherd) return cb();
        var t=0; var iv=setInterval(function(){ t+=100; if(window.Shepherd){ clearInterval(iv); cb(); } if(t>5000){ clearInterval(iv); console.warn('Shepherd did not load'); }} ,100);
    }

    function resolveElement(selector){
        if(!selector) return null;
        // Try simple querySelector for first matching selector
        try{
            // If the selector contains multiple comma-separated selectors, test each
            var parts = selector.split(/\s*,\s*/);
            for(var i=0;i<parts.length;i++){
                try{ var el = document.querySelector(parts[i]); if(el) return el; }catch(e){}
            }
        }catch(e){}
        // Fallback: if jQuery is present, allow jQuery pseudo-selectors like :contains()
        try{
            if(window.jQuery){
                var $el = jQuery(selector);
                if($el && $el.length) return $el.get(0);
            }
        }catch(e){}
        return null;
    }

    whenShepherdReady(function(){
        console.info('shepherd-init: Shepherd ready (or timeout reached). Checking WalkthroughSystem...');
        var tries = 0, maxTries = 20;
        function registerWhenReady(){
            if(!(window.WalkthroughSystem && window.WalkthroughSystem.tours)){
                console.warn('shepherd-init: WalkthroughSystem not present on window; will poll briefly');
                var wi = setInterval(function(){
                    tries++;
                    if(window.WalkthroughSystem && window.WalkthroughSystem.tours){ clearInterval(wi); register(); return; }
                    if(tries > maxTries){ clearInterval(wi); console.warn('shepherd-init: WalkthroughSystem never appeared'); }
                }, 200);
            } else {
                register();
            }
        }

        function register(){
            console.info('shepherd-init: initializing mapping of WalkthroughSystem tours to Shepherd');
            // mapping registration continues below
        }
        registerWhenReady();
        // utility: normalize a URL/path to pathname+search without trailing slash
        function normalizePath(raw){
            try{
                if(!raw) return '';
                var u = new URL(raw, window.location.href);
                var p = (u.pathname || '') + (u.search || '');
                // remove duplicate slashes
                p = p.replace(/\/+/g, '/');
                // trim trailing slash unless it's root '/'
                if(p.length > 1 && p.slice(-1) === '/') p = p.slice(0, -1);
                return p;
            }catch(e){
                try{
                    var s = String(raw || '');
                    if(s.charAt(0) !== '/') s = '/' + s;
                    if(s.length > 1 && s.slice(-1) === '/') s = s.slice(0, -1);
                    return s;
                }catch(ee){ return String(raw||''); }
            }
        }

        // helper to convert a single tour definition to Shepherd
        // options: { noCancelIcon: boolean } - when true, hides the X button and disables escape key
        window.WalkthroughSystem._createShepherdFrom = function(key, options){
            options = options || {};
            var allowCancel = !options.noCancelIcon;
            
            try{
                var def = WalkthroughSystem.tours[key];
                if(!def || !def.length) return null;
                var tour = new Shepherd.Tour({
                    useModalOverlay: true,
                    defaultStepOptions: {
                        scrollTo: { behavior: 'smooth', block: 'center' },
                        cancelIcon: { enabled: allowCancel },
                        classes: 'genta-shepherd-theme',
                        modalOverlayOpeningPadding: 4,
                        modalOverlayOpeningRadius: 8
                    },
                    keyboardNavigation: allowCancel, // Disable escape key when noCancelIcon is true
                    exitOnEsc: allowCancel // Explicitly disable ESC key exit
                });

                def.forEach(function(s, idx){
                    // Use selector string for attachTo so Shepherd resolves it when showing the step.
                    // This improves snapping/pointing when elements are rendered later or after AJAX swaps.
                    // Only pass a selector string to Shepherd if it's a valid CSS selector
                    // (document.querySelector will throw for jQuery pseudos like :contains()).
                    var attachTo = undefined;
                    if (s.target) {
                        try {
                            // Prefer passing a DOM element resolved at positioning-time.
                            // Shepherd supports attachTo.element as a callback function.
                            // This callback is called when Shepherd needs to position the tooltip
                            attachTo = {
                                element: function(){ 
                                    var el = resolveElement(s.target);
                                    if(el){
                                        console.debug && console.debug('shepherd-init: attachTo callback resolved element', { stepId: s.id, target: s.target });
                                    } else {
                                        console.debug && console.debug('shepherd-init: attachTo callback could not resolve element', { stepId: s.id, target: s.target });
                                    }
                                    return el;
                                },
                                on: s.position || 'bottom'
                            };
                        }catch(e){
                            // Fallback: try resolving immediately
                            try{ var _r = resolveElement(s.target); if(_r) attachTo = { element: _r, on: s.position || 'bottom' }; }catch(ee){ attachTo = undefined; }
                        }
                    }
                    var buttons = [];
                    if(idx > 0){ buttons.push({ text: 'Back', action: tour.back, classes: 'shepherd-button shepherd-button-secondary' }); }
                    buttons.push({ text: idx === def.length-1 ? 'Done' : 'Next', action: tour.next, classes: 'shepherd-button shepherd-button-primary' });

                    // Don't skip steps - we need all steps added to the tour even if their targets
                    // aren't currently visible, because navigation will load the correct page.
                    // The when.show handler will resolve targets dynamically when each step is shown.

                    // Debug: note what we'll attach to (selector string vs DOM element)
                    try{ if(window && window.console && console.debug){
                        console.debug('shepherd-init: adding step attachTo', { id: (s.id||'step-'+idx), target: s.target, attachTo: attachTo });
                    }}catch(e){}

                    // Build step text with progress indicator
                    var stepText = s.text || '';
                    var progressHtml = '<div class="genta-shepherd-progress" style="margin-top: 16px;"><div class="genta-shepherd-progress-fill" style="width: ' + Math.round(((idx+1)/def.length)*100) + '%;"></div></div>';
                    var progressText = '<div style="margin-top: 8px; font-size: 0.85rem; color: #6b7280; text-align: right;">Step ' + (idx+1) + ' of ' + def.length + '</div>';
                    var fullText = stepText + progressHtml + progressText;

                    tour.addStep({
                        id: (s.id || 'step-' + idx),
                        title: s.title || '',
                        text: fullText,
                        attachTo: attachTo,
                        when: {
                            show: function() {
                                // Robust resolution strategy when targets are hidden or live on another page.
                                var attempts = 0;
                                var maxAttempts = 12;

                                function isVisible(el){
                                    if(!el) return false;
                                    if(el.offsetWidth === 0 && el.offsetHeight === 0) return false;
                                    if(el.getClientRects && el.getClientRects().length === 0) return false;
                                    return true;
                                }

                                function openCollapsedParents(el){
                                    try{
                                        if(!el) return;
                                        // Bootstrap collapse: find closest .collapse parent and show it
                                        var parentCollapse = el.closest('.collapse');
                                        if(parentCollapse && parentCollapse.classList && !parentCollapse.classList.contains('show')){
                                            // If bootstrap JS is present, use it
                                            try{
                                                if(window.bootstrap && bootstrap.Collapse){
                                                    var inst = bootstrap.Collapse.getInstance(parentCollapse) || new bootstrap.Collapse(parentCollapse, { toggle: true });
                                                    // ensure it's shown
                                                    inst.show();
                                                    return true;
                                                }
                                            }catch(e){}
                                            // Fallback: toggle class
                                            try{ parentCollapse.classList.add('show'); parentCollapse.style.height = 'auto'; }catch(e){}
                                        }

                                        // Dropdowns: open parent dropdown if closed
                                        var dd = el.closest('.dropdown');
                                        if(dd){
                                            var toggle = dd.querySelector('.dropdown-toggle');
                                            if(toggle && toggle.getAttribute('aria-expanded') !== 'true'){
                                                try{ toggle.click(); return true; }catch(e){}
                                            }
                                        }
                                    }catch(e){}
                                    return false;
                                }

                                function tryResolveAndScroll(cb){
                                    try{
                                        var el = resolveElement(s.target);
                                        if(el && isVisible(el)){
                                            console.info('shepherd-init: resolved and attached to target', { stepId: s.id, target: s.target, element: el });
                                            try{ el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }catch(e){ try{ el.scrollIntoView(); }catch(e){} }
                                            // Visual hint: briefly highlight the resolved element so we can see what Shepherd targets
                                            try{
                                                try{ el.__genta_shepherd_orig = el.style.boxShadow || ''; }catch(e){}
                                                try{ el.style.boxShadow = '0 0 0 6px rgba(102,153,255,0.18)'; }catch(e){}
                                                setTimeout(function(){ try{ if(el && el.style) el.style.boxShadow = el.__genta_shepherd_orig || ''; }catch(e){} }, 2500);
                                            }catch(e){}
                                            // Force multiple repositions to ensure Shepherd attaches properly
                                            setTimeout(function(){ try{ window.dispatchEvent(new Event('resize')); }catch(e){}; }, 200);
                                            setTimeout(function(){ try{ window.dispatchEvent(new Event('resize')); }catch(e){}; }, 500);
                                            setTimeout(function(){ try{ window.dispatchEvent(new Event('resize')); }catch(e){}; if(cb) cb(true); }, 800);
                                            return true;
                                        } else {
                                            if(s.target && !el){
                                                console.warn('shepherd-init: target not found', { stepId: s.id, target: s.target });
                                            }
                                        }

                                        // If resolved but hidden, try opening parent containers
                                        if(el && !isVisible(el)){
                                            var opened = openCollapsedParents(el);
                                            if(opened){
                                                // after opening, wait briefly then check visibility again
                                                setTimeout(function(){
                                                    var el2 = resolveElement(s.target);
                                                    if(el2 && isVisible(el2)){
                                                        try{ el2.scrollIntoView({ behavior: 'smooth', block: 'center' }); }catch(e){}
                                                        setTimeout(function(){ try{ window.dispatchEvent(new Event('resize')); }catch(e){}; if(cb) cb(true); }, 320);
                                                    } else {
                                                        if(cb) cb(false);
                                                    }
                                                }, 380);
                                                return true;
                                            }
                                        }
                                    }catch(e){}
                                    if(cb) cb(false);
                                    return false;
                                }

                                function tryNavigateToTargetThenResolve(cb){
                                    // Attempt to find a nav link that likely leads to the page containing the target.
                                    try{
                                        var textHint = (s.title || '') + ' ' + (s.text || '');
                                        textHint = textHint.trim().toLowerCase();
                                        var navCandidates = document.querySelectorAll('#sidebar a.nav-link, a.nav-link, #navbarNav a');
                                        for(var i=0;i<navCandidates.length;i++){
                                            var a = navCandidates[i];
                                            var t = (a.textContent || a.innerText || '').trim().toLowerCase();
                                            if(!t) continue;
                                            // match based on presence of any word from the title/text hints
                                            var words = textHint.split(/\s+/).filter(Boolean);
                                            var match = false;
                                            for(var w=0; w<words.length && w<6; w++){
                                                if(words[w].length < 3) continue;
                                                if(t.indexOf(words[w]) !== -1){ match = true; break; }
                                            }
                                            if(match){
                                                // Only navigate via sidebar hints when the step explicitly requested navigation
                                                // This avoids accidental clicks for generic steps (e.g., dashboard steps mentioning "profile").
                                                if(!s.navigateTo){ continue; }
                                                // Click the link (this will use AJAX loadPage if the app intercepts it)
                                                try{ a.click(); }catch(e){ try{ window.location.href = a.href; }catch(e){} }
                                                // wait for navigation and then retry resolution
                                                setTimeout(function(){ tryResolveAndScroll(cb); }, 700);
                                                return true;
                                            }
                                        }
                                    }catch(e){}
                                    if(cb) cb(false);
                                    return false;
                                }

                                // If the step declares a preOpen hook, run it now.
                                try{
                                    if(s.preOpen){
                                        try{
                                            // If preOpen is a named handler registered on WalkthroughSystem, call it
                                            if(typeof s.preOpen === 'string' && WalkthroughSystem && WalkthroughSystem.preOpenHandlers && typeof WalkthroughSystem.preOpenHandlers[s.preOpen] === 'function'){
                                                try{ WalkthroughSystem.preOpenHandlers[s.preOpen](s); }catch(e){}
                                            } else if(typeof s.preOpen === 'function'){
                                                try{ s.preOpen(s); }catch(e){}
                                            }
                                        }catch(e){}
                                    }
                                }catch(e){}

                                // IMPORTANT: Only navigate on the FIRST show of a step that requires navigation.
                                // Store a flag to prevent re-navigation when Shepherd re-shows the step.
                                var navigated = false;
                                try{
                                    // Check if we've already navigated for this step (use sessionStorage to persist across page loads)
                                    var navKey = 'genta_nav_' + key + '_' + (s.id || 'step-' + idx);
                                    var hasNavigated = false;
                                    try{
                                        if(window.sessionStorage){
                                            hasNavigated = sessionStorage.getItem(navKey) === 'true';
                                        } else {
                                            hasNavigated = window[navKey] === true;
                                        }
                                    }catch(e){ hasNavigated = window[navKey] === true; }
                                    
                                    console.info('shepherd-init: navigation check', { 
                                        stepId: s.id, 
                                        navigateTo: s.navigateTo, 
                                        navKey: navKey,
                                        hasNavigated: hasNavigated,
                                        willSkipNav: hasNavigated
                                    });
                                    
                                    if(s.navigateTo && !hasNavigated){
                                        try{
                                            // Build a navigation URL that respects the application's APP_BASE
                                            // Prefer the existing `buildUrl()` helper when available; otherwise
                                            // fall back to window.APP_BASE prefixing. This ensures navigation
                                            // targets the actual URL the app uses (e.g. when served under /GENTA/).
                                            var navUrlRaw = s.navigateTo;
                                            var navUrlFull = navUrlRaw;
                                            try{
                                                // First, try to find an existing anchor in the page whose
                                                // resolved href includes the requested navigateTo segment.
                                                // Anchors generated by CakePHP/templating usually include
                                                // the proper APP_BASE and prefix (e.g. '/GENTA/teacher/...').
                                                var anchors = document.querySelectorAll('a[href]');
                                                var foundHref = null;
                                                try{
                                                    for(var ai=0; ai<anchors.length; ai++){
                                                        var ah = anchors[ai];
                                                        try{
                                                            var aurl = new URL(ah.href, window.location.href);
                                                            var apath = aurl.pathname + (aurl.search||'');
                                                            // Match only when the anchor path ends with the navigateTo segment
                                                            // (prevents matching '/teacher/dashboard/profile' when '/teacher/dashboard' is intended)
                                                            if(navUrlRaw && navUrlRaw.length && apath.endsWith(navUrlRaw)) { foundHref = apath; break; }
                                                        }catch(e){}
                                                    }
                                                }catch(e){}

                                                if(foundHref){
                                                    navUrlFull = foundHref;
                                                } else if(typeof buildUrl === 'function'){
                                                    navUrlFull = buildUrl(navUrlRaw);
                                                } else if(typeof window.APP_BASE !== 'undefined' && window.APP_BASE){
                                                    var base = String(window.APP_BASE);
                                                    if(base.slice(-1) === '/') base = base.slice(0, -1);
                                                    // Ensure navUrlRaw begins with '/'
                                                    if(navUrlRaw.charAt(0) !== '/') navUrlFull = base + '/' + navUrlRaw; else navUrlFull = base + navUrlRaw;
                                                } else {
                                                    // As last resort, try to keep the original value
                                                    navUrlFull = navUrlRaw;
                                                }
                                            }catch(e){
                                                navUrlFull = navUrlRaw;
                                            }

                                            // Normalize current location and navUrlFull for comparison.
                                            var currentPath = normalizePath(window.location.pathname + (window.location.search || ''));
                                            // If navUrlFull is absolute URL, normalize it
                                            try{ navUrlFull = normalizePath(navUrlFull); }catch(e){}

                                            // Debug: log the final navigation target and current path for quick verification
                                            try{
                                                if (typeof console !== 'undefined' && console.debug) {
                                                    console.debug('Walkthrough navigation resolved', {
                                                        raw: navUrlRaw,
                                                        resolved: navUrlFull,
                                                        current: currentPath,
                                                        willUseLoadPage: (typeof loadPage === 'function')
                                                    });
                                                }
                                            }catch(e){}

                                            // If our current path is not the target, navigate.
                                            // Use strict matching to avoid false positives (e.g., /students shouldn't match /dashboard)
                                            // Normalize both paths for comparison
                                            var currentNorm = currentPath.toLowerCase().replace(/\/+$/, '');
                                            var targetNorm = navUrlFull.toLowerCase().replace(/\/+$/, '');
                                            
                                            var isSame = (currentNorm === targetNorm) || 
                                                         (currentNorm === targetNorm + '/') ||
                                                         (currentNorm + '/' === targetNorm);
                                            
                                            console.info('shepherd-init: path comparison', {
                                                stepId: s.id,
                                                currentNorm: currentNorm,
                                                targetNorm: targetNorm,
                                                isSame: isSame
                                            });
                                            
                                            if(!isSame){
                                                console.warn('shepherd-init: NAVIGATING (paths do not match)', { from: currentNorm, to: targetNorm });
                                                // Mark that we're navigating for this step (persist across page loads)
                                                try{
                                                    if(window.sessionStorage){
                                                        sessionStorage.setItem(navKey, 'true');
                                                    }
                                                    window[navKey] = true;
                                                }catch(e){ window[navKey] = true; }
                                                
                                                // Persist resume information (use step id) so a multi-page walkthrough can continue
                                                try{
                                                    if(window.sessionStorage && key){
                                                        var stepId = (s.id || 'step-' + idx);
                                                        // store expectedPath so resume only triggers when on the intended page
                                                        // Preserve noCancelIcon option if tour was started with it
                                                        var resume = { 
                                                            key: key, 
                                                            stepId: stepId, 
                                                            expectedPath: navUrlFull,
                                                            noCancelIcon: !allowCancel
                                                        };
                                                        try{ 
                                                            sessionStorage.setItem('genta_walkthrough_resume', JSON.stringify(resume)); 
                                                            console.info('shepherd-init: stored resume data', resume);
                                                        }catch(e){}
                                                    }
                                                }catch(e){}

                                                // Close current tour before navigating to prevent double-shows
                                                try{ tour.hide(); }catch(e){}

                                                // Set flag to indicate this is a tour-initiated navigation
                                                // This allows checkResume to run after navigation completes
                                                try{ window._tourNavigating = true; }catch(e){}

                                                if(typeof loadPage === 'function'){
                                                    console.warn('shepherd-init: calling loadPage()', navUrlFull);
                                                    try{ loadPage(navUrlFull); navigated = true; }catch(e){ try{ window.location.href = navUrlFull; navigated = true; }catch(e){} }
                                                } else {
                                                    try{ window.location.href = navUrlFull; navigated = true; }catch(e){}
                                                }
                                                // After navigation, wait for page to load then resume the tour
                                                // The resume logic in shepherd-init will pick up the tour from sessionStorage
                                                return;
                                            } else {
                                                console.info('shepherd-init: SKIPPING navigation (already on target page)', { current: currentNorm, target: targetNorm });
                                                // We're already on the correct page, mark as navigated (persist across page loads)
                                                try{
                                                    if(window.sessionStorage){
                                                        sessionStorage.setItem(navKey, 'true');
                                                    }
                                                    window[navKey] = true;
                                                }catch(e){ window[navKey] = true; }
                                            }
                                        }catch(e){}
                                    }
                                }catch(e){}

                                // Immediate attempt (if navigation was triggered, try after a short delay)
                                tryResolveAndScroll(function(success){
                                    if(success) return;
                                    // Retry loop with backoff - longer for DataTables to initialize
                                    var iv = setInterval(function(){
                                        attempts++;
                                        if(attempts > maxAttempts){ 
                                            clearInterval(iv); 
                                            console.warn('shepherd-init: gave up trying to resolve target after ' + attempts + ' attempts', { stepId: s.id, target: s.target });
                                            return; 
                                        }
                                        tryResolveAndScroll(function(ok){
                                            if(ok){ clearInterval(iv); return; }
                                            // If not resolved after a few attempts, try navigating via sidebar hints
                                            // BUT only if we haven't already navigated for this step
                                            if(attempts === 3){
                                                var navKey = 'genta_nav_' + key + '_' + (s.id || 'step-' + idx);
                                                var hasNavigated = false;
                                                try{
                                                    if(window.sessionStorage){
                                                        hasNavigated = sessionStorage.getItem(navKey) === 'true';
                                                    } else {
                                                        hasNavigated = window[navKey] === true;
                                                    }
                                                }catch(e){ hasNavigated = window[navKey] === true; }
                                                
                                                if(!hasNavigated){
                                                    tryNavigateToTargetThenResolve(function(navOk){ if(navOk){ /* navigation started; resolution will continue */ } });
                                                }
                                            }
                                        });
                                    }, 600);
                                });

                                // update progress bar if present
                                try{
                                    var fill = document.querySelector('.genta-shepherd-progress-fill');
                                    if(fill){ var pct = Math.round(((idx+1)/def.length)*100); fill.style.width = pct + '%'; }
                                }catch(e){}
                                // After show, attempt a second reposition to handle late-rendered content
                                try{
                                    setTimeout(function(){ try{ window.dispatchEvent(new Event('resize')); }catch(e){} }, 450);
                                    setTimeout(function(){ try{ window.dispatchEvent(new Event('resize')); }catch(e){} }, 900);
                                }catch(e){}
                            }
                        },
                        buttons: buttons
                    });
                });

                // Store reference to active tour for DataTables reposition hook
                window._activeShepherdTour = tour;
                
                // Wire Shepherd events to WalkthroughSystem so backend markers and cleanup run
                tour.on('start', function(){ 
                    try{ 
                        if(WalkthroughSystem && typeof WalkthroughSystem.disableScroll === 'function'){ 
                            WalkthroughSystem.disableScroll(); 
                        } 
                        WalkthroughSystem.isActive = true;
                        window._activeShepherdTour = tour;
                    }catch(e){} 
                });
                
                // Add a hide event listener to ensure scroll is always restored
                tour.on('hide', function(){
                    try{
                        // Only restore scroll if tour is actually ending (not just hiding for navigation)
                        setTimeout(function(){
                            try{
                                if(!window._activeShepherdTour || !WalkthroughSystem.isActive){
                                    if(WalkthroughSystem && typeof WalkthroughSystem.enableScroll === 'function'){
                                        WalkthroughSystem.enableScroll();
                                    }
                                }
                            }catch(e){}
                        }, 500);
                    }catch(e){}
                });
                tour.on('cancel', function(){ 
                    try{ 
                        WalkthroughSystem.cancel(); 
                        window._activeShepherdTour = null;
                        // Ensure scroll is restored
                        if(WalkthroughSystem && typeof WalkthroughSystem.enableScroll === 'function'){
                            WalkthroughSystem.enableScroll();
                        }
                        // Clear navigation flags for this tour
                        if(window.sessionStorage){
                            def.forEach(function(s, idx){
                                var navKey = 'genta_nav_' + key + '_' + (s.id || 'step-' + idx);
                                try{ sessionStorage.removeItem(navKey); }catch(e){}
                            });
                        }
                    }catch(e){} 
                });
                tour.on('complete', function(){ 
                    try{ 
                        WalkthroughSystem.complete(); 
                        window._activeShepherdTour = null;
                        // Ensure scroll is restored
                        if(WalkthroughSystem && typeof WalkthroughSystem.enableScroll === 'function'){
                            WalkthroughSystem.enableScroll();
                        }
                        // Clear navigation flags for this tour
                        if(window.sessionStorage){
                            def.forEach(function(s, idx){
                                var navKey = 'genta_nav_' + key + '_' + (s.id || 'step-' + idx);
                                try{ sessionStorage.removeItem(navKey); }catch(e){}
                            });
                        }
                    }catch(e){} 
                });

                return tour;
            }catch(e){ console.warn('createShepherdFrom failed', e); return null; }
        };

        // expose a function to start a Shepherd tour by key
        // `startStep` may be a numeric index (legacy) or a step id string (preferred).
        // `options` may contain: { noCancelIcon: boolean } - when true, hides X button and disables escape
        WalkthroughSystem.startShepherd = function(key, startStep, options){
            console.info && console.info('shepherd-init: startShepherd called', { key: key, startStep: startStep, options: options });
            var t = WalkthroughSystem._createShepherdFrom(key, options);
            if(!t) return false;
            try{
                // If resuming to a specific step, show that step directly instead of starting from beginning
                if(typeof startStep === 'string' && startStep){
                    // Manually trigger start event for WalkthroughSystem integration
                    try{ 
                        if(WalkthroughSystem && typeof WalkthroughSystem.disableScroll === 'function'){ 
                            WalkthroughSystem.disableScroll(); 
                        } 
                        WalkthroughSystem.isActive = true;
                        window._activeShepherdTour = t;
                    }catch(e){}
                    
                    // Show the specific step directly without calling start()
                    setTimeout(function(){
                        try{
                            console.info('shepherd-init: showing specific step after resume', startStep);
                            if(typeof t.show === 'function'){
                                t.show(startStep);
                            }
                        }catch(e){ console.warn('shepherd-init: failed to show step', e); }
                    }, 100);
                } else if(typeof startStep === 'number' && startStep > 0){
                    // Legacy numeric resume - start then advance
                    t.start();
                    setTimeout(function(){
                        try{ for(var i=0;i<startStep;i++){ t.next(); } }catch(e){}
                    }, 300);
                } else {
                    // Normal start from beginning
                    t.start();
                }
                return true;
            }catch(e){ console.warn('Shepherd start error', e); return false; }
        };

        // Expose a function to check and resume tours after AJAX navigation
        WalkthroughSystem.checkResume = function(force) {
            if (window.DISABLE_SHEPHERD_AUTO_RESUME && !force) {
                console.info('shepherd-init: auto-resume disabled by flag');
                return;
            }
            try{
                if(window.sessionStorage){
                    var raw = sessionStorage.getItem('genta_walkthrough_resume');
                    if(raw){
                        try{
                            var obj = JSON.parse(raw);
                            sessionStorage.removeItem('genta_walkthrough_resume');
                            if(obj && obj.key){
                                try{
                                    var currentPathNow = normalizePath(window.location.pathname + (window.location.search || ''));
                                    // Defensive check: only resume if we're on the expected path (or expectedPath is not set)
                                    if(obj.expectedPath && String(obj.expectedPath).length){
                                        try{
                                            var expectedNorm = normalizePath(obj.expectedPath);
                                            // More flexible matching: check if current path contains or ends with expected path
                                            var match = (currentPathNow === expectedNorm) || 
                                                        (currentPathNow.endsWith(expectedNorm)) || 
                                                        (currentPathNow.indexOf(expectedNorm) !== -1) ||
                                                        (expectedNorm.indexOf(currentPathNow) !== -1);
                                            if(!match){
                                                console.warn('shepherd-init: resume marker present but current path does not match expectedPath — skipping resume', { expected: expectedNorm, current: currentPathNow, tourKey: obj.key, stepId: obj.stepId });
                                            } else {
                                                console.info('shepherd-init: resuming walkthrough after delay', obj);
                                                // Mark this step as already navigated to prevent re-navigation
                                                try{
                                                    if(window.sessionStorage && obj.stepId){
                                                        var navKey = 'genta_nav_' + obj.key + '_' + obj.stepId;
                                                        sessionStorage.setItem(navKey, 'true');
                                                        console.info('shepherd-init: marked step as already navigated', navKey);
                                                    }
                                                }catch(e){}
                                                // Wait for page content to fully render (especially DataTables)
                                                setTimeout(function(){
                                                    try{ 
                                                        var t = null;
                                                        // Pass noCancelIcon option when resuming first-time tours
                                                        var resumeOptions = obj.noCancelIcon ? { noCancelIcon: true } : {};
                                                        if(obj.stepId){ t = WalkthroughSystem.startShepherd(obj.key, obj.stepId, resumeOptions); } 
                                                        else if(typeof obj.step === 'number'){ t = WalkthroughSystem.startShepherd(obj.key, obj.step, resumeOptions); } 
                                                        
                                                        if(t && window.jQuery) {
                                                            // Fire event so sequential runner can re-hook
                                                            jQuery(window).trigger('genta:shepherd:resumed', [window._activeShepherdTour, obj.key]);
                                                        }
                                                    }catch(e){ console.warn('shepherd-init: resume failed', e); }
                                                }, 800);
                                            }
                                        }catch(e){ console.warn('shepherd-init: resume path check error', e); }
                                    } else {
                                        console.info('shepherd-init: resuming walkthrough (no expectedPath) after delay', obj);
                                        // Mark this step as already navigated to prevent re-navigation
                                        try{
                                            if(window.sessionStorage && obj.stepId){
                                                var navKey = 'genta_nav_' + obj.key + '_' + obj.stepId;
                                                sessionStorage.setItem(navKey, 'true');
                                                console.info('shepherd-init: marked step as already navigated', navKey);
                                            }
                                        }catch(e){}
                                        // Wait for page content to fully render
                                        setTimeout(function(){
                                            try{ 
                                                var t = null;
                                                // Pass noCancelIcon option when resuming first-time tours
                                                var resumeOptions = obj.noCancelIcon ? { noCancelIcon: true } : {};
                                                if(obj.stepId){ t = WalkthroughSystem.startShepherd(obj.key, obj.stepId, resumeOptions); } 
                                                else if(typeof obj.step === 'number'){ t = WalkthroughSystem.startShepherd(obj.key, obj.step, resumeOptions); } 
                                                
                                                if(t && window.jQuery) {
                                                    // Fire event so sequential runner can re-hook
                                                    jQuery(window).trigger('genta:shepherd:resumed', [window._activeShepherdTour, obj.key]);
                                                }
                                            }catch(e){ console.warn('shepherd-init: resume failed', e); }
                                        }, 800);
                                    }
                                }catch(e){ console.warn('shepherd-init: resume check failed', e); }
                            }
                        }catch(e){}
                    }
                }
            }catch(e){}
        };

        // If a previous navigation set a resume marker, restore the tour state now (on page load)
        try{
            WalkthroughSystem.checkResume();
        }catch(e){}
        
        // Hook into DataTables initialization to reposition Shepherd tooltips
        // when table content loads (which might contain tour target elements)
        try{
            if(window.jQuery && jQuery.fn && jQuery.fn.dataTable){
                jQuery(document).on('init.dt', function(e, settings){
                    console.info('shepherd-init: DataTable initialized, repositioning active tour');
                    // Wait a bit for DOM to settle after DataTable render
                    setTimeout(function(){
                        try{
                            if(window._activeShepherdTour && window._activeShepherdTour.getCurrentStep){
                                var currentStep = window._activeShepherdTour.getCurrentStep();
                                if(currentStep && typeof currentStep.updateStepOptions === 'function'){
                                    // Force Shepherd to recalculate position
                                    window.dispatchEvent(new Event('resize'));
                                }
                            }
                        }catch(e){ console.warn('shepherd-init: failed to reposition after DataTable init', e); }
                    }, 400);
                });
            }
        }catch(e){}
    });
})();
