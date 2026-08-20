#!/usr/bin/env python3
"""
Try multiple bit-shifts and sample rates for the 'cast_shift' decoder
on a captured raw I2S dump. Writes several WAV candidates so you can
listen and pick the clearest one.

Usage:
  python tools\auto_try_cast_shift.py path/to/raw_i2s.bin out_dir

Options (edit at top of file or pass small edits):
  - sample_rates: list of sample rates to try
  - shifts: list of right-shifts to apply to the signed 32-bit words
  - apply_hp: whether to apply a simple DC-blocking high-pass filter
  - normalize: scale each output to 0.9 peak

This script is dependency-free (built-in modules only).
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
    return int(x)


def write_wav_int16(path, samples, sample_rate):
    with wave.open(path, 'wb') as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(sample_rate)
        wf.writeframes(struct.pack('<{}h'.format(len(samples)), *samples))


def decode_cast_shift_samples(data, shift_right=8):
    n = (len(data) // 4) * 4
    samples = []
    for i in range(0, n, 4):
        val = struct.unpack_from('<i', data, i)[0]
        s = val >> shift_right
        samples.append(int(s))
    return samples


def dc_block_filter(samples, R=0.995):
    # Simple DC-blocking filter: y[n] = x[n] - x[n-1] + R*y[n-1]
    if not samples:
        return samples
    out = [0]*len(samples)
    x_prev = samples[0]
    y_prev = 0
    out[0] = samples[0]
    for i in range(1, len(samples)):
        x = samples[i]
        y = x - x_prev + int(R * y_prev)
        out[i] = y
        x_prev = x
        y_prev = y
    return out


def normalize_to_peak(samples, peak=0.9):
    if not samples:
        return samples
    maxv = max(abs(x) for x in samples)
    if maxv == 0:
        return samples
    scale = (32767 * peak) / maxv
    return [clamp_int16(int(x * scale)) for x in samples]


def main():
    if len(sys.argv) < 3:
        print('Usage: {} raw_i2s.bin out_dir'.format(sys.argv[0]))
        sys.exit(1)
    inpath = sys.argv[1]
    outdir = sys.argv[2]

    if not os.path.exists(inpath):
        print('Input file not found:', inpath)
        sys.exit(2)
    if not os.path.exists(outdir):
        os.makedirs(outdir)

    data = open(inpath, 'rb').read()
    if len(data) < 4:
        print('Input file too small')
        sys.exit(3)

    # Configuration - edit here if you want other values
    sample_rates = [16000, 32000, 44100, 48000]
    shifts = [0, 4, 6, 8, 10]
    apply_hp = True
    normalize = True

    print('Read {} bytes from {}'.format(len(data), inpath))
    for sr in sample_rates:
        for sh in shifts:
            print('Decoding: sr={} shift={} hp={} norm={}'.format(sr, sh, apply_hp, normalize))
            samples = decode_cast_shift_samples(data, shift_right=sh)
            # clamp to int16 range before filter to avoid huge values
            samples = [clamp_int16(s) for s in samples]
            if apply_hp:
                samples = dc_block_filter(samples)
                # clamp again
                samples = [clamp_int16(s) for s in samples]
            if normalize:
                samples = normalize_to_peak(samples, peak=0.9)
            fname = 'cast_sr{}__shr{}{}{}.wav'.format(sr, sh, '__hp' if apply_hp else '', '__norm' if normalize else '')
            outpath = os.path.join(outdir, fname)
            write_wav_int16(outpath, samples, sr)
            print('  wrote', outpath, 'samples=', len(samples))

    print('Done. Listen to the generated files in', outdir)


if __name__ == '__main__':
    main()
