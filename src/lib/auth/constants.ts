// O prefixo __Host- obriga HTTPS, path "/" e nenhum domínio: o cookie não vaza para subdomínios.
export const SESSION_COOKIE =
  process.env.NODE_ENV === "production" ? "__Host-controladoria_sessao" : "controladoria_sessao";
