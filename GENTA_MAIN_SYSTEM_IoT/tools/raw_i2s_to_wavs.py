#!/usr/bin/env python3
"""
Convert raw I2S DMA dump (`raw_i2s.bin`) produced by the ESP32 sketch into multiple
candidate WAV files using different 24-bit -> 16-bit decoding strategies.

Usage:
  python tools\raw_i2s_to_wavs.py path/to/raw_i2s.bin out_dir [sample_rate]

Outputs (written to out_dir):
  raw_cast_shift.wav    - interpret 4-byte words as little-endian int32, >>8 -> int16
  raw_be_shift.wav      - interpret 4-byte words as big-endian int32, >>8 -> int16
  raw_24_012.wav        - take bytes [b0,b1,b2] as 24-bit MSB-first
  raw_24_123.wav        - take bytes [b1,b2,b3] as 24-bit MSB-first (common on many INMP441 files)

This script avoids external dependencies and writes standard 16-bit PCM WAV files.
"""
import os
import sys
import wave
import struct


def clamp_int16(x):
    if x > 32767:
        return 32767
    if x < -32768:
        return -32768
    return x


def write_wav_int16(path, samples, sample_rate=16000):
    with wave.open(path, 'wb') as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(sample_rate)
        wf.writeframes(struct.pack('<{}h'.format(len(samples)), *samples))


def decode_cast_shift(data):
    # Interpret every 4 bytes as little-endian signed 32-bit, then >>8 to get 16-bit
    n = (len(data) // 4) * 4
    samples = []
    for i in range(0, n, 4):
        val = struct.unpack_from('<i', data, i)[0]
        s = val >> 8
        samples.append(clamp_int16(s))
    return samples


def decode_be_shift(data):
    n = (len(data) // 4) * 4
    samples = []
    for i in range(0, n, 4):
        val = struct.unpack_from('>i', data, i)[0]
        s = val >> 8
        samples.append(clamp_int16(s))
    return samples


def decode_24_012(data):
    # take bytes [b0,b1,b2] from each 4-byte word as MSB-first 24-bit
    nwords = len(data) // 4
    samples = []
    for w in range(nwords):
        b0 = data[w*4 + 0]
        b1 = data[w*4 + 1]
        b2 = data[w*4 + 2]
        val = (b0 << 16) | (b1 << 8) | b2
        # sign extend 24-bit
        if val & 0x800000:
            val |= ~0xffffff
        s = val >> 8
        samples.append(clamp_int16(s))
    return samples


def decode_24_123(data):
    # take bytes [b1,b2,b3] from each 4-byte word as MSB-first 24-bit
    nwords = len(data) // 4
    samples = []
    for w in range(nwords):
        b1 = data[w*4 + 1]
        b2 = data[w*4 + 2]
        b3 = data[w*4 + 3]
        val = (b1 << 16) | (b2 << 8) | b3
        if val & 0x800000:
            val |= ~0xffffff
        s = val >> 8
        samples.append(clamp_int16(s))
    return samples


def main():
    if len(sys.argv) < 3:
        print('Usage: {} raw_i2s.bin out_dir [sample_rate]'.format(sys.argv[0]))
        sys.exit(1)
    inpath = sys.argv[1]
    outdir = sys.argv[2]
    sample_rate = int(sys.argv[3]) if len(sys.argv) >= 4 else 16000

    if not os.path.exists(inpath):
        print('Input file not found:', inpath)
        sys.exit(2)
    if not os.path.exists(outdir):
        os.makedirs(outdir)

    data = open(inpath, 'rb').read()
    if len(data) < 4:
        print('Input file too small')
        sys.exit(3)

    print('Read {} bytes from {}'.format(len(data), inpath))

    decoders = [
        ('raw_cast_shift.wav', decode_cast_shift),
        ('raw_be_shift.wav', decode_be_shift),
        ('raw_24_012.wav', decode_24_012),
        ('raw_24_123.wav', decode_24_123),
    ]

    for name, fn in decoders:
        outpath = os.path.join(outdir, name)
        print('Decoding ->', outpath)
        samples = fn(data)
        print('  samples:', len(samples))
        write_wav_int16(outpath, samples, sample_rate)
        print('  wrote', outpath)

    print('Done. Try listening to the generated WAVs to find the best match.')

if __name__ == '__main__':
    main()
