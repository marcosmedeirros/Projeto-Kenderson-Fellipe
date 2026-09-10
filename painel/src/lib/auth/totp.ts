import { createHmac, randomBytes } from "node:crypto";
import { safeEqual } from "@/lib/crypto";

// TOTP (RFC 6238): códigos de 6 dígitos a cada 30 s, compatível com Google Authenticator,
// Microsoft Authenticator, 1Password etc.

const ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
const PERIOD = 30;
export const TOTP_ISSUER = "Controladoria do Canal";

function base32Encode(buffer: Buffer) {
  let bits = 0;
  let value = 0;
  let output = "";
  for (const byte of buffer) {
    value = (value << 8) | byte;
    bits += 8;
    while (bits >= 5) {
      output += ALPHABET[(value >>> (bits - 5)) & 31];
      bits -= 5;
    }
  }
  if (bits > 0) output += ALPHABET[(value << (5 - bits)) & 31];
  return output;
}

function base32Decode(input: string) {
  const clean = input.replace(/=+$/, "").replace(/\s/g, "").toUpperCase();
  let bits = 0;
  let value = 0;
  const bytes: number[] = [];
  for (const char of clean) {
    const index = ALPHABET.indexOf(char);
    if (index === -1) throw new Error("Segredo TOTP inválido.");
    value = (value << 5) | index;
    bits += 5;
    if (bits >= 8) {
      bytes.push((value >>> (bits - 8)) & 255);
      bits -= 8;
    }
  }
  return Buffer.from(bytes);
}

function hotp(key: Buffer, counter: number) {
  const buffer = Buffer.alloc(8);
  buffer.writeBigUInt64BE(BigInt(counter));
  const digest = createHmac("sha1", key).update(buffer).digest();
  const offset = digest[digest.length - 1] & 0xf;
  const code =
    (((digest[offset] & 0x7f) << 24) |
      (digest[offset + 1] << 16) |
      (digest[offset + 2] << 8) |
      digest[offset + 3]) %
    1_000_000;
  return code.toString().padStart(6, "0");
}

export function generateTotpSecret() {
  return base32Encode(randomBytes(20));
}

/**
 * Aceita o código atual e o vizinho (±30 s de relógio).
 * Retorna o passo usado, ou null. Passos já usados são recusados para evitar reuso.
 */
export function verifyTotp(secret: string, token: string, lastStep?: number | null) {
  const code = token.replace(/\s/g, "");
  if (!/^\d{6}$/.test(code)) return null;
  const key = base32Decode(secret);
  const current = Math.floor(Date.now() / 1000 / PERIOD);
  for (const drift of [-1, 0, 1]) {
    const step = current + drift;
    if (lastStep != null && step <= lastStep) continue;
    if (safeEqual(hotp(key, step), code)) return step;
  }
  return null;
}

export function totpUri(secret: string, account: string) {
  const label = encodeURIComponent(`${TOTP_ISSUER}:${account}`);
  const params = new URLSearchParams({ secret, issuer: TOTP_ISSUER, algorithm: "SHA1", digits: "6", period: "30" });
  return `otpauth://totp/${label}?${params.toString()}`;
}
