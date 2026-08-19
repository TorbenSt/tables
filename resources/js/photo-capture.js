/**
 * Handy-Kamera + Metadaten für Model 2.
 */

function compassHeading(event) {
    if (event.webkitCompassHeading != null && ! Number.isNaN(event.webkitCompassHeading)) {
        return event.webkitCompassHeading;
    }
    if (event.alpha != null) {
        return (360 - event.alpha) % 360;
    }

    return null;
}

function screenAngle() {
    return screen.orientation?.angle
        ?? (typeof window.orientation === 'number' ? window.orientation : 0)
        ?? 0;
}

function normalizeBearing(value) {
    const n = Number(value);
    if (! Number.isFinite(n)) {
        return null;
    }

    return ((n % 360) + 360) % 360;
}

async function requestOrientationPermission() {
    if (typeof DeviceOrientationEvent !== 'undefined'
        && typeof DeviceOrientationEvent.requestPermission === 'function') {
        const state = await DeviceOrientationEvent.requestPermission();

        return state === 'granted';
    }

    return true;
}

async function getPosition() {
    if (! ('geolocation' in navigator)) {
        return null;
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({
                latitude: pos.coords.latitude,
                longitude: pos.coords.longitude,
                accuracy: pos.coords.accuracy,
                heading: pos.coords.heading,
            }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 5000 },
        );
    });
}

function readExifGps(buffer) {
    const view = new DataView(buffer);
    if (view.byteLength < 12 || view.getUint16(0) !== 0xFFD8) {
        return {};
    }

    let offset = 2;
    while (offset + 4 < view.byteLength) {
        if (view.getUint8(offset) !== 0xFF) {
            break;
        }
        const marker = view.getUint8(offset + 1);
        const size = view.getUint16(offset + 2);
        if (marker === 0xE1) {
            return parseExif(view, offset + 4, size - 2);
        }
        if (marker === 0xDA) {
            break;
        }
        offset += 2 + size;
    }

    return {};
}

function parseExif(view, start, length) {
    const end = Math.min(start + length, view.byteLength);
    if (start + 6 > end) {
        return {};
    }
    const header = String.fromCharCode(
        view.getUint8(start),
        view.getUint8(start + 1),
        view.getUint8(start + 2),
        view.getUint8(start + 3),
    );
    if (header !== 'Exif') {
        return {};
    }

    const tiff = start + 6;
    const little = view.getUint16(tiff) === 0x4949;
    const get16 = (o) => view.getUint16(o, little);
    const get32 = (o) => view.getUint32(o, little);

    const readRational = (o) => {
        const n = get32(o);
        const d = get32(o + 4);

        return d ? n / d : null;
    };

    const readIfd = (ifdOffset) => {
        const abs = tiff + ifdOffset;
        if (abs + 2 > view.byteLength) {
            return {};
        }
        const count = get16(abs);
        const tags = {};
        for (let i = 0; i < count; i++) {
            const entry = abs + 2 + i * 12;
            if (entry + 12 > view.byteLength) {
                break;
            }
            tags[get16(entry)] = {
                type: get16(entry + 2),
                num: get32(entry + 4),
                dataOffset: entry + 8,
            };
        }

        return tags;
    };

    const resolveOffset = (tag) => {
        const typeSize = { 1: 1, 2: 1, 3: 2, 4: 4, 5: 8, 10: 8 }[tag.type] ?? 1;
        const byteLength = tag.num * typeSize;

        return byteLength > 4 ? tiff + get32(tag.dataOffset) : tag.dataOffset;
    };

    const readString = (tag) => {
        if (! tag || tag.type !== 2) {
            return null;
        }
        const dataOffset = resolveOffset(tag);
        let s = '';
        for (let i = 0; i < tag.num - 1; i++) {
            s += String.fromCharCode(view.getUint8(dataOffset + i));
        }

        return s;
    };

    const ifd0 = readIfd(get32(tiff + 4));
    const gpsPtr = ifd0[0x8825];
    const exifPtr = ifd0[0x8769];
    const out = {};

    if (exifPtr && exifPtr.type === 4) {
        const exifIfd = readIfd(get32(exifPtr.dataOffset));
        const dto = readString(exifIfd[0x9003]);
        if (dto && /^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/.test(dto)) {
            out.date = dto.slice(0, 10).replaceAll(':', '-');
            out.time = dto.slice(11, 16);
        }
    }

    if (gpsPtr && gpsPtr.type === 4) {
        const gps = readIfd(get32(gpsPtr.dataOffset));
        const toDecimal = (coordTag, refTag, negativeLetters) => {
            if (! coordTag || coordTag.num < 3) {
                return null;
            }
            const dataOffset = resolveOffset(coordTag);
            const deg = readRational(dataOffset);
            const min = readRational(dataOffset + 8);
            const sec = readRational(dataOffset + 16);
            if (deg == null || min == null || sec == null) {
                return null;
            }
            let dec = deg + min / 60 + sec / 3600;
            const refTagOffset = refTag ? resolveOffset(refTag) : null;
            const ref = refTagOffset != null ? String.fromCharCode(view.getUint8(refTagOffset)) : '';
            if (negativeLetters.includes(ref)) {
                dec *= -1;
            }

            return Math.round(dec * 1e7) / 1e7;
        };

        const lat = toDecimal(gps[0x0002], gps[0x0001], 'S');
        const lng = toDecimal(gps[0x0004], gps[0x0003], 'W');
        if (lat != null && lng != null) {
            out.latitude = lat;
            out.longitude = lng;
        }

        const dirTag = gps[0x0011] ?? gps[0x0018];
        if (dirTag) {
            const dir = readRational(resolveOffset(dirTag));
            if (dir != null) {
                out.bearing = normalizeBearing(dir);
            }
        }
    }

    return out;
}

async function extractFileMeta(file) {
    const slice = file.slice(0, 128 * 1024);
    const buffer = await slice.arrayBuffer();

    return readExifGps(buffer);
}

function closestWire(el) {
    const root = el.closest('[wire\\:id]');
    if (! root || ! window.Livewire) {
        return null;
    }

    return window.Livewire.find(root.getAttribute('wire:id'));
}

function applyMeta(wire, index, meta) {
    if (meta.latitude != null) {
        wire.set(`meta.${index}.latitude`, String(meta.latitude));
    }
    if (meta.longitude != null) {
        wire.set(`meta.${index}.longitude`, String(meta.longitude));
    }
    if (meta.bearing != null) {
        const b = normalizeBearing(meta.bearing);
        if (b != null) {
            wire.set(`meta.${index}.bearing`, String(Math.round(b * 10) / 10));
        }
    }
    if (meta.time) {
        wire.set(`meta.${index}.time`, meta.time);
    }
    if (meta.date) {
        wire.set('capture_date', meta.date);
    }
    if (meta.source) {
        wire.set(`meta.${index}.source`, meta.source);
    }
}

function pad(n) {
    return String(n).padStart(2, '0');
}

function nowStamp() {
    const now = new Date();

    return {
        time: `${pad(now.getHours())}:${pad(now.getMinutes())}`,
        date: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
        fileStamp: `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`,
    };
}

function directionLabel(heading) {
    if (heading == null) {
        return 'Kompass nicht verfügbar – Handy in Blickrichtung halten';
    }
    const dirs = ['N', 'NO', 'O', 'SO', 'S', 'SW', 'W', 'NW'];
    const i = Math.round(heading / 45) % 8;

    return `${Math.round(heading)}° ${dirs[i]}`;
}

const camera = {
    stream: null,
    heading: null,
    geo: null,
    orientHandler: null,
    wire: null,
};

function overlay() {
    return document.getElementById('tables-camera-overlay');
}

function setOverlayError(message) {
    const el = overlay()?.querySelector('[data-camera-error]');
    if (el) {
        el.textContent = message ?? '';
        el.classList.toggle('hidden', ! message);
    }
}

function setHeadingLabel() {
    const el = overlay()?.querySelector('[data-camera-heading]');
    if (el) {
        el.textContent = directionLabel(camera.heading);
    }
}

function startCompass() {
    stopCompass();
    camera.orientHandler = (event) => {
        const raw = compassHeading(event);
        if (raw == null) {
            return;
        }
        camera.heading = normalizeBearing(raw + screenAngle());
        setHeadingLabel();
        const lockStatus = document.querySelector('[data-lock-status]');
        if (lockStatus && camera.heading != null) {
            lockStatus.textContent = `Kompass: ${directionLabel(camera.heading)}`;
        }
    };
    window.addEventListener('deviceorientationabsolute', camera.orientHandler, true);
    window.addEventListener('deviceorientation', camera.orientHandler, true);
}

function stopCompass() {
    if (camera.orientHandler) {
        window.removeEventListener('deviceorientationabsolute', camera.orientHandler, true);
        window.removeEventListener('deviceorientation', camera.orientHandler, true);
        camera.orientHandler = null;
    }
}

function stopCamera() {
    stopCompass();
    if (camera.stream) {
        camera.stream.getTracks().forEach((t) => t.stop());
        camera.stream = null;
    }
    const video = overlay()?.querySelector('video');
    if (video) {
        video.srcObject = null;
    }
    overlay()?.classList.add('hidden');
    overlay()?.setAttribute('aria-hidden', 'true');
}

async function snapshotFile() {
    const video = overlay()?.querySelector('video');
    if (! video?.videoWidth) {
        throw new Error('Kamerabild noch nicht bereit.');
    }

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
    if (! blob) {
        throw new Error('Aufnahme fehlgeschlagen.');
    }

    const stamp = nowStamp();

    return {
        file: new File([blob], `kamera-${stamp.fileStamp}.jpg`, { type: 'image/jpeg' }),
        stamp,
    };
}

async function openSessionCamera(wire) {
    camera.wire = wire;
    camera.heading = null;
    camera.geo = null;
    setOverlayError('');
    overlay()?.classList.remove('hidden');
    overlay()?.setAttribute('aria-hidden', 'false');
    setHeadingLabel();

    try {
        await requestOrientationPermission();
        startCompass();
        camera.geo = await getPosition();

        if (! navigator.mediaDevices?.getUserMedia) {
            throw new Error('getUserMedia fehlt');
        }

        camera.stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1440 },
            },
        });
        const video = overlay().querySelector('video');
        video.srcObject = camera.stream;
        await video.play();
    } catch {
        setOverlayError('Kamera nicht verfügbar. Bitte HTTPS nutzen und Zugriff erlauben.');
        stopCompass();
    }
}

async function shootSession() {
    if (! camera.wire) {
        setOverlayError('Keine Session.');

        return;
    }

    let snapshot;
    try {
        snapshot = await snapshotFile();
    } catch (e) {
        setOverlayError(e.message);

        return;
    }

    const geo = camera.geo ?? await getPosition();
    const gpsHeading = geo?.heading != null && geo.heading >= 0 ? geo.heading : null;
    const heading = camera.heading ?? gpsHeading ?? '';

    await new Promise((resolve, reject) => {
        camera.wire.upload('shot', snapshot.file, () => resolve(), () => reject(new Error('upload')), () => {});
    });

    await camera.wire.storeShot(
        geo ? String(geo.latitude) : '',
        geo ? String(geo.longitude) : '',
        heading === '' ? '' : String(Math.round(normalizeBearing(heading) * 10) / 10),
        snapshot.stamp.time,
    );

    stopCamera();
}

async function fillSharedGeo(wire) {
    await requestOrientationPermission();
    startCompass();
    const geo = await getPosition();
    if (geo) {
        wire.set('sharedLatitude', String(geo.latitude));
        wire.set('sharedLongitude', String(geo.longitude));
    }
    if (camera.heading != null) {
        wire.set('sharedBearing', String(Math.round(camera.heading * 10) / 10));
    }
}

async function fillLockFix(wire) {
    await requestOrientationPermission();
    startCompass();
    const geo = await getPosition();
    const lat = geo ? String(geo.latitude) : '';
    const lng = geo ? String(geo.longitude) : '';
    await new Promise((resolve) => setTimeout(resolve, 400));
    const heading = camera.heading != null ? String(Math.round(camera.heading * 10) / 10) : '';
    await wire.applyDeviceFix(lat, lng, heading);
    const status = document.querySelector('[data-lock-status]');
    if (status) {
        status.textContent = geo
            ? `Standort ±${Math.round(geo.accuracy || 0)} m · ${directionLabel(camera.heading)}`
            : 'Kein GPS – Werte manuell oder Kompass prüfen.';
    }
}

async function handleFileInput(input) {
    const file = input.files?.[0];
    const index = Number(input.dataset.photoIndex);
    const wire = closestWire(input);
    if (! file || Number.isNaN(index) || ! wire) {
        return;
    }

    const applyGeo = input.dataset.applyGeo === '1';
    const [exif, geo] = await Promise.all([extractFileMeta(file), applyGeo ? getPosition() : Promise.resolve(null)]);
    const meta = { source: 'datei' };

    if (exif.time) {
        meta.time = exif.time;
    }
    if (exif.date) {
        meta.date = exif.date;
    }

    if (applyGeo) {
        if (exif.latitude != null) {
            Object.assign(meta, {
                latitude: exif.latitude,
                longitude: exif.longitude,
                source: 'exif',
            });
        } else if (geo) {
            Object.assign(meta, {
                latitude: geo.latitude,
                longitude: geo.longitude,
                source: 'standort',
            });
        }
        if (exif.bearing != null) {
            meta.bearing = exif.bearing;
        }
    }

    applyMeta(wire, index, meta);
}

function bind() {
    document.addEventListener('click', (event) => {
        const sessionBtn = event.target.closest('[data-open-camera-session]');
        if (sessionBtn) {
            event.preventDefault();
            const wire = closestWire(sessionBtn);
            if (wire) {
                openSessionCamera(wire);
            }

            return;
        }
        if (event.target.closest('[data-camera-close]')) {
            event.preventDefault();
            stopCamera();

            return;
        }
        if (event.target.closest('[data-camera-shoot]')) {
            event.preventDefault();
            shootSession();

            return;
        }
        const sharedGeo = event.target.closest('[data-fill-shared-geo]');
        if (sharedGeo) {
            event.preventDefault();
            const wire = closestWire(sharedGeo);
            if (wire) {
                fillSharedGeo(wire);
            }

            return;
        }
        const lockFix = event.target.closest('[data-lock-device-fix]');
        if (lockFix) {
            event.preventDefault();
            const wire = closestWire(lockFix);
            if (wire) {
                fillLockFix(wire);
            }
        }
    });

    document.addEventListener('change', (event) => {
        const input = event.target.closest('[data-photo-file]');
        if (input) {
            handleFileInput(input);
        }
    });
}

export function bootPhotoCapture() {
    bind();
}
