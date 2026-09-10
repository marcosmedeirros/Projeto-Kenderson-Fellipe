export type FormState =
  | {
      ok?: boolean;
      message?: string;
      error?: string;
      /** Valor exibido uma única vez (ex.: senha temporária). */
      secret?: string;
    }
  | undefined;

export const firstIssue = (issues: { message: string }[]) => issues[0]?.message ?? "Dados inválidos.";
