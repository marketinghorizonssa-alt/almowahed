(()=> {
  const dl = window.dataLayer = window.dataLayer || [];
  const qs = new URLSearchParams(location.search);
  const ATTRS = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','campaign_id','adgroup_id','ad_id','creative_id'];
  const ADS = {
    form: 'AW-16848499995/eOdNCLS91_8cEJvq_uE-',
    whatsapp: 'AW-16848499995/4cylCLe91_8cEJvq_uE-',
    call: 'AW-16848499995/mPNJCLq91_8cEJvq_uE-'
  };

  function pushEvent(name, params={}) {
    dl.push({event:name, ...params});
    if (typeof gtag === 'function') gtag('event', name, params);
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

  document.querySelectorAll('[data-event]').forEach(a => {
    a.addEventListener('click', () => {
      const name = a.dataset.event || '';
      const params = {link_url:a.href || '', landing_path:location.pathname};
      pushEvent(name, params);
      if (name === 'click_whatsapp' && !a.hasAttribute('data-whatsapp-complete')) {
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

      const lines = [
        'السلام عليكم، أريد طلب خدمة من موقع الموحد للاستقدام.',
        'الاسم: ' + fullName,
        'رقم الجوال: ' + phone,
        'الخدمة: ' + payload.service,
        payload.nationality ? 'الجنسية: ' + payload.nationality : '',
        payload.message ? 'التفاصيل: ' + payload.message : '',
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