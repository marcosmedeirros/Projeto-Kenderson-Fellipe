"use server";

import { and, eq } from "drizzle-orm";
import { revalidatePath } from "next/cache";
import { z } from "zod";
import { db } from "@/db";
import { videos } from "@/db/schema";
import { logAudit } from "@/lib/audit";
import { requirePermission } from "@/lib/auth/dal";
import { requestMeta } from "@/lib/request";

const schema = z.object({
  videoId: z.string().min(1).max(64),
  status: z.enum(["ok", "pendente"]),
});

export async function setThumbnailStatusAction(formData: FormData) {
  const user = await requirePermission("operar");
  const parsed = schema.safeParse({ videoId: formData.get("videoId"), status: formData.get("status") });
  if (!parsed.success) return;

  const [video] = await db
    .update(videos)
    .set({
      thumbnailStatus: parsed.data.status,
      thumbnailSource: "manual",
      thumbnailUpdatedBy: user.id,
      thumbnailUpdatedAt: new Date(),
    })
    .where(and(eq(videos.id, parsed.data.videoId), eq(videos.status, "agendado")))
    .returning({ title: videos.title });

  if (video) {
    const { ip } = await requestMeta();
    await logAudit({ userId: user.id, action: "capa.status", detail: { video: video.title, status: parsed.data.status }, ip });
  }
  revalidatePath("/", "layout");
}
