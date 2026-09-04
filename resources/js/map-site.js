import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const mapsByLeafletId = new Map();

function payload(raw) {
    if (Array.isArray(raw) && raw.length === 1 && typeof raw[0] === 'object') {
        return raw[0];
    }

    return raw ?? {};
}

function closestWire(el) {
    const root = el.closest('[wire\\:id]');
    if (!root || !window.Livewire) {
        return null;
    }

    return window.Livewire.find(root.getAttribute('wire:id'));
}

function drawTables(inst, tables) {
    inst.layer.clearLayers();
    (tables || []).forEach((t) => {
        const marker = L.circleMarker([t.lat, t.lng], {
            radius: 8,
            color: '#ffffff',
            weight: 2,
            fillColor: t.color_hex || '#e11d48',
            fillOpacity: 0.95,
        });
        const label = `${t.stable_key || t.label || 'Tisch'}${t.has_umbrella ? ' (Schirm)' : ''}`;
        marker.bindTooltip(label, { direction: 'top' });
        inst.layer.addLayer(marker);
    });
}

function instanceFor(el) {
    if (el._tableSunMap) {
        return el._tableSunMap;
    }
    if (el._leaflet_id && mapsByLeafletId.has(el._leaflet_id)) {
        el._tableSunMap = mapsByLeafletId.get(el._leaflet_id);

        return el._tableSunMap;
    }

    return null;
}

function placeTable(el, lat, lng) {
    closestWire(el)?.addTable(lat, lng);
}

function bootOne(el) {
    let inst = instanceFor(el);

    if (!inst && !el._leaflet_id) {
        const lat = parseFloat(el.dataset.lat);
        const lng = parseFloat(el.dataset.lng);
        if (Number.isNaN(lat) || Number.isNaN(lng)) {
            return;
        }

        const zoom = parseInt(el.dataset.zoom || '19', 10);
        const maxZoom = parseInt(el.dataset.maxZoom || '19', 10);
        const map = L.map(el, { maxZoom }).setView([lat, lng], zoom);
        L.tileLayer(el.dataset.tileUrl, {
            attribution: el.dataset.tileAttr || '',
            maxZoom,
        }).addTo(map);

        const layer = L.layerGroup().addTo(map);
        inst = { map, layer };
        el._tableSunMap = inst;
        mapsByLeafletId.set(el._leaflet_id, inst);

        map.on('click', (e) => {
            if (el.dataset.readonly === '1') {
                return;
            }
            placeTable(el, e.latlng.lat, e.latlng.lng);
        });

        setTimeout(() => map.invalidateSize(), 100);
    }

    if (!inst) {
        return;
    }

    let initial = [];
    try {
        initial = el.dataset.tables ? JSON.parse(el.dataset.tables) : [];
    } catch {
        initial = [];
    }
    if (initial.length) {
        drawTables(inst, initial);
    } else {
        const tables = closestWire(el)?.tables;
        if (Array.isArray(tables) && tables.length) {
            drawTables(inst, tables);
        }
    }
}

export function bootTableSunMap() {
    document.querySelectorAll('#table-sun-map-root').forEach(bootOne);

    if (window.Livewire && !window.__tableSunMapListeners) {
        window.__tableSunMapListeners = true;
        window.Livewire.on('map-fly', (...args) => {
            const p = payload(args.length === 1 ? args[0] : args);
            document.querySelectorAll('#table-sun-map-root').forEach((el) => {
                const inst = instanceFor(el);
                if (!inst || p.lat == null || p.lng == null) {
                    return;
                }
                inst.map.setView([p.lat, p.lng], p.zoom ?? inst.map.getZoom());
                setTimeout(() => inst.map.invalidateSize(), 50);
            });
        });
        window.Livewire.on('map-tables-sync', (...args) => {
            const p = payload(args.length === 1 ? args[0] : args);
            document.querySelectorAll('#table-sun-map-root').forEach((el) => {
                const inst = instanceFor(el);
                if (!inst) {
                    return;
                }
                drawTables(inst, p.tables ?? []);
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', bootTableSunMap);
document.addEventListener('livewire:init', bootTableSunMap);
document.addEventListener('livewire:navigated', bootTableSunMap);
