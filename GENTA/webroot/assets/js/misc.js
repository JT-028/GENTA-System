window.jQuery = window.$ = jQuery;
var ChartColor = ["#5D62B4", "#54C3BE", "#EF726F", "#F9C446", "rgb(93.0, 98.0, 180.0)", "#21B7EC", "#04BCCC"];
var primaryColor = getComputedStyle(document.body).getPropertyValue('--primary');
var secondaryColor = getComputedStyle(document.body).getPropertyValue('--secondary');
var successColor = getComputedStyle(document.body).getPropertyValue('--success');
var warningColor = getComputedStyle(document.body).getPropertyValue('--warning');
var dangerColor = getComputedStyle(document.body).getPropertyValue('--danger');
var infoColor = getComputedStyle(document.body).getPropertyValue('--info');
var darkColor = getComputedStyle(document.body).getPropertyValue('--dark');
var lightColor = getComputedStyle(document.body).getPropertyValue('--light');

(function($) {
  'use strict';
  $(function() {
    var body = $('body');
    var contentWrapper = $('.content-wrapper');
    var scroller = $('.container-scroller');
    var footer = $('.footer');
    var sidebar = $('.sidebar');

    // Load the navbar-offset fallback stylesheet if not already present.
    try {
      if (!document.querySelector('link[href*="_navbar-offset.css"]')) {
        var _ln = document.createElement('link');
        _ln.rel = 'stylesheet';
        (function() {
          // Use configured APP_BASE when available (e.g. '/GENTA/'), fallback to relative path
          var base = (window.APP_BASE !== undefined) ? String(window.APP_BASE) : null;
          if (!base) {
            // try to infer base from current location (take first segment)
            var pathParts = location.pathname.split('/').filter(function(p){return p.length>0});
            if (pathParts.length>0) {
              base = '/' + pathParts[0] + '/';
            } else {
              base = '/';
            }
          }
          if (base.slice(-1) !== '/') base += '/';
          _ln.href = base + 'assets/css/_navbar-offset.css';
        })();
        document.head.appendChild(_ln);
      }
    } catch (e) {
      // ignore - non-critical
    }

    // Cache proBanner early and guard uses
    var proBanner = document.querySelector('#proBanner');

    //Add active class to nav-link based on url dynamically
    //Active class can be hard coded directly in html file also as required

    function addActiveClass(element) {
      if (current === "") {
        //for root url
        if (element.attr('href').indexOf("index.html") !== -1) {
          element.parents('.nav-item').last().addClass('active');
          if (element.parents('.sub-menu').length) {
            element.closest('.collapse').addClass('show');
            element.addClass('active');
          }
        }
      } else {
        //for other url
        if (element.attr('href').indexOf(current) !== -1) {
          element.parents('.nav-item').last().addClass('active');
          if (element.parents('.sub-menu').length) {
            element.closest('.collapse').addClass('show');
            element.addClass('active');
          }
          if (element.parents('.submenu-item').length) {
            element.addClass('active');
          }
        }
      }
    }

    var current = location.pathname.split("/").slice(-1)[0].replace(/^\/|\/$/g, '');
    $('.nav li a', sidebar).each(function() {
      var $this = $(this);
      addActiveClass($this);
    })

    $('.horizontal-menu .nav li a').each(function() {
      var $this = $(this);
      addActiveClass($this);
    })

    //Close other submenu in sidebar on opening any

    sidebar.on('show.bs.collapse', '.collapse', function() {
      sidebar.find('.collapse.show').collapse('hide');
    });


    //Change sidebar and content-wrapper height
    applyStyles();

    function applyStyles() {
      //Applying perfect scrollbar
      if (!body.hasClass("rtl")) {
        if (body.hasClass("sidebar-fixed")) {
          var fixedSidebarScroll = new PerfectScrollbar('#sidebar .nav');
        }
      }
    }

    $('[data-toggle="minimize"]').on("click", function() {
      if ((body.hasClass('sidebar-toggle-display')) || (body.hasClass('sidebar-absolute'))) {
        body.toggleClass('sidebar-hidden');
      } else {
        body.toggleClass('sidebar-icon-only');
      }
    });

    //checkbox and radios
    $(".form-check label,.form-radio label").append('<i class="input-helper"></i>');

    //fullscreen
    $("#fullscreen-button").on("click", function toggleFullScreen() {
      if ((document.fullScreenElement !== undefined && document.fullScreenElement === null) || (document.msFullscreenElement !== undefined && document.msFullscreenElement === null) || (document.mozFullScreen !== undefined && !document.mozFullScreen) || (document.webkitIsFullScreen !== undefined && !document.webkitIsFullScreen)) {
        if (document.documentElement.requestFullScreen) {
          document.documentElement.requestFullScreen();
        } else if (document.documentElement.mozRequestFullScreen) {
          document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullScreen) {
          document.documentElement.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);
        } else if (document.documentElement.msRequestFullscreen) {
          document.documentElement.msRequestFullscreen();
        }
      } else {
        if (document.cancelFullScreen) {
          document.cancelFullScreen();
        } else if (document.mozCancelFullScreen) {
          document.mozCancelFullScreen();
        } else if (document.webkitCancelFullScreen) {
          document.webkitCancelFullScreen();
        } else if (document.msExitFullscreen) {
          document.msExitFullscreen();
        }
      }
    })
    // Cookie helpers: prefer $.cookie when available, fall back to document.cookie
    function getCookieValue(name) {
      try {
        if (typeof $.cookie === 'function') return $.cookie(name);
      } catch (e) {
        // ignore and fallback
      }
      var nameEQ = name + "=";
      var ca = document.cookie.split(';');
      for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
      }
      return undefined;
    }

    function setCookieValue(name, value, options) {
      try {
        if (typeof $.cookie === 'function') { $.cookie(name, value, options); return; }
      } catch (e) {
        // ignore and fallback
      }
      var cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value);
      if (options) {
        if (options.expires) {
          var exp = options.expires;
          if (Object.prototype.toString.call(exp) === '[object Date]') {
            cookie += '; expires=' + exp.toUTCString();
          } else if (typeof exp === 'number') {
            var d = new Date();
            d.setTime(d.getTime() + exp * 24 * 60 * 60 * 1000);
            cookie += '; expires=' + d.toUTCString();
          }
        }
        if (options.path) cookie += '; path=' + options.path;
        if (options.domain) cookie += '; domain=' + options.domain;
        if (options.secure) cookie += '; secure';
      }
      document.cookie = cookie;
    }

    var bannerCookie = getCookieValue('purple-free-banner');
    if (bannerCookie !== "true") {
      if (proBanner) {
        proBanner.classList.add('d-flex');
      }
      var tmpNav = document.querySelector('.navbar');
      if (tmpNav) tmpNav.classList.remove('fixed-top');
    } else {
      if (proBanner) {
        proBanner.classList.add('d-none');
      }
      var tmpNav2 = document.querySelector('.navbar');
      if (tmpNav2) tmpNav2.classList.add('fixed-top');
    }
    
    // Update top offset for the page content so it never sits under the navbar.
    // We measure the navbar height (and the optional pro banner height) and set
    // an inline padding-top on the page body wrapper. This avoids toggling
    // multiple Bootstrap spacing classes which were producing inconsistent
    // results when the banner is shown/hidden.
    var pageBody = document.querySelector('.page-body-wrapper');
    var navEl = document.querySelector('.navbar');
    var proBanner = document.querySelector('#proBanner');

    function updateTopOffset() {
      if (!pageBody || !navEl) return;
      var bannerVisible = proBanner && !proBanner.classList.contains('d-none') && !proBanner.classList.contains('d-flex') ? false : (proBanner && proBanner.classList.contains('d-flex'));
      // compute banner height only if visible
      var bannerHeight = (proBanner && proBanner.offsetHeight && bannerVisible) ? proBanner.offsetHeight : 0;

      if (navEl.classList.contains('fixed-top')) {
        // Set explicit padding equal to navbar height + banner height
        var top = navEl.offsetHeight + bannerHeight;
        pageBody.style.paddingTop = top + 'px';
        // add a body class so CSS can also provide a fallback offset
        document.body.classList.add('has-fixed-navbar');
      } else {
        // Let the layout flow naturally
        pageBody.style.paddingTop = '';
        document.body.classList.remove('has-fixed-navbar');
      }
    }

    // Initial update
    updateTopOffset();

    // When the banner close button is clicked we hide the banner, make navbar
    // fixed and recompute the offset.
    var bannerClose = document.querySelector('#bannerClose');
    if (bannerClose) {
      bannerClose.addEventListener('click', function() {
        if (proBanner) {
          proBanner.classList.add('d-none');
          proBanner.classList.remove('d-flex');
        }
        // make navbar fixed and recompute padding
        if (navEl) {
          navEl.classList.add('fixed-top');
          navEl.classList.remove('mt-3');
        }
        // store cookie
  var date = new Date();
  date.setTime(date.getTime() + 24 * 60 * 60 * 1000);
  setCookieValue('purple-free-banner', "true", { expires: date, path: '/' });
        // recompute offset after layout changes
        // use setTimeout to allow DOM reflow
        setTimeout(updateTopOffset, 50);
      });
    }

    // Recompute on window resize in case navbar height changes (responsive)
    window.addEventListener('resize', function() {
      setTimeout(updateTopOffset, 50);
    });
  });
})(jQuery);