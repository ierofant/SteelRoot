(() => {
    const fileInput = document.getElementById('avatar-file');
    const img = document.getElementById('avatar-image');
    const canvas = document.getElementById('avatar-canvas');
    const zoom = document.getElementById('zoom');
    const errBox = document.getElementById('err-box');
    const saveBtn = document.getElementById('save-avatar');
    const form = document.getElementById('avatar-form');
    const hiddenFile = document.getElementById('hidden-file');
    const cx = document.getElementById('crop_x');
    const cy = document.getElementById('crop_y');
    const cw = document.getElementById('crop_w');
    const ch = document.getElementById('crop_h');
    const cs = document.getElementById('crop_scale');

    let state = {
        loaded: false,
        naturalWidth: 0,
        naturalHeight: 0,
        x: 0,
        y: 0,
        scale: 1,
        dragging: false,
        startX: 0,
        startY: 0
    };

    function setError(msg) {
        errBox.textContent = msg || '';
    }

    function updateTransform() {
        img.style.transform = `translate(-50%, -50%) translate(${state.x}px, ${state.y}px) scale(${state.scale})`;
    }

    function handleFile(file) {
        if (!file) return;
        const url = URL.createObjectURL(file);
        img.onload = () => {
            state.naturalWidth = img.naturalWidth;
            state.naturalHeight = img.naturalHeight;
            state.scale = 1;
            state.x = 0;
            state.y = 0;
            zoom.value = 1;
            state.loaded = true;
            updateTransform();
        };
        img.src = url;

        // copy to hidden file input for form submit
        const dt = new DataTransfer();
        dt.items.add(file);
        hiddenFile.files = dt.files;
    }

    fileInput?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        setError('');
        handleFile(file);
    });

    zoom?.addEventListener('input', () => {
        state.scale = parseFloat(zoom.value);
        updateTransform();
    });

    canvas?.addEventListener('mousedown', (e) => {
        if (!state.loaded) return;
        state.dragging = true;
        state.startX = e.clientX - state.x;
        state.startY = e.clientY - state.y;
        img.style.cursor = 'grabbing';
    });
    window.addEventListener('mouseup', () => {
        state.dragging = false;
        img.style.cursor = 'grab';
    });
    window.addEventListener('mousemove', (e) => {
        if (!state.dragging) return;
        state.x = e.clientX - state.startX;
        state.y = e.clientY - state.startY;
        updateTransform();
    });

    function computeCrop() {
        const rect = canvas.getBoundingClientRect();
        const size = Math.min(rect.width, rect.height);
        const viewCenter = { x: rect.width / 2, y: rect.height / 2 };
        const imageCenterScreen = { x: viewCenter.x + state.x, y: viewCenter.y + state.y };

        const topLeftScreen = { x: viewCenter.x - size / 2, y: viewCenter.y - size / 2 };

        const cropX = (topLeftScreen.x - imageCenterScreen.x) / state.scale + (state.naturalWidth / 2);
        const cropY = (topLeftScreen.y - imageCenterScreen.y) / state.scale + (state.naturalHeight / 2);
        const cropW = size / state.scale;
        const cropH = size / state.scale;

        return {
            x: Math.max(0, Math.floor(cropX)),
            y: Math.max(0, Math.floor(cropY)),
            width: Math.min(state.naturalWidth, Math.floor(cropW)),
            height: Math.min(state.naturalHeight, Math.floor(cropH)),
            scale: state.scale,
            naturalWidth: state.naturalWidth,
            naturalHeight: state.naturalHeight
        };
    }

    saveBtn?.addEventListener('click', () => {
        if (!state.loaded || !hiddenFile.files.length) {
            setError('Выберите изображение');
            return;
        }
        const crop = computeCrop();
        cx.value = crop.x;
        cy.value = crop.y;
        cw.value = crop.width;
        ch.value = crop.height;
        cs.value = crop.scale;
        form.submit();
    });

    // preload current avatar if present
    (async () => {
        const current = img.dataset.current;
        if (!current) return;
        try {
            const res = await fetch(current, {cache:'no-cache'});
            if (!res.ok) return;
            const blob = await res.blob();
            const file = new File([blob], 'avatar.jpg', {type: blob.type || 'image/jpeg'});
            handleFile(file);
        } catch (e) {
            // ignore preload errors
        }
    })();
})();
