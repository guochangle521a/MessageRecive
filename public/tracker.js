(function () {
  'use strict';
  var script = document.currentScript;
  var key = script && (script.dataset.siteKey || script.getAttribute('data-site-key'));
  if (!key) return;
  var endpoint = (script.dataset.endpoint || new URL('../api/collect/v1/events.php', script.src).href);
  var params = new URLSearchParams(location.search);
  var referrerDomain = '';
  try { referrerDomain = document.referrer ? new URL(document.referrer).hostname.toLowerCase() : ''; } catch (e) {}
  var productMeta = document.querySelector('meta[name="sanqi-product-key"]');
  var productKey = script.dataset.productKey || (productMeta && productMeta.content) || '';
  var attribution = {
    utm_source: params.get('utm_source') || '',
    utm_medium: params.get('utm_medium') || '',
    utm_campaign: params.get('utm_campaign') || '',
    referrer_domain: referrerDomain
  };
  try {
    var firstTouch = localStorage.getItem('sanqi_first_touch');
    if (!firstTouch) {
      localStorage.setItem('sanqi_first_touch', JSON.stringify(attribution));
      firstTouch = JSON.stringify(attribution);
    }
    window.SanqiAnalytics = {
      getAttribution: function () {
        var first = {};
        try { first = JSON.parse(firstTouch || '{}'); } catch (e) {}
        return {first_touch:first,conversion_touch:attribution};
      }
    };
  } catch (e) {}
  var payload = {
    site_key: key,
    event_type: 'page_view',
    page_url: location.href,
    page_path: location.pathname + location.search,
    page_title: document.title || '',
    referrer: document.referrer || '',
    occurred_at: new Date().toISOString(),
    screen: screen.width + 'x' + screen.height,
    language: navigator.language || '',
    locale: document.documentElement.lang || navigator.language || '',
    referrer_domain: referrerDomain,
    utm_source: attribution.utm_source,
    utm_medium: attribution.utm_medium,
    utm_campaign: attribution.utm_campaign,
    product_key: productKey
  };
  var body = JSON.stringify(payload);
  function fallbackToBeacon() {
    if (!navigator.sendBeacon) return;
    navigator.sendBeacon(endpoint, new Blob([body], {type:'text/plain;charset=UTF-8'}));
  }

  if (window.fetch) {
    fetch(endpoint, {
      method: 'POST',
      headers: {'Content-Type': 'text/plain;charset=UTF-8'},
      body: body,
      keepalive: true,
      credentials: 'omit'
    }).catch(fallbackToBeacon);
  } else {
    fallbackToBeacon();
  }
})();
