/**
 * Scan wajah di browser memakai face-api.js (TensorFlow.js).
 *  - Model & library dimuat dari CDN. Untuk dipakai OFFLINE: unduh paket @vladmandic/face-api,
 *    taruh dist/face-api.js dan folder model/ di assets/vendor/face-api/ lalu ubah FACE_BASE_LOCAL = true.
 *  - Liveness: pengguna harus memutar kepala (kiri/kanan) sesuai arahan — mencegah foto diam. Tanpa kedip.
 *  - Yang dikirim ke server hanya "descriptor" (128 angka) + foto bukti. Pencocokan wajah
 *    dilakukan di SERVER, bukan di browser, sehingga tidak bisa di-bypass lewat DevTools.
 */
const FACE_BASE_LOCAL = false;
const FACE_VER = '1.7.15';
const FACE_LIB_URL  = FACE_BASE_LOCAL ? '../assets/vendor/face-api/face-api.js'
                                      : `https://cdn.jsdelivr.net/npm/@vladmandic/face-api@${FACE_VER}/dist/face-api.js`;
const FACE_MODEL_URL = FACE_BASE_LOCAL ? '../assets/vendor/face-api/model/'
                                       : `https://cdn.jsdelivr.net/npm/@vladmandic/face-api@${FACE_VER}/model/`;

class FaceScanner {
    constructor({ video, wrap, hint, steps }) {
        this.video = video; this.wrap = wrap; this.hint = hint; this.stepsEl = steps;
        this.stream = null; this.cancelled = false; this.ready = false;
        this.opts = null; this.baseYaw = null;
    }

    setHint(t) { if (this.hint) this.hint.textContent = t; }
    setState(cls) { this.wrap.classList.remove('found', 'fail'); if (cls) this.wrap.classList.add(cls); }
    setSteps(list, nowIdx) {
        if (!this.stepsEl) return;
        this.stepsEl.innerHTML = list.map((t, i) =>
            `<span class="cam-step ${i < nowIdx ? 'done' : i === nowIdx ? 'now' : ''}">${i < nowIdx ? '✓ ' : ''}${t}</span>`).join('');
    }
    sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    static loadScript(src) {
        return new Promise((res, rej) => {
            if (window.faceapi) return res();
            const s = document.createElement('script');
            s.src = src; s.onload = res; s.onerror = () => rej(new Error('Gagal memuat library wajah. Periksa koneksi internet.'));
            document.head.appendChild(s);
        });
    }

    async init() {
        if (this.ready) return;
        this.setHint('Memuat model pengenal wajah… (pertama kali agak lama)');
        await FaceScanner.loadScript(FACE_LIB_URL);
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODEL_URL),
        ]);
        this.opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 });
        this.ready = true;
    }

    async startCamera() {
        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Kamera hanya bisa dipakai lewat HTTPS (atau localhost). Buka situs memakai alamat https://');
        }
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false
            });
        } catch (e) {
            throw new Error(e && e.name === 'NotAllowedError'
                ? 'Izin kamera ditolak. Izinkan akses kamera pada browser lalu coba lagi.'
                : 'Kamera tidak ditemukan / tidak bisa dibuka.');
        }
        this.video.srcObject = this.stream;
        await this.video.play();
        this.cancelled = false; this.baseYaw = null;
    }

    stop() {
        this.baseYaw = null;
        this.cancelled = true;
        if (this.stream) this.stream.getTracks().forEach(t => t.stop());
        this.stream = null; this.video.srcObject = null;
        this.setState(null);
    }

    _ear(eye) {
        const d = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
        return (d(eye[1], eye[5]) + d(eye[2], eye[4])) / (2 * d(eye[0], eye[3]));
    }

    /** Wajah harus cukup besar & berada di tengah oval */
    _positionOk(box) {
        const vw = this.video.videoWidth, vh = this.video.videoHeight;
        const cx = (box.x + box.width / 2) / vw, cy = (box.y + box.height / 2) / vh;
        if (box.width / vw < 0.26) return 'Dekatkan wajah ke kamera';
        if (box.width / vw > 0.75) return 'Jauhkan sedikit wajah dari kamera';
        if (Math.abs(cx - 0.5) > 0.14 || cy < 0.28 || cy > 0.62) return 'Posisikan wajah di tengah oval';
        return null;
    }

    async _detect() {
        return faceapi.detectSingleFace(this.video, this.opts).withFaceLandmarks();
    }

    /**
     * Arah kepala dari landmark 68 titik: posisi ujung hidung (titik 30) di antara tepi rahang kiri-kanan (0 & 16).
     * Data kamera tidak dicerminkan: rasio kecil = pengguna menoleh ke KANAN-nya, rasio besar = KIRI.
     * Pose dinilai RELATIF terhadap rasio wajah depan milik pengguna itu sendiri (this.baseYaw),
     * jadi tidak bergantung bentuk wajah / posisi kamera.
     */
    _yawRatio(lm) {
        const p = lm.positions;
        return (p[30].x - p[0].x) / (p[16].x - p[0].x);
    }
    _poseOf(ratio) {
        const base = this.baseYaw == null ? 0.5 : this.baseYaw;
        const d = ratio - base;
        const front = this.baseYaw == null ? 0.09 : 0.05;
        if (Math.abs(d) <= front) return 'depan';
        if (d >= 0.075)  return 'kiri';
        if (d <= -0.075) return 'kanan';
        return null; // setengah menoleh
    }

    static HINTS = {
        depan: 'Lihat lurus ke kamera',
        kiri:  'Putar kepala perlahan ke KIRI',
        kanan: 'Putar kepala perlahan ke KANAN',
    };

    /**
     * Tahan pose ('depan' | 'kiri' | 'kanan') dan, bila need > 0, ambil `need` descriptor wajah pada frame
     * yang SAMA saat pose terpenuhi (tanpa pass kedua). Pose depan pertama juga mengkalibrasi this.baseYaw.
     * Mengembalikan array descriptor (bisa kosong bila need = 0). Throw bila timeout / dibatalkan.
     */
    async holdPose(target, need = 0, timeoutMs = 40000) {
        const t0 = Date.now(); let ok = 0; const ratios = []; const descs = [];
        const calibrate = target === 'depan' && this.baseYaw == null;
        while (!this.cancelled && Date.now() - t0 < timeoutMs) {
            const r = await faceapi.detectSingleFace(this.video, this.opts).withFaceLandmarks();
            if (!r) { ok = 0; this.setState('fail'); this.setHint('Wajah tidak terdeteksi. Hadapkan wajah ke kamera.'); await this.sleep(80); continue; }
            if (target === 'depan') {
                const pos = this._positionOk(r.detection.box);
                if (pos) { ok = 0; ratios.length = 0; this.setState('fail'); this.setHint(pos); await this.sleep(60); continue; }
            }
            const ratio = this._yawRatio(r.landmarks);
            if (this._poseOf(ratio) !== target) {
                ok = 0; ratios.length = 0; this.setState(null); this.setHint(FaceScanner.HINTS[target]);
                await new Promise(res => requestAnimationFrame(res)); continue;
            }
            this.setState('found'); ok++; ratios.push(ratio);
            if (ok < 3) { this.setHint('Tahan sebentar…'); await new Promise(res => requestAnimationFrame(res)); continue; }
            if (calibrate && ratios.length >= 3) { // kalibrasi arah depan dari median
                const s = [...ratios].sort((a, b) => a - b); this.baseYaw = s[Math.floor(s.length / 2)];
            }
            if (need <= 0) return [];
            // Pose sudah stabil: ambil descriptor pada frame ini
            const d = await faceapi.detectSingleFace(this.video, this.opts).withFaceLandmarks().withFaceDescriptor();
            if (d) {
                descs.push(Array.from(d.descriptor));
                this.setHint('Tahan… ' + descs.length + '/' + need);
                if (descs.length >= need) return descs;
            }
            await this.sleep(60);
        }
        if (this.cancelled) throw new Error('Dibatalkan.');
        throw new Error('Posisi kepala "' + target + '" tidak terdeteksi. Pastikan wajah terang, lalu ulangi.');
    }

    static dist(a, b) { let s = 0; for (let i = 0; i < a.length; i++) { const d = a[i] - b[i]; s += d * d; } return Math.sqrt(s); }
    static mean(list) {
        const m = new Array(list[0].length).fill(0);
        list.forEach(v => v.forEach((x, i) => m[i] += x));
        return m.map(x => +(x / list.length).toFixed(6));
    }

    /** Foto bukti (tidak dicerminkan), JPEG */
    snapshot(maxW = 480) {
        const vw = this.video.videoWidth, vh = this.video.videoHeight;
        const scale = Math.min(1, maxW / vw);
        const c = document.createElement('canvas');
        c.width = Math.round(vw * scale); c.height = Math.round(vh * scale);
        c.getContext('2d').drawImage(this.video, 0, 0, c.width, c.height);
        return c.toDataURL('image/jpeg', 0.82);
    }
}
window.FaceScanner = FaceScanner;
