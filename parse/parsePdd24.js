const http = require('http');
const fs = require('fs');
const topics = require('./topics.json');

async function getBody(url) {
    return new Promise(function(resolve, reject) {
        http.get(url, resp => {
            let data = '';
            resp.on('data', chunk => data += chunk);
            resp.on('end', () => resolve(data));
        }).on("error", err => reject(err));
    });
}

function translateReplaceString(replaceStr){
    let pos = 0;
    while (0 <= (pos = replaceStr.indexOf('\\', pos))) {
        replaceStr.charCodeAt(pos + 1) == 36
            ? (replaceStr = replaceStr.substr(0, pos) + '$' + replaceStr.substr(++pos))
            : (replaceStr = replaceStr.substr(0, pos) + ('' + replaceStr.substr(++pos)));
    }
    return replaceStr;
}

function replaceAll(this$static, regex, replace){
    replace = translateReplaceString(replace);
    return this$static.replace(new RegExp(regex, 'g'), replace);
}

function toChars(codePoint, dst, dstIndex){
    let $intern_168 = 65536;
    let $intern_109 = 65535;

    if (codePoint >= $intern_168) {
        dst[dstIndex++] = 55296 + (codePoint - $intern_168 >> 10 & 1023) & $intern_109;
        dst[dstIndex] = 56320 + (codePoint - $intern_168 & 1023) & $intern_109;
        return 2;
    }
    else {
        dst[dstIndex] = codePoint & $intern_109;
        return 1;
    }
}

function decodeString(bytes, ofs, len) {
    var b, ch_0, charCount, chars, count, i, i0, number, outIdx;
    charCount = 0;
    for (i0 = 0; i0 < len;) {
        ++charCount;
        ch_0 = bytes[ofs + i0];
        if ((ch_0 & 192) == 128) {
            throw new Error('Invalid UTF8 sequence');
        }
        else if ((ch_0 & 128) == 0) {
            ++i0;
        }
        else if ((ch_0 & 224) == 192) {
            i0 += 2;
        }
        else if ((ch_0 & 240) == 224) {
            i0 += 3;
        }
        else if ((ch_0 & 248) == 240) {
            i0 += 4;
        }
        else {
            throw new Error('Invalid UTF8 sequence');
        }
        if (i0 > len) {
            throw new Error('Invalid UTF8 sequence');
        }
    }

    chars = initializeArrayElementsWithDefaults(15, charCount);
    outIdx = 0;
    count = 0;
    for (i = 0; i < len;) {
        ch_0 = bytes[ofs + i++];
        if ((ch_0 & 128) == 0) {
            count = 1;
            ch_0 &= 127;
        }
        else if ((ch_0 & 224) == 192) {
            count = 2;
            ch_0 &= 31;
        }
        else if ((ch_0 & 240) == 224) {
            count = 3;
            ch_0 &= 15;
        }
        else if ((ch_0 & 248) == 240) {
            count = 4;
            ch_0 &= 7;
        }
        else if ((ch_0 & 252) == 248) {
            count = 5;
            ch_0 &= 3;
        }
        while (--count > 0) {
            b = bytes[ofs + i++];
            if ((b & 192) != 128) {
                throw new Error('Invalid UTF8 sequence at ' + (ofs + i - 1) + ', byte=' + (number = b >>> 0 , number.toString(16)));
            }
            ch_0 = ch_0 << 6 | b & 63;
        }
        outIdx += toChars(ch_0, chars, outIdx);
    }
    return chars;
}

function isLowSurrogate(ch_0){
    return ch_0 >= 56320 && ch_0 <= 57343;
}

function codePointAt(cs, index_0, limit){
    let $intern_168 = 65536;
    let hiSurrogate, loSurrogate;
    hiSurrogate = cs.charCodeAt(index_0++);
    loSurrogate = cs.charCodeAt(index_0);
    if (hiSurrogate >= 55296 && hiSurrogate <= 56319 && index_0 < limit && isLowSurrogate(loSurrogate)) {
        return $intern_168 + ((hiSurrogate & 1023) << 10) + (loSurrogate & 1023);
    }
    return hiSurrogate;
}

function initializeArrayElementsWithDefaults(elementTypeCategory, length_0){
    var array = new Array(length_0);
    var initValue;
    switch (elementTypeCategory) {
        case 14:
        case 15:
            initValue = 0;
            break;
        case 16:
            initValue = false;
            break;
        default:return array;
    }
    for (var i = 0; i < length_0; ++i) {
        array[i] = initValue;
    }
    return array;
}

function encodeUtf8(bytes, ofs, codePoint){
    let $intern_168 = 65536;
    let $intern_170 = 2097152;
    let $intern_173 = 67108864;

    if (codePoint < 128) {
        bytes[ofs] = (codePoint & 127) << 24 >> 24;
        return 1;
    }
    else if (codePoint < 2048) {
        bytes[ofs++] = (codePoint >> 6 & 31 | 192) << 24 >> 24;
        bytes[ofs] = (codePoint & 63 | 128) << 24 >> 24;
        return 2;
    }
    else if (codePoint < $intern_168) {
        bytes[ofs++] = (codePoint >> 12 & 15 | 224) << 24 >> 24;
        bytes[ofs++] = (codePoint >> 6 & 63 | 128) << 24 >> 24;
        bytes[ofs] = (codePoint & 63 | 128) << 24 >> 24;
        return 3;
    }
    else if (codePoint < $intern_170) {
        bytes[ofs++] = (codePoint >> 18 & 7 | 240) << 24 >> 24;
        bytes[ofs++] = (codePoint >> 12 & 63 | 128) << 24 >> 24;
        bytes[ofs++] = (codePoint >> 6 & 63 | 128) << 24 >> 24;
        bytes[ofs] = (codePoint & 63 | 128) << 24 >> 24;
        return 4;
    }
    else if (codePoint < $intern_173) {
        bytes[ofs++] = (codePoint >> 24 & 3 | 248) << 24 >> 24;
        bytes[ofs++] = (codePoint >> 18 & 63 | 128) << 24 >> 24;
        bytes[ofs++] = (codePoint >> 12 & 63 | 128) << 24 >> 24;
        bytes[ofs++] = (codePoint >> 6 & 63 | 128) << 24 >> 24;
        bytes[ofs] = (codePoint & 63 | 128) << 24 >> 24;
        return 5;
    }
    throw new Error('Character out of range: ' + codePoint);
}

function getBytes(str) {
    let $intern_168 = 65536;
    let $intern_170 = 2097152;
    let $intern_173 = 67108864;

    var byteCount, bytes, ch_0, i, i0, n, out;
    n = str.length;
    byteCount = 0;
    for (i0 = 0; i0 < n;) {
        ch_0 = codePointAt(str, i0, str.length);
        i0 += ch_0 >= $intern_168 ? 2 : 1;
        ch_0 < 128?++byteCount:ch_0 < 2048?(byteCount += 2):ch_0 < $intern_168?(byteCount += 3):ch_0 < $intern_170?(byteCount += 4):ch_0 < $intern_173 && (byteCount += 5);
    }
    bytes = initializeArrayElementsWithDefaults(15, byteCount);
    out = 0;
    for (i = 0; i < n;) {
        ch_0 = codePointAt(str, i, str.length);
        i += ch_0 >= $intern_168?2:1;
        out += encodeUtf8(bytes, out, ch_0);
    }
    return bytes;
}

function bytesToString(x_0, count) {
    var batchEnd, batchStart, s;
    s = '';
    for (batchStart = 0; batchStart < count;) {
        batchEnd = Math.min(batchStart + 10000, count);
        s += String.fromCharCode.apply(null, x_0.slice(batchStart, batchEnd));
        batchStart = batchEnd;
    }
    return s;
}

async function getDecodedBody(url) {
    let text_0 = await getBody(url);
    let bDec = getBytes(text_0);
    for (let i0 = 0; i0 < bDec.length; i0++) {
        --bDec[i0];
    }
    let decoded = decodeString(bDec, 0, bDec.length);
    text_0 = bytesToString(decoded, decoded.length);
    text_0 = replaceAll(text_0, '\uEFBC\uEFBC', '\u043F');
    return text_0;
}

(async () => {
    for (let bNum = 1; bNum <= 40; bNum++) {
        console.log(bNum);
        let text_0 = await getDecodedBody(`http://www.pdd24.com/stat2021/ab/bilet/b${bNum}.json`);
        fs.writeFileSync(`parse/tickets/b${bNum}.json`, text_0);
    }

    for (let topic of topics) {
        let tNum = topic.id;
        console.log(tNum);
        let text_0 = await getDecodedBody(`http://www.pdd24.com/stat2021/ab/theme/${tNum}.json`);
        fs.writeFileSync(`parse/topics/${tNum}.json`, text_0);
    }
})();
