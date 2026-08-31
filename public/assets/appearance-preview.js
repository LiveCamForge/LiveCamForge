(() => {
    const form = document.querySelector('[data-appearance-form]');
    const preview = document.querySelector('[data-appearance-preview]');
    if (!form || !preview) return;

    const fontStacks = {
        system: 'Inter, system-ui, -apple-system, Segoe UI, sans-serif',
        modern: 'Arial, Helvetica, sans-serif',
        rounded: 'Trebuchet MS, Arial, sans-serif',
        serif: 'Georgia, Times New Roman, serif'
    };
    const variables = {primary:'--pink',accent:'--purple',background:'--bg',surface:'--panel',text:'--text',muted:'--muted'};
    const colorInputs = Array.from(form.querySelectorAll('[data-theme-color]'));
    const hexInputs = Array.from(form.querySelectorAll('[data-theme-hex]'));
    const presetSelect = form.querySelector('[data-theme-preset]');
    const presetCards = Array.from(form.querySelectorAll('[data-theme-preset-card]'));
    const font = form.querySelector('[data-preview-font]');
    const cardStyleSelect = form.querySelector('[data-card-style]');
    const cardStyleCards = Array.from(form.querySelectorAll('[data-card-style-card]'));

    const normalizeHex = (value) => {
        let hex = String(value || '').trim().toUpperCase();
        if (hex && !hex.startsWith('#')) hex = '#' + hex;
        return /^#[0-9A-F]{6}$/.test(hex) ? hex : null;
    };

    const hexFor = (name) => form.querySelector(`[data-theme-hex="${name}"]`);
    const pickerFor = (name) => form.querySelector(`[data-theme-color="${name}"]`);

    const updateColor = (input, syncHex = true) => {
        const value = normalizeHex(input.value);
        if (!value) return;
        input.value = value.toLowerCase();
        preview.style.setProperty(variables[input.dataset.themeColor], value);
        if (syncHex) {
            const hex = hexFor(input.dataset.themeColor);
            if (hex) { hex.value = value; hex.classList.remove('invalid'); }
        }
    };

    const presetMatches = (card) => {
        if (!card || font.value !== card.dataset.themeFont) return false;
        return colorInputs.every((input) => input.value.toLowerCase() === (card.dataset[`theme${input.dataset.themeColor[0].toUpperCase()}${input.dataset.themeColor.slice(1)}`] || '').toLowerCase());
    };

    const syncPresetState = () => {
        const matched = presetCards.find(presetMatches);
        const value = matched ? matched.dataset.themePresetCard : 'custom';
        if (presetSelect) presetSelect.value = value;
        presetCards.forEach((card) => card.classList.toggle('active', card === matched));
    };

    function updateFont() {
        preview.style.fontFamily = fontStacks[font.value] || fontStacks.system;
    }

    const applyPreset = (card) => {
        if (!card) return;
        colorInputs.forEach((input) => {
            const key = `theme${input.dataset.themeColor[0].toUpperCase()}${input.dataset.themeColor.slice(1)}`;
            if (card.dataset[key]) {
                input.value = card.dataset[key];
                updateColor(input, true);
            }
        });
        if (card.dataset.themeFont && fontStacks[card.dataset.themeFont]) {
            font.value = card.dataset.themeFont;
            updateFont();
        }
        syncPresetState();
    };

    colorInputs.forEach((input) => {
        input.addEventListener('input', () => { updateColor(input, true); syncPresetState(); });
        updateColor(input, true);
    });

    hexInputs.forEach((hex) => {
        const commit = () => {
            const value = normalizeHex(hex.value);
            if (!value) { hex.classList.add('invalid'); return; }
            hex.value = value;
            hex.classList.remove('invalid');
            const picker = pickerFor(hex.dataset.themeHex);
            if (picker) { picker.value = value.toLowerCase(); updateColor(picker, false); }
            syncPresetState();
        };
        hex.addEventListener('input', () => {
            const value = normalizeHex(hex.value);
            if (!value) { hex.classList.toggle('invalid', hex.value.trim().length >= 6); return; }
            commit();
        });
        hex.addEventListener('blur', commit);
        hex.addEventListener('change', commit);
    });

    const bindText = (inputSelector, outputSelector) => {
        const input = form.querySelector(inputSelector);
        const output = preview.querySelector(outputSelector);
        if (!input || !output) return;
        input.addEventListener('input', () => { output.textContent = input.value; });
    };
    bindText('[data-preview-name]', '[data-preview-name-output]');
    bindText('[data-preview-eyebrow]', '[data-preview-eyebrow-output]');
    bindText('[data-preview-title]', '[data-preview-title-output]');
    bindText('[data-preview-intro]', '[data-preview-intro-output]');

    font.addEventListener('change', () => { updateFont(); syncPresetState(); });
    updateFont();

    presetCards.forEach((card) => card.addEventListener('click', () => applyPreset(card)));
    if (presetSelect) presetSelect.addEventListener('change', () => {
        if (presetSelect.value === 'custom') return;
        applyPreset(presetCards.find((card) => card.dataset.themePresetCard === presetSelect.value));
    });

    const applyCardStyle = (card) => {
        if (!card) return;
        preview.style.setProperty('--preview-card-radius', card.dataset.cardRadius || '10px');
        preview.style.setProperty('--preview-card-padding', card.dataset.cardBodyPadding || '10px');
        preview.style.setProperty('--preview-card-title-size', card.dataset.cardTitleSize || '12px');
        preview.style.setProperty('--preview-card-border', card.dataset.cardBorder || 'var(--border)');
        if (cardStyleSelect) cardStyleSelect.value = card.dataset.cardStyleCard;
        cardStyleCards.forEach((item) => item.classList.toggle('active', item === card));
    };
    cardStyleCards.forEach((card) => card.addEventListener('click', () => applyCardStyle(card)));
    if (cardStyleSelect) {
        cardStyleSelect.addEventListener('change', () => applyCardStyle(cardStyleCards.find((card) => card.dataset.cardStyleCard === cardStyleSelect.value)));
        applyCardStyle(cardStyleCards.find((card) => card.dataset.cardStyleCard === cardStyleSelect.value));
    }

    form.addEventListener('submit', (event) => {
        let invalid = false;
        hexInputs.forEach((hex) => {
            const value = normalizeHex(hex.value);
            if (!value) { hex.classList.add('invalid'); invalid = true; }
            else hex.value = value;
        });
        if (invalid) event.preventDefault();
    });

    const hero = form.querySelector('[data-preview-hero]');
    const heroOutput = preview.querySelector('[data-preview-hero-output]');
    const updateHero = () => { heroOutput.hidden = !hero.checked; };
    hero.addEventListener('change', updateHero);
    updateHero();
    syncPresetState();
})();
