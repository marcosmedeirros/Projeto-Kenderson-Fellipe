import { FlaskConical } from "lucide-react";
import Link from "next/link";
import { Sidebar } from "@/components/sidebar";
import { requireUser } from "@/lib/auth/dal";
import { can, ROLE_LABEL } from "@/lib/auth/permissions";
import { getScheduledVideos, summarizeStock } from "@/lib/channel/queries";
import { isYoutubeConnected } from "@/lib/integrations";
import { getSetting } from "@/lib/settings";

export default async function PainelLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser();
  const [scheduled, alertas, youtubeConnected] = await Promise.all([
    getScheduledVideos(),
    getSetting("alertas"),
    isYoutubeConnected(),
  ]);
  const stock = summarizeStock(scheduled);

  return (
    <div className="lg:pl-[264px]">
      <Sidebar
        user={{ name: user.name, email: user.email, roleLabel: ROLE_LABEL[user.role] }}
        show={{ integracoes: can(user.role, "integracoes"), usuarios: can(user.role, "usuarios"), auditoria: can(user.role, "auditoria") }}
        counts={{ semCapa: stock.missingThumbs, estoqueBaixo: stock.daysCovered < alertas.estoqueMinimoDias }}
      />
      {!youtubeConnected && (
        <div className="flex items-center gap-2.5 border-b border-warn/20 bg-warn/[0.06] px-4 py-2 text-[13px] text-warn sm:px-8">
          <FlaskConical className="size-4 shrink-0" />
          <span>
            <strong className="font-semibold">Modo demonstração:</strong> os vídeos e números são fictícios até o YouTube ser conectado.
          </span>
          {can(user.role, "integracoes") && (
            <Link href="/integracoes" className="ml-auto hidden font-semibold underline-offset-4 hover:underline sm:inline">
              Ver integrações
            </Link>
          )}
        </div>
      )}
      <main className="mx-auto w-full max-w-[1280px] px-4 pt-7 pb-16 sm:px-8 sm:pt-9">{children}</main>
    </div>
  );
}
