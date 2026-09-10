import { hash, verify } from "@node-rs/argon2";
import { z } from "zod";

// Argon2id com os parâmetros mínimos recomendados pela OWASP.
const OPTIONS = { memoryCost: 19456, timeCost: 2, parallelism: 1, outputLen: 32 };

export function hashPassword(password: string) {
  return hash(password, OPTIONS);
}

export async function verifyPassword(passwordHash: string, password: string) {
  try {
    return await verify(passwordHash, password);
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
