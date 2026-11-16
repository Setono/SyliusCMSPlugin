import { createHash } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import shared from '../../../tests/Fixture/fingerprints.json';
import { canonicalJson, fingerprint, sha256 } from '../src/fingerprint';
import type { JsonValue } from '../src/types';

describe('a fingerprint', () => {
    it('should be the lowercase hex SHA-256 of the value in canonical JSON', () => {
        expect(fingerprint('Winter sale')).toBe(createHash('sha256').update('"Winter sale"').digest('hex'));
        expect(fingerprint({ type: 'doc', content: [] })).toBe(createHash('sha256').update('{"content":[],"type":"doc"}').digest('hex'));
    });

    it("should not change with the order of an object's keys", () => {
        const document: JsonValue = { type: 'doc', content: [{ type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: 'Hi' }] }] };
        const reordered: JsonValue = { content: [{ content: [{ text: 'Hi', type: 'text' }], attrs: { level: 2 }, type: 'heading' }], type: 'doc' };

        expect(fingerprint(reordered)).toBe(fingerprint(document));
    });

    it('should change with the value', () => {
        expect(fingerprint('Winter sale!')).not.toBe(fingerprint('Winter sale'));
        expect(fingerprint(['a', 'b'])).not.toBe(fingerprint(['b', 'a']));
    });

    it.each(shared.map((sharedCase) => [sharedCase.case, sharedCase.value as JsonValue, sharedCase.fingerprint] as const))(
        'should give the server the same fingerprint: %s',
        (_, value, expected) => {
            expect(fingerprint(value)).toBe(expected);
        },
    );
});

describe('canonical JSON', () => {
    it('should sort keys by their bytes in UTF-8, which is code point order, and keep lists in order', () => {
        expect(canonicalJson({ é: 1, z: 2, Z: 3, a: 4, '10': 5, '2': 6, '😀': 7, ﬀ: 8, ab: 9, abc: 10 })).toBe(
            '{"10":5,"2":6,"Z":3,"a":4,"ab":9,"abc":10,"z":2,"é":1,"ﬀ":8,"😀":7}',
        );
        expect(canonicalJson(['b', 'a', null, true, 1.5])).toBe('["b","a",null,true,1.5]');
    });

    it('should leave slashes and characters past ASCII as they are, and escape the line and paragraph separators', () => {
        expect(canonicalJson('a/b “ø” 😀     "\\\n')).toBe('"a/b “ø” 😀 \\u2028 \\u2029 \\"\\\\\\n"');
    });

    it('should write empty objects and lists as they are', () => {
        expect(canonicalJson({ attrs: {}, content: [] })).toBe('{"attrs":{},"content":[]}');
    });
});

describe('SHA-256', () => {
    it('should hash the test vectors of FIPS 180-4', () => {
        expect(sha256('')).toBe('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
        expect(sha256('abc')).toBe('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad');
        expect(sha256('abcdbcdecdefdefgefghfghighijhijkijkljklmklmnlmnomnopnopq')).toBe('248d6a61d20638b8e5c026930c3e6039a33ce45964ff2167f6ecedd419db06c1');
    });

    it('should hash texts of every length around the block size, and characters past ASCII as UTF-8', () => {
        for (let length = 0; length <= 200; length++) {
            const text = 'aø😀'.repeat(length).slice(0, length);
            expect(sha256(text)).toBe(createHash('sha256').update(text, 'utf8').digest('hex'));
        }
    });

    it('should hash a long text', () => {
        const text = 'Vinterudsalg '.repeat(10_000);

        expect(sha256(text)).toBe(createHash('sha256').update(text, 'utf8').digest('hex'));
    });
});
