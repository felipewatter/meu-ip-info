/* global document, fetch, navigator, URL */
(function (root) {
    'use strict';

    function validCoordinates(lat, lng) {
        return typeof lat === 'number' && Number.isFinite(lat) &&
            typeof lng === 'number' && Number.isFinite(lng) &&
            lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
    }

    function safeText(value) {
        return typeof value === 'string' && value.trim() ? value : 'Não disponível';
    }

    function mapURL(lat, lng) {
        if (!validCoordinates(lat, lng)) {
            return null;
        }
        // The bounding box is intentionally broad: IP geolocation is approximate.
        const latMin = Math.max(-90, lat - 0.08);
        const latMax = Math.min(90, lat + 0.08);
        const lngMin = Math.max(-180, lng - 0.08);
        const lngMax = Math.min(180, lng + 0.08);
        const url = new URL('https://www.openstreetmap.org/export/embed.html');
        url.searchParams.set('bbox', [lngMin, latMin, lngMax, latMax].join(','));
        url.searchParams.set('layer', 'mapnik');
        url.searchParams.set('marker', lat + ',' + lng);
        return url.toString();
    }

    function setup(widget) {
        const endpoint = widget.getAttribute('data-lookup-url');
        if (!endpoint || widget.dataset.miiInitialized === 'yes') {
            return;
        }
        widget.dataset.miiInitialized = 'yes';
        const queryButton = widget.querySelector('.mii-lookup');
        const copyButton = widget.querySelector('.mii-copy');
        const mapButton = widget.querySelector('.mii-map');
        const status = widget.querySelector('.mii-status');
        const details = widget.querySelector('.mii-details');
        const mapContainer = widget.querySelector('.mii-map-container');
        if (!queryButton || !copyButton || !mapButton || !status || !details || !mapContainer) {
            return;
        }

        let ip = '';
        let lat = null;
        let lng = null;
        const fields = ['ip', 'org', 'country', 'region', 'city'];

        queryButton.addEventListener('click', async function () {
            queryButton.disabled = true;
            status.textContent = 'Consultando informações aproximadas…';
            details.hidden = true;
            copyButton.hidden = true;
            mapButton.hidden = true;
            mapContainer.hidden = true;
            mapContainer.replaceChildren();
            ip = '';
            lat = null;
            lng = null;

            try {
                const response = await fetch(endpoint, {
                    method: 'GET',
                    credentials: 'omit',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) {
                    throw new Error('Consulta indisponível');
                }
                const data = await response.json();
                if (!data || typeof data.ip !== 'string' || !data.ip.trim()) {
                    throw new Error('Resposta incompleta');
                }
                fields.forEach(function (name) {
                    const destination = details.querySelector('[data-field="' + name + '"]');
                    if (destination) {
                        destination.textContent = safeText(data[name]);
                    }
                });
                ip = data.ip;
                lat = data.latitude;
                lng = data.longitude;
                details.hidden = false;
                copyButton.hidden = false;
                mapButton.hidden = !validCoordinates(lat, lng);
                status.textContent = 'Consulta concluída. A localização por IP é aproximada.';
            } catch (_) {
                status.textContent = 'Não foi possível consultar os dados. Verifique a conexão ou tente novamente.';
            } finally {
                queryButton.disabled = false;
            }
        });

        copyButton.addEventListener('click', async function () {
            if (!ip) return;
            try {
                if (!navigator.clipboard || !navigator.clipboard.writeText) {
                    throw new Error('Clipboard não disponível');
                }
                await navigator.clipboard.writeText(ip);
                status.textContent = 'Endereço IP copiado.';
            } catch (_) {
                status.textContent = 'Não foi possível copiar automaticamente. Selecione o IP exibido para copiar.';
            }
        });

        mapButton.addEventListener('click', function () {
            const url = mapURL(lat, lng);
            if (!url) return;
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.title = 'Mapa de localização aproximada do endereço IP';
            iframe.loading = 'lazy';
            iframe.referrerPolicy = 'no-referrer';
            iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');
            iframe.setAttribute('allow', '');
            iframe.width = '100%';
            iframe.height = '350';
            mapContainer.replaceChildren(iframe);
            mapContainer.hidden = false;
            mapButton.hidden = true;
            status.textContent = 'Mapa carregado a partir do OpenStreetMap mediante sua solicitação.';
        });
    }

    function start() {
        document.querySelectorAll('.mii-widget').forEach(setup);
    }

    if (typeof module === 'object' && module.exports) {
        module.exports = { validCoordinates, safeText, mapURL };
    }
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }
})(typeof globalThis !== 'undefined' ? globalThis : this);
