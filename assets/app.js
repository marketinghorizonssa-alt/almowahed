(()=> {
  if (window.__almowahidDirectTagInit) return;
  window.__almowahidDirectTagInit = true;
  const dl = window.dataLayer = window.dataLayer || [];
  const qs = new URLSearchParams(location.search);
  const ATTRS = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','utm_id','utm_source_platform','utm_creative_format','utm_marketing_tactic','gclid','gbraid','wbraid','dclid','ttclid','fbclid','scclid','ScCid','msclkid','li_fat_id','twclid','srsltid','gad_source','gad_campaignid','campaign_id','campaignid','campaign_name','adgroup_id','adgroupid','adgroup_name','ad_id','creative','creative_id','ad_name','keyword','matchtype','network','device','placement','targetid','loc_physical_ms','loc_interest_ms','feeditemid','extensionid','adposition'];
  const ADS = {
    form: 'AW-16848499995/eOdNCLS91_8cEJvq_uE-',
    whatsapp: 'AW-16848499995/4cylCLe91_8cEJvq_uE-',
    call: 'AW-16848499995/mPNJCLq91_8cEJvq_uE-'
  };

  const WA_ATTR_ENDPOINT = 'https://marketing.hositee.com/wa_click_attribution.php';
  const WA_CLIENT_ID = 'cl_3ea5ae96e05c6b';
  const WA_BUSINESS_NUMBER = '+966537033347';
  const WA_DEFAULT_TEXT = 'السلام عليكم، أرغب في الاستفسار عن خدمات الموحد للاستقدام.';
  let firstTouch = null;
  let lastTouch = null;

  function attributionParams() {
    const out = {};
    ATTRS.forEach(key => {
      const value = getSavedAttribution(key);
      if (value) out[key] = value;
    });
    return out;
  }

  function touchSnapshot() {
    return {
      url: location.href,
      referrer: document.referrer || '',
      params: attributionParams(),
      at: new Date().toISOString()
    };
  }

  function initTouches() {
    const now = touchSnapshot();
    try {
      firstTouch = JSON.parse(sessionStorage.getItem('almowahid_first_touch_v1') || 'null');
    } catch (_) { firstTouch = null; }
    if (!firstTouch || typeof firstTouch !== 'object') {
      firstTouch = now;
      try { sessionStorage.setItem('almowahid_first_touch_v1', JSON.stringify(firstTouch)); } catch (_) {}
    }
    lastTouch = now;
    try { sessionStorage.setItem('almowahid_last_touch_v1', JSON.stringify(lastTouch)); } catch (_) {}
  }

  function makeClickId() {
    const rnd = (() => {
      try {
        const a = new Uint32Array(2);
        crypto.getRandomValues(a);
        return Array.from(a, n => n.toString(36)).join('');
      } catch (_) {
        return Math.random().toString(36).slice(2,14);
      }
    })();
    return 'clk_' + Date.now().toString(36) + '_' + rnd.slice(0,18);
  }

  function encodeHiddenMarker(value) {
    const alphabet = ['\u200B','\u200C','\u200D','\uFEFF'];
    const bytes = new TextEncoder().encode(value);
    let out = '';
    bytes.forEach(b => {
      out += alphabet[(b >> 6) & 3] + alphabet[(b >> 4) & 3] + alphabet[(b >> 2) & 3] + alphabet[b & 3];
    });
    return out;
  }

  function prepareWhatsappHref(href, clickId) {
    try {
      const u = new URL(href, location.href);
      if (!['wa.me','api.whatsapp.com'].includes(u.hostname)) return null;
      let visible = u.searchParams.get('text') || WA_DEFAULT_TEXT;
      visible = visible.replace(/[\u200B\u200C\u200D\uFEFF]{16,}/gu, '').trim();
      const token = 'hzn.attr.' + clickId;
      u.searchParams.set('text', visible + encodeHiddenMarker(token));
      return {href:u.toString(), token};
    } catch (_) {
      return null;
    }
  }

  function recordWhatsappClick(a, clickId, token, originalHref) {
    const current = touchSnapshot();
    lastTouch = current;
    const payload = {
      client_id: WA_CLIENT_ID,
      business_number: WA_BUSINESS_NUMBER,
      click_id: clickId,
      click_token: token,
      source_url: location.href,
      page_url: location.href,
      landing_url: firstTouch && firstTouch.url ? firstTouch.url : location.href,
      referrer: document.referrer || '',
      original_href: originalHref,
      button_text: (a.textContent || '').trim(),
      button_context: a.getAttribute('aria-label') || a.className || '',
      first_touch: firstTouch || current,
      last_touch: lastTouch,
      current_touch: current,
      query_params: attributionParams(),
      touch_history: [firstTouch || current, current],
      browser_time: new Date().toISOString()
    };
    fetch(WA_ATTR_ENDPOINT, {
      method: 'POST',
      headers: {'Content-Type':'application/json', 'Accept':'application/json'},
      body: JSON.stringify(payload),
      mode: 'cors',
      credentials: 'omit',
      keepalive: true
    }).catch(() => {});
  }

  function pushEvent(name, params={}) {
    // Direct Google tag only. Do not duplicate the same event with a second dataLayer event.
    if (typeof gtag === 'function') {
      gtag('event', name, params);
    }
  }

  function adsConversion(sendTo, params={}) {
    if (typeof gtag === 'function') {
      gtag('event', 'conversion', {send_to:sendTo, ...params});
    }
  }

  function getSavedAttribution(key) {
    return qs.get(key) || sessionStorage.getItem('almowahid_'+key) || '';
  }

  ATTRS.forEach(key => {
    const value = qs.get(key);
    if (value) {
      try { sessionStorage.setItem('almowahid_'+key, value); } catch (_) {}
    }
  });

  initTouches();

  document.querySelectorAll('[data-event]').forEach(a => {
    a.addEventListener('click', () => {
      const isPostFormWhatsapp = a.hasAttribute('data-whatsapp-complete');
      const rawName = a.dataset.event || '';
      const name = isPostFormWhatsapp && rawName === 'click_whatsapp'
        ? 'post_form_whatsapp'
        : rawName;
      const originalHref = a.href || '';
      const isWhatsapp = rawName === 'click_whatsapp';

      if (isWhatsapp) {
        const clickId = makeClickId();
        const tracked = prepareWhatsappHref(originalHref, clickId);
        if (tracked) {
          a.href = tracked.href;
          recordWhatsappClick(a, clickId, tracked.token, originalHref);
        }
      }

      const params = {link_url:a.href || originalHref, landing_path:location.pathname};
      pushEvent(name, params);

      // Only a normal website WhatsApp click is a Google Ads conversion.
      // The post-form CTA is tracked separately to avoid double counting.
      if (rawName === 'click_whatsapp' && !isPostFormWhatsapp) {
        adsConversion(ADS.whatsapp, {value:1, currency:'SAR'});
      }
      if (name === 'click_call') {
        adsConversion(ADS.call, {value:1, currency:'SAR'});
      }
    }, {passive:true});
  });

  document.querySelectorAll('[data-lead-form]').forEach(form => {
    const nameEl = form.querySelector('[name=name]');
    const phoneEl = form.querySelector('[name=phone]');
    const serviceEl = form.querySelector('[name=service]');
    const nationalityEl = form.querySelector('[name=nationality]');
    const saved = (() => {
      try { return JSON.parse(localStorage.getItem('almowahid_identity') || '{}'); }
      catch (_) { return {}; }
    })();

    if (nameEl && !nameEl.value && saved.name) nameEl.value = saved.name;
    if (phoneEl) {
      if (!phoneEl.value && saved.phone) phoneEl.value = saved.phone;
      phoneEl.removeAttribute('pattern');
      phoneEl.removeAttribute('minlength');
      phoneEl.placeholder = 'اكتب رقمك بأي صيغة محلية أو دولية';
    }

    if (serviceEl && qs.get('service')) {
      [...serviceEl.options].some(o => o.value === qs.get('service') && (serviceEl.value = o.value, true));
    }
    if (nationalityEl && qs.get('nationality')) {
      [...nationalityEl.options].some(o => o.value === qs.get('nationality') && (nationalityEl.value = o.value, true));
    }

    form.addEventListener('submit', async e => {
      e.preventDefault();

      const fd = new FormData(form);
      const raw = Object.fromEntries(fd.entries());
      const msg = form.querySelector('.msg');
      const success = form.querySelector('.success-box');
      const wa = form.querySelector('[data-whatsapp-complete]');
      const submit = form.querySelector('button[type=submit]');

      const fullName = String(raw.name || '').trim();
      const phone = String(raw.phone || '').trim();

      if (!fullName || !phone) {
        msg.className = 'msg err';
        msg.textContent = 'من فضلك أدخل الاسم ورقم الجوال.';
        return;
      }

      if (!raw.privacy_consent) {
        msg.className = 'msg err';
        msg.textContent = 'يلزم الموافقة على سياسة الخصوصية.';
        return;
      }

      const submissionId = 'ALMOWAHID-WEB-' + Date.now() + '-' + Math.random().toString(36).slice(2,10).toUpperCase();

      const payload = {
        submission_id: submissionId,
        form_id: 'ALMOWAHID_WEBSITE_FORM_V1',
        full_name: fullName,
        phone,
        service: String(raw.service || '').trim(),
        nationality: String(raw.nationality || '').trim(),
        message: String(raw.message || '').trim(),
        privacy_consent: 'yes',
        page_url: location.href,
        utm_source: getSavedAttribution('utm_source'),
        utm_medium: getSavedAttribution('utm_medium'),
        utm_campaign: getSavedAttribution('utm_campaign'),
        utm_term: getSavedAttribution('utm_term'),
        utm_content: getSavedAttribution('utm_content'),
        gclid: getSavedAttribution('gclid'),
        gbraid: getSavedAttribution('gbraid'),
        wbraid: getSavedAttribution('wbraid'),
        campaign_id: getSavedAttribution('campaign_id') || qs.get('gad_campaignid') || '',
        adgroup_id: getSavedAttribution('adgroup_id'),
        creative_id: getSavedAttribution('creative_id') || getSavedAttribution('ad_id')
      };

      const googlePaid = !!(payload.gclid || payload.gbraid || payload.wbraid || String(payload.utm_source || '').toLowerCase() === 'google');
      const sourceTag = googlePaid
        ? 'المصدر: Google Ads'
        : (payload.utm_source ? 'المصدر: ' + payload.utm_source : 'المصدر: Website');

      const lines = [
        'السلام عليكم، أريد طلب خدمة من موقع الموحد للاستقدام.',
        'الاسم: ' + fullName,
        'رقم الجوال: ' + phone,
        'الخدمة: ' + payload.service,
        payload.nationality ? 'الجنسية: ' + payload.nationality : '',
        payload.message ? 'التفاصيل: ' + payload.message : '',
        sourceTag,
        'الصفحة: ' + location.pathname
      ].filter(Boolean);

      if (success) success.hidden = true;
      msg.className = 'msg';
      msg.textContent = 'جاري إرسال طلبك...';

      const oldText = submit ? submit.textContent : '';
      if (submit) {
        submit.disabled = true;
        submit.textContent = 'جاري الإرسال...';
      }

      try {
        const res = await fetch('/ads/lead.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json', 'Accept':'application/json'},
          body: JSON.stringify(payload),
          credentials: 'same-origin'
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data.ok) {
          throw new Error(data.message || data.error || 'تعذر إرسال الطلب الآن.');
        }

        try {
          localStorage.setItem('almowahid_identity', JSON.stringify({name:fullName, phone}));
        } catch (_) {}

        if (wa) {
          wa.href = 'https://wa.me/966537033347?text=' + encodeURIComponent(lines.join('\n'));
        }

        msg.className = 'msg ok';
        msg.textContent = 'تم استلام طلبك بنجاح.';
        if (success) {
          success.hidden = false;
          success.scrollIntoView({behavior:'smooth', block:'nearest'});
        }

        const params = {
          form_name: 'almowahid_campaign_lead',
          service: payload.service,
          nationality: payload.nationality,
          landing_path: location.pathname,
          lead_id: data.lead_id || '',
          transaction_id: data.lead_id || submissionId
        };
        pushEvent('lead_form_success', params);
        adsConversion(ADS.form, {
          value:1,
          currency:'SAR',
          transaction_id:data.lead_id || submissionId
        });
      } catch (err) {
        msg.className = 'msg err';
        msg.textContent = err && err.message ? err.message : 'حدث خطأ، حاول مرة أخرى أو تواصل عبر واتساب.';
      } finally {
        if (submit) {
          submit.disabled = false;
          submit.textContent = oldText;
        }
      }
    });
  });

  document.querySelectorAll('.nav details').forEach(d => d.addEventListener('toggle', () => {
    if (d.open) {
      document.querySelectorAll('.nav details').forEach(o => { if (o !== d) o.open = false; });
    }
  }));
})();