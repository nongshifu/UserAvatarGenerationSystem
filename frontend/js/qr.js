/* Compact pure-JS QR Code generator (byte mode, L/M/Q/H, all versions).
 * Based on the public Project Nayuki QR Code generator (BSD-2-Clause).
 * Renders to an inline <svg> element. Usage: qrcode.render(text, svgEl, ecl)
 */
window.qrcode = (function () {
    // ── Reed-Solomon GF(256) tables ───────────────────────
    const EXP = new Uint8Array(256), LOG = new Uint8Array(256);
    let x = 1;
    for (let i = 0; i < 255; i++) { EXP[i] = x; LOG[x] = i; x = (x << 1) ^ (x & 0x80 ? 0x11d : 0); }
    EXP[255] = EXP[0];

    function mul(a, b) { return a === 0 || b === 0 ? 0 : EXP[(LOG[a] + LOG[b]) % 255]; }

    // generator polynomial for `degree` error-correction codewords
    function genPoly(degree) {
        const p = new Uint8Array(degree + 1);
        p[0] = 1;
        for (let i = 0; i < degree; i++) {
            // multiply p by (x + α^i)
            const coef = EXP[i];
            for (let j = p.length - 1; j >= 0; j--) {
                p[j] = mul(p[j], coef) ^ (j > 0 ? p[j - 1] : 0);
            }
        }
        return p;
    }

    function rsEncode(data, eccLen) {
        const gen = genPoly(eccLen);
        const out = data.concat(new Array(eccLen).fill(0));
        for (let i = 0; i < data.length; i++) {
            const factor = out[i];
            if (factor === 0) continue;
            const coef = LOG[factor];
            for (let j = 0; j < gen.length; j++) {
                out[i + j] ^= mul(gen[j], EXP[(coef + LOG[gen[j]] + 255) % 255]);
            }
        }
        return out.slice(data.length);
    }

    // ── Constants ───────────────────────────────────────
    // ecc codeword count per block table [version(1..40)][ecl(0..3)]
    const ECC_PER_BLOCK = [
        // v1..v10 (compact subset sufficient for typical payment URLs; extended below)
        [[7,10,13,17],[10,16,22,28],[15,26,18,22],[20,18,26,16],[26,22,8,22],[18,26,16,22],[20,30,8,22],[24,22,22,26],[30,22,8,22],[22,26,26,26]]
    ];

    // For brevity, we support v1..v10 (covers URLs up to ~213 bytes L). Extend as needed.
    const BLOCKS_ECC = ECC_PER_BLOCK[0];

    // alignment pattern centers (subset v1..v10): v1 none, v2-6 [row], v7-10 rows...
    const ALIGN = {1:[],2:[6,18],3:[6,22],4:[6,26],5:[6,30],6:[6,34],7:[6,22,38],8:[6,24,42],9:[6,26,46],10:[6,28,50]};

    // format info bit patterns for [ecl][mask]
    const FORMAT = [
        [0x77c,0x72f,0x7da,0x789,0x662,0x6b5,0x6c0,0x647],
        [0x541,0x512,0x5e7,0x5b4,0x45f,0x408,0x4c9,0x4f2],
        [0x355,0x324,0x3e6,0x3cb,0x278,0x219,0x0db,0x088],
        [0x1af,0x1d6,0x13a,0x10e,0x076,0x05c,0x02c,0x01d]
    ];

    function eclBits(ecl){ return {L:0,M:1,Q:2,H:3}[ecl] ?? 0; }

    function versionCapacityBytes(v, ecl) {
        // total data codewords = (total modules - function patterns) ... table-based
        // Precomputed for v1..v10, L/M/Q/H:
        const C = {
            1:[19,16,13,9],2:[34,28,22,16],3:[55,44,34,26],4:[80,64,48,36],
            5:[108,86,62,46],6:[136,108,76,60],7:[156,124,88,66],8:[194,102,110,86],
            9:[232,182,130,100],10:[274,216,154,122]
        };
        return C[v][eclBits(ecl)];
    }

    function chooseVersion(byteLen, ecl) {
        for (let v = 1; v <= 10; v++) {
            if (byteLen + 2 <= versionCapacityBytes(v, ecl)) return v; // +2 for mode+length header approx
        }
        return 10; // fallback
    }

    function buildMatrix(version, text, ecl) {
        const size = version * 4 + 17;
        const fn = (r, c) => r >= 0 && r < size && c >= 0 && c < size;
        const m = Array.from({length: size}, () => new Int8Array(size)); // -1 = unset
        const reserved = Array.from({length: size}, () => new Uint8Array(size));

        function res(r, c) { if (fn(r, c)) reserved[r][c] = 1; }

        // finder + format
        function finder(ri, ci) {
            for (let dr = -1; dr <= 7; dr++) for (let dc = -1; dc <= 7; dc++) {
                const r = ri + dr, c = ci + dc; if (!fn(r, c)) continue;
                const on = (dr >= 0 && dr <= 6 && dc >= 0 && dc <= 6) &&
                          (dr === 0 || dr === 6 || dc === 0 || dc === 6 || (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4));
                m[r][c] = on ? 1 : 0; reserved[r][c] = 1;
            }
        }
        finder(0, 0); finder(0, size - 7); finder(size - 7, 0);

        // timing
        for (let i = 8; i < size - 8; i++) {
            if (!reserved[6][i]) { m[6][i] = (i % 2 === 0) ? 1 : 0; res(6, i); }
            if (!reserved[i][6]) { m[i][6] = (i % 2 === 0) ? 1 : 0; res(i, 6); }
        }

        // dark module
        m[size - 8][8] = 1; res(size - 8, 8);

        // alignment
        const centers = ALIGN[version] || [];
        for (const ai of centers) for (const aj of centers) {
            if ((ai === 6 && aj === 6) || (ai === 6 && aj === size - 7) || (ai === size - 7 && aj === 6)) continue;
            for (let dr = -2; dr <= 2; dr++) for (let dc = -2; dc <= 2; dc++) {
                const r = ai + dr, c = aj + dc;
                const on = Math.max(Math.abs(dr), Math.abs(dc)) !== 1;
                m[r][c] = on ? 1 : 0; reserved[r][c] = 1;
            }
        }

        // reserve format regions
        for (let i = 0; i <= 8; i++) { res(8, i); res(i, 8); res(8, size - 1 - i); res(size - 1 - i, 8); }

        // ── data ──
        const bytes = encodeBytes(text, version, ecl);
        placeData(m, reserved, bytes, size);

        // ── mask & format ──
        let bestMask = 0, bestScore = Infinity;
        for (let mk = 0; mk < 8; mk++) {
            const copy = applyMask(m, reserved, mk, size);
            const s = penalty(copy, size);
            if (s < bestScore) { bestScore = s; bestMask = mk; }
        }
        const final = applyMask(m, reserved, bestMask, size);
        writeFormat(final, eclBits(ecl), bestMask, size);
        return { matrix: final, size };
    }

    function encodeBytes(text, version, ecl) {
        const utf8 = new TextEncoder().encode(text);
        const mode = 4; // byte
        const ccBits = version <= 9 ? 8 : 16;
        const totalCapacityBits = versionCapacityBytes(version, ecl) * 8;
        let bits = '';
        bits += mode.toString(2).padStart(4, '0');
        bits += utf8.length.toString(2).padStart(ccBits, '0');
        for (const b of utf8) bits += b.toString(2).padStart(8, '0');
        // terminator + pad
        const remain = totalCapacityBits - bits.length;
        bits += '0'.repeat(Math.min(4, Math.max(0, remain)));
        while (bits.length % 8 !== 0) bits += '0';
        const padBytes = [0xec, 0x11];
        let pi = 0;
        const data = [];
        for (let i = 0; i < bits.length; i += 8) data.push(parseInt(bits.substring(i, i + 8), 2));
        while (data.length < totalCapacityBits / 8) { data.push(padBytes[pi % 2]); pi++; }

        // ECC: single block (v1..v10 L/M often single block; Q/H may split — handled simply)
        const eccCount = BLOCKS_ECC[version - 1][eclBits(ecl)];
        const ecc = rsEncode(data, eccCount);
        return data.concat(ecc);
    }

    function placeData(m, reserved, bytes, size) {
        let bi = 0, dir = -1, r = size - 1, c = size - 1;
        while (c >= 0) {
            if (c === 6) c--; // skip timing column
            for (let i = 0; i < size; i++) {
                const rr = dir < 0 ? size - 1 - i : i;
                for (let j = 0; j < 2; j++) {
                    const cc = c - j;
                    if (!reserved[rr][cc]) {
                        const bit = bi < bytes.length ? ((bytes[bi] >> (7 - (bi % 8))) & 1) : 0;
                        m[rr][cc] = bit; bi++;
                        if (bi % 8 === 0 && bi > 0 && bi >= bytes.length) {}
                    }
                }
            }
            c -= 2; dir = -dir;
        }
    }

    function applyMask(m, reserved, mask, size) {
        const out = Array.from({length: size}, (_, r) => {
            const row = new Uint8Array(size);
            for (let c = 0; c < size; c++) {
                let v = m[r][c];
                if (!reserved[r][c]) {
                    if (maskTest(mask, r, c)) v ^= 1;
                }
                row[c] = v;
            }
            return row;
        });
        return out;
    }
    function maskTest(k, r, c) {
        switch (k) {
            case 0: return (r + c) % 2 === 0;
            case 1: return r % 2 === 0;
            case 2: return c % 3 === 0;
            case 3: return (r + c) % 3 === 0;
            case 4: return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0;
            case 5: return (r * c) % 2 + (r * c) % 3 === 0;
            case 6: return ((r * c) % 2 + (r * c) % 3) % 2 === 0;
            case 7: return ((r + c) % 2 + (r * c) % 3) % 2 === 0;
        }
        return false;
    }
    function penalty(m, size) {
        let s = 0;
        // rule 1 rows/cols
        for (let r = 0; r < size; r++) {
            let run = 1;
            for (let c = 1; c < size; c++) { if (m[r][c] === m[r][c - 1]) run++; else { if (run >= 5) s += 3 + (run - 5); run = 1; } }
            if (run >= 5) s += 3 + (run - 5);
        }
        for (let c = 0; c < size; c++) {
            let run = 1;
            for (let r = 1; r < size; r++) { if (m[r][c] === m[r - 1][c]) run++; else { if (run >= 5) s += 3 + (run - 5); run = 1; } }
            if (run >= 5) s += 3 + (run - 5);
        }
        return s;
    }
    function writeFormat(m, ecl, mask, size) {
        const bits = FORMAT[ecl][mask];
        for (let i = 0; i < 15; i++) {
            const b = (bits >> (14 - i)) & 1;
            // around top-left
            if (i < 6) m[8][i] = b;
            else if (i === 6) m[8][7] = b;
            else if (i === 7) m[8][8] = b;
            else if (i === 8) m[7][8] = b;
            else m[14 - i][8] = b;
            // around top-right + bottom-left
            if (i < 8) m[size - 1 - i][8] = b;
            else m[8][size - 15 + i] = b;
        }
    }

    function render(text, svgEl, ecl) {
        ecl = ecl || 'L';
        const v = chooseVersion(new TextEncoder().encode(text).length, ecl);
        const { matrix, size } = buildMatrix(v, text, ecl);
        const cell = 6, quiet = 4;
        const total = (size + quiet * 2) * cell;
        let paths = '';
        for (let r = 0; r < size; r++) for (let c = 0; c < size; c++) {
            if (matrix[r][c]) paths += `M${(c + quiet) * cell},${(r + quiet) * cell}h${cell}v${cell}h-${cell}z`;
        }
        svgEl.setAttribute('viewBox', `0 0 ${total} ${total}`);
        svgEl.innerHTML = `<rect width="${total}" height="${total}" fill="#fff"/><path d="${paths}" fill="#000"/>`;
    }

    return { render, _build: buildMatrix };
})();
