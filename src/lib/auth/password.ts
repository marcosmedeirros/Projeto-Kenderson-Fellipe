import { randomBytes, scrypt, timingSafeEqual, type ScryptOptions } from "node:crypto";
import { z } from "zod";

// scrypt nativo do Node (sem dependências para compilar na hospedagem).
// Parâmetros recomendados pela OWASP: N=2^15, r=8, p=3 (~32 MiB por verificação).
const N = 32768;
const R = 8;
const P = 3;
const KEY_LENGTH = 32;
const MAX_MEMORY = 64 * 1024 * 1024;

function derive(password: string, salt: Buffer, length: number, options: ScryptOptions) {
  return new Promise<Buffer>((resolve, reject) =>
    scrypt(password.normalize("NFKC"), salt, length, { ...options, maxmem: MAX_MEMORY }, (error, key) =>
      error ? reject(error) : resolve(key),
    ),
  );
}

/** Formato salvo: scrypt$N$r$p$sal$chave (base64url). */
export async function hashPassword(password: string) {
  const salt = randomBytes(16);
  const key = await derive(password, salt, KEY_LENGTH, { N, r: R, p: P });
  return ["scrypt", N, R, P, salt.toString("base64url"), key.toString("base64url")].join("$");
}

export async function verifyPassword(passwordHash: string, password: string) {
  try {
    const [algorithm, n, r, p, salt, key] = passwordHash.split("$");
    if (algorithm !== "scrypt" || !salt || !key) return false;
    const expected = Buffer.from(key, "base64url");
    const actual = await derive(password, Buffer.from(salt, "base64url"), expected.length, {
      N: Number(n),
      r: Number(r),
      p: Number(p),
    });
    return timingSafeEqual(actual, expected);
  } catch {
    return false;
  }
}

let dummyHash: Promise<string> | undefined;

/** Gasta o mesmo tempo de uma verificação real, para não revelar se o e-mail existe. */
export async function dummyVerify(password: string) {
  dummyHash ??= hashPassword("senha-ficticia-para-tempo-constante");
  await verifyPassword(await dummyHash, password);
}

export const passwordSchema = z
  .string()
  .min(10, { error: "A senha precisa ter pelo menos 10 caracteres." })
  .max(128, { error: "A senha pode ter no máximo 128 caracteres." })
  .regex(/[A-Za-z]/, { error: "A senha precisa ter pelo menos uma letra." })
  .regex(/[0-9]/, { error: "A senha precisa ter pelo menos um número." });

export function generateTemporaryPassword() {
  const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789";
  const bytes = crypto.getRandomValues(new Uint8Array(14));
  const body = Array.from(bytes, (b) => alphabet[b % alphabet.length]).join("");
  return `${body.slice(0, 7)}-${body.slice(7)}${(bytes[0] % 10).toString()}`;
}
