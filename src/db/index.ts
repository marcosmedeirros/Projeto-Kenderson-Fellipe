import { createDb, type Db, type DbDriver } from "./client";

// Uma única conexão por processo (sobrevive ao hot reload do dev e é compartilhada
// entre a inicialização do servidor e as rotas).
const store = globalThis as unknown as { __controladoriaDb?: { db: Db; driver: DbDriver } };

export function getDbHandle() {
  store.__controladoriaDb ??= createDb();
  return store.__controladoriaDb;
}

/** Conexão criada só no primeiro uso, para não abrir o banco durante o build. */
export const db: Db = new Proxy({} as Db, {
  get(_target, prop) {
    const real = getDbHandle().db as object;
    const value = Reflect.get(real, prop, real);
    return typeof value === "function" ? value.bind(real) : value;
  },
});
