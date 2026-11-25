(function () {
  'use strict';

  var cfg = window.alminutoCookiesConfig || {};
  var COOKIE_NAME = cfg.cookieName || 'am_cookie_consent';
  var POLICY_VERSION = Number(cfg.version || 1);
  var CONSENT_DAYS = Number(cfg.consentDays || 365);
  var ENABLE_CONSENT_MODE = !!cfg.enableConsentMode;

  var banner = document.getElementById('am-cookie-banner');
  var modal = document.getElementById('am-cookies-modal');
  var executedTemplates = {};

  function getCookie(name) {
    var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + escaped + '=([^;]+)'));
    return match ? match[1] : null;
  }

  function setCookie(name, value, days) {
    var maxAge = Math.max(1, Math.round(days * 86400));
    var parts = [
      name + '=' + encodeURIComponent(value),
      'path=/',
      'max-age=' + maxAge,
      'SameSite=Lax'
    ];
    if (window.location.protocol === 'https:') {
      parts.push('Secure');
    }
    document.cookie = parts.join('; ');
  }

  function readConsent() {
    var raw = getCookie(COOKIE_NAME);
    if (!raw) return null;
    try {
      var parsed = JSON.parse(decodeURIComponent(raw));
      if (!parsed || Number(parsed.version) !== POLICY_VERSION || !parsed.categories) {
        return null;
      }
      return {
        necessary: true,
        preferences: !!parsed.categories.preferences,
        analytics: !!parsed.categories.analytics,
        marketing: !!parsed.categories.marketing
      };
    } catch (e) {
      return null;
    }
  }

  function writeConsent(categories) {
    var payload = {
      version: POLICY_VERSION,
      timestamp: new Date().toISOString(),
      categories: {
        necessary: true,
        preferences: !!categories.preferences,
        analytics: !!categories.analytics,
        marketing: !!categories.marketing
      }
    };
    setCookie(COOKIE_NAME, JSON.stringify(payload), CONSENT_DAYS);
    return payload.categories;
  }

  function updateConsentMode(categories) {
    if (!ENABLE_CONSENT_MODE || typeof window.gtag !== 'function') {
      return;
    }
    window.gtag('consent', 'update', {
      analytics_storage: categories.analytics ? 'granted' : 'denied',
      ad_storage: categories.marketing ? 'granted' : 'denied',
      ad_user_data: categories.marketing ? 'granted' : 'denied',
      ad_personalization: categories.marketing ? 'granted' : 'denied',
      functionality_storage: categories.preferences ? 'granted' : 'denied',
      personalization_storage: categories.preferences ? 'granted' : 'denied',
      security_storage: 'granted'
    });
  }

  function activateTemplateScripts(category) {
    if (executedTemplates[category]) return;
    var tpl = document.getElementById('am-cookie-tpl-' + category);
    if (!tpl) return;
    executedTemplates[category] = true;

    var fragment = tpl.content ? tpl.content.cloneNode(true) : null;
    if (!fragment) return;

    var scripts = fragment.querySelectorAll('script');
    scripts.forEach(function (oldScript) {
      var newScript = document.createElement('script');
      Array.prototype.forEach.call(oldScript.attributes, function (attr) {
        newScript.setAttribute(attr.name, attr.value);
      });
      if (oldScript.textContent) {
        newScript.textContent = oldScript.textContent;
      }
      if (oldScript.parentNode) {
        oldScript.parentNode.replaceChild(newScript, oldScript);
      }
    });

    document.body.appendChild(fragment);
  }

  function unblockEmbeds(category) {
    var boxes = document.querySelectorAll('[data-am-cookie-embed="' + category + '"]');
    boxes.forEach(function (box) {
      var iframe = box.querySelector('iframe[data-am-src]');
      if (iframe) {
        var realSrc = iframe.getAttribute('data-am-src');
        if (realSrc) {
          iframe.setAttribute('src', realSrc);
          iframe.removeAttribute('data-am-src');
        }
      }
      box.classList.add('is-unblocked');
    });
  }

  function applyConsent(categories) {
    updateConsentMode(categories);
    if (categories.analytics) {
      activateTemplateScripts('analytics');
    }
    if (categories.marketing) {
      activateTemplateScripts('marketing');
      unblockEmbeds('marketing');
    }
    syncModalToggles(categories);
    document.dispatchEvent(new CustomEvent('alminuto:consent-updated', { detail: categories }));
  }

  function syncModalToggles(categories) {
    if (!modal) return;
    var toggles = modal.querySelectorAll('[data-am-category-toggle]');
    toggles.forEach(function (input) {
      var cat = input.getAttribute('data-am-category-toggle');
      input.checked = !!(categories && categories[cat]);
    });
  }

  function showBanner() {
    if (banner) {
      banner.hidden = false;
    }
  }

  function hideBanner() {
    if (banner) {
      banner.hidden = true;
    }
  }

  function openModal() {
    if (!modal) return;
    var current = readConsent() || {
      necessary: true,
      preferences: false,
      analytics: false,
      marketing: false
    };
    syncModalToggles(current);
    if (typeof modal.showModal === 'function') {
      if (!modal.open) {
        modal.showModal();
      }
    } else {
      modal.setAttribute('open', '');
    }
  }

  function closeModal() {
    if (!modal) return;
    if (typeof modal.close === 'function') {
      if (modal.open) {
        modal.close();
      }
    } else {
      modal.removeAttribute('open');
    }
  }

  function acceptAll() {
    var cats = writeConsent({
      necessary: true,
      preferences: !!(cfg.categories && cfg.categories.preferences),
      analytics: !!(cfg.categories && cfg.categories.analytics),
      marketing: !!(cfg.categories && cfg.categories.marketing)
    });
    hideBanner();
    closeModal();
    applyConsent(cats);
  }

  function rejectAll() {
    var cats = writeConsent({
      necessary: true,
      preferences: false,
      analytics: false,
      marketing: false
    });
    hideBanner();
    closeModal();
    applyConsent(cats);
  }

  function saveModalPreferences() {
    var selection = {
      necessary: true,
      preferences: false,
      analytics: false,
      marketing: false
    };
    if (modal) {
      modal.querySelectorAll('[data-am-category-toggle]').forEach(function (input) {
        var cat = input.getAttribute('data-am-category-toggle');
        if (cat in selection) {
          selection[cat] = !!input.checked;
        }
      });
    }
    var cats = writeConsent(selection);
    hideBanner();
    closeModal();
    applyConsent(cats);
  }

  function setupDialogFallbacks() {
    if (!modal) return;
    if (!('switch' in HTMLInputElement.prototype)) {
      modal.querySelectorAll('input[type="checkbox"][switch]').forEach(function (el) {
        el.setAttribute('role', 'switch');
      });
    }
    if (!('closedBy' in HTMLDialogElement.prototype)) {
      var outside = function (e, dialog) {
        var r = dialog.getBoundingClientRect();
        return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
      };
      var pressedOutside = false;
      modal.addEventListener('pointerdown', function (e) {
        pressedOutside = outside(e, modal);
      });
      modal.addEventListener('pointerup', function (e) {
        if (pressedOutside && outside(e, modal)) {
          closeModal();
        }
      });
    }
  }

  function bindEvents() {
    document.addEventListener('click', function (e) {
      var actionEl = e.target.closest('[data-am-action]');
      if (actionEl) {
        var action = actionEl.getAttribute('data-am-action');
        if (action === 'accept-all') {
          acceptAll();
        } else if (action === 'reject-all') {
          rejectAll();
        } else if (action === 'open-settings') {
          openModal();
        } else if (action === 'save-settings') {
          saveModalPreferences();
        } else if (action === 'close-modal') {
          closeModal();
        }
        return;
      }

      var openTrigger = e.target.closest('[data-am-cookies-open]');
      if (openTrigger) {
        e.preventDefault();
        openModal();
        return;
      }

      var acceptCategoryBtn = e.target.closest('[data-am-accept-category]');
      if (acceptCategoryBtn) {
        var cat = acceptCategoryBtn.getAttribute('data-am-accept-category');
        var current = readConsent() || {
          necessary: true,
          preferences: false,
          analytics: false,
          marketing: false
        };
        current[cat] = true;
        var saved = writeConsent(current);
        hideBanner();
        applyConsent(saved);
      }
    });
  }

  function init() {
    setupDialogFallbacks();
    bindEvents();

    var existing = readConsent();
    if (existing) {
      hideBanner();
      applyConsent(existing);
    } else {
      showBanner();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
