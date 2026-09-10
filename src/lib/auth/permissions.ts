export type Role = "admin" | "editor" | "visualizador";

export const ROLE_LABEL: Record<Role, string> = {
  admin: "Administrador",
  editor: "Editor",
  visualizador: "Visualizador",
};

export const ROLE_DESCRIPTION: Record<Role, string> = {
  admin: "Acesso total: integrações, usuários e auditoria.",
  editor: "Opera o canal: capas, ideias, bot e alertas.",
  visualizador: "Só consulta os dados do canal.",
};

const MATRIX = {
  operar: ["admin", "editor"],
  automacoes: ["admin", "editor"],
  integracoes: ["admin"],
  usuarios: ["admin"],
  auditoria: ["admin"],
} as const satisfies Record<string, readonly Role[]>;

export type Permission = keyof typeof MATRIX;

export function can(role: Role, permission: Permission) {
  return (MATRIX[permission] as readonly Role[]).includes(role);
}
