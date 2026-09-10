import type { NextConfig } from "next";

const isProd = process.env.NODE_ENV === "production";

// A CSP (com nonce) é aplicada em src/proxy.ts. Aqui ficam os demais cabeçalhos de segurança.
const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=(), payment=()" },
  { key: "Cross-Origin-Opener-Policy", value: "same-origin" },
  ...(isProd
    ? [{ key: "Strict-Transport-Security", value: "max-age=63072000; includeSubDomains" }]
    : []),
];

// Atrás do proxy da hospedagem, o domínio público pode chegar diferente do "Host" interno.
// Liberar o domínio do APP_URL evita que as Server Actions sejam recusadas.
const allowedOrigins = [process.env.APP_URL, ...(process.env.ALLOWED_ORIGINS ?? "").split(",")]
  .map((value) => value?.trim())
  .filter((value): value is string => Boolean(value))
  .map((value) => {
    try {
      return new URL(value).host;
    } catch {
      return value;
    }
  });

const nextConfig: NextConfig = {
  poweredByHeader: false,
  serverExternalPackages: ["mysql2"],
  ...(allowedOrigins.length ? { experimental: { serverActions: { allowedOrigins } } } : {}),
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
};

export default nextConfig;
