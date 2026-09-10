<?php

defined('CONTROLADORIA') || exit;

// Dados fictícios para demonstrar o painel enquanto o YouTube não está conectado.
// Tudo é gerado em relação à data atual, então o painel sempre parece "vivo".

function seed_demo_if_empty(): bool
{
    if ((int) db_value('SELECT COUNT(*) FROM videos') > 0) {
        return false;
    }

    mt_srand(20260910);
    $random = static fn (): float => mt_rand() / mt_getrandmax();
    // Horário de São Paulo (UTC-3) convertido para UTC.
    $at = static fn (int $days, int $hour): DateTimeImmutable => utc_now()->modify(sprintf('%+d days', $days))->setTime($hour + 3, 0);

    $scheduled = [
        ['Respondendo as perguntas mais pedidas de vocês', 2, 'ok', false],
        ['O que ninguém te conta sobre começar do zero', 5, 'pendente', false],
        ['O erro que eu mais cometia (e como parei)', 6, 'pendente', true],
        ['Testei acordar às 5h por 30 dias', 9, 'ok', false],
        ['Reagindo aos comentários do último vídeo', 13, 'ok', false],
    ];
    $published = [
        ['Minha rotina real de gravação (sem cortes)', 3, 21400, false],
        ['3 hábitos que mudaram meu ano', 5, 88300, true],
        ['Como organizo a semana em 20 minutos', 7, 64900, false],
        ['Vale a pena largar tudo para empreender?', 10, 19800, false],
        ['A pergunta que mais recebo', 12, 41200, true],
        ['Bastidores: um dia inteiro comigo', 14, 23600, false],
        ['O que aprendi com 100 vídeos publicados', 17, 18900, false],
        ['Disciplina x motivação: qual importa mais', 19, 27100, false],
        ['Não cometa esse erro no começo', 21, 212000, true],
        ['Metas que eu desisti (e por quê)', 24, 16700, false],
        ['Respondendo críticas sem filtro', 26, 58400, false],
        ['Minha mesa de trabalho em 2026', 28, 22300, false],
        ['Quanto tempo leva para ver resultado', 31, 35900, true],
        ['Mudei minha alimentação por 1 mês', 33, 20100, false],
        ['Como lido com dias improdutivos', 35, 24800, false],
        ['Perguntas rápidas, respostas sinceras', 38, 29700, true],
        ['O livro que mais me marcou este ano', 42, 17200, false],
        ['Organização financeira do jeito simples', 46, 31500, false],
        ['Meu maior arrependimento', 52, 26900, false],
        ['Planejamento do segundo semestre', 60, 19400, false],
        ['Rotina da manhã que funciona de verdade', 68, 23000, false],
    ];
    $terms = [
        ['luiz jordão', 38200], ['rotina da manhã', 21700], ['como organizar a semana', 18400], ['disciplina', 12900],
        ['acordar cedo', 11300], ['começar do zero', 9800], ['hábitos que mudam a vida', 8600], ['produtividade', 7900],
        ['organização financeira', 6200], ['metas 2026', 5100], ['dias improdutivos', 3400], ['mesa de trabalho', 2700],
    ];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($scheduled as $index => [$title, $days, $thumb, $short]) {
            db_insert('videos', [
                'id' => 'demo-agendado-' . ($index + 1),
                'title' => $title,
                'status' => 'agendado',
                'publish_at' => to_db($at($days, $short ? 12 : 18)),
                'is_short' => $short ? 1 : 0,
                'duration_sec' => $short ? 45 : 600 + (int) round($random() * 900),
                'thumbnail_status' => $thumb,
                'thumbnail_source' => 'automatico',
                'thumbnail_updated_at' => to_db(utc_now()),
            ]);
        }

        $current = month_key();
        $months = [$current, shift_month($current, -1), shift_month($current, -2)];
        $spikes = [];

        foreach ($published as $index => [$title, $days, $views7d, $short]) {
            $growth = $days >= 7 ? 1.9 + $random() * 1.4 + $days / 60 : 0.5 + $days * 0.08;
            $views = (int) round($views7d * $growth);
            $likes = (int) round($views * (0.03 + $random() * 0.02));
            $comments = (int) round($views * (0.002 + $random() * 0.003));
            $duration = $short ? 30 + (int) round($random() * 30) : 480 + (int) round($random() * 1200);
            $publishedAt = $at(-$days, $short ? 12 : 18);
            $id = 'demo-publicado-' . ($index + 1);
            if ($views7d > 60000) {
                $spikes[] = $publishedAt->getTimestamp();
            }
            db_insert('videos', [
                'id' => $id,
                'title' => $title,
                'status' => 'publicado',
                'published_at' => to_db($publishedAt),
                'is_short' => $short ? 1 : 0,
                'duration_sec' => $duration,
                'thumbnail_status' => 'ok',
                'thumbnail_source' => 'automatico',
                'views' => $views,
                'likes' => $likes,
                'comments' => $comments,
                'views_7d' => $days >= 7 ? $views7d : (int) round($views7d * min(1, $days / 7)),
                'synced_at' => to_db(utc_now()),
            ]);

            $publishedMonth = month_key($publishedAt);
            $activeMonths = array_values(array_filter($months, static fn ($month) => $month >= $publishedMonth));
            foreach ($activeMonths as $month) {
                $share = $month === $publishedMonth ? 0.72 : 0.28 / max(1, count($activeMonths) - 1);
                $monthViews = (int) round($views * $share);
                db_insert('video_metrics', [
                    'video_id' => $id,
                    'month' => $month,
                    'views' => $monthViews,
                    'watch_minutes' => (int) round($monthViews * ($duration / 60) * ($short ? 0.85 : 0.42)),
                    'avg_view_pct' => $short ? 70 + $random() * 25 : 34 + $random() * 22,
                    'subscribers_gained' => (int) round($monthViews * (0.004 + $random() * 0.004)),
                    'likes' => (int) round($likes * $share),
                    'comments' => (int) round($comments * $share),
                ]);
            }
        }

        // Série diária do canal (últimos 95 dias), com picos após os vídeos virais
        $seenDays = [];
        for ($i = 0; $i < 95; $i++) {
            $date = utc_now()->modify('-' . (94 - $i) . ' days');
            $day = day_key($date);
            if (isset($seenDays[$day])) {
                continue;
            }
            $seenDays[$day] = true;
            $weekday = (int) $date->format('w');
            $base = 19000 + $i * 45 + ($weekday === 0 || $weekday === 6 ? -2500 : 1200);
            $spike = 0.0;
            foreach ($spikes as $spikeAt) {
                $diff = ($date->getTimestamp() - $spikeAt) / 86400;
                if ($diff >= 0 && $diff < 9) {
                    $spike += 26000 * exp(-$diff / 2.2);
                }
            }
            $views = (int) round(($base + $spike) * (0.88 + $random() * 0.24));
            db_insert('channel_daily', [
                'day' => $day,
                'views' => $views,
                'watch_minutes' => (int) round($views * (2.4 + $random() * 0.8)),
                'subscribers_gained' => (int) round($views * (0.0045 + $random() * 0.002)),
            ]);
        }

        foreach ($months as $monthIndex => $month) {
            foreach ($terms as $termIndex => [$term, $views]) {
                $trend = $monthIndex === 0 ? 1 : ($monthIndex === 1 ? 0.78 - ($termIndex % 3) * 0.12 : 0.6 - ($termIndex % 4) * 0.1);
                $partial = $monthIndex === 0 ? 0.36 : 1;
                db_insert('search_terms', [
                    'month' => $month,
                    'term' => $term,
                    'views' => max(120, (int) round($views * max(0.15, $trend) * $partial * (0.9 + $random() * 0.2))),
                ]);
            }
        }

        $now = to_db(utc_now());
        $ideas = [
            ['Rotina da manhã: versão para quem trabalha à noite', '"Rotina da manhã" é o 2º termo mais buscado que traz gente ao canal, e ninguém fala da rotina de quem trabalha à noite.', 'buscas', 86, 'aprovada'],
            ['Os erros do começo, parte 2', 'O Short sobre erros do começo fez mais de 7x a média em 7 dias. Vale uma sequência em vídeo longo.', 'desempenho', 91, 'aprovada'],
            ['Minha semana organizada ao vivo, do zero', 'Buscas por "como organizar a semana" cresceram e o vídeo sobre o tema segurou 51% de retenção.', 'buscas', 78, 'nova'],
            ['Respondendo quem disse que acordar cedo é bobagem', 'Os comentários do vídeo sobre acordar às 5h dividiram opiniões. Polêmica boa gera conversa.', 'comentarios', 72, 'nova'],
            ['Finanças para quem ganha pouco', '"Organização financeira" apareceu entre os termos de busca que mais cresceram no mês.', 'buscas', 64, 'nova'],
            ['Tour pela mesa de trabalho', 'Teve pouca busca e o vídeo anterior sobre o tema ficou abaixo da média.', 'desempenho', 31, 'descartada'],
        ];
        foreach ($ideas as [$title, $rationale, $source, $score, $status]) {
            db_insert('ideas', [
                'title' => $title, 'rationale' => $rationale, 'source' => $source, 'score' => $score,
                'status' => $status, 'generated_by' => 'exemplo', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $minutesAgo = static fn (int $minutes) => utc_now()->modify("-$minutes minutes");
        foreach ([
            ['saida', 'automacao', null, '⚠️ *Estoque baixo:* os vídeos programados cobrem só até a próxima semana.', 'alerta-estoque', 190],
            ['entrada', 'whatsapp', 'Luiz', '/programados', 'programados', 64],
            ['saida', 'whatsapp', null, '📅 *5 vídeos programados* …', 'programados', 64],
            ['entrada', 'whatsapp', 'Kenderson', '/semcapa', 'semcapa', 22],
            ['saida', 'whatsapp', null, '🖼️ *2 vídeos sem capa* …', 'semcapa', 22],
        ] as [$direction, $origin, $sender, $text, $command, $ago]) {
            record_bot_message([
                'direction' => $direction, 'origin' => $origin, 'chat_id' => 'demo@g.us', 'sender' => $sender,
                'text' => $text, 'command' => $command, 'created_at' => $minutesAgo($ago),
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    return true;
}
