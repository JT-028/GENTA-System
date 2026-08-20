<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- META TAGS -->
        <?= $this->Html->charset() ?>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

        <title>GENTA</title>

        <script>
            // expose the application base for client-side normalizers
            window.APP_BASE = <?= json_encode($this->Url->build('/')) ?>;
        </script>
        <!-- Inline: hide server-rendered flash messages immediately; JS will show SweetAlert toasts instead -->
        <style>
            .alert.alert-danger, .alert.alert-success, .flash-error, .flash-success, .message.error, .message.success { display: none !important; }
        </style>

        <!-- CSS -->
        <?=
            $this->Html->css([
                // CSS VENDOR
                '/assets/vendors/mdi/css/materialdesignicons.min.css',
                '/assets/vendors/css/vendor.bundle.base.css',
                // STYLES
                '/assets/css/style.css',
                '/assets/css/custom.css',
                '/assets/css/login.css',
            ])
        ?>
        <script>
            (function(){
                try {
                    var base = (typeof window.APP_BASE !== 'undefined' && window.APP_BASE) ? String(window.APP_BASE) : '/';
                    var appTrim = base.replace(/^\/+|\/+$/g, '');
                    if (base.slice(-1) !== '/') base += '/';
                    var links = document.getElementsByTagName('link');
                    Array.prototype.forEach.call(links, function(l){
                        try {
                            var h = l.getAttribute('href');
                            if (!h) return;
                            if (/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//.test(h)) return;
                            if (h.charAt(0) === '/') return;
                            if (appTrim && h.indexOf(appTrim + '/') === 0) {
                                l.setAttribute('href', '/' + h.replace(/^\/+/, ''));
                            } else {
                                l.setAttribute('href', base + h.replace(/^\/+/, ''));
                            }
                        } catch(e){}
                    });
                } catch(e) { console.warn('guest asset href normalizer failed', e); }
            })();
        </script>

    <!-- ICONS -->
        <script>
            // Collapse duplicate APP_BASE occurrences in already-rendered asset tags.
            (function(){
                try {
                    var base = (typeof window.APP_BASE !== 'undefined' && window.APP_BASE) ? String(window.APP_BASE) : '/';
                    if (base.slice(-1) !== '/') base += '/';
                    var norm = function(v){ if(!v) return v; try{ return v.replace(new RegExp('('+base.replace(/\//g,'\\/')+')+','g'), base); }catch(e){} return v; };
                    Array.prototype.forEach.call(document.querySelectorAll('link[rel="stylesheet"]'), function(l){ try{ var h=l.getAttribute('href'); if(h){ h = norm(h); if(h.charAt(0)!=='/') h = '/' + h.replace(/^\/+/, ''); l.setAttribute('href', h); } }catch(e){} });
                    Array.prototype.forEach.call(document.querySelectorAll('script[src]'), function(s){ try{ var v=s.getAttribute('src'); if(v){ v = norm(v); if(v.charAt(0)!=='/') v = '/' + v.replace(/^\/+/, ''); s.setAttribute('src', v); } }catch(e){} });
                    Array.prototype.forEach.call(document.querySelectorAll('img[src]'), function(i){ try{ var v=i.getAttribute('src'); if(v){ v = norm(v); if(v.charAt(0)!=='/') v = '/' + v.replace(/^\/+/, ''); i.setAttribute('src', v); } }catch(e){} });
                } catch (e) { console.warn('guest collapse duplicate app base failed', e); }
            })();
        </script>
    <?= $this->Html->meta('icon', '/assets/images/mascot_head.svg') ?>
    <!-- SWEETALERT2 CDN (to enable consistent toast styling on guest pages) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <div class="container-scroller">
            <div class="container-fluid page-body-wrapper full-page-wrapper">
                <div class="content-wrapper d-flex align-items-center auth">
                    <div class="row flex-grow">
                        <div class="col-xxl-4 col-lg-6 col-md-12 mx-auto">
                            <div class="auth-form-light text-left p-5">
                                <!-- LOGO (hide on Users pages with animated mascot to avoid duplicate) -->
                                <?php
                                $ctl = $this->request->getParam('controller');
                                $act = $this->request->getParam('action');
                                // Hide the brand logo on Users pages that have their own animated mascot
                                if (!($ctl === 'Users' && in_array($act, ['login', 'register', 'forgotPassword', 'resetPassword']))): ?>
                                <div class="brand-logo">
                                    <?= $this->Html->image('/assets/images/mascot_head.svg', ['alt' => 'GENTA Icon']) ?>
                                </div>
                                <?php endif; ?>
                                <?= $this->Flash->render() ?>
                                <?= $this->fetch('content') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- JS -->
        <?php
            $script = <<<'JS'
(function(){
    try{
        var base=(typeof window.APP_BASE!=='undefined'&&window.APP_BASE)?String(window.APP_BASE):'/';
        if(base.slice(-1)!=='/') base += '/';
        var appTrim = base.replace(/^\/+|\/+$/g,'');
        var fix = function(el, attr){ try{ var v = el.getAttribute(attr); if(!v) return; if(/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//.test(v)) return; if(v.charAt(0)=='/') return; if(appTrim && v.indexOf(appTrim + '/')===0){ el.setAttribute(attr, '/' + v.replace(/^\/+/,'')); } else { el.setAttribute(attr, base + v.replace(/^\/+/,'')); } }catch(e){} };
        var scripts=document.getElementsByTagName('script'); for(var i=0;i<scripts.length;i++) fix(scripts[i], 'src');
        var imgs=document.getElementsByTagName('img'); for(var j=0;j<imgs.length;j++) fix(imgs[j], 'src');
    }catch(e){}
})();
JS;
            echo $this->Html->scriptBlock($script);
            echo $this->Html->script([
                // JS VENDOR
                '/assets/vendors/js/vendor.bundle.base.js',
                // JS
                '/assets/js/off-canvas.js',
                '/assets/js/hoverable-collapse.js',
                '/assets/js/misc.js'
            ]);
        ?>
    </body>
</html>