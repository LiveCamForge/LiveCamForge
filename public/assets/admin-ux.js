(() => {
  const ready = (fn) => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn();
  ready(() => {
    document.querySelectorAll('.provider-config-card').forEach((card, index) => {
      const heading = card.querySelector('.provider-config-heading');
      if (!heading || heading.querySelector('.provider-collapse-toggle')) return;
      const status = heading.querySelector('.run-status');
      const configured = status && status.classList.contains('success');
      if (configured) card.classList.add('collapsed');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'provider-collapse-toggle';
      const setLabel = () => {
        const collapsed = card.classList.contains('collapsed');
        const form = card.closest('[data-integration-tabs]');
        button.textContent = collapsed ? (form?.dataset.showLabel || 'Show') : (form?.dataset.hideLabel || 'Hide');
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      };
      button.addEventListener('click', () => { card.classList.toggle('collapsed'); setLabel(); });
      heading.appendChild(button);
      setLabel();
    });

    document.querySelectorAll('[data-localized-editor]').forEach((editor) => {
      const tabs = Array.from(editor.querySelectorAll(':scope > .localized-language-tabs [data-language-tab]'));
      const panels = Array.from(editor.querySelectorAll(':scope > [data-language-panel]'));
      const activate = (locale) => {
        tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.languageTab === locale));
        panels.forEach((panel) => panel.classList.toggle('active', panel.dataset.languagePanel === locale));
      };
      tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.languageTab)));
      const initial = editor.dataset.defaultLocale || tabs[0]?.dataset.languageTab;
      if (initial) activate(initial);
    });

    document.querySelectorAll('[data-language-panel]').forEach((panel) => {
      const titleInput = panel.querySelector('[data-seo-title-input]');
      const descriptionInput = panel.querySelector('[data-seo-description-input]');
      const titleCount = panel.querySelector('[data-seo-title-count]');
      const descriptionCount = panel.querySelector('[data-seo-description-count]');
      const previewTitle = panel.querySelector('[data-seo-preview-title]');
      const previewDescription = panel.querySelector('[data-seo-preview-description]');
      if (!titleInput && !descriptionInput) return;
      const refreshSeoPreview = () => {
        if (titleInput) {
          if (titleCount) titleCount.textContent = `${titleInput.value.length} ${titleCount.dataset.countLabel || 'characters'}`;
          if (previewTitle) previewTitle.textContent = titleInput.value || 'SEO title preview';
        }
        if (descriptionInput) {
          if (descriptionCount) descriptionCount.textContent = `${descriptionInput.value.length} ${descriptionCount.dataset.countLabel || 'characters'}`;
          if (previewDescription) previewDescription.textContent = descriptionInput.value || 'Meta description preview';
        }
      };
      titleInput?.addEventListener('input', refreshSeoPreview);
      descriptionInput?.addEventListener('input', refreshSeoPreview);
      refreshSeoPreview();
    });

    document.querySelectorAll('.landing-list-item[data-edit-url]').forEach((item) => {
      item.tabIndex = 0;
      const go = (event) => {
        if (event.target.closest('a,button,input,select,textarea')) return;
        window.location.href = item.dataset.editUrl;
      };
      item.addEventListener('click', go);
      item.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && event.target === item) {
          event.preventDefault();
          window.location.href = item.dataset.editUrl;
        }
      });
    });



    const integrationTabs = document.querySelector('[data-integration-tabs]');
    if (integrationTabs) {
      const buttons = document.querySelectorAll('[data-integration-tab]');
      const panels = integrationTabs.querySelectorAll('[data-integration-panel]');
      const activate = (name) => {
        buttons.forEach((b) => b.classList.toggle('active', b.dataset.integrationTab === name));
        panels.forEach((p) => p.classList.toggle('active', p.dataset.integrationPanel === name));
      };
      buttons.forEach((button) => button.addEventListener('click', () => activate(button.dataset.integrationTab)));
      const requested = location.hash ? location.hash.slice(1) : '';
      if (requested && integrationTabs.querySelector(`[data-integration-panel="${requested}"]`)) activate(requested);
    }



    const catalogForm = document.querySelector('[data-catalog-settings-form]');
    if (catalogForm) {
      const modeSelect = catalogForm.querySelector('[data-catalog-mode]');
      const combinedOption = catalogForm.querySelector('[data-catalog-combined]');
      const enabledSourceCount = () => {
        const values = new Set();
        catalogForm.querySelectorAll('input[name="enabled_providers[]"]:checked').forEach((input) => {
          if (input.value) values.add(input.value);
        });
        catalogForm.querySelectorAll('select[name="enabled_providers[]"]').forEach((select) => {
          if (select.value) values.add(select.value);
        });
        return values.size;
      };
      const refreshCatalogMode = () => {
        if (!combinedOption || !modeSelect) return;
        const available = enabledSourceCount() >= 2;
        combinedOption.disabled = !available;
        if (!available && modeSelect.value === 'combined') modeSelect.value = 'single';
      };
      catalogForm.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement || target instanceof HTMLSelectElement) {
          if (target.name === 'enabled_providers[]') refreshCatalogMode();
        }
      });
      refreshCatalogMode();
    }

    const modal = document.querySelector('[data-responsive-preview-modal]');
    const openPreview = document.querySelector('[data-responsive-preview-open]');
    const closePreview = document.querySelector('[data-responsive-preview-close]');
    const livePreview = document.querySelector('[data-appearance-preview]');
    const modalFrame = document.querySelector('[data-responsive-preview-frame]');
    const syncResponsivePreview = () => {
      if (!livePreview || !modalFrame) return;
      modalFrame.innerHTML = livePreview.innerHTML;
      modalFrame.style.cssText = livePreview.style.cssText;
      modalFrame.className = 'appearance-preview responsive-preview-frame';
      const current = document.querySelector('[data-modal-preview-device].active')?.dataset.modalPreviewDevice || 'desktop';
      modalFrame.dataset.previewDeviceCurrent = current;
    };
    if (openPreview && modal) openPreview.addEventListener('click', () => { syncResponsivePreview(); modal.hidden = false; document.body.classList.add('modal-open'); });
    const closeResponsivePreview = () => { if (modal) modal.hidden = true; document.body.classList.remove('modal-open'); };
    if (closePreview) closePreview.addEventListener('click', closeResponsivePreview);
    if (modal) modal.addEventListener('click', (event) => { if (event.target === modal) closeResponsivePreview(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal && !modal.hidden) closeResponsivePreview(); });
    document.querySelectorAll('[data-modal-preview-device]').forEach((button) => button.addEventListener('click', () => {
      document.querySelectorAll('[data-modal-preview-device]').forEach((b) => b.classList.remove('active'));
      button.classList.add('active');
      if (modalFrame) modalFrame.dataset.previewDeviceCurrent = button.dataset.modalPreviewDevice;
    }));
    const appearanceForm = document.querySelector('[data-appearance-form]');
    if (appearanceForm) appearanceForm.addEventListener('input', () => { if (modal && !modal.hidden) requestAnimationFrame(syncResponsivePreview); });

    const rtf = typeof Intl !== 'undefined' && Intl.RelativeTimeFormat
      ? new Intl.RelativeTimeFormat(document.documentElement.lang || 'en', { numeric: 'auto' })
      : null;
    const providerFilter = document.querySelector('[data-conversion-provider-filter]');
    const attributionFilter = document.querySelector('[data-conversion-attribution-filter]');
    const applyConversionFilters = () => {
      const provider = providerFilter ? providerFilter.value : '';
      const attribution = attributionFilter ? attributionFilter.value : '';
      document.querySelectorAll('[data-conversion-row]').forEach((row) => {
        const visible = (!provider || row.dataset.provider === provider) && (!attribution || row.dataset.attribution === attribution);
        row.hidden = !visible;
      });
    };
    if (providerFilter) providerFilter.addEventListener('change', applyConversionFilters);
    if (attributionFilter) attributionFilter.addEventListener('change', applyConversionFilters);

    document.querySelectorAll('time.relative-time[datetime]').forEach((el) => {
      const date = new Date(el.getAttribute('datetime').replace(' ', 'T'));
      if (!rtf || Number.isNaN(date.getTime())) return;
      const seconds = Math.round((date.getTime() - Date.now()) / 1000);
      const abs = Math.abs(seconds);
      let unit = 'second', value = seconds;
      if (abs >= 86400) { unit = 'day'; value = Math.round(seconds / 86400); }
      else if (abs >= 3600) { unit = 'hour'; value = Math.round(seconds / 3600); }
      else if (abs >= 60) { unit = 'minute'; value = Math.round(seconds / 60); }
      el.textContent = rtf.format(value, unit);
    });
  });
})();
