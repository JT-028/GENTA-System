<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */

$cakeDescription = 'CakePHP: the rapid development php framework';
?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= $cakeDescription ?>:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake']) ?>

    <?= $this->fetch('meta') ?>
    <?php if (isset($this) && method_exists($this->request, 'getAttribute')): ?>
        <meta name="csrfToken" content="<?= h($this->request->getAttribute('csrfToken')) ?>">
    <?php else: ?>
        <!-- WARNING: CSRF token meta tag not available. AJAX POSTs will fail. -->
    <?php endif; ?>
        <?= $this->fetch('css') ?>
        <?php
        $walkthrough_shown = null;
        if (isset($this) && $this->get('identity')) {
            $walkthrough_shown = $this->get('identity')->walkthrough_shown ?? null;
        } elseif (isset($this) && $this->Identity && $this->Identity->isLoggedIn()) {
            $walkthrough_shown = $this->Identity->get('walkthrough_shown');
        }
        ?>
        <script>
            window.walkthrough_shown = <?= ($walkthrough_shown === null ? 'null' : (int)$walkthrough_shown) ?>;
            try {
                window.APP_BASE = <?= json_encode($this->Url->build('/')) ?>;
            } catch (e) {
                window.APP_BASE = undefined;
            }
        </script>
        <?= $this->fetch('script') ?>
</head>
<body>
    <nav class="top-nav">
        <div class="top-nav-title">
            <a href="<?= $this->Url->build('/') ?>"><span>Cake</span>PHP</a>
        </div>
        <div class="top-nav-links">
            <a target="_blank" rel="noopener" href="https://book.cakephp.org/4/">Documentation</a>
            <a target="_blank" rel="noopener" href="https://api.cakephp.org/">API</a>
        </div>
    </nav>
    <main class="main">
        <div class="container">
            <?= $this->Flash->render() ?>
            <?= $this->fetch('content') ?>
        </div>
    </main>
    <footer>
    </footer>
</body>
    <script>
        (function(){
            try {
                // Fix asset URLs that were rendered with a different origin (e.g. old IP).
                var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
                function fixHref(el, attr){
                    var val = el.getAttribute(attr);
                    if(!val) return;
                    // only touch absolute URLs
                    if(val.indexOf('http://') === 0 || val.indexOf('https://') === 0){
                        try{
                            var u = new URL(val);
                            if(u.origin !== origin){
                                // replace origin but keep the path + query + hash
                                var newUrl = origin + u.pathname + u.search + u.hash;
                                el.setAttribute(attr, newUrl);
                            }
                        }catch(e){/* ignore malformed URLs */}
                    }
                }
                var links = document.querySelectorAll('link[rel="stylesheet"]');
                links.forEach(function(l){ fixHref(l, 'href'); });
                var scripts = document.querySelectorAll('script[src]');
                scripts.forEach(function(s){ fixHref(s, 'src'); });
                var imgs = document.querySelectorAll('img[src]');
                imgs.forEach(function(i){ fixHref(i, 'src'); });
            } catch(e) { console.error('asset origin fixer failed', e); }
        })();
    </script>
</html>
