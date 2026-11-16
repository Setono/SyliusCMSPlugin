/**
 * The fingerprint a translated value records of the value it was translated from: the lowercase hex SHA-256 of that
 * value in canonical JSON. The server computes the same one (src/Translation/Fingerprint.php), and both are tested
 * against tests/Fixture/fingerprints.json. Pure functions, with no React and no I/O. It's synchronous, unlike
 * crypto.subtle, so the builder can flag outdated values as it renders. See "Translation state" and "Canonical JSON" in
 * docs/spec/content-model.md
 */
import type { JsonValue } from './types';

export function fingerprint(value: JsonValue): string {
    return sha256(canonicalJson(value));
}

/**
 * A value in canonical JSON, as the server's CanonicalJson writes a value it decoded: every object's keys sorted by
 * byte value, slashes and characters past ASCII unescaped, except the line and paragraph separators, which PHP escapes.
 * A number is written as JavaScript writes it, so a whole number stored as 1.0 would differ, but no translatable field
 * type stores one.
 */
export function canonicalJson(value: JsonValue): string {
    if (Array.isArray(value)) {
        return `[${value.map(canonicalJson).join(',')}]`;
    }

    if (typeof value === 'string') {
        return string(value);
    }

    if (value === null || typeof value !== 'object') {
        return JSON.stringify(value);
    }

    const keys = Object.keys(value).sort(byCodePoint);

    return `{${keys.map((key) => `${string(key)}:${canonicalJson(value[key] ?? null)}`).join(',')}}`;
}

function string(text: string): string {
    return JSON.stringify(text).replaceAll('\u2028', '\\u2028').replaceAll('\u2029', '\\u2029');
}

/** Code point order, which is the order of the strings' bytes in UTF-8. */
function byCodePoint(a: string, b: string): number {
    const left = Array.from(a, (character) => character.codePointAt(0) ?? 0);
    const right = Array.from(b, (character) => character.codePointAt(0) ?? 0);
    for (let i = 0; i < Math.min(left.length, right.length); i++) {
        const difference = (left[i] ?? 0) - (right[i] ?? 0);
        if (difference !== 0) {
            return difference;
        }
    }

    return left.length - right.length;
}

const ROUND_CONSTANTS = [
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3,
    0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174, 0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967, 0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13,
    0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208,
    0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
];

const rotate = (word: number, by: number): number => (word >>> by) | (word << (32 - by));

/** The lowercase hex SHA-256 of a text's UTF-8 bytes (FIPS 180-4). */
export function sha256(text: string): string {
    const bytes = new TextEncoder().encode(text);
    // The bytes, a 1 bit, zeros, and the length in bits as 64 bits, to a multiple of 64 bytes
    const message = new Uint8Array(Math.ceil((bytes.length + 9) / 64) * 64);
    message.set(bytes);
    message[bytes.length] = 0x80;
    const view = new DataView(message.buffer);
    view.setUint32(message.length - 8, Math.floor(bytes.length / 0x20000000));
    view.setUint32(message.length - 4, (bytes.length * 8) >>> 0);

    const hash = [0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19];
    const schedule = new Array<number>(64).fill(0);

    for (let offset = 0; offset < message.length; offset += 64) {
        for (let i = 0; i < 64; i++) {
            if (i < 16) {
                schedule[i] = view.getUint32(offset + i * 4);
                continue;
            }

            const w15 = schedule[i - 15] ?? 0;
            const w2 = schedule[i - 2] ?? 0;
            const s0 = rotate(w15, 7) ^ rotate(w15, 18) ^ (w15 >>> 3);
            const s1 = rotate(w2, 17) ^ rotate(w2, 19) ^ (w2 >>> 10);
            schedule[i] = ((schedule[i - 16] ?? 0) + s0 + (schedule[i - 7] ?? 0) + s1) | 0;
        }

        let [a, b, c, d, e, f, g, h] = hash as [number, number, number, number, number, number, number, number];
        for (let i = 0; i < 64; i++) {
            const t1 = (h + (rotate(e, 6) ^ rotate(e, 11) ^ rotate(e, 25)) + ((e & f) ^ (~e & g)) + (ROUND_CONSTANTS[i] ?? 0) + (schedule[i] ?? 0)) | 0;
            const t2 = ((rotate(a, 2) ^ rotate(a, 13) ^ rotate(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) | 0;
            h = g;
            g = f;
            f = e;
            e = (d + t1) | 0;
            d = c;
            c = b;
            b = a;
            a = (t1 + t2) | 0;
        }

        [a, b, c, d, e, f, g, h].forEach((word, i) => {
            hash[i] = ((hash[i] ?? 0) + word) | 0;
        });
    }

    return hash.map((word) => (word >>> 0).toString(16).padStart(8, '0')).join('');
}
