@props(['index'])

<div class="select-none">
    <p class="text-sm text-stone-600">Blickrichtung</p>
    <p class="text-xs text-stone-500">Zeiger auf die Richtung ziehen, in die die Kamera schaut.</p>

    <div
        class="mt-3 flex flex-col items-center gap-2"
        x-data="{
            bearing: @entangle('meta.'.$index.'.bearing').live,
            dragging: false,
            get deg() {
                const n = Number(this.bearing);
                return Number.isFinite(n) ? ((n % 360) + 360) % 360 : 0;
            },
            get isSet() {
                return this.bearing !== '' && this.bearing !== null && Number.isFinite(Number(this.bearing));
            },
            get caption() {
                const names = ['N', 'NO', 'O', 'SO', 'S', 'SW', 'W', 'NW'];
                if (! this.isSet) {
                    return 'noch nicht gesetzt';
                }
                return names[Math.round(this.deg / 45) % 8] + ' · ' + Math.round(this.deg) + '°';
            },
            fromEvent(event) {
                const rect = this.$refs.dial.getBoundingClientRect();
                const x = event.clientX - (rect.left + rect.width / 2);
                const y = event.clientY - (rect.top + rect.height / 2);
                let deg = Math.atan2(x, -y) * 180 / Math.PI;
                if (deg < 0) deg += 360;
                return Math.round(deg * 10) / 10;
            },
            apply(event) {
                this.bearing = String(this.fromEvent(event));
            },
            nudge(delta) {
                this.bearing = String(Math.round((((this.deg + delta) % 360) + 360) % 360));
            },
        }"
    >
        <div
            x-ref="dial"
            role="slider"
            tabindex="0"
            aria-label="Blickrichtung auf dem Kompass"
            :aria-valuenow="isSet ? Math.round(deg) : null"
            aria-valuemin="0"
            aria-valuemax="360"
            :aria-valuetext="caption"
            class="relative h-44 w-44 cursor-grab touch-none rounded-full outline-none ring-offset-2 focus-visible:ring-2 focus-visible:ring-amber-700 active:cursor-grabbing"
            @pointerdown.prevent="dragging = true; $refs.dial.setPointerCapture($event.pointerId); apply($event)"
            @pointermove="if (dragging) apply($event)"
            @pointerup="dragging = false"
            @pointercancel="dragging = false"
            @keydown.left.prevent="nudge(-5)"
            @keydown.right.prevent="nudge(5)"
            @keydown.up.prevent="nudge(-15)"
            @keydown.down.prevent="nudge(15)"
        >
            <svg viewBox="0 0 200 200" class="h-full w-full drop-shadow-sm" aria-hidden="true">
                <circle cx="100" cy="100" r="96" fill="#fafaf9" stroke="#d6d3d1" stroke-width="2" />
                <circle cx="100" cy="100" r="78" fill="none" stroke="#e7e5e4" stroke-width="1" />

                @for ($tick = 0; $tick < 72; $tick++)
                    @php
                        $angle = $tick * 5;
                        $major = $angle % 45 === 0;
                        $medium = $angle % 15 === 0;
                        $inner = $major ? 62 : ($medium ? 68 : 72);
                        $width = $major ? 2.2 : ($medium ? 1.4 : 0.8);
                    @endphp
                    <line
                        x1="100" y1="{{ $inner }}" x2="100" y2="84"
                        stroke="{{ $major ? '#44403c' : '#a8a29e' }}"
                        stroke-width="{{ $width }}"
                        transform="rotate({{ $angle }} 100 100)"
                    />
                @endfor

                <text x="100" y="28" text-anchor="middle" font-size="14" font-weight="700" fill="#b91c1c">N</text>
                <text x="176" y="105" text-anchor="middle" font-size="13" font-weight="600" fill="#44403c">O</text>
                <text x="100" y="184" text-anchor="middle" font-size="13" font-weight="600" fill="#44403c">S</text>
                <text x="24" y="105" text-anchor="middle" font-size="13" font-weight="600" fill="#44403c">W</text>

                <g :transform="`rotate(${deg} 100 100)`" :opacity="isSet ? 1 : 0.35">
                    <polygon points="100,22 108,100 100,88 92,100" fill="#b45309" />
                    <polygon points="100,178 92,100 100,112 108,100" fill="#57534e" />
                    <circle cx="100" cy="100" r="7" fill="#1c1917" stroke="#fafaf9" stroke-width="2" />
                </g>
            </svg>
        </div>

        <p class="text-sm font-medium tabular-nums text-stone-800" x-text="caption"></p>
        @error("meta.$index.bearing")
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
