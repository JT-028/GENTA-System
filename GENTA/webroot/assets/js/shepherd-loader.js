// shepherd-loader.js
// Load Shepherd.js from CDN with a fallback to unpkg if needed.
(function(){
    function loadCss(href){
        if(document.querySelector('link[href="'+href+'"]')) return;
        var l=document.createElement('link'); l.rel='stylesheet'; l.href=href; document.head.appendChild(l);
    }
    function loadScript(src, cb){
        var s=document.createElement('script'); s.src=src; s.onload=cb; s.onerror=function(){ if(cb) cb(new Error('failed')); };
        document.head.appendChild(s);
    }

    // Local preferred path
    var localCss = (window.APP_BASE||'') + 'assets/vendors/shepherd/shepherd.css';
    var localJs  = (window.APP_BASE||'') + 'assets/vendors/shepherd/shepherd.min.js';

    var cssCdn1 = 'https://unpkg.com/shepherd.js/dist/css/shepherd.css';
    var jsCdn1  = 'https://unpkg.com/shepherd.js/dist/js/shepherd.min.js';
    var cssCdn2 = 'https://cdn.jsdelivr.net/npm/shepherd.js/dist/css/shepherd.css';
    var jsCdn2  = 'https://cdn.jsdelivr.net/npm/shepherd.js/dist/js/shepherd.min.js';

    // Prefer UMD CDN builds first (they expose a global Shepherd), then fall back to local files.
    function loadCssWithFallback(){
        // Try CDN first for predictable UMD CSS
        loadCss(cssCdn1);
        // Also attempt local CSS - if local exists it will override
        loadCss(localCss);
    }

    function trySourcesSequentially(sources, onReady){
        var idx = 0;
        function next(){
            if(idx >= sources.length){ if(onReady) onReady(new Error('all failed')); return; }
            var src = sources[idx++];
            loadScript(src, function(err){
                // After load attempt, check for Shepherd global
                setTimeout(function(){
                    if(typeof window.Shepherd !== 'undefined'){
                        console.info('Shepherd loaded from', src);
                        if(onReady) onReady(null, src);
                        return;
                    }
                    console.warn('Shepherd not present after loading', src, 'error:', err);
                    // try next
                    next();
                }, 50);
            });
        }
        next();
    }

    // Load CSS first (CDN preferred, local allowed to override)
    loadCssWithFallback();

    // Try CDN UMD first, then alternate CDN, then local UMD filename, then local ESM as last resort
    // Prefer a local UMD bundle if present (shepherd.umd.js) to avoid ESM parse errors when shepherd.min.js is an ES module.
    var localUmd = (window.APP_BASE||'') + 'assets/vendors/shepherd/shepherd.umd.js';
    var sources = [jsCdn1, jsCdn2, localUmd, localJs];
    trySourcesSequentially(sources, function(err, okSrc){
        if(err){ 
            console.error('Shepherd load failed from all non-module sources, attempting dynamic import of local file as module');
            // As a last resort, try dynamic import of the local file (useful if it's an ES module build)
            try{
                import(localJs).then(function(mod){
                    // Try to attach exported symbol to window
                    var exported = (mod && (mod.default || mod.Shepherd)) || mod;
                    if(exported){
                        window.Shepherd = exported;
                        console.info('Shepherd loaded via dynamic import from', localJs);
                        return;
                    }
                    console.error('Dynamic import completed but did not expose Shepherd');
                }).catch(function(impErr){
                    console.error('Dynamic import of local Shepherd failed', impErr);
                });
            }catch(e){ console.error('Dynamic import not supported or failed', e); }
        }
    });
})();
