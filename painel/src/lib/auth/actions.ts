"use server";

import { redirect } from "next/navigation";
import { logAudit } from "@/lib/audit";
import { requestMeta } from "@/lib/request";
import { deleteCurrentSession, readSession } from "./session";

export async function logoutAction() {
  const session = await readSession();
  if (session) {
    const { ip } = await requestMeta();
    await logAudit({ userId: session.user.id, action: "logout", ip });
  }
  await deleteCurrentSession();
  redirect("/login");
}
